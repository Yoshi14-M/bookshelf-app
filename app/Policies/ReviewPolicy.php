<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * レビューはログイン済みであれば誰でも（自分が登録した書籍にも）投稿できる。
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * レビューを編集できるのは投稿者本人のみ（書籍の登録者でも他人のレビューは不可）。
     */
    public function update(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }

    /**
     * レビューを削除できるのは投稿者本人のみ。
     */
    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }
}
