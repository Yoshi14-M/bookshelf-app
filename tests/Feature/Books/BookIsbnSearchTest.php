<?php

namespace Tests\Feature\Books;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BookIsbnSearchTest extends TestCase
{
    use RefreshDatabase;

    private const ISBN = '9784101010014';

    /**
     * Google Books API の volumes レスポンス（1 件ヒット）。
     * `industryIdentifiers` を省略した場合は、検索した ISBN-13 と一致する値を付ける。
     *
     * @param  array<string, mixed>  $volumeInfo
     * @return array<string, mixed>
     */
    private function volumes(array $volumeInfo): array
    {
        return $this->volumesOf([$volumeInfo]);
    }

    /**
     * Google Books API の volumes レスポンス（複数件ヒット）。
     *
     * @param  array<int, array<string, mixed>>  $volumeInfos
     * @return array<string, mixed>
     */
    private function volumesOf(array $volumeInfos): array
    {
        $items = array_map(fn (array $volumeInfo) => ['volumeInfo' => $volumeInfo + [
            'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => self::ISBN]],
        ]], $volumeInfos);

        return ['kind' => 'books#volumes', 'totalItems' => count($items), 'items' => $items];
    }

    /**
     * @return array<string, mixed>
     */
    private function fullVolumeInfo(): array
    {
        return [
            'title' => '吾輩は猫である',
            'authors' => ['夏目漱石'],
            'description' => '中学校の英語教師の家に飼われる猫の物語。',
            'publishedDate' => '1905-10-06',
            'imageLinks' => ['thumbnail' => 'https://books.google.com/books/content?id=abc'],
        ];
    }

    private function search(string $isbn = self::ISBN): TestResponse
    {
        return $this->getJson('/books/isbn/'.$isbn);
    }

    // ---- 成功 ----

    /** @test */
    public function 書籍番号から書籍情報を取得できる(): void
    {
        Http::fake(['*' => Http::response($this->volumes($this->fullVolumeInfo()))]);

        $this->search()
            ->assertOk()
            ->assertExactJson([
                'title' => '吾輩は猫である',
                'author' => '夏目漱石',
                'description' => '中学校の英語教師の家に飼われる猫の物語。',
                'image_url' => 'https://books.google.com/books/content?id=abc',
                'published_date' => '1905-10-06',
            ]);
    }

    /** @test */
    public function 外部の通信先には書籍番号指定の検索で設定のアドレスを呼び出す(): void
    {
        config(['services.google_books.url' => 'https://books.example.test/volumes', 'services.google_books.key' => null]);
        Http::fake(['*' => Http::response($this->volumes($this->fullVolumeInfo()))]);

        $this->search()->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://books.example.test/volumes')
            && $request['q'] === 'isbn:'.self::ISBN
            && ! $request->hasHeader('X-Goog-Api-Key')
            && ! isset($request['key']));
    }

    /** @test */
    public function 設定したキーが外部の通信先へ送られる(): void
    {
        config(['services.google_books.key' => 'secret-key']);
        Http::fake(['*' => Http::response($this->volumes($this->fullVolumeInfo()))]);

        $this->search()->assertOk();

        Http::assertSent(fn (Request $request) => $request['key'] === 'secret-key');
    }

    /** @test */
    public function 著者が複数なら読点で連結される(): void
    {
        Http::fake(['*' => Http::response($this->volumes(['title' => 'x', 'authors' => ['岸見一郎', '古賀史健']]))]);

        $this->search()->assertOk()->assertJsonPath('author', '岸見一郎、古賀史健');
    }

    /** @test */
    public function 画像のアドレスは暗号化通信の形式に変換される(): void
    {
        Http::fake(['*' => Http::response($this->volumes(['title' => 'x', 'imageLinks' => ['thumbnail' => 'http://books.google.com/a?zoom=1']]))]);

        $this->search()->assertOk()->assertJsonPath('image_url', 'https://books.google.com/a?zoom=1');
    }

    /**
     * @test
     *
     * @dataProvider publishedDates
     */
    public function 出版日は補完され不正なら空になる(?string $given, ?string $expected): void
    {
        $volumeInfo = ['title' => 'x'] + ($given === null ? [] : ['publishedDate' => $given]);
        Http::fake(['*' => Http::response($this->volumes($volumeInfo))]);

        $this->search()->assertOk()->assertJsonPath('published_date', $expected);
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function publishedDates(): array
    {
        return [
            '年月日' => ['2012-06-23', '2012-06-23'],
            '年月' => ['2012-06', '2012-06-01'],
            '年のみ' => ['2012', '2012-01-01'],
            '存在しない日付' => ['2012-02-30', null],
            '月が範囲外' => ['2012-13', null],
            '日付でない文字列' => ['unknown', null],
            '項目なし' => [null, null],
        ];
    }

    /** @test */
    public function 取得できない項目は空文字かnullになる(): void
    {
        Http::fake(['*' => Http::response($this->volumes(['title' => '題名だけ']))]);

        $this->search()
            ->assertOk()
            ->assertExactJson([
                'title' => '題名だけ',
                'author' => '',
                'description' => '',
                'image_url' => null,
                'published_date' => null,
            ]);
    }

    /** @test */
    public function 登録済みの書籍番号でも検索結果は返る(): void
    {
        Book::factory()->create(['isbn' => self::ISBN]);
        Http::fake(['*' => Http::response($this->volumes($this->fullVolumeInfo()))]);

        $this->search()->assertOk()->assertJsonPath('title', '吾輩は猫である');
    }

    // ---- ISBN の一致確認 ----

    /** @test */
    public function 検索結果の書籍番号が入力と違う場合は該当なしとして扱う(): void
    {
        // Google は存在しない番号でも近い番号（チェックディジットを正規化した番号）の書籍を返すことがある
        Http::fake(['*' => Http::response($this->volumes([
            'title' => '別の本',
            'industryIdentifiers' => [
                ['type' => 'ISBN_13', 'identifier' => '9784101010021'],
                ['type' => 'ISBN_10', 'identifier' => '4101010021'],
            ],
        ]))]);

        $this->search()->assertNotFound()->assertExactJson(['error' => '書籍が見つかりませんでした。']);
    }

    /** @test */
    public function チェックディジットが不正な書籍番号は外部通信を行わず該当なしになる(): void
    {
        Http::fake();

        $this->search('9780000000000')->assertNotFound()->assertExactJson(['error' => '書籍が見つかりませんでした。']);

        Http::assertNothingSent();
    }

    /** @test */
    public function 書籍番号の情報を持たない検索結果は該当なしとして扱う(): void
    {
        Http::fake(['*' => Http::response($this->volumes(['title' => '番号なし', 'industryIdentifiers' => []]))]);

        $this->search()->assertNotFound();
    }

    /** @test */
    public function 複数件の候補から入力と一致する書籍を選ぶ(): void
    {
        Http::fake(['*' => Http::response($this->volumesOf([
            ['title' => '別の本', 'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => '9784101010021']]],
            ['title' => '一致する本'],
        ]))]);

        $this->search()->assertOk()->assertJsonPath('title', '一致する本');
    }

    /** @test */
    public function isb_n13を持たず同じ書籍の_isb_n10だけを持つ場合も一致として扱う(): void
    {
        Http::fake(['*' => Http::response($this->volumes([
            'title' => '吾輩は猫である',
            'industryIdentifiers' => [['type' => 'ISBN_10', 'identifier' => '4101010013']],
        ]))]);

        $this->search()->assertOk()->assertJsonPath('title', '吾輩は猫である');
    }

    /** @test */
    public function チェックディジットが_xの_isb_n10にも一致する(): void
    {
        // 080442957X の本体 9 桁の加重和は 199（11 で割った余りが 1）なので、チェックディジットは X になる
        Http::fake(['*' => Http::response($this->volumes([
            'title' => 'X の本',
            'industryIdentifiers' => [['type' => 'ISBN_10', 'identifier' => '080442957X']],
        ]))]);

        $this->search('9780804429573')->assertOk()->assertJsonPath('title', 'X の本');
    }

    /** @test */
    public function 接頭辞979の書籍番号は_isb_n13の完全一致だけで判定する(): void
    {
        Http::fake(['*' => Http::response($this->volumes([
            'title' => '別の本',
            'industryIdentifiers' => [['type' => 'ISBN_10', 'identifier' => '4101010013']],
        ]))]);

        $this->search('9791234567896')->assertNotFound();
    }

    // ---- 該当なし ----

    /** @test */
    public function 該当する書籍がない場合は404でエラーメッセージを返す(): void
    {
        Http::fake(['*' => Http::response(['kind' => 'books#volumes', 'totalItems' => 0])]);

        $this->search()
            ->assertNotFound()
            ->assertExactJson(['error' => '書籍が見つかりませんでした。']);
    }

    /** @test */
    public function 本文が空のレスポンスも該当なしとして扱う(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $this->search()->assertNotFound()->assertJsonPath('error', '書籍が見つかりませんでした。');
    }

    // ---- 外部 API の失敗 ----

    /** @test */
    public function 外部の通信先がエラーを返した場合は500になる(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 500]], 500)]);

        $this->search()
            ->assertStatus(500)
            ->assertExactJson(['error' => 'API通信エラーが発生しました。']);
    }

    /** @test */
    public function 外部の通信先の利用制限に達した場合はキー設定を促す429になる(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 429]], 429)]);

        $this->search()
            ->assertStatus(429)
            ->assertExactJson(['error' => 'Google Books API のクォータを超過しました。.env に GOOGLE_BOOKS_API_KEY を設定してください。']);
    }

    /** @test */
    public function 外部の通信先へ接続できない場合は500になる(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host'));

        $this->search()
            ->assertStatus(500)
            ->assertJsonPath('error', 'API通信エラーが発生しました。');
    }

    // ---- 入力不正 ----

    /**
     * @test
     *
     * @dataProvider invalidIsbns
     */
    public function 書籍番号が13桁の数字でなければ400になり外部通信は行われない(string $isbn): void
    {
        Http::fake();

        $this->search($isbn)
            ->assertStatus(400)
            ->assertExactJson(['error' => 'ISBNは13桁で入力してください。']);

        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidIsbns(): array
    {
        return [
            '12桁' => ['978410101001'],
            '14桁' => ['97841010100141'],
            '数字以外を含む' => ['978-4101010014'],
            '英字' => ['abcdefghijklm'],
        ];
    }

    // ---- 認証 ----

    /** @test */
    public function ログインしていなくても書籍情報を取得できる(): void
    {
        Http::fake(['*' => Http::response($this->volumes($this->fullVolumeInfo()))]);

        $this->assertGuest();
        $this->search()->assertOk()->assertJsonPath('title', '吾輩は猫である');
    }

    /** @test */
    public function ログイン中でも同じ結果を返す(): void
    {
        Http::fake(['*' => Http::response($this->volumes($this->fullVolumeInfo()))]);

        $this->actingAs(User::factory()->create())->getJson('/books/isbn/'.self::ISBN)
            ->assertOk()
            ->assertJsonPath('title', '吾輩は猫である');
    }

    // ---- 画面 ----

    /** @test */
    public function 登録画面と編集画面に書籍番号の検索フォームが表示される(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->actingAs($user)->get(route('books.create'))->assertOk()->assertSee('id="isbn-search"', false);
        $this->actingAs($user)->get(route('books.edit', $book))->assertOk()->assertSee('id="isbn-search"', false);
    }
}
