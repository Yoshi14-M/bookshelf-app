<?php

namespace App\Http\Requests;

class UpdateReviewRequest extends ReviewRequest
{
    /**
     * 他人のレビューは入力内容にかかわらず 403 にするため、バリデーションの前に Policy で判定する。
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('review'));
    }
}
