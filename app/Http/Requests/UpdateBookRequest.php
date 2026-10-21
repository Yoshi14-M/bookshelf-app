<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateBookRequest extends BookRequest
{
    /**
     * 他人の書籍は入力内容にかかわらず 403 にするため、バリデーションの前に Policy で判定する。
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('book'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = $this->commonRules();
        $rules['isbn'][] = Rule::unique('books', 'isbn')->ignore($this->route('book'));

        return $rules;
    }
}
