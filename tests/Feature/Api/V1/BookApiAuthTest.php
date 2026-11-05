<?php

namespace Tests\Feature\Api\V1;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 公開 API の認証（Sanctum）と認可（登録者のみ）。参照は認証不要、書き込み系はトークンが必要。
 */
class BookApiAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validInput(): array
    {
        return [
            'title' => 'リーダブルコード',
            'author' => 'Dustin Boswell',
            'isbn' => '9784873115658',
            'published_date' => '2012-06-23',
            'genres' => [Genre::factory()->create()->id],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function 書き込み系のリクエスト(): array
    {
        return [
            '登録' => ['postJson', '/api/v1/books'],
            '更新' => ['putJson', '/api/v1/books/1'],
            '削除' => ['deleteJson', '/api/v1/books/1'],
        ];
    }

    // ---- 参照は認証不要 ----

    /** @test */
    public function 一覧と詳細はトークンなしで取得できる(): void
    {
        $book = Book::factory()->create();

        $this->getJson('/api/v1/books')->assertOk();
        $this->getJson('/api/v1/books/'.$book->id)->assertOk();
    }

    /** @test */
    public function 一覧と詳細は不正なトークンが付いていても取得できる(): void
    {
        $book = Book::factory()->create();
        $headers = ['Authorization' => 'Bearer invalid-token'];

        $this->getJson('/api/v1/books', $headers)->assertOk();
        $this->getJson('/api/v1/books/'.$book->id, $headers)->assertOk();
    }

    // ---- 未認証は 401 ----

    /**
     * @test
     *
     * @dataProvider 書き込み系のリクエスト
     */
    public function 書き込み系はトークンなしだと401になる(string $method, string $uri): void
    {
        $book = Book::factory()->create();

        $this->{$method}($uri, $this->validInput())
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->assertDatabaseCount('books', 1);
        $this->assertSame($book->title, $book->fresh()->title);
    }

    /**
     * @test
     *
     * @dataProvider 書き込み系のリクエスト
     */
    public function 書き込み系は不正なトークンだと401になる(string $method, string $uri): void
    {
        Book::factory()->create();

        $this->{$method}($uri, $this->validInput(), ['Authorization' => 'Bearer 1|invalid-token'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    /** @test */
    public function ヘッダ指定がなくても未認証はリダイレクトされず401のエラー応答になる(): void
    {
        $this->post('/api/v1/books', [])
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
    }

    /** @test */
    public function 存在しない書籍の更新と削除も未認証なら401になる(): void
    {
        $this->putJson('/api/v1/books/9999', $this->validInput())->assertUnauthorized();
        $this->deleteJson('/api/v1/books/9999')->assertUnauthorized();
    }

    // ---- 実際のトークン ----

    /** @test */
    public function 発行したトークンを認証ヘッダで送ると書き込み系を利用できる(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $created = $this->postJson('/api/v1/books', $this->validInput(), $headers)->assertCreated();
        $id = $created->json('data.id');
        $this->assertSame($user->id, Book::findOrFail($id)->user_id);

        $this->putJson('/api/v1/books/'.$id, [...$this->validInput(), 'title' => '更新後'], $headers)
            ->assertOk()
            ->assertJsonPath('data.title', '更新後');
        $this->deleteJson('/api/v1/books/'.$id, [], $headers)->assertNoContent();
    }

    /** @test */
    public function 失効させたトークンでは利用できなくなる(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test');
        $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];

        $this->postJson('/api/v1/books', $this->validInput(), $headers)->assertCreated();

        $token->accessToken->delete();
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/books', [...$this->validInput(), 'isbn' => '9784101010014'], $headers)
            ->assertUnauthorized();
    }

    // ---- 他人の書籍は 403 ----

    /** @test */
    public function 他人の書籍の更新は403になり内容は変わらない(): void
    {
        $book = Book::factory()->create(['title' => '更新前']);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/books/'.$book->id, [...$this->validInput(), 'title' => '更新後'])
            ->assertForbidden()
            ->assertExactJson(['error' => 'この操作を実行する権限がありません。']);

        $this->assertSame('更新前', $book->fresh()->title);
    }

    /** @test */
    public function 他人の書籍の削除は403になり書籍は残る(): void
    {
        $book = Book::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/v1/books/'.$book->id)
            ->assertForbidden()
            ->assertExactJson(['error' => 'この操作を実行する権限がありません。']);

        $this->assertModelExists($book);
    }

    /** @test */
    public function 他人の書籍の更新は入力が不正でも422ではなく403になる(): void
    {
        $book = Book::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/books/'.$book->id, ['title' => ''])->assertForbidden();
    }

    /** @test */
    public function 登録者本人のトークンなら更新と削除ができる(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        Sanctum::actingAs($owner);

        $this->putJson('/api/v1/books/'.$book->id, $this->validInput())->assertOk();
        $this->deleteJson('/api/v1/books/'.$book->id)->assertNoContent();
    }

    /** @test */
    public function 画面側の権限エラーは従来どおりで公開用の形式にならない(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('books.destroy', $book))
            ->assertForbidden()
            ->assertDontSee('この操作を実行する権限がありません。');
    }
}
