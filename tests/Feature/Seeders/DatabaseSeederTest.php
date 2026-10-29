<?php

namespace Tests\Feature\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    /** @test */
    public function 要件どおりの件数が投入される(): void
    {
        $this->assertSame(5, User::count());
        $this->assertSame(10, Genre::count());
        $this->assertSame(11, Book::count());
        $this->assertSame(32, Review::count());
    }

    /** @test */
    public function 再実行しても件数が増えない(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, User::count());
        $this->assertSame(10, Genre::count());
        $this->assertSame(11, Book::count());
        $this->assertSame(32, Review::count());
        $this->assertDatabaseCount('book_genre', 16);
        $this->assertDatabaseCount('favorites', 19);
        $this->assertDatabaseCount('review_likes', 48);
    }

    /** @test */
    public function ユーザーのパスワードはハッシュ化されpasswordでログインできる値である(): void
    {
        $user = User::where('email', 'yamada@example.com')->first();

        $this->assertSame('山田太郎', $user->name);
        $this->assertNotSame('password', $user->password);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    /** @test */
    public function ジャンルは固定の10件である(): void
    {
        $this->assertEqualsCanonicalizing(
            ['小説', 'ビジネス', '技術書', '自己啓発', 'エッセイ', '歴史', '科学', '芸術', '料理', '旅行'],
            Genre::pluck('name')->all(),
        );
    }

    /** @test */
    public function 書籍の登録者は山田太郎でジャンルと画像が設定される(): void
    {
        $owner = User::where('email', 'yamada@example.com')->first();
        $this->assertSame(11, $owner->books()->count());

        $book = Book::where('isbn', '9784422100524')->with('genres')->first();

        $this->assertSame('人を動かす', $book->title);
        $this->assertSame('1936-10-01', $book->published_date->format('Y-m-d'));
        $this->assertEqualsCanonicalizing(['ビジネス', '自己啓発'], $book->genres->pluck('name')->all());
        $this->assertNotEmpty($book->description);
        $this->assertSame('https://placehold.co/200x300/e2e8f0/475569?text=2', $book->image_url);
    }

    /** @test */
    public function 全ての書籍にジャンルが紐付いている(): void
    {
        $this->assertSame(0, Book::doesntHave('genres')->count());
    }

    /** @test */
    public function 各書籍のレビューは2件から4件で評価は3から5である(): void
    {
        Book::withCount('reviews')->get()->each(function (Book $book) {
            $this->assertGreaterThanOrEqual(2, $book->reviews_count);
            $this->assertLessThanOrEqual(4, $book->reviews_count);
        });

        $this->assertSame(0, Review::whereNotBetween('rating', [3, 5])->count());
        $this->assertSame(0, Review::whereNull('comment')->orWhere('comment', '')->count());
    }

    /** @test */
    public function 同じ書籍に同じユーザーのレビューは重複しない(): void
    {
        $duplicates = Review::query()
            ->selectRaw('book_id, user_id')
            ->groupBy('book_id', 'user_id')
            ->havingRaw('count(*) > 1')
            ->get();

        $this->assertCount(0, $duplicates);
    }

    /** @test */
    public function 各ユーザーのお気に入りは3冊から5冊である(): void
    {
        User::withCount('favoriteBooks')->get()->each(function (User $user) {
            $this->assertGreaterThanOrEqual(3, $user->favorite_books_count);
            $this->assertLessThanOrEqual(5, $user->favorite_books_count);
        });
    }

    /** @test */
    public function 各レビューへのいいねは0人から3人で自分のレビューへのいいねは含まれない(): void
    {
        Review::withCount('likedByUsers')->get()->each(function (Review $review) {
            $this->assertLessThanOrEqual(3, $review->liked_by_users_count);
        });

        $this->assertSame(0, Review::whereHas(
            'likedByUsers',
            fn ($query) => $query->whereColumn('users.id', 'reviews.user_id'),
        )->count());
        $this->assertGreaterThan(0, Review::has('likedByUsers')->count());
        $this->assertGreaterThan(0, Review::doesntHave('likedByUsers')->count());
    }
}
