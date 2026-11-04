<?php

namespace Tests\Feature\ReadingPlans;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingPlanTest extends TestCase
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

    private function plan(array $attributes = [], ?User $user = null): ReadingPlan
    {
        return ReadingPlan::factory()->for($user ?? $this->user)->create($attributes);
    }

    // ---- 未認証 ----

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guestRoutes(): array
    {
        return [
            '一覧' => ['get', '/reading-plans'],
            '作成フォーム' => ['get', '/reading-plans/create'],
            '登録' => ['post', '/reading-plans'],
            '編集フォーム' => ['get', '/reading-plans/1/edit'],
            '更新' => ['put', '/reading-plans/1'],
            '削除' => ['delete', '/reading-plans/1'],
            '読了' => ['post', '/reading-plans/1/complete'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider guestRoutes
     */
    public function 未認証ではログイン画面へ遷移する(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertRedirect(route('login'));
    }

    // ---- 一覧 ----

    /** @test */
    public function 計画がなければ該当なしのメッセージが表示される(): void
    {
        $this->actingAs($this->user)->get(route('reading-plans.index'))
            ->assertOk()
            ->assertSee('該当する読書計画はありません。');
    }

    /** @test */
    public function 自分の計画だけが期日の昇順で表示され同じ日は_i_d順になる(): void
    {
        $other = User::factory()->create();
        $later = $this->plan(['target_date' => '2026-12-10', 'book_id' => Book::factory()->create(['title' => '十二月の本'])]);
        $first = $this->plan(['target_date' => '2026-11-10', 'book_id' => Book::factory()->create(['title' => '十一月の本その1'])]);
        $second = $this->plan(['target_date' => '2026-11-10', 'book_id' => Book::factory()->create(['title' => '十一月の本その2'])]);
        $this->plan(['book_id' => Book::factory()->create(['title' => '他人の本'])], $other);

        $this->actingAs($this->user)->get(route('reading-plans.index'))
            ->assertOk()
            ->assertViewHas('readingPlans', fn ($plans) => $plans->pluck('id')->all() === [$first->id, $second->id, $later->id])
            ->assertDontSee('他人の本');
    }

    /** @test */
    public function 状態で絞り込める(): void
    {
        $this->plan(['book_id' => Book::factory()->create(['title' => '進行中の本'])]);
        $this->plan(['book_id' => Book::factory()->create(['title' => '期限切れの本'])] + ['status' => ReadingPlanStatus::Expired]);
        $this->plan(['book_id' => Book::factory()->create(['title' => '完了した本'])] + ['status' => ReadingPlanStatus::Completed]);

        $this->actingAs($this->user)->get(route('reading-plans.index', ['status' => 'expired']))
            ->assertOk()
            ->assertSee('期限切れの本')
            ->assertDontSee('進行中の本')
            ->assertDontSee('完了した本')
            ->assertViewHas('currentStatus', 'expired');
    }

    /** @test */
    public function 未知の状態は絞り込まずに全件を表示する(): void
    {
        $this->plan();
        $this->plan();

        foreach (['unknown', '', 'COMPLETED'] as $status) {
            $this->actingAs($this->user)->get(route('reading-plans.index', ['status' => $status]))
                ->assertOk()
                ->assertViewHas('readingPlans', fn ($plans) => $plans->count() === 2)
                ->assertViewHas('currentStatus', null);
        }

        $this->actingAs($this->user)->get('/reading-plans?status[]=completed')
            ->assertOk()
            ->assertViewHas('readingPlans', fn ($plans) => $plans->count() === 2);
    }

    /** @test */
    public function 状態のラベルと操作ボタンが状態ごとに表示される(): void
    {
        $inProgress = $this->plan();
        $completed = $this->plan(['status' => ReadingPlanStatus::Completed, 'completed_at' => now()]);

        $response = $this->actingAs($this->user)->get(route('reading-plans.index'))->assertOk();

        $response->assertSee('進行中')->assertSee('完了')
            ->assertSee(route('reading-plans.edit', $inProgress), false)
            ->assertSee(route('reading-plans.complete', $inProgress), false)
            ->assertDontSee(route('reading-plans.edit', $completed), false)
            ->assertDontSee(route('reading-plans.complete', $completed), false)
            ->assertSee(route('reading-plans.destroy', $completed), false);
    }

    // ---- 作成フォーム ----

    /** @test */
    public function 作成フォームには全書籍がタイトルの昇順で表示される(): void
    {
        $c = Book::factory()->create(['title' => 'C']);
        $a = Book::factory()->create(['title' => 'A']);
        $b = Book::factory()->create(['title' => 'B']);

        $this->actingAs($this->user)->get(route('reading-plans.create'))
            ->assertOk()
            ->assertViewHas('books', fn ($books) => $books->pluck('id')->all() === [$a->id, $b->id, $c->id]);
    }

    // ---- 登録 ----

    /** @test */
    public function 書籍と期日を指定して進行中の計画を登録できる(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => $book->id, 'target_date' => '2026-12-01'])
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を登録しました。');

        $plan = ReadingPlan::sole();
        $this->assertSame($this->user->id, $plan->user_id);
        $this->assertSame($book->id, $plan->book_id);
        $this->assertSame('2026-12-01', $plan->target_date->format('Y-m-d'));
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
        $this->assertNull($plan->completed_at);
    }

    /** @test */
    public function 期日は今日でも登録できる(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => $book->id, 'target_date' => '2026-11-04'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ReadingPlan::count());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidStoreInputs(): array
    {
        return [
            '書籍が未選択' => [['book_id' => '', 'target_date' => '2026-12-01'], 'book_id', '書籍は必須です。'],
            '存在しない書籍' => [['book_id' => 99999, 'target_date' => '2026-12-01'], 'book_id', '選択した書籍が存在しません。'],
            '書籍が数値でない' => [['book_id' => 'abc', 'target_date' => '2026-12-01'], 'book_id', '書籍の指定が正しくありません。'],
            '期日が未入力' => [['target_date' => ''], 'target_date', '期日は必須です。'],
            '期日が日付でない' => [['target_date' => '明日'], 'target_date', '期日は有効な日付で入力してください。'],
            '期日が昨日' => [['target_date' => '2026-11-03'], 'target_date', '期日は今日以降の日付を指定してください。'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider invalidStoreInputs
     *
     * @param  array<string, mixed>  $input
     */
    public function 不正な入力はエラーになり計画は登録されない(array $input, string $field, string $message): void
    {
        $book = Book::factory()->create();
        $input += ['book_id' => $book->id];

        $this->actingAs($this->user)
            ->from(route('reading-plans.create'))
            ->post(route('reading-plans.store'), $input)
            ->assertRedirect(route('reading-plans.create'))
            ->assertSessionHasErrors([$field => $message])
            ->assertSessionHasInput('target_date', $input['target_date'] ?? null);

        $this->assertSame(0, ReadingPlan::count());
    }

    /** @test */
    public function 進行中の計画がある書籍は重複して登録できない(): void
    {
        $book = Book::factory()->create();
        $this->plan(['book_id' => $book->id]);

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => $book->id, 'target_date' => '2026-12-01'])
            ->assertSessionHasErrors(['book_id' => 'この書籍には進行中の読書計画が既にあります。']);

        $this->assertSame(1, ReadingPlan::count());
    }

    /** @test */
    public function 完了済みや期限切れの計画が残っている書籍は再計画できる(): void
    {
        $completedBook = Book::factory()->create();
        $expiredBook = Book::factory()->create();
        $this->plan(['book_id' => $completedBook->id, 'status' => ReadingPlanStatus::Completed, 'completed_at' => now()]);
        $this->plan(['book_id' => $expiredBook->id, 'status' => ReadingPlanStatus::Expired]);

        foreach ([$completedBook, $expiredBook] as $book) {
            $this->actingAs($this->user)
                ->post(route('reading-plans.store'), ['book_id' => $book->id, 'target_date' => '2026-12-01'])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(4, ReadingPlan::count());
    }

    /** @test */
    public function 他のユーザーの進行中の計画は重複の判定に影響しない(): void
    {
        $book = Book::factory()->create();
        $this->plan(['book_id' => $book->id], User::factory()->create());

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => $book->id, 'target_date' => '2026-12-01'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ReadingPlan::count());
    }

    // ---- 編集フォーム ----

    /** @test */
    public function 所有者は既存の期日が初期値の編集フォームを開ける(): void
    {
        $plan = $this->plan(['target_date' => '2026-12-24']);

        $this->actingAs($this->user)->get(route('reading-plans.edit', $plan))
            ->assertOk()
            ->assertSee('value="2026-12-24"', false)
            ->assertSee($plan->book->title);
    }

    /** @test */
    public function 期限切れの計画も編集フォームを開ける(): void
    {
        $plan = $this->plan(['status' => ReadingPlanStatus::Expired, 'target_date' => '2026-11-01']);

        $this->actingAs($this->user)->get(route('reading-plans.edit', $plan))->assertOk()->assertSee('期限切れ');
    }

    /** @test */
    public function 他人の計画と完了した計画の編集フォームは403になる(): void
    {
        $others = $this->plan([], User::factory()->create());
        $completed = $this->plan(['status' => ReadingPlanStatus::Completed, 'completed_at' => now()]);

        $this->actingAs($this->user)->get(route('reading-plans.edit', $others))->assertForbidden();
        $this->actingAs($this->user)->get(route('reading-plans.edit', $completed))->assertForbidden();
    }

    // ---- 更新 ----

    /** @test */
    public function 進行中の計画の期日を変更できる(): void
    {
        $plan = $this->plan(['target_date' => '2026-11-20']);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => '2026-12-31'])
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を更新しました。');

        $plan->refresh();
        $this->assertSame('2026-12-31', $plan->target_date->format('Y-m-d'));
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
    }

    /** @test */
    public function 期限切れの計画の期日を今日以降に変更すると進行中に戻る(): void
    {
        $plan = $this->plan(['status' => ReadingPlanStatus::Expired, 'target_date' => '2026-11-01']);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => '2026-11-04'])
            ->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
        $this->assertSame('2026-11-04', $plan->target_date->format('Y-m-d'));
    }

    /** @test */
    public function 期限切れの計画は過去の日付に変更できず状態も変わらない(): void
    {
        $plan = $this->plan(['status' => ReadingPlanStatus::Expired, 'target_date' => '2026-11-01']);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => '2026-11-03'])
            ->assertSessionHasErrors(['target_date' => '期日は今日以降の日付を指定してください。']);

        $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
    }

    /** @test */
    public function 期限切れの計画を戻すと同じ書籍の進行中の計画と重複する場合は変更できない(): void
    {
        $book = Book::factory()->create();
        $expired = $this->plan(['book_id' => $book->id, 'status' => ReadingPlanStatus::Expired, 'target_date' => '2026-11-01']);
        $this->plan(['book_id' => $book->id]);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $expired), ['target_date' => '2026-12-01'])
            ->assertSessionHasErrors(['target_date' => 'この書籍には進行中の読書計画が既にあるため、期日を変更できません。']);

        $expired->refresh();
        $this->assertSame(ReadingPlanStatus::Expired, $expired->status);
        $this->assertSame('2026-11-01', $expired->target_date->format('Y-m-d'));
    }

    /** @test */
    public function 進行中の計画の期日は過去に変更できない(): void
    {
        $plan = $this->plan(['target_date' => '2026-11-20']);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => '2026-11-03'])
            ->assertSessionHasErrors('target_date');
        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => ''])
            ->assertSessionHasErrors(['target_date' => '期日は必須です。']);

        $this->assertSame('2026-11-20', $plan->fresh()->target_date->format('Y-m-d'));
    }

    /** @test */
    public function 他人の計画の更新は入力が不正でも403になる(): void
    {
        $plan = $this->plan(['target_date' => '2026-11-20'], User::factory()->create());

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => '2000-01-01'])
            ->assertForbidden();
        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => '2026-12-31'])
            ->assertForbidden();

        $this->assertSame('2026-11-20', $plan->fresh()->target_date->format('Y-m-d'));
    }

    /** @test */
    public function 完了した計画は更新できない(): void
    {
        $plan = $this->plan(['status' => ReadingPlanStatus::Completed, 'completed_at' => now(), 'target_date' => '2026-11-01']);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => '2026-12-31'])
            ->assertForbidden();

        $this->assertSame('2026-11-01', $plan->fresh()->target_date->format('Y-m-d'));
    }

    // ---- 削除 ----

    /** @test */
    public function 所有者は状態にかかわらず計画を削除できる(): void
    {
        $plans = [
            $this->plan(),
            $this->plan(['status' => ReadingPlanStatus::Expired]),
            $this->plan(['status' => ReadingPlanStatus::Completed, 'completed_at' => now()]),
        ];

        foreach ($plans as $plan) {
            $this->actingAs($this->user)
                ->delete(route('reading-plans.destroy', $plan))
                ->assertRedirect(route('reading-plans.index'))
                ->assertSessionHas('success', '読書計画を削除しました。');
        }

        $this->assertSame(0, ReadingPlan::count());
    }

    /** @test */
    public function 他人の計画は削除できない(): void
    {
        $plan = $this->plan([], User::factory()->create());

        $this->actingAs($this->user)->delete(route('reading-plans.destroy', $plan))->assertForbidden();

        $this->assertSame(1, ReadingPlan::count());
    }

    /** @test */
    public function 書籍を削除するとその書籍の計画も削除される(): void
    {
        $plan = $this->plan();

        $plan->book->delete();

        $this->assertSame(0, ReadingPlan::count());
    }

    // ---- 読了 ----

    /** @test */
    public function 進行中の計画を読了にすると完了日時が記録される(): void
    {
        $plan = $this->plan();

        $this->actingAs($this->user)
            ->post(route('reading-plans.complete', $plan))
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を完了にしました。');

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::Completed, $plan->status);
        $this->assertEquals(now(), $plan->completed_at);
    }

    /** @test */
    public function 期限切れの計画も読了にできる(): void
    {
        $plan = $this->plan(['status' => ReadingPlanStatus::Expired]);

        $this->actingAs($this->user)->post(route('reading-plans.complete', $plan));

        $this->assertSame(ReadingPlanStatus::Completed, $plan->fresh()->status);
    }

    /** @test */
    public function 完了済みの計画を読了にしても何も変わらず一覧へ戻る(): void
    {
        $completedAt = Carbon::parse('2026-10-01 09:00:00');
        $plan = $this->plan(['status' => ReadingPlanStatus::Completed, 'completed_at' => $completedAt]);

        $this->actingAs($this->user)
            ->post(route('reading-plans.complete', $plan))
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionMissing('success');

        $this->assertEquals($completedAt, $plan->fresh()->completed_at);
    }

    /** @test */
    public function 他人の計画は読了にできない(): void
    {
        $plan = $this->plan([], User::factory()->create());

        $this->actingAs($this->user)->post(route('reading-plans.complete', $plan))->assertForbidden();

        $this->assertSame(ReadingPlanStatus::InProgress, $plan->fresh()->status);
    }

    // ---- 状態（Enum） ----

    /** @test */
    public function 状態の表示名とバッジが定義されている(): void
    {
        $this->assertSame('進行中', ReadingPlanStatus::InProgress->label());
        $this->assertSame('完了', ReadingPlanStatus::Completed->label());
        $this->assertSame('期限切れ', ReadingPlanStatus::Expired->label());

        foreach (ReadingPlanStatus::cases() as $status) {
            $this->assertNotSame('', $status->badgeClass());
        }
        $this->assertSame(['in_progress', 'completed', 'expired'], array_column(ReadingPlanStatus::cases(), 'value'));
    }
}
