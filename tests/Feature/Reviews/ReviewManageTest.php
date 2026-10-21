<?php

namespace Tests\Feature\Reviews;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewManageTest extends TestCase
{
    use RefreshDatabase;

    // ---- 未認証 ----

    /** @test */
    public function 未認証ユーザーはレビューの投稿と編集と更新と削除ができない(): void
    {
        $review = Review::factory()->create(['rating' => 3]);

        $this->post(route('reviews.store', $review->book), ['rating' => 5])->assertRedirect(route('login'));
        $this->get(route('reviews.edit', $review))->assertRedirect(route('login'));
        $this->put(route('reviews.update', $review), ['rating' => 1])->assertRedirect(route('login'));
        $this->delete(route('reviews.destroy', $review))->assertRedirect(route('login'));

        $this->assertDatabaseCount('reviews', 1);
        $this->assertSame(3, $review->fresh()->rating);
    }

    // ---- 投稿 ----

    /** @test */
    public function レビューを投稿すると書籍詳細へ遷移し投稿者が記録される(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)
            ->post(route('reviews.store', $book), ['rating' => 4, 'comment' => 'とても参考になりました。'])
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', 'レビューを投稿しました。');

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => 'とても参考になりました。',
        ]);
    }

    /** @test */
    public function 投稿したレビューが書籍詳細に表示される(): void
    {
        $user = User::factory()->create(['name' => '鈴木花子']);
        $book = Book::factory()->create();

        $this->actingAs($user)
            ->followingRedirects()
            ->post(route('reviews.store', $book), ['rating' => 5, 'comment' => '何度も読み返しています。'])
            ->assertOk()
            ->assertSee('レビューを投稿しました。')
            ->assertSee('何度も読み返しています。')
            ->assertSee('鈴木花子');
    }

    /** @test */
    public function コメントを省略してもレビューを投稿できる(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('reviews.store', Book::factory()->create()), ['rating' => 3, 'comment' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull(Review::first()->comment);
    }

    /** @test */
    public function 評価は1から5の全ての値で投稿できる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        foreach (range(1, 5) as $rating) {
            $this->actingAs($user)->post(route('reviews.store', $book), ['rating' => $rating])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('reviews', 5);
    }

    /** @test */
    public function 同じユーザーが同じ書籍に何件でもレビューを投稿できる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $book), ['rating' => 4]);
        $this->actingAs($user)->post(route('reviews.store', $book), ['rating' => 5]);

        $this->assertSame(2, $book->reviews()->where('user_id', $user->id)->count());
    }

    /** @test */
    public function 自分が登録した書籍にもレビューを投稿できる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->actingAs($user)->post(route('reviews.store', $book), ['rating' => 5])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('reviews', 1);
    }

    /** @test */
    public function 投稿時にリクエストで送られた投稿者の指定は無視される(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $book), ['rating' => 5, 'user_id' => $other->id, 'book_id' => 9999]);

        $review = Review::first();
        $this->assertSame($user->id, $review->user_id);
        $this->assertSame($book->id, $review->book_id);
    }

    /** @test */
    public function 存在しない書籍へのレビュー投稿は404になる(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/books/9999/reviews', ['rating' => 5])
            ->assertNotFound();
    }

    /**
     * @test
     *
     * @dataProvider 入力不備のパターン
     *
     * @param  array<string, mixed>  $input
     */
    public function 入力不備のあるレビューは投稿できず日本語のエラーになる(array $input, string $field, string $message): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), $input)
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('reviews', 0);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function 入力不備のパターン(): array
    {
        return [
            '評価未選択' => [['rating' => '', 'comment' => 'a'], 'rating', '評価は必須です。'],
            '評価が0' => [['rating' => 0], 'rating', '評価は1から5の間で指定してください。'],
            '評価が6' => [['rating' => 6], 'rating', '評価は1から5の間で指定してください。'],
            '評価が整数でない' => [['rating' => 'abc'], 'rating', '評価は1から5の整数で指定してください。'],
            '評価が小数' => [['rating' => 3.5], 'rating', '評価は1から5の整数で指定してください。'],
            'コメントが1001文字' => [['rating' => 5, 'comment' => str_repeat('あ', 1001)], 'comment', 'コメントは1000文字以内で入力してください。'],
        ];
    }

    /** @test */
    public function コメントが1000文字ちょうどなら投稿できる(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('reviews.store', Book::factory()->create()), ['rating' => 5, 'comment' => str_repeat('あ', 1000)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('reviews', 1);
    }

    /** @test */
    public function 投稿に失敗するとエラーが表示され入力値が保持される(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->from(route('books.show', $book))
            ->followingRedirects()
            ->post(route('reviews.store', $book), ['rating' => '', 'comment' => '保持されるコメント'])
            ->assertOk()
            ->assertSee('評価は必須です。')
            ->assertSee('保持されるコメント');
    }

    // ---- 編集・更新 ----

    /** @test */
    public function 投稿者はレビュー編集フォームを表示できる(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->for($user)->create(['comment' => '編集前のコメント']);

        $this->actingAs($user)
            ->get(route('reviews.edit', $review))
            ->assertOk()
            ->assertViewIs('reviews.edit')
            ->assertSee($review->book->title)
            ->assertSee('編集前のコメント');
    }

    /** @test */
    public function 投稿者以外はレビュー編集フォームを表示できない(): void
    {
        $review = Review::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('reviews.edit', $review))
            ->assertForbidden();
    }

    /** @test */
    public function 書籍の登録者でも他人のレビューは編集できない(): void
    {
        $bookOwner = User::factory()->create();
        $review = Review::factory()->for(Book::factory()->for($bookOwner))->create(['comment' => '元のコメント']);

        $this->actingAs($bookOwner)->get(route('reviews.edit', $review))->assertForbidden();
        $this->actingAs($bookOwner)->put(route('reviews.update', $review), ['rating' => 1, 'comment' => '改ざん'])->assertForbidden();

        $this->assertSame('元のコメント', $review->fresh()->comment);
    }

    /** @test */
    public function 投稿者はレビューを更新でき書籍詳細へ遷移する(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->for($user)->create(['rating' => 3, 'comment' => '更新前']);

        $this->actingAs($user)
            ->put(route('reviews.update', $review), ['rating' => 5, 'comment' => '更新後'])
            ->assertRedirect(route('books.show', $review->book))
            ->assertSessionHas('success', 'レビューを更新しました。');

        $review->refresh();
        $this->assertSame(5, $review->rating);
        $this->assertSame('更新後', $review->comment);
        $this->assertSame($user->id, $review->user_id);
    }

    /** @test */
    public function 更新の入力不備は日本語のエラーになり元の値は変わらない(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->for($user)->create(['rating' => 3]);

        $this->actingAs($user)
            ->from(route('reviews.edit', $review))
            ->put(route('reviews.update', $review), ['rating' => 9])
            ->assertRedirect(route('reviews.edit', $review))
            ->assertSessionHasErrors(['rating' => '評価は1から5の間で指定してください。']);

        $this->assertSame(3, $review->fresh()->rating);
    }

    /** @test */
    public function 投稿者以外は入力が不正でも検証より先に403になる(): void
    {
        $review = Review::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('reviews.update', $review), ['rating' => 99])
            ->assertForbidden();
    }

    // ---- 削除 ----

    /** @test */
    public function 投稿者はレビューを削除でき関連するいいねも削除される(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->for($user)->create();
        $review->likedByUsers()->attach(User::factory()->count(2)->create());

        $this->actingAs($user)
            ->delete(route('reviews.destroy', $review))
            ->assertRedirect(route('books.show', $review->book))
            ->assertSessionHas('success', 'レビューを削除しました。');

        $this->assertModelMissing($review);
        $this->assertDatabaseCount('review_likes', 0);
        $this->assertModelExists($review->book);
    }

    /** @test */
    public function 投稿者以外は書籍の登録者であってもレビューを削除できない(): void
    {
        $bookOwner = User::factory()->create();
        $review = Review::factory()->for(Book::factory()->for($bookOwner))->create();

        $this->actingAs(User::factory()->create())->delete(route('reviews.destroy', $review))->assertForbidden();
        $this->actingAs($bookOwner)->delete(route('reviews.destroy', $review))->assertForbidden();

        $this->assertModelExists($review);
    }

    /** @test */
    public function 存在しないレビューの削除は404になる(): void
    {
        $this->actingAs(User::factory()->create())->delete('/reviews/9999')->assertNotFound();
    }

    // ---- 詳細画面の編集・削除リンク ----

    /** @test */
    public function 詳細画面のレビューの編集と削除は投稿者にのみ表示される(): void
    {
        $author = User::factory()->create();
        $review = Review::factory()->for($author)->create();
        $book = $review->book;

        $this->actingAs($author)->get(route('books.show', $book))
            ->assertSee(route('reviews.edit', $review), false);

        $this->actingAs(User::factory()->create())->get(route('books.show', $book))
            ->assertDontSee(route('reviews.edit', $review), false);

        $this->actingAs($book->user)->get(route('books.show', $book))
            ->assertDontSee(route('reviews.edit', $review), false);
    }
}
