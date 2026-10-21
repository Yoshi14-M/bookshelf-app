<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * 書籍を更新できるのは登録者のみ（管理者ロールはない）。
     */
    public function update(User $user, Book $book): bool
    {
        return $book->user_id === $user->id;
    }

    /**
     * 書籍を削除できるのは登録者のみ。
     */
    public function delete(User $user, Book $book): bool
    {
        return $book->user_id === $user->id;
    }
}
