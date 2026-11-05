<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreBookRequest extends BookRequest
{
    /**
     * 書籍の登録はログイン済みなら誰でもできる（ルートの `auth` ミドルウェアで保証）。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = $this->commonRules();
        $rules['isbn'][] = Rule::unique('books', 'isbn');

        return $rules;
    }
}
