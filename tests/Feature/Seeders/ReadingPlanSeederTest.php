<?php

namespace Tests\Feature\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReadingPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingPlanSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-04 10:00:00');
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function 読書計画が6件投入され山田太郎に5件と鈴木花子に1件がある(): void
    {
        $plans = ReadingPlan::with('user')->orderBy('id')->get();

        $this->assertCount(6, $plans);
        $this->assertSame(
            array_fill(0, 5, 'yamada@example.com') + [5 => 'suzuki@example.com'],
            $plans->pluck('user.email')->all(),
        );
    }

    /** @test */
    public function 期日と状態が今日を起点に設定される(): void
    {
        $plans = ReadingPlan::orderBy('id')->get();

        $this->assertSame(
            ['2026-11-07', '2026-11-04', '2026-11-01', '2026-11-11', '2026-10-25', '2026-11-09'],
            $plans->map(fn (ReadingPlan $plan) => $plan->target_date->format('Y-m-d'))->all(),
        );
        $this->assertSame(
            [false, false, false, false, true, false],
            $plans->map(fn (ReadingPlan $plan) => $plan->status === ReadingPlanStatus::Completed)->all(),
        );
        $this->assertSame('2026-10-30', $plans[4]->completed_at->format('Y-m-d'));
    }

    /** @test */
    public function 計画ごとに別の書籍が割り当てられる(): void
    {
        $this->assertSame(6, ReadingPlan::distinct()->count('book_id'));
    }

    /** @test */
    public function 再実行しても件数が増えない(): void
    {
        $this->seed(ReadingPlanSeeder::class);

        $this->assertSame(6, ReadingPlan::count());
    }
}
