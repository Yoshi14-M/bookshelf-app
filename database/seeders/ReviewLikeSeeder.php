<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * レビューの並び順に 0, 1, 2, 3 人を繰り返し、投稿者の次のユーザーから順にいいねさせる
     * （投稿者本人は含まれない）。
     */
    public function run(): void
    {
        $userIds = User::orderBy('id')->pluck('id');

        foreach (Review::orderBy('id')->get() as $index => $review) {
            $authorPosition = $userIds->search($review->user_id);
            $likerIds = collect();

            for ($step = 1; $step <= $index % 4; $step++) {
                $likerIds->push($userIds[($authorPosition + $step) % $userIds->count()]);
            }

            $review->likedByUsers()->syncWithoutDetaching($likerIds->all());
        }
    }
}
