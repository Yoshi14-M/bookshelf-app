<?php

namespace Tests\Feature\Reports;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use App\Services\ReadingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * @param  array<int, Genre>  $genres
     */
    private function book(string $title = '書籍', array $genres = []): Book
    {
        $book = Book::factory()->create(['title' => $title]);
        $book->genres()->attach(collect($genres)->pluck('id'));

        return $book;
    }

    private function review(Book $book, int $rating, ?User $user = null, ?string $createdAt = null): Review
    {
        return Review::factory()->for($book)->for($user ?? $this->user)->create(array_filter([
            'rating' => $rating,
            'created_at' => $createdAt,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(?User $user = null): array
    {
        return app(ReadingReportService::class)->build($user ?? $this->user);
    }

    // ---- 画面 ----

    /** @test */
    public function ゲストはログイン画面へ遷移する(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    /** @test */
    public function レビューが0件でもエラーなく表示される(): void
    {
        $this->actingAs($this->user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('マイ読書レポート')
            ->assertSee('4星以上の書籍がありません')
            ->assertSee('ジャンルが設定された書籍のレビューがありません');
    }

    /** @test */
    public function 集計結果が画面に表示される(): void
    {
        $genre = Genre::factory()->create(['name' => '技術書']);
        $book = $this->book('リーダブルコード', [$genre]);
        $this->review($book, 5);

        $this->actingAs($this->user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('リーダブルコード')
            ->assertSee('技術書')
            ->assertSee('1件のレビュー')
            ->assertSee(route('books.show', $book), false)
            ->assertSee(route('genres.show', $genre), false);
    }

    /** @test */
    public function 画面にはログインユーザー自身の集計が渡される(): void
    {
        $this->review($this->book(), 4);

        $this->actingAs($this->user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats) => $stats['summary']['total_reviews'] === 1);
    }

    // ---- 基本サマリー ----

    /** @test */
    public function レビューが0件なら件数は0で平均評価も0になる(): void
    {
        $stats = $this->stats();

        $this->assertSame(['total_reviews' => 0, 'books_read' => 0, 'average_rating' => 0.0], $stats['summary']);
    }

    /** @test */
    public function 総レビュー数と読了冊数と平均評価が集計される(): void
    {
        $bookA = $this->book();
        $bookB = $this->book();
        $this->review($bookA, 5);
        $this->review($bookA, 3);
        $this->review($bookB, 4);

        $summary = $this->stats()['summary'];

        $this->assertSame(3, $summary['total_reviews']);
        $this->assertSame(2, $summary['books_read'], '同じ書籍への複数のレビューは 1 冊と数える');
        $this->assertSame(4.0, $summary['average_rating']);
    }

    /** @test */
    public function 読書計画は読了冊数に影響しない(): void
    {
        $this->review($this->book(), 3);
        $this->book(); // レビューしていない書籍

        $this->assertSame(1, $this->stats()['summary']['books_read']);
    }

    /** @test */
    public function 他のユーザーのレビューは集計に含まれない(): void
    {
        $other = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = $this->book('他人の高評価', [$genre]);
        $this->review($book, 5, $other);
        $this->review($this->book(), 2);

        $stats = $this->stats();

        $this->assertSame(1, $stats['summary']['total_reviews']);
        $this->assertSame(2.0, $stats['summary']['average_rating']);
        $this->assertCount(0, $stats['top_rated_books']);
        $this->assertCount(0, $stats['genre_ratings']);
    }

    // ---- 評価分布 ----

    /** @test */
    public function 評価分布は星1から5を0件も含めて5要素で返す(): void
    {
        $distribution = $this->stats()['rating_distribution'];

        $this->assertSame([0, 0, 0, 0, 0], $distribution->all());
    }

    /** @test */
    public function 評価分布は星ごとの件数を添字0から4で返す(): void
    {
        foreach ([1, 3, 3, 5, 5, 5] as $rating) {
            $this->review($this->book(), $rating);
        }

        $this->assertSame([1, 0, 2, 0, 3], $this->stats()['rating_distribution']->all());
    }

    // ---- 高評価書籍 TOP5 ----

    /** @test */
    public function 高評価書籍は4星以上だけが対象になる(): void
    {
        $this->review($this->book('星3'), 3);
        $this->review($this->book('星4'), 4);
        $this->review($this->book('星5'), 5);

        $titles = $this->stats()['top_rated_books']->pluck('title')->all();

        $this->assertSame(['星5', '星4'], $titles);
    }

    /** @test */
    public function 高評価書籍は最大5冊で評価の降順かつ同じ評価は新しい順になる(): void
    {
        $this->review($this->book('古い星4'), 4, createdAt: '2026-01-01 00:00:00');
        $this->review($this->book('新しい星4'), 4, createdAt: '2026-03-01 00:00:00');
        $this->review($this->book('星5'), 5, createdAt: '2026-02-01 00:00:00');
        $this->review($this->book('星4その2'), 4, createdAt: '2026-02-01 00:00:00');
        $this->review($this->book('星4その3'), 4, createdAt: '2026-01-15 00:00:00');
        $this->review($this->book('圏外の星4'), 4, createdAt: '2025-12-01 00:00:00');

        $top = $this->stats()['top_rated_books'];

        $this->assertSame(['星5', '新しい星4', '星4その2', '星4その3', '古い星4'], $top->pluck('title')->all());
    }

    /** @test */
    public function 高評価書籍は書籍の情報と評価を含み添字は0から始まる(): void
    {
        $book = Book::factory()->create(['title' => 'タイトル', 'author' => '著者']);
        $this->review($book, 5);

        $this->assertSame(
            [['id' => $book->id, 'title' => 'タイトル', 'author' => '著者', 'rating' => 5]],
            $this->stats()['top_rated_books']->all(),
        );
    }

    /** @test */
    public function 同じ書籍に複数のレビューがあっても高評価書籍には1回だけ最も高い評価で載る(): void
    {
        $book = $this->book('二度読んだ本');
        $this->review($book, 4, createdAt: '2026-03-01 00:00:00');
        $this->review($book, 5, createdAt: '2026-01-01 00:00:00');

        $top = $this->stats()['top_rated_books'];

        $this->assertCount(1, $top);
        $this->assertSame(5, $top->first()['rating']);
    }

    // ---- ジャンル別評価傾向 ----

    /** @test */
    public function ジャンル別に平均評価とレビュー件数が集計される(): void
    {
        $genre = Genre::factory()->create(['name' => '小説']);
        $this->review($this->book('A', [$genre]), 5);
        $this->review($this->book('B', [$genre]), 4);
        $this->review($this->book('C', [$genre]), 4);

        $this->assertSame(
            [['id' => $genre->id, 'name' => '小説', 'count' => 3, 'average_rating' => 4.3]],
            $this->stats()['genre_ratings']->all(),
        );
    }

    /** @test */
    public function 複数のジャンルを持つ書籍のレビューは各ジャンルに数えられる(): void
    {
        $novel = Genre::factory()->create(['name' => '小説']);
        $history = Genre::factory()->create(['name' => '歴史']);
        $this->review($this->book('A', [$novel, $history]), 4);

        $genres = $this->stats()['genre_ratings'];

        $this->assertSame(['小説', '歴史'], $genres->pluck('name')->sort()->values()->all());
        $this->assertSame([1, 1], $genres->pluck('count')->all());
    }

    /** @test */
    public function ジャンル別は平均の降順から件数の降順からジャンル名の順に並ぶ(): void
    {
        $high = Genre::factory()->create(['name' => '高評価']);
        $many = Genre::factory()->create(['name' => '件数多い']);
        $few = Genre::factory()->create(['name' => '件数少ない']);
        $aTie = Genre::factory()->create(['name' => 'あ同数']);
        $bTie = Genre::factory()->create(['name' => 'い同数']);

        $this->review($this->book('高', [$high]), 5);
        foreach ([4, 4] as $rating) {
            $this->review($this->book('多', [$many]), $rating);
        }
        $this->review($this->book('少', [$few]), 4);
        $this->review($this->book('同1', [$aTie]), 3);
        $this->review($this->book('同2', [$bTie]), 3);

        $names = $this->stats()['genre_ratings']->pluck('name')->all();

        $this->assertSame(['高評価', '件数多い', '件数少ない', 'あ同数', 'い同数'], $names);
    }

    /** @test */
    public function ジャンル別は丸める前の平均で並べる(): void
    {
        // 平均 4.04（25 件。丸めると 4.0）と平均 4.0（30 件）。丸めた後の同点なら件数の多い後者が先になるが、丸め前の平均で前者が先になる
        $slightlyHigher = Genre::factory()->create(['name' => 'わずかに高い']);
        $exactlyFour = Genre::factory()->create(['name' => 'ちょうど4']);

        foreach (array_merge(array_fill(0, 24, 4), [5]) as $rating) {
            $this->review($this->book('x', [$slightlyHigher]), $rating);
        }
        foreach (range(1, 30) as $i) {
            $this->review($this->book('y', [$exactlyFour]), 4);
        }

        $genres = $this->stats()['genre_ratings'];

        $this->assertSame(['わずかに高い', 'ちょうど4'], $genres->pluck('name')->all());
        $this->assertSame([4.0, 4.0], $genres->pluck('average_rating')->all());
    }

    /** @test */
    public function ジャンル別は最大5件でジャンルの無い書籍は対象外になる(): void
    {
        foreach (range(1, 6) as $i) {
            $this->review($this->book("書籍{$i}", [Genre::factory()->create(['name' => "ジャンル{$i}"])]), 5);
        }
        $this->review($this->book('ジャンルなし'), 5);

        $this->assertCount(5, $this->stats()['genre_ratings']);
    }

    // ---- 日時の扱い ----

    /** @test */
    public function 投稿日時が同じ場合は登録順の新しい順で安定して並ぶ(): void
    {
        $at = Carbon::parse('2026-01-01 00:00:00')->toDateTimeString();
        $first = $this->review($this->book('先'), 4, createdAt: $at);
        $second = $this->review($this->book('後'), 4, createdAt: $at);

        $this->assertSame([$second->book_id, $first->book_id], $this->stats()['top_rated_books']->pluck('id')->all());
    }
}
