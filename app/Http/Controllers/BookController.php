<?php

namespace App\Http\Controllers;

use App\Enums\BookSort;
use App\Http\Requests\IndexBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use App\Services\BookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BookController extends Controller
{
    /**
     * 書籍一覧（キーワード・ジャンル絞り込み・並び替え。既定は新着順）。検索条件はページ遷移でも引き継ぐ。
     */
    public function index(IndexBookRequest $request): View
    {
        $books = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->search($request->validated('keyword'))
            ->inGenre($request->validated('genre'))
            ->sortBy(BookSort::fromInput($request->validated('sort')))
            ->paginate(config('bookshelf.per_page'))
            ->withQueryString();
        $genres = Genre::orderBy('id')->get();

        return view('books.index', compact('books', 'genres'));
    }

    /**
     * 書籍登録フォーム。
     */
    public function create(): View
    {
        $genres = Genre::orderBy('id')->get();

        return view('books.create', compact('genres'));
    }

    /**
     * 書籍を登録する（登録者はログインユーザー）。
     */
    public function store(StoreBookRequest $request, BookService $books): RedirectResponse
    {
        $book = $books->create($request->user(), $request->safe()->except('genres'), $request->validated('genres'));

        return redirect()->route('books.show', $book)->with('success', '書籍を登録しました。');
    }

    /**
     * 書籍詳細。レビューは投稿日時の新しい順。
     */
    public function show(Book $book): View
    {
        $book->load([
            'genres',
            'reviews' => fn ($query) => $query->latest()->latest('id'),
            'reviews.user',
            'reviews.likedByUsers',
        ]);

        return view('books.show', compact('book'));
    }

    /**
     * 書籍編集フォーム（登録者のみ）。
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $book->load('genres');
        $genres = Genre::orderBy('id')->get();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 書籍を更新する（登録者のみ。登録者自体は変更しない）。
     */
    public function update(UpdateBookRequest $request, Book $book, BookService $books): RedirectResponse
    {
        $this->authorize('update', $book);

        $books->update($book, $request->safe()->except('genres'), $request->validated('genres'));

        return redirect()->route('books.show', $book)->with('success', '書籍情報を更新しました。');
    }

    /**
     * 書籍を削除する（登録者のみ。レビュー・お気に入り・読書計画などの関連データも同時に削除する）。
     */
    public function destroy(Book $book, BookService $books): RedirectResponse
    {
        $this->authorize('delete', $book);

        $books->delete($book);

        return redirect()->route('books.index')->with('success', '書籍を削除しました。');
    }
}
