<?php

namespace Tests\Feature\Services;

use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use App\Services\BookService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class BookServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BookService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(array $overrides = []): array
    {
        return array_merge([
            'title' => 'リーダブルコード',
            'author' => 'Dustin Boswell',
            'isbn' => '9784873115658',
            'published_date' => '2012-06-23',
            'description' => null,
            'image_url' => null,
        ], $overrides);
    }

    // ---- 登録 ----

    /** @test */
    public function 書籍を登録するとジャンルが紐付き登録者が設定される(): void
    {
        $owner = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $book = $this->service->create($owner, $this->attributes(), $genres->pluck('id')->all());

        $this->assertTrue($book->exists);
        $this->assertSame($owner->id, $book->user_id);
        $this->assertEqualsCanonicalizing($genres->pluck('id')->all(), $book->genres()->pluck('genres.id')->all());
    }

    /** @test */
    public function ジャンルの紐付けに失敗すると書籍の登録も取り消される(): void
    {
        $owner = User::factory()->create();

        try {
            $this->service->create($owner, $this->attributes(), [9999]);
            $this->fail('存在しないジャンルの紐付けは失敗するはず');
        } catch (QueryException) {
            // ロールバックされる
        }

        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('book_genre', 0);
    }

    // ---- 更新 ----

    /** @test */
    public function 書籍を更新するとジャンルが付け替わり登録者は変わらない(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create(['title' => '更新前']);
        $old = Genre::factory()->create();
        $book->genres()->attach($old);
        $new = Genre::factory()->create();

        $this->service->update($book, $this->attributes(['title' => '更新後']), [$new->id]);

        $book->refresh();
        $this->assertSame('更新後', $book->title);
        $this->assertSame($owner->id, $book->user_id);
        $this->assertSame([$new->id], $book->genres()->pluck('genres.id')->all());
    }

    /** @test */
    public function ジャンルの付け替えに失敗すると書籍の更新も取り消される(): void
    {
        $book = Book::factory()->create(['title' => '更新前']);
        $old = Genre::factory()->create();
        $book->genres()->attach($old);

        try {
            $this->service->update($book, $this->attributes(['title' => '更新後']), [9999]);
            $this->fail('存在しないジャンルの紐付けは失敗するはず');
        } catch (QueryException) {
            // ロールバックされる
        }

        $this->assertSame('更新前', $book->fresh()->title);
        $this->assertSame([$old->id], $book->genres()->pluck('genres.id')->all());
    }

    // ---- 削除 ----

    /**
     * 書籍に関連データ（ジャンル・レビュー・いいね・お気に入り・読書計画）を付けて返す。
     */
    private function bookWithRelations(): Book
    {
        $book = Book::factory()->create();
        $book->genres()->attach(Genre::factory()->create());
        $review = Review::factory()->for($book)->create();
        $review->likedByUsers()->attach(User::factory()->create());
        $book->favoritedByUsers()->attach(User::factory()->create());
        ReadingPlan::factory()->for($book)->create();

        return $book;
    }

    /** @test */
    public function 書籍を削除すると関連データも削除される(): void
    {
        $book = $this->bookWithRelations();

        $this->service->delete($book);

        $this->assertModelMissing($book);
        foreach (['reviews', 'review_likes', 'favorites', 'book_genre', 'reading_plans'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    /** @test */
    public function 他の書籍の関連データは削除されない(): void
    {
        $target = $this->bookWithRelations();
        $other = $this->bookWithRelations();

        $this->service->delete($target);

        $this->assertModelExists($other);
        $this->assertSame(1, Review::where('book_id', $other->id)->count());
        $this->assertSame(1, ReadingPlan::where('book_id', $other->id)->count());
        $this->assertSame(1, $other->favoritedByUsers()->count());
        $this->assertSame(1, $other->genres()->count());
        $this->assertDatabaseCount('review_likes', 1);
    }

    /** @test */
    public function 削除の途中で失敗すると関連データも書籍も残る(): void
    {
        $book = $this->bookWithRelations();
        // 関連データを削除した後、書籍そのものの削除で失敗させる
        Event::listen('eloquent.deleting: '.Book::class, function () {
            throw new RuntimeException('削除に失敗');
        });

        try {
            $this->service->delete($book);
            $this->fail('例外が発生するはず');
        } catch (RuntimeException) {
            // ロールバックされる
        }

        $this->assertModelExists($book);
        foreach (['reviews', 'review_likes', 'favorites', 'book_genre', 'reading_plans'] as $table) {
            $this->assertDatabaseCount($table, 1);
        }
    }
}
