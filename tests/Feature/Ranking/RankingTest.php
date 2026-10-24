<?php

namespace Tests\Feature\Ranking;

use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 指定した評価のレビューを持つ書籍を作成する。
     *
     * @param  array<int, int>  $ratings
     */
    private function bookWithRatings(array $ratings, string $title = '書籍'): Book
    {
        $book = Book::factory()->create(['title' => $title]);

        foreach ($ratings as $rating) {
            Review::factory()->for($book)->create(['rating' => $rating]);
        }

        return $book;
    }

    /**
     * @return array<int, int>
     */
    private function rankedIds(): array
    {
        return $this->get(route('ranking.index'))->viewData('rankedBooks')->pluck('id')->all();
    }

    /** @test */
    public function ゲストでもランキングを表示できる(): void
    {
        $this->bookWithRatings([5], '殿堂入りの一冊');

        $this->get('/ranking')
            ->assertOk()
            ->assertViewIs('ranking.index')
            ->assertSee('殿堂入りの一冊');
    }

    /** @test */
    public function 平均評価の高い順に並ぶ(): void
    {
        $low = $this->bookWithRatings([3, 3]);
        $high = $this->bookWithRatings([5, 4]);
        $middle = $this->bookWithRatings([4, 4]);

        $this->assertSame([$high->id, $middle->id, $low->id], $this->rankedIds());
    }

    /** @test */
    public function 同点の場合は書籍番号の昇順に並ぶ(): void
    {
        $first = $this->bookWithRatings([4]);
        $second = $this->bookWithRatings([4]);
        $third = $this->bookWithRatings([5]);

        $this->assertSame([$third->id, $first->id, $second->id], $this->rankedIds());
    }

    /** @test */
    public function 同点でもレビュー件数は並び順に影響しない(): void
    {
        $fewReviews = $this->bookWithRatings([4]);
        $manyReviews = $this->bookWithRatings([4, 4, 4, 4]);

        $this->assertSame([$fewReviews->id, $manyReviews->id], $this->rankedIds());
    }

    /** @test */
    public function 並び順は丸め前の平均値で決まる(): void
    {
        // 平均 4.33（小数第 1 位は 4.3）と平均 4.25（小数第 1 位は 4.3）
        $lower = $this->bookWithRatings([4, 4, 4, 5]);
        $higher = $this->bookWithRatings([4, 4, 5]);

        $this->assertSame([$higher->id, $lower->id], $this->rankedIds());
    }

    /** @test */
    public function レビューが1件でも対象になる(): void
    {
        $book = $this->bookWithRatings([2]);

        $this->assertSame([$book->id], $this->rankedIds());
    }

    /** @test */
    public function レビューのない書籍は含まれない(): void
    {
        $reviewed = $this->bookWithRatings([1]);
        Book::factory()->create(['title' => 'レビューなしの書籍']);

        $this->assertSame([$reviewed->id], $this->rankedIds());
        $this->get(route('ranking.index'))->assertDontSee('レビューなしの書籍');
    }

    /** @test */
    public function 上位10件までしか表示されない(): void
    {
        foreach (range(1, 12) as $i) {
            $this->bookWithRatings([($i % 5) + 1]);
        }

        $this->assertCount(10, $this->rankedIds());
    }

    /** @test */
    public function 圏外の書籍は評価の低いものから外れる(): void
    {
        $books = collect(range(1, 11))->map(fn () => $this->bookWithRatings([5]));
        $lowest = $this->bookWithRatings([1]);

        $ids = $this->rankedIds();

        $this->assertCount(10, $ids);
        $this->assertNotContains($lowest->id, $ids);
        $this->assertSame($books->take(10)->pluck('id')->all(), $ids);
    }

    /** @test */
    public function レビューが1件もない場合はその旨が表示される(): void
    {
        Book::factory()->count(2)->create();

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('まだレビューが投稿された書籍がありません。');
    }

    /** @test */
    public function 平均評価とレビュー件数が表示される(): void
    {
        $this->bookWithRatings([5, 4]);

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('4.50')
            ->assertSee('(2件のレビュー)');
    }

    /** @test */
    public function ランキングのクエリ数は書籍数に依存しない(): void
    {
        $this->bookWithRatings([5]);
        $few = $this->countQueries(fn () => $this->get(route('ranking.index'))->assertOk());

        foreach (range(1, 8) as $i) {
            $this->bookWithRatings([4, 3]);
        }
        $many = $this->countQueries(fn () => $this->get(route('ranking.index'))->assertOk());

        $this->assertSame($few, $many);
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
