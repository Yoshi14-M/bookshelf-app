<?php

namespace Tests\Feature\Models;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 登録者を取得できる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->assertTrue($book->user->is($user));
        $this->assertTrue($user->books->contains($book));
    }

    /** @test */
    public function 複数のジャンルを紐付けて取得できる(): void
    {
        $book = Book::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $book->genres()->sync($genres->pluck('id'));

        $this->assertCount(2, $book->fresh()->genres);
        $this->assertTrue($genres->first()->books->contains($book));
    }

    /** @test */
    public function レビューを取得できる(): void
    {
        $book = Book::factory()->create();
        Review::factory()->count(3)->for($book)->create();

        $this->assertCount(3, $book->reviews);
    }

    /** @test */
    public function 平均評価と件数を集計できる(): void
    {
        $book = Book::factory()->create();
        Review::factory()->for($book)->create(['rating' => 5]);
        Review::factory()->for($book)->create(['rating' => 4]);
        Review::factory()->for($book)->create(['rating' => 3]);

        $aggregated = Book::withAvg('reviews', 'rating')->withCount('reviews')->find($book->id);

        $this->assertEquals(4.0, $aggregated->reviews_avg_rating);
        $this->assertSame(3, $aggregated->reviews_count);
    }

    /** @test */
    public function レビューのない書籍の平均評価はnullになる(): void
    {
        $book = Book::factory()->create();

        $aggregated = Book::withAvg('reviews', 'rating')->find($book->id);

        $this->assertNull($aggregated->reviews_avg_rating);
    }

    /** @test */
    public function 出版日はcastされず日付文字列のまま取得できる(): void
    {
        $book = Book::factory()->create(['published_date' => '2012-06-23']);

        $this->assertSame('2012-06-23', $book->fresh()->published_date);
    }

    /** @test */
    public function 書籍を削除すると関連するレビューとお気に入りとジャンル紐付けといいねも削除される(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();
        $book->genres()->attach($genre);
        $review = Review::factory()->for($book)->create();
        $user = User::factory()->create();
        $user->favoriteBooks()->attach($book);
        $user->likedReviews()->attach($review);

        $book->delete();

        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('favorites', 0);
        $this->assertDatabaseCount('book_genre', 0);
        $this->assertDatabaseCount('review_likes', 0);
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }

    /** @test */
    public function 書籍番号が重複する書籍は登録できない(): void
    {
        Book::factory()->create(['isbn' => '9784101010014']);

        $this->expectException(QueryException::class);

        Book::factory()->create(['isbn' => '9784101010014']);
    }
}
