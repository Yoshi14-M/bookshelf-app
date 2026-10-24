<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;

class RankingController extends Controller
{
    /**
     * レビュー平均評価の TOP10（ゲストも閲覧可）。
     *
     * レビューが 1 件でもある書籍が対象。平均評価の降順で、同点は書籍 ID の昇順。
     */
    public function index(): View
    {
        $rankedBooks = Book::has('reviews')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderByDesc('reviews_avg_rating')
            ->orderBy('id')
            ->limit(config('bookshelf.ranking_limit'))
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
