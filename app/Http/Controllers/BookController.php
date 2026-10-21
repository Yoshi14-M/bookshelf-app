<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BookController extends Controller
{
    /**
     * 書籍一覧（新着順）。
     */
    public function index(): View
    {
        $books = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->latest()
            ->latest('id')
            ->paginate(config('bookshelf.per_page'));

        return view('books.index', compact('books'));
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
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $book = $request->user()->books()->create($request->safe()->except('genres'));
        $book->genres()->sync($request->validated('genres'));

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
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $book->update($request->safe()->except('genres'));
        $book->genres()->sync($request->validated('genres'));

        return redirect()->route('books.show', $book)->with('success', '書籍情報を更新しました。');
    }

    /**
     * 書籍を削除する（登録者のみ。関連データは FK の CASCADE で削除される）。
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()->route('books.index')->with('success', '書籍を削除しました。');
    }
}
