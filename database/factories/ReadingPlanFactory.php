<?php

namespace Database\Factories;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ReadingPlan>
 */
class ReadingPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'target_date' => Carbon::today()->addDays(7),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ];
    }

    /**
     * 完了した計画（完了日時つき）。
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * 期限切れの計画（期日は過去）。
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'target_date' => Carbon::today()->subDays(3),
            'status' => ReadingPlanStatus::Expired,
        ]);
    }
}
