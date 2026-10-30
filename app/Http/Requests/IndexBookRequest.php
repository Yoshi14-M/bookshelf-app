<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * 書籍一覧の検索条件（キーワード・ジャンル・並び順・ページ）。
 *
 * `sort` は未知の値でもエラーにせず、既定の並び順にフォールバックする（`BookSort::fromInput()`）。
 */
class IndexBookRequest extends FormRequest
{
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
            'genre' => ['nullable', 'integer', 'exists:genres,id'],
            'sort' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * 一覧の Blade にはエラー表示欄が無いため、条件なしの一覧へ戻して `error` フラッシュで知らせる。
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new ValidationException(
            $validator,
            redirect()->route('books.index')
                ->with('error', $validator->errors()->first())
                ->withErrors($validator->errors(), $this->errorBag),
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'keyword.string' => 'キーワードは文字列で指定してください。',
            'keyword.max' => 'キーワードは100文字以内で指定してください。',
            'genre.integer' => 'ジャンルの指定が正しくありません。',
            'genre.exists' => '指定されたジャンルは存在しません。',
            'sort.string' => '並び順の指定が正しくありません。',
            'page.integer' => 'ページは整数で指定してください。',
            'page.min' => 'ページは1以上で指定してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'keyword' => 'キーワード',
            'genre' => 'ジャンル',
            'sort' => '並び順',
            'page' => 'ページ',
        ];
    }
}
