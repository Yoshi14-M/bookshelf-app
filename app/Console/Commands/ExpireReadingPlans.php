<?php

namespace App\Console\Commands;

use App\Services\ReadingPlanReminderService;
use Illuminate\Console\Command;

class ExpireReadingPlans extends Command
{
    /**
     * @var string
     */
    protected $signature = 'reading-plans:expire';

    /**
     * @var string
     */
    protected $description = '期日を経過した進行中の読書計画を期限切れにする（リマインドの前に実行する）';

    /**
     * 期日を経過した進行中の計画を期限切れにして、件数を表示する。
     */
    public function handle(ReadingPlanReminderService $service): int
    {
        $count = $service->expireOverdue();

        $this->info("{$count} 件の読書計画を期限切れにしました。");

        return self::SUCCESS;
    }
}
