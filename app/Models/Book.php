<?php

namespace App\Models;

use App\Enums\BookSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'author',
        'isbn',
        'published_date',
        'description',
        'image_url',
    ];

    /**
     * 出版日は未入力を許容する。DATE カラムに合わせて `Y-m-d` で保存・シリアライズする。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'published_date' => 'date:Y-m-d',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * タイトルまたは著者に対するキーワードの部分一致（`%` `_` はワイルドカードとして扱わない）。
     *
     * @param  Builder<Book>  $query
     */
    public function scopeSearch(Builder $query, ?string $keyword): void
    {
        $keyword = trim((string) $keyword);

        if ($keyword === '') {
            return;
        }

        // MySQL の LIKE の既定のエスケープ文字（バックスラッシュ）で `\` `%` `_` を無効化する
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword).'%';

        $query->where(function (Builder $query) use ($pattern) {
            $query->where('title', 'like', $pattern)
                ->orWhere('author', 'like', $pattern);
        });
    }

    /**
     * 並び順を指定する。同順位は新着順（登録日時 → id）、`Oldest` のみ古い順で安定させる。
     *
     * `Rating` は集計列（`reviews_avg_rating` / `reviews_count`）を使うため、呼び出し側で
     * `withAvg('reviews', 'rating')` と `withCount('reviews')` を済ませておくこと。
     * レビューなしの書籍（平均が NULL）は降順で最後になる。
     *
     * @param  Builder<Book>  $query
     */
    public function scopeSortBy(Builder $query, BookSort $sort): void
    {
        match ($sort) {
            BookSort::Latest => $query->latest()->latest('id'),
            BookSort::Oldest => $query->oldest()->oldest('id'),
            BookSort::Title => $query->orderBy('title')->latest('id'),
            BookSort::Rating => $query->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count')->latest()->latest('id'),
        };
    }

    /**
     * 指定したジャンルに紐付く書籍に絞り込む。
     *
     * @param  Builder<Book>  $query
     */
    public function scopeInGenre(Builder $query, ?int $genreId): void
    {
        if ($genreId === null) {
            return;
        }

        $query->whereHas('genres', fn (Builder $query) => $query->where('genres.id', $genreId));
    }
}
