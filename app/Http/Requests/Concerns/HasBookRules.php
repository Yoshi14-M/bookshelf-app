<?php

namespace App\Http\Requests\Concerns;

/**
 * 書籍の登録・更新で共通のルールと日本語メッセージ（Web と公開 API の FormRequest で共有する）。
 */
trait HasBookRules
{
    /**
     * 登録・更新で共通のバリデーションルール（isbn の一意性は各 FormRequest で追加する）。
     *
     * @return array<string, array<int, mixed>>
     */
    protected function commonRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'digits:13'],
            'published_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'exists:genres,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function commonMessages(): array
    {
        return [
            'title.required' => 'タイトルは必須です。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者は必須です。',
            'author.max' => '著者は255文字以内で入力してください。',
            'isbn.required' => 'ISBNは必須です。',
            'isbn.digits' => 'ISBNは13桁の数字で入力してください。',
            'isbn.unique' => 'このISBNは既に登録されています。',
            'published_date.date' => '出版日は正しい日付で入力してください。',
            'description.string' => '説明は文字列で入力してください。',
            'image_url.url' => '画像URLは正しいURL形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',
            'genres.required' => 'ジャンルは必須です。',
            'genres.array' => 'ジャンルは配列で指定してください。',
            'genres.min' => 'ジャンルは1つ以上選択してください。',
            'genres.*.integer' => 'ジャンルの選択が正しくありません。',
            'genres.*.exists' => 'ジャンルの選択が正しくありません。',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function commonAttributes(): array
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
