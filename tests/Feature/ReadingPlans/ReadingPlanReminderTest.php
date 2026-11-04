<?php

namespace Tests\Feature\ReadingPlans;

use App\Enums\ReadingPlanStatus;
use App\Enums\ReminderTiming;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use App\Services\ReadingPlanReminderService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class ReadingPlanReminderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 今日 = 2026-11-04（Asia/Tokyo）。
     */
    private const TODAY = '2026-11-04';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::TODAY.' 20:00:00');
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function plan(string $targetDate, ReadingPlanStatus $status = ReadingPlanStatus::InProgress, ?User $user = null): ReadingPlan
    {
        return ReadingPlan::factory()->for($user ?? $this->user)->create([
            'target_date' => $targetDate,
            'status' => $status,
            'completed_at' => $status === ReadingPlanStatus::Completed ? now() : null,
        ]);
    }

    private function service(): ReadingPlanReminderService
    {
        return app(ReadingPlanReminderService::class);
    }

    // ---- 自動失効 ----

    /** @test */
    public function 期日を過ぎた進行中の計画が期限切れになる(): void
    {
        $overdue = $this->plan('2026-11-03');
        $old = $this->plan('2026-10-01');

        $this->assertSame(2, $this->service()->expireOverdue());

        $this->assertSame(ReadingPlanStatus::Expired, $overdue->fresh()->status);
        $this->assertSame(ReadingPlanStatus::Expired, $old->fresh()->status);
    }

    /** @test */
    public function 期日が当日や先の計画は失効しない(): void
    {
        $today = $this->plan('2026-11-04');
        $future = $this->plan('2026-11-05');

        $this->assertSame(0, $this->service()->expireOverdue());

        $this->assertSame(ReadingPlanStatus::InProgress, $today->fresh()->status);
        $this->assertSame(ReadingPlanStatus::InProgress, $future->fresh()->status);
    }

    /** @test */
    public function 完了済みの計画は期日を過ぎていても変わらない(): void
    {
        $completed = $this->plan('2026-10-01', ReadingPlanStatus::Completed);

        $this->assertSame(0, $this->service()->expireOverdue());

        $this->assertSame(ReadingPlanStatus::Completed, $completed->fresh()->status);
        $this->assertNotNull($completed->fresh()->completed_at);
    }

    // ---- リマインダー ----

    /** @test */
    public function 期日の3日前と当日と3日後に各1通の通知が所有者に送られる(): void
    {
        $before = $this->plan('2026-11-07');
        $due = $this->plan('2026-11-04');
        $after = $this->plan('2026-11-01', ReadingPlanStatus::Expired);

        $counts = $this->service()->sendReminders();

        $this->assertSame(
            ['three_days_before' => 1, 'on_due_date' => 1, 'three_days_after' => 1],
            $counts->all(),
        );
        $this->assertSame(3, DatabaseNotification::count());
        $this->assertSame(3, $this->user->notifications()->count());

        $timings = $this->user->notifications->mapWithKeys(fn ($n) => [$n->data['reading_plan_id'] => $n->data['timing']]);
        $this->assertSame('three_days_before', $timings[$before->id]);
        $this->assertSame('on_due_date', $timings[$due->id]);
        $this->assertSame('three_days_after', $timings[$after->id]);
    }

    /** @test */
    public function 対象外の日付や状態の計画には通知されない(): void
    {
        $this->plan('2026-11-11');                                          // 7 日後
        $this->plan('2026-11-06');                                          // 2 日後
        $this->plan('2026-11-05');                                          // 1 日後
        $this->plan('2026-11-03', ReadingPlanStatus::Expired);              // 1 日前の期限切れ
        $this->plan('2026-11-07', ReadingPlanStatus::Completed);            // 3 日前でも完了済み
        $this->plan('2026-11-04', ReadingPlanStatus::Completed);            // 当日でも完了済み
        $this->plan('2026-11-01', ReadingPlanStatus::Completed);            // 3 日後でも完了済み
        $this->plan('2026-11-04', ReadingPlanStatus::Expired);              // 当日の期限切れ
        $this->plan('2026-11-07', ReadingPlanStatus::Expired);              // 3 日前の期限切れ

        $this->assertSame(0, $this->service()->sendReminders()->sum());
        $this->assertSame(0, DatabaseNotification::count());
    }

    /** @test */
    public function 失効前の進行中の計画は3日後の通知の対象にならない(): void
    {
        $this->plan('2026-11-01');

        $this->assertSame(0, $this->service()->sendReminders()->sum());
    }

    /** @test */
    public function 失効してからリマインドすると期日の3日後の計画に通知される(): void
    {
        // シードの二重シナリオ: 期日が 3 日前の進行中の計画は、失効して 3 日後の通知対象にもなる
        $plan = $this->plan('2026-11-01');

        $this->service()->expireOverdue();
        $this->service()->sendReminders();

        $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
        $this->assertSame('three_days_after', $this->user->notifications()->sole()->data['timing']);
    }

    /** @test */
    public function 通知は計画の所有者ごとに送られる(): void
    {
        $other = User::factory()->create();
        $this->plan('2026-11-04');
        $this->plan('2026-11-04', user: $other);

        $this->service()->sendReminders();

        $this->assertSame(1, $this->user->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
    }

    /** @test */
    public function 期日を変更した計画は実行した時点の期日で判定される(): void
    {
        $plan = $this->plan('2026-11-07');
        $plan->update(['target_date' => '2026-11-20']);

        $this->assertSame(0, $this->service()->sendReminders()->sum());

        $plan->update(['target_date' => '2026-11-04']);

        $this->assertSame(['on_due_date' => 1], $this->service()->sendReminders()->filter()->all());
    }

    /** @test */
    public function 通知はデータベースチャネルのみで送られる(): void
    {
        Notification::fake();
        $this->plan('2026-11-04');

        $this->service()->sendReminders();

        Notification::assertSentTo(
            $this->user,
            ReadingPlanReminder::class,
            fn (ReadingPlanReminder $notification, array $channels) => $channels === ['database'],
        );
    }

    /**
     * @return array<string, array{0: ReminderTiming, 1: string, 2: string, 3: string, 4: ReadingPlanStatus}>
     */
    public static function timings(): array
    {
        return [
            '3日前' => [ReminderTiming::ThreeDaysBefore, '2026-11-07', '読書期限が近づいています', '「吾輩は猫である」の期日（2026-11-07）まであと3日です。', ReadingPlanStatus::InProgress],
            '当日' => [ReminderTiming::OnDueDate, '2026-11-04', '本日が読書の期日です', '「吾輩は猫である」の期日は本日（2026-11-04）です。', ReadingPlanStatus::InProgress],
            '3日後' => [ReminderTiming::ThreeDaysAfter, '2026-11-01', '読書の期日を過ぎています', '「吾輩は猫である」の期日（2026-11-01）から3日が経過しました。', ReadingPlanStatus::Expired],
        ];
    }

    /**
     * @test
     *
     * @dataProvider timings
     */
    public function 通知の内容にタイトルと本文と計画を特定する情報が入る(ReminderTiming $timing, string $targetDate, string $title, string $body, ReadingPlanStatus $status): void
    {
        $book = Book::factory()->create(['title' => '吾輩は猫である']);
        $plan = ReadingPlan::factory()->for($this->user)->for($book)->create(['target_date' => $targetDate, 'status' => $status]);

        $this->service()->sendReminders();

        $notification = $this->user->notifications()->sole();
        $this->assertSame(ReadingPlanReminder::class, $notification->type);
        $this->assertSame([
            'title' => $title,
            'body' => $body,
            'timing' => $timing->value,
            'reading_plan_id' => $plan->id,
            'book_id' => $book->id,
            'book_title' => '吾輩は猫である',
            'target_date' => $targetDate,
        ], $notification->data);
        $this->assertNull($notification->read_at);
    }

    /** @test */
    public function 送信の途中で失敗した場合は通知が1通も残らない(): void
    {
        $this->plan('2026-11-07');
        $this->plan('2026-11-04');
        $this->plan('2026-11-01', ReadingPlanStatus::Expired);

        $sent = 0;
        EventFacade::listen(NotificationSending::class, function () use (&$sent) {
            if (++$sent === 2) {
                throw new RuntimeException('送信に失敗');
            }
        });

        try {
            $this->service()->sendReminders();
            $this->fail('例外が発生するはず');
        } catch (RuntimeException) {
            // ロールバックされる
        }

        $this->assertSame(0, DatabaseNotification::count());
    }

    // ---- Artisan コマンド ----

    /** @test */
    public function 失効コマンドは期限切れにした件数を表示する(): void
    {
        $plan = $this->plan('2026-11-03');

        $this->artisan('reading-plans:expire')
            ->expectsOutput('1 件の読書計画を期限切れにしました。')
            ->assertSuccessful();

        $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
    }

    /** @test */
    public function リマインドコマンドは通知を送りタイミングごとの件数を表示する(): void
    {
        $this->plan('2026-11-07');
        $this->plan('2026-11-04');

        $this->artisan('reading-plans:remind')
            ->expectsOutput('読書期限が近づいています（three_days_before）: 1 件')
            ->expectsOutput('本日が読書の期日です（on_due_date）: 1 件')
            ->expectsOutput('読書の期日を過ぎています（three_days_after）: 0 件')
            ->expectsOutput('合計 2 件の通知を送りました。')
            ->assertSuccessful();

        $this->assertSame(2, DatabaseNotification::count());
    }

    /** @test */
    public function 対象の計画がなくてもコマンドは正常終了する(): void
    {
        $this->artisan('reading-plans:expire')->expectsOutput('0 件の読書計画を期限切れにしました。')->assertSuccessful();
        $this->artisan('reading-plans:remind')->expectsOutput('合計 0 件の通知を送りました。')->assertSuccessful();
    }

    // ---- スケジュール ----

    /**
     * @return array<string, Event>
     */
    private function scheduledEvents(): array
    {
        $events = [];
        foreach (app(Schedule::class)->events() as $event) {
            $events[str_contains($event->command, 'reading-plans:expire') ? 'expire' : 'remind'] = $event;
        }

        return $events;
    }

    /** @test */
    public function 毎日20時に失効とリマインドの2つのコマンドが登録される(): void
    {
        $events = app(Schedule::class)->events();

        $this->assertCount(2, $events);
        $this->assertStringContainsString('reading-plans:expire', $events[0]->command, '先に失効');
        $this->assertStringContainsString('reading-plans:remind', $events[1]->command, '次にリマインド');
        foreach ($events as $event) {
            $this->assertSame('0 20 * * *', $event->expression);
            $this->assertSame('Asia/Tokyo', $event->timezone);
        }
    }

    /** @test */
    public function 実行時刻は設定で変更できる(): void
    {
        config(['bookshelf.reading_plan_batch_time' => '06:30']);

        foreach ($this->scheduledEvents() as $event) {
            $this->assertSame('30 6 * * *', $event->expression);
        }
    }

    /** @test */
    public function 設定した時刻の分だけ実行対象になる(): void
    {
        $events = $this->scheduledEvents();

        Carbon::setTestNow(self::TODAY.' 20:00:00');
        $this->assertTrue($events['expire']->isDue(app()) && $events['remind']->isDue(app()));

        Carbon::setTestNow(self::TODAY.' 19:59:00');
        $this->assertFalse($events['expire']->isDue(app()) || $events['remind']->isDue(app()));
    }
}
