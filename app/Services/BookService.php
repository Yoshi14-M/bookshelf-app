<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 書籍の登録・更新・削除。複数のテーブルを更新するため、それぞれ 1 つのトランザクションにまとめる
 * （途中で失敗した場合は書籍本体・ジャンル・関連データのどれも変更されない）。Web と公開 API で共有する。
 */
class BookService
{
    /**
     * 書籍を登録し、ジャンルを紐付ける。
     *
     * @param  User  $owner  登録者
     * @param  array<string, mixed>  $attributes  書籍の項目（`genres` を含まない）
     * @param  array<int, int>  $genreIds  紐付けるジャンルの ID
     */
    public function create(User $owner, array $attributes, array $genreIds): Book
    {
        return DB::transaction(function () use ($owner, $attributes, $genreIds): Book {
            $book = $owner->books()->create($attributes);
            $book->genres()->sync($genreIds);

            return $book;
        });
    }

    /**
     * 書籍を更新し、ジャンルを付け替える（登録者は変更しない）。
     *
     * @param  array<string, mixed>  $attributes  書籍の項目（`genres` を含まない）
     * @param  array<int, int>  $genreIds  紐付けるジャンルの ID
     */
    public function update(Book $book, array $attributes, array $genreIds): Book
    {
        return DB::transaction(function () use ($book, $attributes, $genreIds): Book {
            $book->update($attributes);
            $book->genres()->sync($genreIds);

            return $book;
        });
    }

    /**
     * 書籍と関連データ（レビューのいいね・レビュー・お気に入り・ジャンルの紐付け・読書計画）を削除する。
     * 外部キーの `CASCADE` にも任せられるが、削除の範囲が分かるよう明示的に削除する。
     */
    public function delete(Book $book): void
    {
        DB::transaction(function () use ($book): void {
            $book->reviews->each(fn (Review $review) => $review->likedByUsers()->detach());
            $book->reviews()->delete();
            $book->favoritedByUsers()->detach();
            $book->genres()->detach();
            $book->readingPlans()->delete();
            $book->delete();
        });
    }
}
