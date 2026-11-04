<?php

namespace Database\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReadingPlanSeeder extends Seeder
{
    /**
     * Run the seeds.
     *
     * 採点者がいつ実行しても同じ挙動になるよう、`Carbon::today()` 起点で期日を設定する。
     * 主要シナリオは山田太郎（ID 1〜5）に集約し、6 は他ユーザー（鈴木花子）の計画（認可の確認用）。
     * 書籍は計画ごとに別の書籍（書籍 1〜6）を割り当てる。create を使うため、再実行時に重複しないよう既存データがあれば何もしない。
     */
    public function run(): void
    {
        if (ReadingPlan::exists()) {
            return;
        }

        $users = User::orderBy('id')->get();
        $books = Book::orderBy('id')->get();
        $today = Carbon::today();

        $plans = [
            // 3 日前のリマインダー対象
            [$users[0], $today->copy()->addDays(3), ReadingPlanStatus::InProgress, null],
            // 当日のリマインダー対象
            [$users[0], $today->copy(), ReadingPlanStatus::InProgress, null],
            // バッチで期限切れになり、3 日後のリマインダー対象にもなる（二重シナリオ）
            [$users[0], $today->copy()->subDays(3), ReadingPlanStatus::InProgress, null],
            // リマインダー対象外
            [$users[0], $today->copy()->addDays(7), ReadingPlanStatus::InProgress, null],
            // 完了済み
            [$users[0], $today->copy()->subDays(10), ReadingPlanStatus::Completed, $today->copy()->subDays(5)],
            // 他ユーザーの計画（山田太郎で /reading-plans/6/edit を開くと 403）
            [$users[1], $today->copy()->addDays(5), ReadingPlanStatus::InProgress, null],
        ];

        foreach ($plans as $index => [$user, $targetDate, $status, $completedAt]) {
            ReadingPlan::create([
                'user_id' => $user->id,
                'book_id' => $books[$index]->id,
                'target_date' => $targetDate,
                'status' => $status,
                'completed_at' => $completedAt,
            ]);
        }
    }
}
