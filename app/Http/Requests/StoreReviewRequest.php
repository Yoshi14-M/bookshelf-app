<?php

namespace App\Http\Requests;

class StoreReviewRequest extends ReviewRequest
{
    /**
     * レビューの投稿はログイン済みなら誰でもできる（コントローラーで `ReviewPolicy@create` も確認する）。
     */
    public function authorize(): bool
    {
        return true;
    }
}
