<?php

namespace Tests\Feature\Favorites;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ゲストはお気に入りの追加と一覧表示でログイン画面へ遷移する(): void
    {
        $book = Book::factory()->create();

        $this->post(route('favorites.toggle', $book))->assertRedirect(route('login'));
        $this->get(route('favorites.index'))->assertRedirect(route('login'));

        $this->assertDatabaseCount('favorites', 0);
    }

    /** @test */
    public function お気に入りの追加と解除と再追加ができる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->post(route('favorites.toggle', $book));
        $this->assertTrue($user->favoriteBooks()->where('books.id', $book->id)->exists());

        $this->actingAs($user)->post(route('favorites.toggle', $book));
        $this->assertFalse($user->favoriteBooks()->where('books.id', $book->id)->exists());

        $this->actingAs($user)->post(route('favorites.toggle', $book));
        $this->assertTrue($user->favoriteBooks()->where('books.id', $book->id)->exists());
        $this->assertDatabaseCount('favorites', 1);
    }

    /** @test */
    public function お気に入りの切り替え後は元の画面へ戻りメッセージは表示されない(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book))
            ->assertRedirect(route('books.show', $book))
            ->assertSessionMissing('success')
            ->assertSessionMissing('error');

        $this->actingAs($user)
            ->from(route('favorites.index'))
            ->post(route('favorites.toggle', $book))
            ->assertRedirect(route('favorites.index'));
    }

    /** @test */
    public function 自分が登録した書籍もお気に入りにできる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->actingAs($user)->post(route('favorites.toggle', $book));

        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);
    }

    /** @test */
    public function お気に入りは他のユーザーに影響しない(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $book = Book::factory()->create();
        $other->favoriteBooks()->attach($book);

        $this->actingAs($user)->post(route('favorites.toggle', $book));
        $this->actingAs($user)->post(route('favorites.toggle', $book));

        $this->assertDatabaseHas('favorites', ['user_id' => $other->id, 'book_id' => $book->id]);
    }

    /** @test */
    public function 存在しない書籍のお気に入りは404になる(): void
    {
        $this->actingAs(User::factory()->create())->post('/books/9999/favorites')->assertNotFound();
    }

    /** @test */
    public function 詳細画面にお気に入りの状態が反映される(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->get(route('books.show', $book))->assertSee('お気に入りに追加');

        $user->favoriteBooks()->attach($book);

        // 1 回目のリクエストで読み込まれた関連のキャッシュを避けるため、取得し直したユーザーで確認する
        $this->actingAs($user->fresh())->get(route('books.show', $book))->assertSee('お気に入りから削除');
    }

    /** @test */
    public function お気に入り一覧には自分のお気に入りだけが表示される(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->favoriteBooks()->attach(Book::factory()->create(['title' => '自分のお気に入り']));
        $other->favoriteBooks()->attach(Book::factory()->create(['title' => '他人のお気に入り']));
        Book::factory()->create(['title' => '未登録の書籍']);

        $this->actingAs($user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertViewIs('favorites.index')
            ->assertSee('自分のお気に入り')
            ->assertDontSee('他人のお気に入り')
            ->assertDontSee('未登録の書籍');
    }

    /** @test */
    public function お気に入りがない場合はその旨が表示される(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('お気に入りに登録された書籍はありません。');
    }

    /** @test */
    public function お気に入り一覧は10件ごとにページネーションされる(): void
    {
        $user = User::factory()->create();
        $user->favoriteBooks()->attach(Book::factory()->count(12)->create());

        $first = $this->actingAs($user)->get(route('favorites.index'))->assertOk();
        $this->assertCount(10, $first->viewData('books')->items());
        $this->assertSame(12, $first->viewData('books')->total());

        $second = $this->actingAs($user)->get(route('favorites.index').'?page=2')->assertOk();
        $this->assertCount(2, $second->viewData('books')->items());
    }

    /** @test */
    public function お気に入り一覧は追加した新しい順に表示される(): void
    {
        $user = User::factory()->create();
        [$a, $b, $c] = Book::factory()->count(3)->create();
        $user->favoriteBooks()->attach($a, ['created_at' => now()->subDays(2)]);
        $user->favoriteBooks()->attach($c, ['created_at' => now()]);
        $user->favoriteBooks()->attach($b, ['created_at' => now()->subDay()]);

        $ids = $this->actingAs($user)->get(route('favorites.index'))->viewData('books')->pluck('id')->all();

        $this->assertSame([$c->id, $b->id, $a->id], $ids);
    }

    /** @test */
    public function お気に入り一覧のクエリ数は件数に依存しない(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $attach = function (int $count) use ($user, $genre) {
            Book::factory()->count($count)->create()->each(function (Book $book) use ($user, $genre) {
                $book->genres()->attach($genre);
                $user->favoriteBooks()->attach($book);
            });
        };

        $attach(2);
        $few = $this->countQueries(fn () => $this->actingAs($user)->get(route('favorites.index'))->assertOk());
        $attach(7);
        $many = $this->countQueries(fn () => $this->actingAs($user)->get(route('favorites.index'))->assertOk());

        $this->assertSame($few, $many);
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
