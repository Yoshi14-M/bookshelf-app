<?php

namespace Tests\Feature;

use App\Enums\ReminderTiming;
use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * アクセス制御のステータスコードの一覧表（ゲストは認証へ、本人以外は 403、存在しない ID は 404）。
 *
 * 403（権限が無い）と 404（存在しない）を取り違えていないことを、保護された操作ごとに確認する。
 * 各機能のテストでも個別に確認しているが、ここで全体を網羅して抜けを防ぐ。
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private const MISSING_ID = 99999;

    /**
     * 所有者だけが操作できる画面・API。[HTTP メソッド, 対象, URI（`%d` は対象の ID）, 公開 API か]
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    public static function 所有者だけが操作できる操作(): array
    {
        return [
            '書籍の編集フォーム' => ['GET', 'book', '/books/%d/edit', false],
            '書籍の更新' => ['PUT', 'book', '/books/%d', false],
            '書籍の削除' => ['DELETE', 'book', '/books/%d', false],
            'レビューの編集フォーム' => ['GET', 'review', '/reviews/%d/edit', false],
            'レビューの更新' => ['PUT', 'review', '/reviews/%d', false],
            'レビューの削除' => ['DELETE', 'review', '/reviews/%d', false],
            '読書計画の編集フォーム' => ['GET', 'plan', '/reading-plans/%d/edit', false],
            '読書計画の更新' => ['PUT', 'plan', '/reading-plans/%d', false],
            '読書計画の削除' => ['DELETE', 'plan', '/reading-plans/%d', false],
            '読書計画の読了' => ['POST', 'plan', '/reading-plans/%d/complete', false],
            '公開APIの書籍の更新' => ['PUT', 'book', '/api/v1/books/%d', true],
            '公開APIの書籍の削除' => ['DELETE', 'book', '/api/v1/books/%d', true],
        ];
    }

    /**
     * 存在しない ID を指定すると 404 になる操作（所有者だけが操作できるものを除く）。
     *
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function 存在しないIDを指定する操作(): array
    {
        return [
            '書籍の詳細' => ['GET', '/books/99999', false],
            'ジャンルの詳細' => ['GET', '/genres/99999', false],
            'ジャンルの編集フォーム' => ['GET', '/genres/99999/edit', false],
            'ジャンルの更新' => ['PUT', '/genres/99999', false],
            'ジャンルの削除' => ['DELETE', '/genres/99999', false],
            'レビューの投稿' => ['POST', '/books/99999/reviews', false],
            'お気に入りの切り替え' => ['POST', '/books/99999/favorites', false],
            'いいねの切り替え' => ['POST', '/reviews/99999/like', false],
            '通知の既読化' => ['POST', '/notifications/not-exists/read', false],
            '公開APIの書籍の詳細' => ['GET', '/api/v1/books/99999', true],
            '存在しないパス' => ['GET', '/unknown-path', false],
        ];
    }

    /**
     * 所有者が持つ対象を作り、その ID を返す。
     */
    private function createTarget(string $kind, User $owner): int
    {
        return match ($kind) {
            'book' => Book::factory()->for($owner)->create()->id,
            'review' => Review::factory()->for($owner)->create()->id,
            'plan' => ReadingPlan::factory()->for($owner)->create()->id,
        };
    }

    /**
     * 公開 API は JSON、画面は通常のリクエストで送る。
     */
    private function send(string $method, string $uri, bool $api)
    {
        return $api ? $this->json($method, $uri, []) : $this->call($method, $uri);
    }

    // ---- ゲスト（未認証） ----

    /**
     * @test
     *
     * @dataProvider 所有者だけが操作できる操作
     */
    public function ゲストは画面ならログイン画面へ遷移し外部向けの機能なら401になる(string $method, string $kind, string $uri, bool $api): void
    {
        $id = $this->createTarget($kind, User::factory()->create());

        $response = $this->send($method, sprintf($uri, $id), $api);

        $api ? $response->assertUnauthorized() : $response->assertRedirect(route('login'));
    }

    // ---- 本人以外は 403 ----

    /**
     * @test
     *
     * @dataProvider 所有者だけが操作できる操作
     */
    public function 本人以外は入力が空でも404ではなく403になる(string $method, string $kind, string $uri, bool $api): void
    {
        $id = $this->createTarget($kind, User::factory()->create());
        $other = User::factory()->create();
        $api ? Sanctum::actingAs($other) : $this->actingAs($other);

        $this->send($method, sprintf($uri, $id), $api)->assertForbidden();
    }

    // ---- 存在しない ID は 404 ----

    /**
     * @test
     *
     * @dataProvider 所有者だけが操作できる操作
     */
    public function 所有者だけが操作できる対象でも存在しない番号は403ではなく404になる(string $method, string $kind, string $uri, bool $api): void
    {
        $user = User::factory()->create();
        $api ? Sanctum::actingAs($user) : $this->actingAs($user);

        $this->send($method, sprintf($uri, self::MISSING_ID), $api)->assertNotFound();
    }

    /**
     * @test
     *
     * @dataProvider 存在しないIDを指定する操作
     */
    public function 存在しない番号やパスは404になる(string $method, string $uri, bool $api): void
    {
        $user = User::factory()->create();
        $api ? Sanctum::actingAs($user) : $this->actingAs($user);

        $this->send($method, $uri, $api)->assertNotFound();
    }

    // ---- 他人の通知は 404（存在を隠す） ----

    /** @test */
    public function 他人の通知の既読化は403ではなく404になる(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->create();
        $owner->notify(new ReadingPlanReminder($plan, ReminderTiming::OnDueDate));
        $notificationId = $owner->notifications()->sole()->id;

        $this->actingAs(User::factory()->create())
            ->post(route('notifications.read', $notificationId))
            ->assertNotFound();
    }

    // ---- 共有リソースは誰でも操作できる（403 にならない） ----

    /** @test */
    public function ジャンルは他のユーザーが作成したものでも403にならない(): void
    {
        $genre = Genre::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get(route('genres.edit', $genre))->assertOk();
        $this->put(route('genres.update', $genre), ['name' => '別名'])->assertRedirect(route('genres.index'));
        $this->delete(route('genres.destroy', $genre))->assertRedirect(route('genres.index'));
    }
}
