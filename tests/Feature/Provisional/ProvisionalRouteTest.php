<?php

namespace Tests\Feature\Provisional;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Advanced Blade が参照する未実装ルート（暫定ルート）と、それを前提にしたナビの表示。
 * 本実装（#12〜#15）に置き換えたら、該当のテストは各機能のテストへ移して削除する。
 */
class ProvisionalRouteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provisionalRoutes(): array
    {
        return [
            'マイレポート' => ['get', '/reports'],
            '読書計画の一覧' => ['get', '/reading-plans'],
            '読書計画の作成フォーム' => ['get', '/reading-plans/create'],
            '読書計画の登録' => ['post', '/reading-plans'],
            '読書計画の編集フォーム' => ['get', '/reading-plans/1/edit'],
            '読書計画の更新' => ['put', '/reading-plans/1'],
            '読書計画の削除' => ['delete', '/reading-plans/1'],
            '読書計画の完了' => ['post', '/reading-plans/1/complete'],
            '通知一覧' => ['get', '/notifications'],
            '通知の既読化' => ['post', '/notifications/abc/read'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provisionalRoutes
     */
    public function 暫定ルートは未認証だとログイン画面へ遷移する(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertRedirect(route('login'));
    }

    /**
     * @test
     *
     * @dataProvider provisionalRoutes
     */
    public function 暫定ルートは認証済みだと準備中の501を返す(string $method, string $uri): void
    {
        $this->actingAs(User::factory()->create())->{$method}($uri)->assertStatus(501);
    }

    /** @test */
    public function 暫定ルートの名前が画面から参照できる(): void
    {
        foreach (['reports.index', 'reading-plans.index', 'reading-plans.create', 'notifications.index'] as $name) {
            $this->assertNotEmpty(route($name));
        }
        $this->assertStringEndsWith('/reading-plans/5/complete', route('reading-plans.complete', 5));
        $this->assertStringEndsWith('/notifications/abc/read', route('notifications.read', 'abc'));
    }

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
    public function 応用版の画面に差し替えても既存の全画面がログイン中に例外なく表示される(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->for($user)->create(['published_date' => null]);
        $book->genres()->attach($genre);
        $review = Review::factory()->for($book)->for($user)->create();

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
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
