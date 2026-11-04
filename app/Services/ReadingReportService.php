<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * マイ読書レポートの集計。ログインユーザー自身のレビューだけを対象にする。
 */
class ReadingReportService
{
    /**
     * 高評価書籍とみなす評価の下限。
     */
    private const HIGH_RATING = 4;

    /**
     * 高評価書籍・ジャンル別評価傾向に表示する件数。
     */
    private const TOP_LIMIT = 5;

    /**
     * 評価の段階（星 1〜5）。
     */
    private const RATINGS = [1, 2, 3, 4, 5];

    /**
     * ユーザーのレビューを集計して、`reports.index`（Blade）が参照する `$stats` を作る。
     *
     * @return array{
     *     summary: array{total_reviews: int, books_read: int, average_rating: float},
     *     rating_distribution: Collection<int, int>,
     *     top_rated_books: Collection<int, array{id: int, title: string, author: string, rating: int}>,
     *     genre_ratings: Collection<int, array{id: int, name: string, count: int, average_rating: float}>
     * }
     */
    public function build(User $user): array
    {
        $reviews = $user->reviews()->with('book.genres')->get();

        return [
            'summary' => $this->summary($reviews),
            'rating_distribution' => $this->ratingDistribution($reviews),
            'top_rated_books' => $this->topRatedBooks($reviews),
            'genre_ratings' => $this->genreRatings($reviews),
        ];
    }

    /**
     * 総レビュー数・読了冊数（レビューした書籍の重複なし件数。読書計画は無関係）・平均評価（0 件は 0）。
     *
     * @param  Collection<int, Review>  $reviews
     * @return array{total_reviews: int, books_read: int, average_rating: float}
     */
    private function summary(Collection $reviews): array
    {
        return [
            'total_reviews' => $reviews->count(),
            'books_read' => $reviews->pluck('book_id')->unique()->count(),
            'average_rating' => (float) $reviews->avg('rating'),
        ];
    }

    /**
     * 星 1〜5 の件数。レビューが無い星も 0 件として含める（添字 0〜4 が星 1〜5）。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, int>
     */
    private function ratingDistribution(Collection $reviews): Collection
    {
        $counts = $reviews->countBy('rating');

        return collect(self::RATINGS)->map(fn (int $rating): int => $counts->get($rating, 0));
    }

    /**
     * 評価 4 以上の書籍を評価の降順（同じ評価は投稿の新しい順）に最大 5 冊。
     * 同じ書籍に複数のレビューがある場合は、評価が最も高い（同点は新しい）レビューを 1 冊分として数える。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, array{id: int, title: string, author: string, rating: int}>
     */
    private function topRatedBooks(Collection $reviews): Collection
    {
        return $reviews
            ->filter(fn (Review $review): bool => $review->rating >= self::HIGH_RATING)
            ->sortBy([['rating', 'desc'], ['created_at', 'desc'], ['id', 'desc']])
            ->unique('book_id')
            ->take(self::TOP_LIMIT)
            ->values()
            ->map(fn (Review $review): array => [
                'id' => $review->book->id,
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => $review->rating,
            ]);
    }

    /**
     * ジャンルごとの平均評価（小数第 1 位）とレビュー件数の上位 5 件。
     * 平均の降順 → 件数の降順 → ジャンル名の順。並び替えは丸める前の平均で行い、ジャンルの無い書籍は対象外。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, array{id: int, name: string, count: int, average_rating: float}>
     */
    private function genreRatings(Collection $reviews): Collection
    {
        return $reviews
            ->flatMap(fn (Review $review): Collection => $review->book->genres->map(fn ($genre): array => [
                'id' => $genre->id,
                'name' => $genre->name,
                'rating' => $review->rating,
            ]))
            ->groupBy('id')
            ->map(fn (Collection $rows): array => [
                'id' => $rows->first()['id'],
                'name' => $rows->first()['name'],
                'count' => $rows->count(),
                'average' => $rows->avg('rating'),
            ])
            ->sortBy([['average', 'desc'], ['count', 'desc'], ['name', 'asc']])
            ->take(self::TOP_LIMIT)
            ->values()
            ->map(fn (array $genre): array => [
                'id' => $genre['id'],
                'name' => $genre['name'],
                'count' => $genre['count'],
                'average_rating' => round($genre['average'], 1),
            ]);
    }
}
