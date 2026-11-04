<?php

namespace App\Enums;

/**
 * 読書計画のリマインダーを送るタイミング（通知データの `timing` に入る値）。
 */
enum ReminderTiming: string
{
    case ThreeDaysBefore = 'three_days_before';
    case OnDueDate = 'on_due_date';
    case ThreeDaysAfter = 'three_days_after';

    /**
     * 今日を基準に、リマインド対象の期日が今日から何日後か（期日が過去なら負の数）。
     */
    public function daysUntilDue(): int
    {
        return match ($this) {
            self::ThreeDaysBefore => 3,
            self::OnDueDate => 0,
            self::ThreeDaysAfter => -3,
        };
    }

    /**
     * リマインド対象の計画の状態。期日の 3 日後は、前日までの失効処理で期限切れになった計画が対象になる。
     */
    public function targetStatus(): ReadingPlanStatus
    {
        return match ($this) {
            self::ThreeDaysBefore, self::OnDueDate => ReadingPlanStatus::InProgress,
            self::ThreeDaysAfter => ReadingPlanStatus::Expired,
        };
    }

    /**
     * 通知の見出し。
     */
    public function title(): string
    {
        return match ($this) {
            self::ThreeDaysBefore => '読書期限が近づいています',
            self::OnDueDate => '本日が読書の期日です',
            self::ThreeDaysAfter => '読書の期日を過ぎています',
        };
    }

    /**
     * 通知の本文。
     */
    public function body(string $bookTitle, string $targetDate): string
    {
        return match ($this) {
            self::ThreeDaysBefore => "「{$bookTitle}」の期日（{$targetDate}）まであと3日です。",
            self::OnDueDate => "「{$bookTitle}」の期日は本日（{$targetDate}）です。",
            self::ThreeDaysAfter => "「{$bookTitle}」の期日（{$targetDate}）から3日が経過しました。",
        };
    }
}
