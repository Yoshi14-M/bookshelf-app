<?php

namespace Tests\Feature\Books;

use App\Enums\BookSort;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 一覧に表示された書籍のタイトル（表示順）。
     *
     * @return array<int, string>
     */
    private function titlesOf(string $query, ?int $expectedTotal = null): array
    {
        $response = $this->get('/books'.$query)->assertOk();
        $books = $response->viewData('books');

        if ($expectedTotal !== null) {
            $this->assertSame($expectedTotal, $books->total());
        }

        return $books->pluck('title')->all();
    }

    // ---- キーワード ----

    /** @test */
    public function キーワードでタイトルと著者を部分一致で検索できる(): void
    {
        Book::factory()->create(['title' => '吾輩は猫である', 'author' => '夏目漱石']);
        Book::factory()->create(['title' => '夏目漱石入門', 'author' => '解説者']);
        Book::factory()->create(['title' => 'リーダブルコード', 'author' => 'Dustin Boswell']);

        $this->assertEqualsCanonicalizing(['吾輩は猫である', '夏目漱石入門'], $this->titlesOf('?keyword=夏目', 2));
        $this->assertSame(['リーダブルコード'], $this->titlesOf('?keyword=dustin', 1));
        $this->assertSame([], $this->titlesOf('?keyword=存在しない', 0));
    }

    /** @test */
    public function キーワードの前後の空白は無視され空のキーワードでは絞り込まれない(): void
    {
        Book::factory()->create(['title' => '吾輩は猫である']);
        Book::factory()->create(['title' => '坊っちゃん']);

        $this->assertSame(['吾輩は猫である'], $this->titlesOf('?keyword='.urlencode('  猫  '), 1));
        $this->assertSame(2, count($this->titlesOf('?keyword=', 2)));
    }

    /** @test */
    public function キーワードが101文字だと一覧へ戻りエラーが表示される(): void
    {
        $this->get('/books?keyword='.str_repeat('あ', 101))
            ->assertRedirect(route('books.index'))
            ->assertSessionHas('error', 'キーワードは100文字以内で指定してください。');

        $this->followingRedirects()
            ->get('/books?keyword='.str_repeat('あ', 101))
            ->assertSee('キーワードは100文字以内で指定してください。');
    }

    // ---- ジャンル ----

    /** @test */
    public function ジャンルで絞り込める(): void
    {
        $novel = Genre::factory()->create();
        $tech = Genre::factory()->create();
        Book::factory()->create(['title' => '小説の本'])->genres()->attach($novel);
        Book::factory()->create(['title' => '技術の本'])->genres()->attach($tech);
        Book::factory()->create(['title' => '両方の本'])->genres()->attach([$novel->id, $tech->id]);

        $this->assertEqualsCanonicalizing(['小説の本', '両方の本'], $this->titlesOf('?genre='.$novel->id, 2));
        $this->assertSame(3, count($this->titlesOf('?genre=', 3)));
    }

    /** @test */
    public function 存在しないジャンルを指定すると一覧へ戻りエラーが表示される(): void
    {
        $this->get('/books?genre=9999&keyword=猫')
            ->assertRedirect(route('books.index'))
            ->assertSessionHas('error', '指定されたジャンルは存在しません。');
        $this->get('/books?genre=abc')->assertRedirect(route('books.index'));
    }

    /** @test */
    public function 非同期通信での不正な条件は422になる(): void
    {
        $this->getJson('/books?genre=9999')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['genre' => '指定されたジャンルは存在しません。']);
    }

    /** @test */
    public function 一覧のジャンル選択肢に全ジャンルが表示され選択中のジャンルが維持される(): void
    {
        $novel = Genre::factory()->create(['name' => '小説']);
        Genre::factory()->create(['name' => '技術書']);

        $response = $this->get('/books?genre='.$novel->id)->assertOk();

        $response->assertSeeInOrder(['すべて', '小説', '技術書']);
        $response->assertSee('value="'.$novel->id.'" selected', false);
    }

    // ---- 併用・ページ遷移 ----

    /** @test */
    public function キーワードとジャンルを併用して絞り込める(): void
    {
        $genre = Genre::factory()->create();
        Book::factory()->create(['title' => '夏目の小説'])->genres()->attach($genre);
        Book::factory()->create(['title' => '夏目の随筆']);
        Book::factory()->create(['title' => '別の小説'])->genres()->attach($genre);

        $this->assertSame(['夏目の小説'], $this->titlesOf('?keyword=夏目&genre='.$genre->id, 1));
    }

    /** @test */
    public function ページ遷移でも検索条件と並び順が維持される(): void
    {
        $genre = Genre::factory()->create();
        Book::factory()->count(12)->create(['author' => '夏目漱石'])->each(fn (Book $book) => $book->genres()->attach($genre));
        Book::factory()->create(['author' => '別の著者']);

        $response = $this->get('/books?keyword=夏目&genre='.$genre->id.'&sort=title')->assertOk();
        $books = $response->viewData('books');

        $this->assertSame(12, $books->total());
        $this->assertCount(10, $books);
        $next = $books->nextPageUrl();
        $this->assertStringContainsString('keyword=', $next);
        $this->assertStringContainsString('genre='.$genre->id, $next);
        $this->assertStringContainsString('sort=title', $next);
        $this->assertCount(2, $this->get($next)->assertOk()->viewData('books'));
    }

    /** @test */
    public function ルートのパスでも同じ検索ができる(): void
    {
        Book::factory()->create(['title' => '吾輩は猫である']);
        Book::factory()->create(['title' => '坊っちゃん']);

        $this->assertSame(1, $this->get('/?keyword=猫')->assertOk()->viewData('books')->total());
    }

    // ---- 並び替え ----

    /** @test */
    public function 新着順と古い順で並び替えられる(): void
    {
        Book::factory()->create(['title' => '一番古い', 'created_at' => Carbon::parse('2026-01-01')]);
        Book::factory()->create(['title' => '真ん中', 'created_at' => Carbon::parse('2026-02-01')]);
        Book::factory()->create(['title' => '一番新しい', 'created_at' => Carbon::parse('2026-03-01')]);

        $this->assertSame(['一番新しい', '真ん中', '一番古い'], $this->titlesOf(''));
        $this->assertSame(['一番新しい', '真ん中', '一番古い'], $this->titlesOf('?sort=latest'));
        $this->assertSame(['一番古い', '真ん中', '一番新しい'], $this->titlesOf('?sort=oldest'));
    }

    /** @test */
    public function 画面の新しい順の値newestも新着順として扱われる(): void
    {
        Book::factory()->create(['title' => '古い', 'created_at' => Carbon::parse('2026-01-01')]);
        Book::factory()->create(['title' => '新しい', 'created_at' => Carbon::parse('2026-02-01')]);

        $this->assertSame(['新しい', '古い'], $this->titlesOf('?sort=newest'));
    }

    /** @test */
    public function タイトル順で並び替えられる(): void
    {
        Book::factory()->create(['title' => 'うさぎ']);
        Book::factory()->create(['title' => 'あひる']);
        Book::factory()->create(['title' => 'いるか']);

        $this->assertSame(['あひる', 'いるか', 'うさぎ'], $this->titlesOf('?sort=title'));
    }

    /** @test */
    public function 評価順は平均評価の高い順でレビューのない書籍は最後になる(): void
    {
        $noReview = Book::factory()->create(['title' => 'レビューなし']);
        $low = Book::factory()->create(['title' => '低評価']);
        $high = Book::factory()->create(['title' => '高評価']);
        Review::factory()->for($low)->create(['rating' => 2]);
        Review::factory()->for($high)->create(['rating' => 5]);
        Review::factory()->for($high)->create(['rating' => 4]);

        $this->assertSame(['高評価', '低評価', 'レビューなし'], $this->titlesOf('?sort=rating'));
        $this->assertSame($noReview->title, collect($this->titlesOf('?sort=rating'))->last());
    }

    /** @test */
    public function 評価順で平均が同じならレビュー件数の多い順になる(): void
    {
        $few = Book::factory()->create(['title' => '件数少']);
        $many = Book::factory()->create(['title' => '件数多']);
        Review::factory()->for($few)->create(['rating' => 4]);
        Review::factory()->for($many)->count(3)->create(['rating' => 4]);

        $this->assertSame(['件数多', '件数少'], $this->titlesOf('?sort=rating'));
    }

    /** @test */
    public function 不正な並び順は新着順にフォールバックしエラーにならない(): void
    {
        Book::factory()->create(['title' => '古い', 'created_at' => Carbon::parse('2026-01-01')]);
        Book::factory()->create(['title' => '新しい', 'created_at' => Carbon::parse('2026-02-01')]);

        $this->assertSame(['新しい', '古い'], $this->titlesOf('?sort=unknown'));
        $this->assertSame(['新しい', '古い'], $this->titlesOf('?sort='));
        $this->assertSame(BookSort::Latest, BookSort::fromInput(null));
        $this->assertSame(BookSort::Rating, BookSort::fromInput('rating'));
    }

    // ---- 表示 ----

    /** @test */
    public function 検索条件がある場合は件数が表示され該当なしの文言も表示される(): void
    {
        Book::factory()->create(['title' => '吾輩は猫である']);

        $this->get('/books?keyword=猫')->assertOk()->assertSee('検索結果: 1件');
        $this->get('/books?keyword=存在しない')->assertOk()->assertSee('検索結果: 0件')->assertSee('書籍が見つかりませんでした。');
        $this->get('/books')->assertOk()->assertDontSee('検索結果:');
    }

    /** @test */
    public function ページ番号が不正だと一覧へ戻る(): void
    {
        $this->get('/books?page=0')->assertRedirect(route('books.index'));
        $this->get('/books?page=abc')->assertRedirect(route('books.index'));
    }
}
