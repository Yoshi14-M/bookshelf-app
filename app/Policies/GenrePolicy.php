<?php

namespace App\Policies;

use App\Models\Genre;
use App\Models\User;

/**
 * ジャンルは共有マスタで管理者ロールもないため、ログイン済みであれば全操作を許可する。
 */
class GenrePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Genre $genre): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Genre $genre): bool
    {
        return true;
    }

    public function delete(User $user, Genre $genre): bool
    {
        return true;
    }
}
