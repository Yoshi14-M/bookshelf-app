<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\HasBookRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 公開 API の書籍登録・更新で共通のルールと日本語メッセージ。
 *
 * 登録者は入力ではなく Sanctum のトークンの所有者から決まるため、`user_id` は受け付けない（送られても無視する）。
 */
abstract class BookRequest extends FormRequest
{
    use HasBookRules;

    /**
     * 登録は認証済み（`auth:sanctum`）なら誰でもできる。更新の認可は `UpdateBookRequest` と `BookPolicy` で行う。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function apiRules(): array
    {
        return $this->commonRules();
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
