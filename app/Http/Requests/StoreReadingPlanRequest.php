<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Illuminate\Validation\Rule;

class StoreReadingPlanRequest extends ReadingPlanRequest
{
    /**
     * 読書計画はログイン済みなら誰でも作成できる（`ReadingPlanPolicy@create`）。
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', ReadingPlan::class);
    }

    /**
     * 書籍の存在確認と、同じユーザーが同じ書籍の進行中の計画を重複して持てないことの検証を加える
     * （完了・期限切れの計画が残っている書籍は再計画できる）。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'book_id' => [
                'required',
                'integer',
                'exists:books,id',
                Rule::unique('reading_plans', 'book_id')->where(
                    fn ($query) => $query
                        ->where('user_id', $this->user()->id)
                        ->where('status', ReadingPlanStatus::InProgress->value),
                ),
            ],
            ...parent::rules(),
        ];
    }
}
