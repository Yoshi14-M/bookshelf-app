<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewLikeController extends Controller
{
    /**
     * レビューへのいいねの追加・解除を切り替える（自分のレビューにも可）。元の画面へ戻る。
     */
    public function toggle(Request $request, Review $review): RedirectResponse
    {
        $request->user()->likedReviews()->toggle($review->id);

        return back();
    }
}
