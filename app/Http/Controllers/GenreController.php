<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Models\Genre;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class GenreController extends Controller
{
    /**
     * ジャンル一覧（紐付く書籍数つき）。
     */
    public function index(): View
    {
        $this->authorize('viewAny', Genre::class);

        $genres = Genre::withCount('books')->orderBy('id')->get();

        return view('genres.index', compact('genres'));
    }

    /**
     * ジャンル登録フォーム。
     */
    public function create(): View
    {
        $this->authorize('create', Genre::class);

        return view('genres.create');
    }

    /**
     * ジャンルを登録する。
     */
    public function store(StoreGenreRequest $request): RedirectResponse
    {
        $this->authorize('create', Genre::class);

        Genre::create($request->validated());

        return redirect()->route('genres.index')->with('success', 'ジャンルを登録しました。');
    }

    /**
     * ジャンル詳細（そのジャンルの書籍を新着順に表示）。
     */
    public function show(Genre $genre): View
    {
        $this->authorize('view', $genre);

        $books = $genre->books()
            ->with('genres')
            ->latest()
            ->latest('id')
            ->paginate(config('bookshelf.per_page'));

        return view('genres.show', compact('genre', 'books'));
    }

    /**
     * ジャンル編集フォーム。
     */
    public function edit(Genre $genre): View
    {
        $this->authorize('update', $genre);

        return view('genres.edit', compact('genre'));
    }

    /**
     * ジャンルを更新する。
     */
    public function update(UpdateGenreRequest $request, Genre $genre): RedirectResponse
    {
        $this->authorize('update', $genre);

        $genre->update($request->validated());

        return redirect()->route('genres.index')->with('success', 'ジャンルを更新しました。');
    }

    /**
     * ジャンルを削除する。書籍が紐付いている場合は削除しない。
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        $this->authorize('delete', $genre);

        if ($genre->books()->exists()) {
            return redirect()->route('genres.index')
                ->with('error', 'このジャンルには書籍が紐付いているため削除できません。');
        }

        $genre->delete();

        return redirect()->route('genres.index')->with('success', 'ジャンルを削除しました。');
    }
}
