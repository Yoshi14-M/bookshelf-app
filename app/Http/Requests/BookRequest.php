<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * 書籍の登録・更新で共通のルールと日本語メッセージ。
 */
abstract class BookRequest extends FormRequest
{
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
     * 登録・更新で共通のバリデーションルール（isbn の一意性は継承先で追加する）。
     *
     * @return array<string, array<int, mixed>>
     */
    protected function commonRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'digits:13'],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'exists:genres,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'タイトルは必須です。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者は必須です。',
            'author.max' => '著者は255文字以内で入力してください。',
            'isbn.required' => 'ISBNは必須です。',
            'isbn.digits' => 'ISBNは13桁の数字で入力してください。',
            'isbn.unique' => 'このISBNは既に登録されています。',
            'published_date.required' => '出版日は必須です。',
            'published_date.date' => '出版日は正しい日付で入力してください。',
            'description.string' => '説明は文字列で入力してください。',
            'image_url.url' => '画像URLは正しいURL形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',
            'genres.required' => 'ジャンルは必須です。',
            'genres.min' => 'ジャンルは1つ以上選択してください。',
            'genres.*.integer' => 'ジャンルの選択が正しくありません。',
            'genres.*.exists' => 'ジャンルの選択が正しくありません。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'author' => '著者',
            'isbn' => 'ISBN',
            'published_date' => '出版日',
            'description' => '説明',
            'image_url' => '画像URL',
            'genres' => 'ジャンル',
            'genres.*' => 'ジャンル',
        ];
    }
}
