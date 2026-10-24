<?php

namespace App\Models;

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
     * Basic Blade は published_date をそのまま出力するため date cast は付けない（応用で追加する）。
     *
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

        // DB ごとに LIKE の既定のエスケープ文字が異なるため、ESCAPE を明示して統一する
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword).'%';

        $query->where(function (Builder $query) use ($pattern) {
            $query->whereRaw("title like ? escape '!'", [$pattern])
                ->orWhereRaw("author like ? escape '!'", [$pattern]);
        });
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
