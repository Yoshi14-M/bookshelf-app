<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * 各ユーザーに3〜5冊（ユーザーの並び順に 3, 4, 5, 3, 4 冊）を固定で設定する。
     */
    public function run(): void
    {
        $users = User::orderBy('id')->get();
        $bookIds = Book::orderBy('id')->pluck('id');

        foreach ($users as $index => $user) {
            $count = 3 + ($index % 3);
            $favoriteIds = collect(range(0, $count - 1))
                ->map(fn (int $offset) => $bookIds[($index * 2 + $offset) % $bookIds->count()]);

            $user->favoriteBooks()->syncWithoutDetaching($favoriteIds->all());
        }
    }
}
