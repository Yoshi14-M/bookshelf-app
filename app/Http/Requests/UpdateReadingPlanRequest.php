<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Illuminate\Contracts\Validation\Validator;

class UpdateReadingPlanRequest extends ReadingPlanRequest
{
    /**
     * 他人の計画や完了した計画は入力内容にかかわらず 403 にするため、バリデーションの前に Policy で判定する。
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('reading_plan'));
    }

    /**
     * 期限切れの計画は期日を変更すると進行中に戻るため、同じ書籍の進行中の計画が別にある場合は
     * 進行中の重複になってしまうので変更させない。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var ReadingPlan $plan */
            $plan = $this->route('reading_plan');

            if ($plan->status !== ReadingPlanStatus::Expired) {
                return;
            }

            $hasOtherInProgress = ReadingPlan::where('user_id', $plan->user_id)
                ->where('book_id', $plan->book_id)
                ->where('status', ReadingPlanStatus::InProgress->value)
                ->exists();

            if ($hasOtherInProgress) {
                $validator->errors()->add('target_date', 'この書籍には進行中の読書計画が既にあるため、期日を変更できません。');
            }
        });
    }
}
