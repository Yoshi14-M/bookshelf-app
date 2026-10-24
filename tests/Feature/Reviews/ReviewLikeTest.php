<?php

namespace Tests\Feature\Reviews;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLikeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ゲストはいいねできずログイン画面へ遷移する(): void
    {
        $review = Review::factory()->create();

        $this->post(route('reviews.like', $review))->assertRedirect(route('login'));

        $this->assertDatabaseCount('review_likes', 0);
    }

    /** @test */
    public function いいねの追加と解除と再追加ができる(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        $this->actingAs($user)->post(route('reviews.like', $review));
        $this->assertTrue($review->likedByUsers()->where('users.id', $user->id)->exists());

        $this->actingAs($user)->post(route('reviews.like', $review));
        $this->assertFalse($review->likedByUsers()->where('users.id', $user->id)->exists());

        $this->actingAs($user)->post(route('reviews.like', $review));
        $this->assertTrue($review->likedByUsers()->where('users.id', $user->id)->exists());
        $this->assertDatabaseCount('review_likes', 1);
    }

    /** @test */
    public function いいねの切り替え後は元の画面へ戻りメッセージは表示されない(): void
    {
        $review = Review::factory()->create();

        $this->actingAs(User::factory()->create())
            ->from(route('books.show', $review->book))
            ->post(route('reviews.like', $review))
            ->assertRedirect(route('books.show', $review->book))
            ->assertSessionMissing('success')
            ->assertSessionMissing('error');
    }

    /** @test */
    public function 自分のレビューにもいいねできる(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->for($user)->create();

        $this->actingAs($user)->post(route('reviews.like', $review))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('review_likes', ['user_id' => $user->id, 'review_id' => $review->id]);
    }

    /** @test */
    public function 書籍の登録者でない他人のレビューにもいいねできる(): void
    {
        $review = Review::factory()->for(Book::factory())->create();
        $liker = User::factory()->create();

        $this->actingAs($liker)->post(route('reviews.like', $review))->assertRedirect();

        $this->assertDatabaseHas('review_likes', ['user_id' => $liker->id, 'review_id' => $review->id]);
    }

    /** @test */
    public function 複数のユーザーのいいねが集計される(): void
    {
        $review = Review::factory()->create();

        foreach (User::factory()->count(3)->create() as $user) {
            $this->actingAs($user)->post(route('reviews.like', $review));
        }

        $this->assertSame(3, $review->likedByUsers()->count());
    }

    /** @test */
    public function 存在しないレビューへのいいねは404になる(): void
    {
        $this->actingAs(User::factory()->create())->post('/reviews/9999/like')->assertNotFound();
    }

    /** @test */
    public function 詳細画面にいいねの状態と件数が反映される(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        $this->actingAs($user)->get(route('books.show', $review->book))->assertSee('いいね (0)');

        $this->actingAs($user)->post(route('reviews.like', $review));

        // 1 回目のリクエストで読み込まれた関連のキャッシュを避けるため、取得し直したユーザーで確認する
        $this->actingAs($user->fresh())->get(route('books.show', $review->book))->assertSee('いいね済み (1)');
    }
}
