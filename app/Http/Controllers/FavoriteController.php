<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * ログインユーザー自身のお気に入り一覧（お気に入りに追加した新しい順）。
     */
    public function index(Request $request): View
    {
        $books = $request->user()
            ->favoriteBooks()
            ->with('genres')
            ->orderByPivot('created_at', 'desc')
            ->orderByDesc('books.id')
            ->paginate(config('bookshelf.per_page'));

        return view('favorites.index', compact('books'));
    }

    /**
     * お気に入りの追加・解除を切り替える（自分が登録した書籍も可）。元の画面へ戻る。
     */
    public function toggle(Request $request, Book $book): RedirectResponse
    {
        $request->user()->favoriteBooks()->toggle($book->id);

        return back();
    }
}
