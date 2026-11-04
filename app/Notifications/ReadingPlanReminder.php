<?php

namespace App\Notifications;

use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use Illuminate\Notifications\Notification;

/**
 * 読書計画の期日が近い・当日・過ぎたことを知らせる通知（データベースチャネルのみ。メールは送らない）。
 */
class ReadingPlanReminder extends Notification
{
    public function __construct(
        private readonly ReadingPlan $readingPlan,
        private readonly ReminderTiming $timing,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * 通知一覧（Blade）が使う `title` / `body` / `timing` と、計画を特定する情報。
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $bookTitle = $this->readingPlan->book->title;
        $targetDate = $this->readingPlan->target_date->format('Y-m-d');

        return [
            'title' => $this->timing->title(),
            'body' => $this->timing->body($bookTitle, $targetDate),
            'timing' => $this->timing->value,
            'reading_plan_id' => $this->readingPlan->id,
            'book_id' => $this->readingPlan->book_id,
            'book_title' => $bookTitle,
            'target_date' => $targetDate,
        ];
    }
}
