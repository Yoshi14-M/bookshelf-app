<?php

namespace Tests\Feature\Layout;

use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 応用版の画面（Advanced Blade）のナビゲーションと、既存の全画面の表示。
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ログイン中のナビにマイレポートと読書計画と通知へのリンクが表示される(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('books.index'))
            ->assertOk()
            ->assertSee(route('reports.index'), false)
            ->assertSee(route('reading-plans.index'), false)
            ->assertSee(route('notifications.index'), false);
    }

    /** @test */
    public function 未読の通知件数がナビのバッジに表示され既読は数えない(): void
    {
        $user = User::factory()->create();
        foreach ([null, null, now()] as $readAt) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\Dummy',
                'data' => ['title' => 'テスト'],
                'read_at' => $readAt,
            ]);
        }

        $this->actingAs($user->fresh())->get(route('books.index'))
            ->assertOk()
            ->assertSee('bg-red-600 rounded-full">2</span>', false);

        $this->actingAs(User::factory()->create())->get(route('books.index'))
            ->assertOk()
            ->assertDontSee('bg-red-600 rounded-full', false);
    }

    /** @test */
    public function ゲストのナビには通知やマイレポートが表示されずゲストも一覧を閲覧できる(): void
    {
        $this->get(route('books.index'))->assertOk()->assertDontSee(route('notifications.index'), false);
    }

    /** @test */
    public function 応用版の画面に差し替えても全画面がログイン中に例外なく表示される(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->for($user)->create(['published_date' => null]);
        $book->genres()->attach($genre);
        $review = Review::factory()->for($book)->for($user)->create();
        $plan = ReadingPlan::factory()->for($user)->for($book)->create();

        $this->actingAs($user);

        foreach ([
            route('books.index'),
            route('books.show', $book),
            route('books.create'),
            route('books.edit', $book),
            route('genres.index'),
            route('genres.show', $genre),
            route('genres.create'),
            route('genres.edit', $genre),
            route('reviews.edit', $review),
            route('favorites.index'),
            route('ranking.index'),
            route('reports.index'),
            route('reading-plans.index'),
            route('reading-plans.create'),
            route('reading-plans.edit', $plan),
            route('notifications.index'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
