<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexBookRequest extends FormRequest
{
    /**
     * 一覧は認証不要で、誰でも利用できる。
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
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'genre_id' => ['nullable', 'integer', 'exists:genres,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'keyword.string' => 'キーワードは文字列で指定してください。',
            'keyword.max' => 'キーワードは100文字以内で指定してください。',
            'genre_id.integer' => 'ジャンルIDは整数で指定してください。',
            'genre_id.exists' => '指定されたジャンルIDは存在しません。',
            'page.integer' => 'ページは整数で指定してください。',
            'page.min' => 'ページは1以上で指定してください。',
            'per_page.integer' => '1ページあたりの件数は整数で指定してください。',
            'per_page.between' => '1ページあたりの件数は1から100の間で指定してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'keyword' => 'キーワード',
            'genre_id' => 'ジャンルID',
            'page' => 'ページ',
            'per_page' => '1ページあたりの件数',
        ];
    }
}
