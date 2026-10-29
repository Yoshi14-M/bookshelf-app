<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasBookRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * 書籍の登録・更新で共通のルールと日本語メッセージ。
 */
abstract class BookRequest extends FormRequest
{
    use HasBookRules;

    /**
     * `genres` が配列以外で送られると、失敗後のフォームが `in_array()` で落ちる（Blade は変更しない契約）ため、
     * 配列でない値は未選択（空配列）として扱い、フラッシュする入力値もこの正規化後のものにする。
     */
    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('genres'))) {
            $this->merge(['genres' => []]);
        }
    }

    /**
     * 既定ではグローバルのリクエスト（正規化前）が入力値としてフラッシュされるため、正規化後の入力で自前のリダイレクトを返す。
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException(
            $validator,
            redirect($this->getRedirectUrl())->withInput($this->input())->withErrors($validator->errors(), $this->errorBag),
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->commonMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->commonAttributes();
    }
}
