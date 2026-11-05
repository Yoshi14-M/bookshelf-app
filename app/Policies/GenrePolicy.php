<?php

namespace App\Policies;

use App\Models\Genre;
use App\Models\User;

/**
 * ジャンルは共有マスタで管理者ロールもないため、ログイン済みであれば全操作を許可する。
 */
class GenrePolicy
{
    /**
     * ジャンルの一覧を閲覧できる。
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * ジャンルの詳細を閲覧できる。
     */
    public function view(User $user, Genre $genre): bool
    {
        return true;
    }

    /**
     * ジャンルを登録できる。
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * ジャンルを編集できる。
     */
    public function update(User $user, Genre $genre): bool
    {
        return true;
    }

    /**
     * ジャンルを削除できる（書籍が紐付いている場合の制限は `GenreController` で行う）。
     */
    public function delete(User $user, Genre $genre): bool
    {
        return true;
    }
}
