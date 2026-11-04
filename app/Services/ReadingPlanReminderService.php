<?php

namespace App\Services;

use App\Enums\ReadingPlanStatus;
use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 読書計画の日次バッチの処理。毎日、先に失効（`expireOverdue`）、その後にリマインド（`sendReminders`）の順で実行する。
 */
class ReadingPlanReminderService
{
    /**
     * 期日を経過した進行中の計画（期日が今日より前）を期限切れにする。期日が今日の計画は失効しない。
     *
     * @param  Carbon|null  $today  基準日（省略時は今日）
     * @return int 期限切れにした件数
     */
    public function expireOverdue(?Carbon $today = null): int
    {
        $today ??= Carbon::today();

        return ReadingPlan::where('status', ReadingPlanStatus::InProgress->value)
            ->where('target_date', '<', $today->toDateString())
            ->update(['status' => ReadingPlanStatus::Expired->value]);
    }

    /**
     * 期日の 3 日前・当日（進行中）と 3 日後（期限切れ）の計画の所有者にリマインダーを送る。
     * 途中で失敗した場合に一部だけ送信済みにならないよう、すべて 1 つのトランザクションで送る。
     * 通知の重複防止は行わず、実行した時点の状態だけで判定する。
     *
     * @param  Carbon|null  $today  基準日（省略時は今日）
     * @return Collection<string, int> タイミング（`ReminderTiming` の値）ごとに送った件数
     */
    public function sendReminders(?Carbon $today = null): Collection
    {
        $today ??= Carbon::today();

        return DB::transaction(
            fn (): Collection => collect(ReminderTiming::cases())
                ->mapWithKeys(fn (ReminderTiming $timing): array => [$timing->value => $this->remind($timing, $today)]),
        );
    }

    /**
     * 1 つのタイミングの対象計画に通知を送る。
     *
     * @return int 送った件数
     */
    private function remind(ReminderTiming $timing, Carbon $today): int
    {
        $plans = ReadingPlan::with(['user', 'book'])
            ->where('status', $timing->targetStatus()->value)
            ->where('target_date', $today->copy()->addDays($timing->daysUntilDue())->toDateString())
            ->get();

        $plans->each(fn (ReadingPlan $plan) => $plan->user->notify(new ReadingPlanReminder($plan, $timing)));

        return $plans->count();
    }
}
