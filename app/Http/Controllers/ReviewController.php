<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ReviewController extends Controller
{
    /**
     * レビューを投稿する（同じユーザーが同じ書籍に複数回投稿できる）。
     */
    public function store(StoreReviewRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('create', Review::class);

        $book->reviews()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('books.show', $book)->with('success', 'レビューを投稿しました。');
    }

    /**
     * レビュー編集フォーム（投稿者のみ）。
     */
    public function edit(Review $review): View
    {
        $this->authorize('update', $review);

        $review->load('book');

        return view('reviews.edit', compact('review'));
    }

    /**
     * レビューを更新する（投稿者のみ）。
     */
    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $review->update($request->validated());

        return redirect()->route('books.show', $review->book_id)->with('success', 'レビューを更新しました。');
    }

    /**
     * レビューを削除する（投稿者のみ。いいねは FK の CASCADE で削除される）。
     */
    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $review->delete();

        return redirect()->route('books.show', $review->book_id)->with('success', 'レビューを削除しました。');
    }
}
