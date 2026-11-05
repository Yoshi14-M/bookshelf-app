<?php

namespace Tests\Feature\Models;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function お気に入りの書籍を取得できる(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->count(2)->create();

        $user->favoriteBooks()->attach($books);

        $this->assertCount(2, $user->favoriteBooks);
        $this->assertTrue($user->favoriteBooks->contains($books->first()));
    }

    /** @test */
    public function お気に入りの登録日時が自動で記録される(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->favoriteBooks()->attach($book);

        $this->assertNotNull(DB::table('favorites')->value('created_at'));
    }

    /** @test */
    public function いいねしたレビューを取得できる(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        $user->likedReviews()->attach($review);

        $this->assertTrue($user->likedReviews->contains($review));
    }

    /** @test */
    public function ユーザーを削除すると登録した書籍と投稿したレビューとお気に入りも削除される(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        $review = Review::factory()->for($user)->create();
        $user->favoriteBooks()->attach($book);

        $user->delete();

        $this->assertModelMissing($book);
        $this->assertModelMissing($review);
        $this->assertDatabaseCount('favorites', 0);
    }

    /** @test */
    public function 同じ書籍を重複してお気に入りにするとデータベースの制約で拒否される(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->favoriteBooks()->attach($book);

        $this->expectException(QueryException::class);

        $user->favoriteBooks()->attach($book);
    }

    /** @test */
    public function 存在しない書籍はお気に入りにできない(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        $user->favoriteBooks()->attach(99999);
    }
}
