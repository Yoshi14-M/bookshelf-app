<?php

namespace Tests\Feature\Models;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 投稿者と対象書籍を取得できる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->for($user)->for($book)->create();

        $this->assertTrue($review->user->is($user));
        $this->assertTrue($review->book->is($book));
        $this->assertTrue($user->reviews->contains($review));
    }

    /** @test */
    public function 同じユーザーが同じ書籍に複数のレビューを投稿できる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        Review::factory()->count(2)->for($user)->for($book)->create();

        $this->assertSame(2, $book->reviews()->where('user_id', $user->id)->count());
    }

    /** @test */
    public function いいねしたユーザーを取得できる(): void
    {
        $review = Review::factory()->create();
        $likers = User::factory()->count(2)->create();

        $review->likedByUsers()->attach($likers);

        $this->assertCount(2, $review->likedByUsers);
        $this->assertTrue($likers->first()->likedReviews->contains($review));
    }

    /** @test */
    public function 投稿者自身のレビューにもいいねできる(): void
    {
        $review = Review::factory()->create();

        $review->likedByUsers()->attach($review->user);

        $this->assertTrue($review->user->likedReviews->contains($review));
    }

    /** @test */
    public function 同じレビューへのいいねを重複して追加しても1件のままである(): void
    {
        $review = Review::factory()->create();
        $user = User::factory()->create();

        $review->likedByUsers()->syncWithoutDetaching([$user->id]);
        $review->likedByUsers()->syncWithoutDetaching([$user->id]);

        $this->assertDatabaseCount('review_likes', 1);
    }

    /** @test */
    public function レビューを削除すると関連するいいねも削除される(): void
    {
        $review = Review::factory()->create();
        $review->likedByUsers()->attach(User::factory()->create());

        $review->delete();

        $this->assertDatabaseCount('review_likes', 0);
    }
}
