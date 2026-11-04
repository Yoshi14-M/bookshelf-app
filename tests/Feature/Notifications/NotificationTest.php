<?php

namespace Tests\Feature\Notifications;

use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-04 10:00:00');
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function notify(User $user, ReminderTiming $timing = ReminderTiming::OnDueDate, ?string $createdAt = null): DatabaseNotification
    {
        $plan = ReadingPlan::factory()->for($user)->create(['target_date' => '2026-11-04']);
        $user->notify(new ReadingPlanReminder($plan, $timing));

        // 時刻を固定しているため、作成日時では特定できない。計画の ID で探す
        $notification = $user->notifications()->get()->first(fn ($n) => $n->data['reading_plan_id'] === $plan->id);
        if ($createdAt !== null) {
            $notification->forceFill(['created_at' => $createdAt])->save();
        }

        return $notification;
    }

    // ---- 未認証 ----

    /** @test */
    public function 未認証ではログイン画面へ遷移する(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->post(route('notifications.read', 'abc'))->assertRedirect(route('login'));
    }

    // ---- 一覧 ----

    /** @test */
    public function 通知がなければ該当なしのメッセージが表示される(): void
    {
        $this->actingAs($this->user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('通知はありません。');
    }

    /** @test */
    public function 自分の通知だけが新しい順に表示される(): void
    {
        $old = $this->notify($this->user, ReminderTiming::ThreeDaysBefore, '2026-11-01 09:00:00');
        $new = $this->notify($this->user, ReminderTiming::OnDueDate, '2026-11-03 09:00:00');
        $this->notify(User::factory()->create(), ReminderTiming::ThreeDaysAfter);

        $this->actingAs($this->user)->get(route('notifications.index'))
            ->assertOk()
            ->assertViewHas('notifications', fn ($notifications) => $notifications->pluck('id')->all() === [$new->id, $old->id])
            ->assertSee('本日が読書の期日です')
            ->assertSee('読書期限が近づいています')
            ->assertDontSee('読書の期日を過ぎています');
    }

    /** @test */
    public function 既読の通知も一覧に残り既読にするボタンは未読にだけ表示される(): void
    {
        $unread = $this->notify($this->user);
        $read = $this->notify($this->user);
        $read->markAsRead();

        $response = $this->actingAs($this->user)->get(route('notifications.index'))->assertOk();

        $response->assertViewHas('notifications', fn ($notifications) => $notifications->count() === 2)
            ->assertSee(route('notifications.read', $unread->id), false)
            ->assertDontSee(route('notifications.read', $read->id), false)
            ->assertSee('未読');
    }

    /** @test */
    public function 通知の本文が画面に表示される(): void
    {
        $this->notify($this->user, ReminderTiming::ThreeDaysAfter);

        $this->actingAs($this->user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('の期日（2026-11-04）から3日が経過しました。');
    }

    // ---- 既読 ----

    /** @test */
    public function 通知を既読にできる(): void
    {
        $notification = $this->notify($this->user);

        $this->actingAs($this->user)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success', '通知を既読にしました。');

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame(0, $this->user->unreadNotifications()->count());
    }

    /** @test */
    public function 他人の通知は既読にできず存在を隠して404になる(): void
    {
        $others = $this->notify(User::factory()->create());

        $this->actingAs($this->user)
            ->post(route('notifications.read', $others->id))
            ->assertNotFound();

        $this->assertNull($others->fresh()->read_at);
    }

    /** @test */
    public function 存在しない通知は404になる(): void
    {
        $this->actingAs($this->user)
            ->post(route('notifications.read', 'not-exists'))
            ->assertNotFound();
    }
}
