<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 読書計画の登録・更新で共通のルールと日本語メッセージ。
 */
abstract class ReadingPlanRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'target_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'book_id.required' => '書籍は必須です。',
            'book_id.integer' => '書籍の指定が正しくありません。',
            'book_id.exists' => '選択した書籍が存在しません。',
            'book_id.unique' => 'この書籍には進行中の読書計画が既にあります。',
            'target_date.required' => '期日は必須です。',
            'target_date.date' => '期日は有効な日付で入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を指定してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'book_id' => '書籍',
            'target_date' => '期日',
        ];
    }
}
