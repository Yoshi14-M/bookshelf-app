<?php

namespace App\Policies;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;

class ReadingPlanPolicy
{
    /**
     * 読書計画はログイン済みであれば誰でも作成できる。
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * 期日を変更できるのは所有者本人のみ。完了した計画は変更できない。
     */
    public function update(User $user, ReadingPlan $readingPlan): bool
    {
        return $readingPlan->user_id === $user->id
            && $readingPlan->status !== ReadingPlanStatus::Completed;
    }

    /**
     * 削除できるのは所有者本人のみ（状態は問わない）。
     */
    public function delete(User $user, ReadingPlan $readingPlan): bool
    {
        return $readingPlan->user_id === $user->id;
    }

    /**
     * 読了にできるのは所有者本人のみ。完了済みの計画は何もせず一覧へ戻るため、ここでは弾かない。
     */
    public function complete(User $user, ReadingPlan $readingPlan): bool
    {
        return $readingPlan->user_id === $user->id;
    }
}
