<?php

namespace Tests\Feature\Books;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookManageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'title' => 'リーダブルコード',
            'author' => 'Dustin Boswell',
            'isbn' => '9784873115658',
            'published_date' => '2012-06-23',
            'description' => '読みやすいコードの書き方',
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=3',
            'genres' => [Genre::factory()->create()->id],
        ], $overrides);
    }

    // ---- 未認証 ----

    /** @test */
    public function 未認証ユーザーは書籍の登録フォームにアクセスできずログイン画面へ遷移する(): void
    {
        $this->get(route('books.create'))->assertRedirect(route('login'));
    }

    /** @test */
    public function 未認証ユーザーは書籍を登録できない(): void
    {
        $this->post(route('books.store'), $this->validInput())->assertRedirect(route('login'));

        $this->assertDatabaseCount('books', 0);
    }

    /** @test */
    public function 未認証ユーザーは書籍の編集と更新と削除ができない(): void
    {
        $book = Book::factory()->create();

        $this->get(route('books.edit', $book))->assertRedirect(route('login'));
        $this->put(route('books.update', $book), $this->validInput())->assertRedirect(route('login'));
        $this->delete(route('books.destroy', $book))->assertRedirect(route('login'));

        $this->assertModelExists($book);
    }

    // ---- 登録 ----

    /** @test */
    public function 登録フォームに全ジャンルが表示される(): void
    {
        Genre::factory()->create(['name' => '技術書']);
        Genre::factory()->create(['name' => '歴史']);

        $this->actingAs(User::factory()->create())
            ->get(route('books.create'))
            ->assertOk()
            ->assertViewIs('books.create')
            ->assertSee('技術書')
            ->assertSee('歴史');
    }

    /** @test */
    public function 書籍を登録するとログインユーザーが登録者になり詳細へ遷移する(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $response = $this->actingAs($user)->post(route('books.store'), $this->validInput([
            'genres' => $genres->pluck('id')->all(),
        ]));

        $book = Book::firstWhere('isbn', '9784873115658');
        $response->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', '書籍を登録しました。');
        $this->assertSame($user->id, $book->user_id);
        $this->assertSame('2012-06-23', $book->published_date->format('Y-m-d'));
        $this->assertEqualsCanonicalizing($genres->pluck('id')->all(), $book->genres->pluck('id')->all());
    }

    /** @test */
    public function 任意項目を省略しても書籍を登録できる(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('books.store'), $this->validInput(['description' => '', 'image_url' => '']))
            ->assertSessionHasNoErrors();

        $book = Book::firstWhere('isbn', '9784873115658');
        $this->assertNull($book->description);
        $this->assertNull($book->image_url);
    }

    /** @test */
    public function 出版日が未入力でも書籍を登録でき詳細には未登録と表示される(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('books.store'), $this->validInput(['published_date' => '']))
            ->assertSessionHasNoErrors();

        $book = Book::firstWhere('isbn', '9784873115658');
        $this->assertNull($book->published_date);
        $this->get(route('books.show', $book))->assertOk()->assertSee('出版日:', false)->assertSee('未登録');
    }

    /** @test */
    public function 出版日を空にして更新できる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create(['published_date' => '2012-06-23']);
        $book->genres()->attach(Genre::factory()->create());

        $this->actingAs($user)
            ->get(route('books.edit', $book))
            ->assertOk()
            ->assertSee('value="2012-06-23"', false);

        $this->actingAs($user)
            ->put(route('books.update', $book), $this->validInput(['isbn' => $book->isbn, 'published_date' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($book->fresh()->published_date);
        $this->actingAs($user)->get(route('books.edit', $book))->assertOk();
    }

    /** @test */
    public function 説明は長文でも登録できる(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('books.store'), $this->validInput(['description' => str_repeat('あ', 10000)]))
            ->assertSessionHasNoErrors();

        $this->assertSame(10000, mb_strlen(Book::firstWhere('isbn', '9784873115658')->description));
    }

    /** @test */
    public function 登録時にリクエストで送られた登録者の指定は無視される(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post(route('books.store'), $this->validInput(['user_id' => $other->id]));

        $this->assertSame($user->id, Book::firstWhere('isbn', '9784873115658')->user_id);
    }

    /**
     * @test
     *
     * @dataProvider 入力不備のパターン
     */
    public function 入力不備のある書籍は登録できず日本語のエラーになる(array $overrides, string $field, string $message): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('books.create'))
            ->post(route('books.store'), $this->validInput($overrides))
            ->assertRedirect(route('books.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function 入力不備のパターン(): array
    {
        return [
            'タイトル未入力' => [['title' => ''], 'title', 'タイトルは必須です。'],
            'タイトルが256文字' => [['title' => str_repeat('あ', 256)], 'title', 'タイトルは255文字以内で入力してください。'],
            '著者未入力' => [['author' => ''], 'author', '著者は必須です。'],
            '著者が256文字' => [['author' => str_repeat('あ', 256)], 'author', '著者は255文字以内で入力してください。'],
            'ISBN未入力' => [['isbn' => ''], 'isbn', 'ISBNは必須です。'],
            'ISBNが12桁' => [['isbn' => '978487311565'], 'isbn', 'ISBNは13桁の数字で入力してください。'],
            'ISBNが14桁' => [['isbn' => '97848731156581'], 'isbn', 'ISBNは13桁の数字で入力してください。'],
            'ISBNに数字以外を含む' => [['isbn' => '978-4873115658'], 'isbn', 'ISBNは13桁の数字で入力してください。'],
            '出版日が日付でない' => [['published_date' => '2012-13-45'], 'published_date', '出版日は正しい日付で入力してください。'],
            '画像URLがURL形式でない' => [['image_url' => 'image.jpg'], 'image_url', '画像URLは正しいURL形式で入力してください。'],
            '画像URLが256文字' => [['image_url' => 'https://example.com/'.str_repeat('a', 237)], 'image_url', '画像URLは255文字以内で入力してください。'],
            'ジャンル未選択' => [['genres' => []], 'genres', 'ジャンルは必須です。'],
            'ジャンルが配列でない' => [['genres' => 'abc'], 'genres', 'ジャンルは必須です。'],
            '存在しないジャンル' => [['genres' => [9999]], 'genres.0', 'ジャンルの選択が正しくありません。'],
            'ジャンルが数値でない' => [['genres' => ['abc']], 'genres.0', 'ジャンルの選択が正しくありません。'],
        ];
    }

    /** @test */
    public function 書籍番号が既に登録されている場合は登録できない(): void
    {
        Book::factory()->create(['isbn' => '9784873115658']);

        $this->actingAs(User::factory()->create())
            ->post(route('books.store'), $this->validInput())
            ->assertSessionHasErrors(['isbn' => 'このISBNは既に登録されています。']);
    }

    /** @test */
    public function 登録に失敗すると入力値が保持される(): void
    {
        $genre = Genre::factory()->create(['name' => '技術書']);

        $this->actingAs(User::factory()->create())
            ->from(route('books.create'))
            ->followingRedirects()
            ->post(route('books.store'), $this->validInput(['title' => '', 'author' => '保持される著者', 'genres' => [$genre->id]]))
            ->assertOk()
            ->assertSee('タイトルは必須です。')
            ->assertSee('value="保持される著者"', false)
            ->assertSee('value="'.$genre->id.'"', false);
    }

    /** @test */
    public function ジャンルに不正な形式を送って失敗してもフォームは表示できる(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('books.create'))
            ->followingRedirects()
            ->post(route('books.store'), $this->validInput(['genres' => 'abc']))
            ->assertOk()
            ->assertSee('ジャンルは必須です。');
    }

    /** @test */
    public function 同じジャンルの指定を重複して送っても登録できる(): void
    {
        $genre = Genre::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('books.store'), $this->validInput(['genres' => [$genre->id, $genre->id]]))
            ->assertSessionHasNoErrors();

        $this->assertCount(1, Book::firstWhere('isbn', '9784873115658')->genres);
    }

    // ---- 編集・更新 ----

    /** @test */
    public function 登録者は編集フォームを表示できる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create(['title' => '編集前のタイトル']);
        $book->genres()->attach($genre = Genre::factory()->create());

        $this->actingAs($user)
            ->get(route('books.edit', $book))
            ->assertOk()
            ->assertViewIs('books.edit')
            ->assertSee('編集前のタイトル')
            ->assertSee('value="'.$genre->id.'"', false);
    }

    /** @test */
    public function 登録者以外は編集フォームを表示できない(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('books.edit', $book))
            ->assertForbidden();
    }

    /** @test */
    public function 登録者は書籍を更新でき詳細へ遷移する(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        $book->genres()->attach(Genre::factory()->create());
        $newGenre = Genre::factory()->create();

        $this->actingAs($user)
            ->put(route('books.update', $book), $this->validInput(['title' => '更新後のタイトル', 'genres' => [$newGenre->id]]))
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', '書籍情報を更新しました。');

        $book->refresh();
        $this->assertSame('更新後のタイトル', $book->title);
        $this->assertSame([$newGenre->id], $book->genres->pluck('id')->all());
    }

    /** @test */
    public function 自分の書籍番号のままであれば更新できる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create(['isbn' => '9784873115658']);

        $this->actingAs($user)
            ->put(route('books.update', $book), $this->validInput(['isbn' => '9784873115658']))
            ->assertSessionHasNoErrors();
    }

    /** @test */
    public function 他の書籍と同じ書籍番号には更新できない(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        Book::factory()->create(['isbn' => '9784873115658']);

        $this->actingAs($user)
            ->from(route('books.edit', $book))
            ->put(route('books.update', $book), $this->validInput())
            ->assertRedirect(route('books.edit', $book))
            ->assertSessionHasErrors(['isbn' => 'このISBNは既に登録されています。']);
    }

    /** @test */
    public function 更新しても登録者は変わらない(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->actingAs($user)->put(route('books.update', $book), $this->validInput(['user_id' => User::factory()->create()->id]));

        $this->assertSame($user->id, $book->fresh()->user_id);
    }

    /** @test */
    public function 更新の入力不備は日本語のエラーになり元の値は変わらない(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create(['title' => '元のタイトル']);

        $this->actingAs($user)
            ->from(route('books.edit', $book))
            ->put(route('books.update', $book), $this->validInput(['title' => '']))
            ->assertRedirect(route('books.edit', $book))
            ->assertSessionHasErrors(['title' => 'タイトルは必須です。']);

        $this->assertSame('元のタイトル', $book->fresh()->title);
    }

    /** @test */
    public function 登録者以外は書籍を更新できない(): void
    {
        $book = Book::factory()->create(['title' => '元のタイトル']);

        $this->actingAs(User::factory()->create())
            ->put(route('books.update', $book), $this->validInput())
            ->assertForbidden();

        $this->assertSame('元のタイトル', $book->fresh()->title);
    }

    /** @test */
    public function 登録者以外は入力が不正でも検証より先に403になる(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('books.update', $book), $this->validInput(['title' => '']))
            ->assertForbidden();
    }

    // ---- 削除 ----

    /** @test */
    public function 登録者は書籍を削除でき関連データも削除される(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        $book->genres()->attach(Genre::factory()->create());
        $review = Review::factory()->for($book)->create();
        $review->likedByUsers()->attach(User::factory()->create());
        $user->favoriteBooks()->attach($book);

        $this->actingAs($user)
            ->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'))
            ->assertSessionHas('success', '書籍を削除しました。');

        $this->assertModelMissing($book);
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('book_genre', 0);
        $this->assertDatabaseCount('favorites', 0);
        $this->assertDatabaseCount('review_likes', 0);
        $this->assertDatabaseCount('genres', 1);
    }

    /** @test */
    public function 登録者以外は書籍を削除できない(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('books.destroy', $book))
            ->assertForbidden();

        $this->assertModelExists($book);
    }

    /** @test */
    public function 存在しない書籍の削除は404になる(): void
    {
        $this->actingAs(User::factory()->create())
            ->delete(route('books.destroy', 9999))
            ->assertNotFound();
    }

    // ---- 詳細画面の編集・削除ボタン ----

    /** @test */
    public function 詳細画面の編集と削除ボタンは登録者にのみ表示される(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create();

        $this->actingAs($owner)->get(route('books.show', $book))
            ->assertSee(route('books.edit', $book), false)
            ->assertSee('name="_method" value="DELETE"', false);

        $this->actingAs(User::factory()->create())->get(route('books.show', $book))
            ->assertDontSee(route('books.edit', $book), false)
            ->assertDontSee('name="_method" value="DELETE"', false);
    }

    /** @test */
    public function 詳細画面の編集と削除ボタンはゲストには表示されない(): void
    {
        $book = Book::factory()->create();

        $this->get(route('books.show', $book))
            ->assertDontSee(route('books.edit', $book), false)
            ->assertDontSee('name="_method" value="DELETE"', false);
    }
}
