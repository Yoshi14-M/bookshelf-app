<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\HasBookRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 公開 API の書籍登録・更新で共通のルールと日本語メッセージ。
 *
 * 基本フェーズは認証なしのため、登録者 ID を `user_id` で受けて存在のみ検証する（レスポンスには出さない）。
 */
abstract class BookRequest extends FormRequest
{
    use HasBookRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function apiRules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            ...$this->commonRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->commonMessages(),
            'user_id.required' => '登録者IDは必須です。',
            'user_id.integer' => '登録者IDは整数で指定してください。',
            'user_id.exists' => '指定された登録者IDは存在しません。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...$this->commonAttributes(),
            'user_id' => '登録者ID',
        ];
    }
}
