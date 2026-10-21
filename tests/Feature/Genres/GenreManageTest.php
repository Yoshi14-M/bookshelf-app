<?php

namespace Tests\Feature\Genres;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreManageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // ---- 未認証 ----

    /** @test */
    public function ゲストはジャンルの全画面と操作でログイン画面へ遷移する(): void
    {
        $genre = Genre::factory()->create();

        $this->get(route('genres.index'))->assertRedirect(route('login'));
        $this->get(route('genres.create'))->assertRedirect(route('login'));
        $this->post(route('genres.store'), ['name' => '小説'])->assertRedirect(route('login'));
        $this->get(route('genres.show', $genre))->assertRedirect(route('login'));
        $this->get(route('genres.edit', $genre))->assertRedirect(route('login'));
        $this->put(route('genres.update', $genre), ['name' => '更新'])->assertRedirect(route('login'));
        $this->delete(route('genres.destroy', $genre))->assertRedirect(route('login'));

        $this->assertDatabaseCount('genres', 1);
        $this->assertDatabaseMissing('genres', ['name' => '更新']);
    }

    // ---- 一覧・詳細 ----

    /** @test */
    public function 一覧にジャンル名と紐付く書籍数が表示される(): void
    {
        $tech = Genre::factory()->create(['name' => '技術書']);
        Genre::factory()->create(['name' => '料理']);
        $tech->books()->attach(Book::factory()->count(2)->create());

        $this->actingAs($this->user)
            ->get(route('genres.index'))
            ->assertOk()
            ->assertViewIs('genres.index')
            ->assertSeeInOrder(['技術書', '2冊', '料理', '0冊']);
    }

    /** @test */
    public function ジャンルがない場合は登録されていない旨が表示される(): void
    {
        $this->actingAs($this->user)
            ->get(route('genres.index'))
            ->assertOk()
            ->assertSee('ジャンルが登録されていません。');
    }

    /** @test */
    public function ジャンル詳細にそのジャンルの書籍だけが10件ごとに表示される(): void
    {
        $genre = Genre::factory()->create(['name' => '歴史']);
        $genre->books()->attach(Book::factory()->count(12)->create());
        Book::factory()->create(['title' => '別ジャンルの書籍'])->genres()->attach(Genre::factory()->create());

        $first = $this->actingAs($this->user)->get(route('genres.show', $genre))->assertOk();
        $this->assertCount(10, $first->viewData('books')->items());
        $this->assertSame(12, $first->viewData('books')->total());
        $first->assertDontSee('別ジャンルの書籍');

        $second = $this->actingAs($this->user)->get(route('genres.show', $genre).'?page=2')->assertOk();
        $this->assertCount(2, $second->viewData('books')->items());
    }

    /** @test */
    public function 書籍のないジャンル詳細にはその旨が表示される(): void
    {
        $genre = Genre::factory()->create();

        $this->actingAs($this->user)
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertSee('このジャンルの書籍はまだ登録されていません。');
    }

    /** @test */
    public function 存在しないジャンルの詳細は404になる(): void
    {
        $this->actingAs($this->user)->get('/genres/9999')->assertNotFound();
    }

    // ---- 登録 ----

    /** @test */
    public function ログイン済みであれば誰でもジャンルを登録できる(): void
    {
        $this->actingAs($this->user)
            ->get(route('genres.create'))
            ->assertOk();

        $this->actingAs($this->user)
            ->post(route('genres.store'), ['name' => '小説'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを登録しました。');

        $this->assertDatabaseHas('genres', ['name' => '小説']);
    }

    /** @test */
    public function 登録後の一覧に成功メッセージが表示される(): void
    {
        $this->actingAs($this->user)
            ->followingRedirects()
            ->post(route('genres.store'), ['name' => '小説'])
            ->assertOk()
            ->assertSee('ジャンルを登録しました。')
            ->assertSee('小説');
    }

    /**
     * @test
     *
     * @dataProvider 入力不備のパターン
     */
    public function 入力不備のあるジャンルは登録できず日本語のエラーになる(string $name, string $message): void
    {
        Genre::factory()->create(['name' => '小説']);

        $this->actingAs($this->user)
            ->from(route('genres.create'))
            ->post(route('genres.store'), ['name' => $name])
            ->assertRedirect(route('genres.create'))
            ->assertSessionHasErrors(['name' => $message]);

        $this->assertDatabaseCount('genres', 1);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function 入力不備のパターン(): array
    {
        return [
            '未入力' => ['', 'ジャンル名は必須です。'],
            '51文字' => [str_repeat('あ', 51), 'ジャンル名は50文字以内で入力してください。'],
            '登録済みの名前' => ['小説', 'このジャンル名は既に登録されています。'],
        ];
    }

    /** @test */
    public function 上限ちょうどの文字数のジャンル名は登録できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('genres.store'), ['name' => str_repeat('あ', 50)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('genres', 1);
    }

    /** @test */
    public function 登録に失敗するとジャンル名が保持される(): void
    {
        Genre::factory()->create(['name' => '小説']);

        $this->actingAs($this->user)
            ->from(route('genres.create'))
            ->followingRedirects()
            ->post(route('genres.store'), ['name' => '小説'])
            ->assertOk()
            ->assertSee('このジャンル名は既に登録されています。')
            ->assertSee('value="小説"', false);
    }

    // ---- 更新 ----

    /** @test */
    public function ジャンルを更新すると一覧へ遷移する(): void
    {
        $genre = Genre::factory()->create(['name' => '旧名称']);

        $this->actingAs($this->user)
            ->get(route('genres.edit', $genre))
            ->assertOk()
            ->assertSee('value="旧名称"', false);

        $this->actingAs($this->user)
            ->put(route('genres.update', $genre), ['name' => '新名称'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを更新しました。');

        $this->assertSame('新名称', $genre->fresh()->name);
    }

    /** @test */
    public function 自分の名前のままであれば更新できる(): void
    {
        $genre = Genre::factory()->create(['name' => '小説']);

        $this->actingAs($this->user)
            ->put(route('genres.update', $genre), ['name' => '小説'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('genres.index'));
    }

    /** @test */
    public function 他のジャンルと同じ名前には更新できない(): void
    {
        Genre::factory()->create(['name' => '小説']);
        $genre = Genre::factory()->create(['name' => '歴史']);

        $this->actingAs($this->user)
            ->from(route('genres.edit', $genre))
            ->put(route('genres.update', $genre), ['name' => '小説'])
            ->assertRedirect(route('genres.edit', $genre))
            ->assertSessionHasErrors(['name' => 'このジャンル名は既に登録されています。']);

        $this->assertSame('歴史', $genre->fresh()->name);
    }

    /** @test */
    public function 更新時に名前が未入力だと日本語のエラーになる(): void
    {
        $genre = Genre::factory()->create(['name' => '歴史']);

        $this->actingAs($this->user)
            ->put(route('genres.update', $genre), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'ジャンル名は必須です。']);
    }

    // ---- 削除 ----

    /** @test */
    public function 書籍が紐付いていないジャンルは削除できる(): void
    {
        $genre = Genre::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを削除しました。');

        $this->assertModelMissing($genre);
    }

    /** @test */
    public function 書籍が紐付いているジャンルは削除できずエラーが表示される(): void
    {
        $genre = Genre::factory()->create(['name' => '技術書']);
        $genre->books()->attach(Book::factory()->create());

        $this->actingAs($this->user)
            ->followingRedirects()
            ->delete(route('genres.destroy', $genre))
            ->assertOk()
            ->assertSee('このジャンルには書籍が紐付いているため削除できません。')
            ->assertDontSee('ジャンルを削除しました。');

        $this->assertModelExists($genre);
        $this->assertDatabaseCount('book_genre', 1);
    }

    /** @test */
    public function 紐付き書籍の削除拒否ではerrorフラッシュが設定される(): void
    {
        $genre = Genre::factory()->create();
        $genre->books()->attach(Book::factory()->create());

        $this->actingAs($this->user)
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('error', 'このジャンルには書籍が紐付いているため削除できません。')
            ->assertSessionMissing('success');
    }

    /** @test */
    public function 存在しないジャンルの削除は404になる(): void
    {
        $this->actingAs($this->user)->delete('/genres/9999')->assertNotFound();
    }

    /** @test */
    public function 他のユーザーが作成したジャンルでも編集と削除ができる(): void
    {
        $other = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->actingAs($other)->put(route('genres.update', $genre), ['name' => '共有マスタ'])->assertRedirect(route('genres.index'));
        $this->actingAs($this->user)->delete(route('genres.destroy', $genre))->assertRedirect(route('genres.index'));

        $this->assertModelMissing($genre);
    }

    // ---- 書籍フォームとの連携 ----

    /** @test */
    public function 登録したジャンルが書籍登録フォームに表示される(): void
    {
        $this->actingAs($this->user)->post(route('genres.store'), ['name' => '新ジャンル']);

        $this->actingAs($this->user)
            ->get(route('books.create'))
            ->assertSee('新ジャンル');
    }
}
