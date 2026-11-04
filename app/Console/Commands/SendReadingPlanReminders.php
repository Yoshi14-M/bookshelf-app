<?php

namespace App\Console\Commands;

use App\Enums\ReminderTiming;
use App\Services\ReadingPlanReminderService;
use Illuminate\Console\Command;

class SendReadingPlanReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'reading-plans:remind';

    /**
     * @var string
     */
    protected $description = '読書計画の期日の3日前・当日・3日後にリマインダー通知を送る（失効処理の後に実行する）';

    public function handle(ReadingPlanReminderService $service): int
    {
        $counts = $service->sendReminders();

        foreach (ReminderTiming::cases() as $timing) {
            $this->line("{$timing->title()}（{$timing->value}）: {$counts[$timing->value]} 件");
        }

        $this->info("合計 {$counts->sum()} 件の通知を送りました。");

        return self::SUCCESS;
    }
}
