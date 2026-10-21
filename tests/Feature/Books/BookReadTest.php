<?php

namespace Tests\Feature\Books;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookReadTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ゲストでも書籍一覧を表示できる(): void
    {
        $book = Book::factory()->create(['title' => 'リーダブルコード']);

        $this->get('/books')
            ->assertOk()
            ->assertViewIs('books.index')
            ->assertSee('リーダブルコード')
            ->assertSee(route('books.show', $book), false);
    }

    /** @test */
    public function トップページでも同じ書籍一覧を表示できる(): void
    {
        Book::factory()->create(['title' => '吾輩は猫である']);

        $this->get('/')
            ->assertOk()
            ->assertViewIs('books.index')
            ->assertSee('吾輩は猫である');
    }

    /** @test */
    public function 書籍がない場合は登録されていない旨が表示される(): void
    {
        $this->get('/books')->assertOk()->assertSee('書籍が登録されていません。');
    }

    /** @test */
    public function 一覧は10件ごとにページネーションされる(): void
    {
        Book::factory()->count(12)->create();

        $first = $this->get('/books')->assertOk();
        $this->assertCount(10, $first->viewData('books')->items());
        $this->assertSame(12, $first->viewData('books')->total());

        $second = $this->get('/books?page=2')->assertOk();
        $this->assertCount(2, $second->viewData('books')->items());
    }

    /** @test */
    public function 一覧は新着順で表示される(): void
    {
        $old = Book::factory()->create(['created_at' => now()->subDays(2)]);
        $new = Book::factory()->create(['created_at' => now()]);
        $sameTime = Book::factory()->create(['created_at' => now()]);

        $ids = $this->get('/books')->viewData('books')->pluck('id')->all();

        $this->assertSame([$sameTime->id, $new->id, $old->id], $ids);
    }

    /** @test */
    public function 一覧にジャンルと平均評価が表示される(): void
    {
        $book = Book::factory()->create();
        $book->genres()->attach(Genre::factory()->create(['name' => '技術書']));
        Review::factory()->for($book)->create(['rating' => 5]);
        Review::factory()->for($book)->create(['rating' => 4]);

        $this->get('/books')
            ->assertOk()
            ->assertSee('技術書')
            ->assertSee('(4.5)');
    }

    /** @test */
    public function 一覧でレビューのない書籍には平均評価が表示されない(): void
    {
        Book::factory()->create();

        $this->get('/books')->assertOk()->assertDontSee('(0.0)');
    }

    /** @test */
    public function 一覧のクエリ数は書籍数に依存しない(): void
    {
        Book::factory()->count(2)->create()->each(fn (Book $book) => $this->attachRelations($book));
        $few = $this->countQueries(fn () => $this->get('/books')->assertOk());

        Book::factory()->count(8)->create()->each(fn (Book $book) => $this->attachRelations($book));
        $many = $this->countQueries(fn () => $this->get('/books')->assertOk());

        $this->assertSame($few, $many);
    }

    /** @test */
    public function ゲストでも書籍詳細を表示できる(): void
    {
        $book = Book::factory()->create([
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
            'isbn' => '9784048930598',
            'published_date' => '2017-12-18',
            'description' => 'クリーンなコードの書き方',
        ]);
        $book->genres()->attach(Genre::factory()->create(['name' => '技術書']));

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertSeeInOrder(['Robert C. Martin', '9784048930598', '2017-12-18', '技術書', 'クリーンなコードの書き方'])
            ->assertSee('レビューを投稿するには')
            ->assertSee('まだレビューはありません。');
    }

    /** @test */
    public function 存在しない書籍の詳細は404になる(): void
    {
        $this->get('/books/9999')->assertNotFound();
    }

    /** @test */
    public function 詳細のレビューは投稿日時の新しい順で投稿者といいね数が表示される(): void
    {
        $book = Book::factory()->create();
        $reviewer = User::factory()->create(['name' => '鈴木花子']);
        Review::factory()->for($book)->create(['comment' => '古いレビュー', 'created_at' => now()->subDay()]);
        $new = Review::factory()->for($book)->for($reviewer)->create(['comment' => '新しいレビュー', 'created_at' => now()]);
        $new->likedByUsers()->attach(User::factory()->count(2)->create());

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSeeInOrder(['新しいレビュー', '古いレビュー'])
            ->assertSee('鈴木花子')
            ->assertSee('いいね (2)');
    }

    /** @test */
    public function ログイン中はお気に入りといいねの状態が反映される(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->for($book)->create();
        $user->favoriteBooks()->attach($book);
        $user->likedReviews()->attach($review);

        $this->actingAs($user)
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('お気に入りから削除')
            ->assertSee('いいね済み (1)')
            ->assertDontSee('レビューを投稿するには');
    }

    /** @test */
    public function 詳細のクエリ数はレビュー数に依存しない(): void
    {
        $book = Book::factory()->create();
        Review::factory()->count(2)->for($book)->create()->each(fn (Review $review) => $review->likedByUsers()->attach(User::factory()->create()));
        $few = $this->countQueries(fn () => $this->get(route('books.show', $book))->assertOk());

        Review::factory()->count(8)->for($book)->create()->each(fn (Review $review) => $review->likedByUsers()->attach(User::factory()->create()));
        $many = $this->countQueries(fn () => $this->get(route('books.show', $book))->assertOk());

        $this->assertSame($few, $many);
    }

    /** @test */
    public function 未実装画面へのリンク先は準備中として応答する(): void
    {
        $this->get('/ranking')->assertStatus(501);
        $this->actingAs(User::factory()->create())->get('/favorites')->assertStatus(501);
    }

    /** @test */
    public function 未実装の認証必須画面にゲストがアクセスするとログイン画面へ遷移する(): void
    {
        $this->get('/books/create')->assertRedirect(route('login'));
    }

    private function attachRelations(Book $book): void
    {
        $book->genres()->attach(Genre::factory()->create());
        Review::factory()->count(2)->for($book)->create();
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
