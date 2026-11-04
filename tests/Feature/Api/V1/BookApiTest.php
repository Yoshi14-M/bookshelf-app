<?php

namespace Tests\Feature\Api\V1;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'title' => 'リーダブルコード',
            'author' => 'Dustin Boswell',
            'isbn' => '9784873115658',
            'published_date' => '2012-06-23',
            'description' => '読みやすいコードの書き方',
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=3',
            'genres' => [Genre::factory()->create()->id],
        ], $overrides);
    }

    /**
     * Sanctum のトークンで認証した状態にする（書き込み系の API に必要）。
     */
    private function signIn(?User $user = null): User
    {
        return Sanctum::actingAs($user ?? User::factory()->create());
    }

    // ---- 一覧 ----

    /** @test */
    public function 認証もヘッダ指定もなしで書籍一覧を取得できる(): void
    {
        $book = Book::factory()->create(['title' => '吾輩は猫である']);
        $book->genres()->attach(Genre::factory()->create(['name' => '小説']));
        Review::factory()->for($book)->create(['rating' => 5]);
        Review::factory()->for($book)->create(['rating' => 4]);

        $this->get('/api/v1/books')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure([
                'data' => [['id', 'title', 'author', 'isbn', 'published_date', 'description', 'image_url', 'genres', 'average_rating', 'reviews_count', 'created_at']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('data.0.title', '吾輩は猫である')
            ->assertJsonPath('data.0.genres', [['id' => $book->genres[0]->id, 'name' => '小説']])
            ->assertJsonPath('data.0.average_rating', 4.5)
            ->assertJsonPath('data.0.reviews_count', 2);
    }

    /** @test */
    public function レビューのない書籍の平均評価はnullで件数は0になる(): void
    {
        Book::factory()->create();

        $this->getJson('/api/v1/books')
            ->assertJsonPath('data.0.average_rating', null)
            ->assertJsonPath('data.0.reviews_count', 0);
    }

    /** @test */
    public function 一覧のレスポンスに登録者の情報は含まれない(): void
    {
        $user = User::factory()->create(['name' => '登録者の名前']);
        Book::factory()->for($user)->create();

        $response = $this->getJson('/api/v1/books')->assertOk();

        $response->assertJsonMissingPath('data.0.user_id')
            ->assertJsonMissingPath('data.0.user');
        $this->assertStringNotContainsString('登録者の名前', $response->getContent());
    }

    /** @test */
    public function 一覧は既定で20件ずつ新着順に返る(): void
    {
        Book::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/books')->assertOk();

        $this->assertCount(20, $response->json('data'));
        $this->assertSame(25, $response->json('meta.total'));
        $this->assertSame(20, $response->json('meta.per_page'));
        $this->assertSame(Book::max('id'), $response->json('data.0.id'));
    }

    /** @test */
    public function ページと件数を指定できる(): void
    {
        Book::factory()->count(7)->create();

        $response = $this->getJson('/api/v1/books?per_page=3&page=3')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(3, $response->json('meta.current_page'));
        $this->assertSame(3, $response->json('meta.per_page'));
    }

    /** @test */
    public function 一覧のリンクに検索条件が引き継がれる(): void
    {
        Book::factory()->count(3)->create(['author' => '夏目漱石']);

        $response = $this->getJson('/api/v1/books?keyword=夏目&per_page=2')->assertOk();

        $this->assertStringContainsString('per_page=2', $response->json('links.next'));
        $this->assertStringContainsString('keyword=', $response->json('links.next'));
    }

    /** @test */
    public function タイトルと著者の部分一致で検索できる(): void
    {
        Book::factory()->create(['title' => '吾輩は猫である', 'author' => '夏目漱石']);
        Book::factory()->create(['title' => '坊っちゃん', 'author' => '夏目漱石']);
        Book::factory()->create(['title' => 'リーダブルコード', 'author' => 'Dustin Boswell']);
        Book::factory()->create(['title' => '夏目漱石入門', 'author' => '解説者']);

        $this->assertSame(3, $this->getJson('/api/v1/books?keyword=夏目')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/books?keyword=猫')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/books?keyword=dustin')->json('meta.total'));
        $this->assertSame(0, $this->getJson('/api/v1/books?keyword=存在しない')->json('meta.total'));
        $this->assertSame(4, $this->getJson('/api/v1/books?keyword=')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/books?keyword='.urlencode('  猫  '))->json('meta.total'));
    }

    /** @test */
    public function キーワードの記号はワイルドカードとして扱われない(): void
    {
        // LIKE の既定のエスケープ文字（バックスラッシュ）は MySQL のみ。SQLite（テスト既定）には無い
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL の LIKE エスケープ仕様に依存するため、MySQL 接続でのみ実行する。');
        }

        Book::factory()->create(['title' => '100%の力']);
        Book::factory()->create(['title' => '普通の本']);
        Book::factory()->create(['title' => 'a_b']);
        Book::factory()->create(['title' => 'axb']);
        Book::factory()->create(['title' => 'C:\\temp']);

        $this->assertSame(1, $this->getJson('/api/v1/books?keyword='.urlencode('%'))->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/books?keyword='.urlencode('a_b'))->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/books?keyword='.urlencode('\\'))->json('meta.total'));
    }

    /** @test */
    public function ジャンルで絞り込める(): void
    {
        $novel = Genre::factory()->create();
        $tech = Genre::factory()->create();
        Book::factory()->create(['title' => '小説の本'])->genres()->attach($novel);
        Book::factory()->create(['title' => '技術の本'])->genres()->attach($tech);
        Book::factory()->create(['title' => '両方の本'])->genres()->attach([$novel->id, $tech->id]);

        $response = $this->getJson('/api/v1/books?genre_id='.$novel->id)->assertOk();

        $this->assertEqualsCanonicalizing(['小説の本', '両方の本'], collect($response->json('data'))->pluck('title')->all());
    }

    /** @test */
    public function キーワードとジャンルを組み合わせて絞り込める(): void
    {
        $genre = Genre::factory()->create();
        Book::factory()->create(['title' => '猫の本'])->genres()->attach($genre);
        Book::factory()->create(['title' => '犬の本'])->genres()->attach($genre);
        Book::factory()->create(['title' => '猫の別ジャンル本']);

        $response = $this->getJson('/api/v1/books?keyword=猫&genre_id='.$genre->id);

        $this->assertSame(['猫の本'], collect($response->json('data'))->pluck('title')->all());
    }

    /**
     * @test
     *
     * @dataProvider 一覧パラメータの不備
     */
    public function 一覧のパラメータが不正だと422になり日本語のエラーが返る(string $query, string $field, string $message): void
    {
        $this->getJson('/api/v1/books?'.$query)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field => $message]);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function 一覧パラメータの不備(): array
    {
        return [
            '件数が0' => ['per_page=0', 'per_page', '1ページあたりの件数は1から100の間で指定してください。'],
            '件数が101' => ['per_page=101', 'per_page', '1ページあたりの件数は1から100の間で指定してください。'],
            '件数が整数でない' => ['per_page=abc', 'per_page', '1ページあたりの件数は整数で指定してください。'],
            'ページが0' => ['page=0', 'page', 'ページは1以上で指定してください。'],
            'ジャンルIDが存在しない' => ['genre_id=9999', 'genre_id', '指定されたジャンルIDは存在しません。'],
            'ジャンルIDが整数でない' => ['genre_id=abc', 'genre_id', 'ジャンルIDは整数で指定してください。'],
            'キーワードが101文字' => ['keyword='.str_repeat('x', 101), 'keyword', 'キーワードは100文字以内で指定してください。'],
        ];
    }

    /** @test */
    public function 件数の上限100を指定できる(): void
    {
        Book::factory()->count(3)->create();

        $this->getJson('/api/v1/books?per_page=100')->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    /** @test */
    public function 一覧のクエリ数は書籍数に依存しない(): void
    {
        $genre = Genre::factory()->create();
        $make = function (int $count) use ($genre) {
            Book::factory()->count($count)->create()->each(function (Book $book) use ($genre) {
                $book->genres()->attach($genre);
                Review::factory()->count(2)->for($book)->create();
            });
        };

        $make(2);
        $few = $this->countQueries(fn () => $this->getJson('/api/v1/books')->assertOk());
        $make(10);
        $many = $this->countQueries(fn () => $this->getJson('/api/v1/books')->assertOk());

        $this->assertSame($few, $many);
    }

    // ---- 詳細 ----

    /** @test */
    public function 書籍詳細にジャンルとレビューが含まれる(): void
    {
        $book = Book::factory()->create(['published_date' => '2012-06-23']);
        $book->genres()->attach(Genre::factory()->create(['name' => '技術書']));
        Review::factory()->for($book)->for(User::factory()->create(['name' => '鈴木花子']))
            ->create(['rating' => 5, 'comment' => '古いレビュー', 'created_at' => now()->subDay()]);
        $new = Review::factory()->for($book)->for(User::factory()->create(['name' => '田中一郎']))
            ->create(['rating' => 3, 'comment' => '新しいレビュー', 'created_at' => now()]);

        $this->getJson('/api/v1/books/'.$book->id)
            ->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.published_date', '2012-06-23')
            ->assertJsonPath('data.genres.0.name', '技術書')
            ->assertJsonPath('data.average_rating', 4)
            ->assertJsonPath('data.reviews_count', 2)
            ->assertJsonPath('data.reviews.0', [
                'id' => $new->id,
                'user_name' => '田中一郎',
                'rating' => 3,
                'comment' => '新しいレビュー',
                'created_at' => $new->created_at->toIso8601String(),
            ])
            ->assertJsonPath('data.reviews.1.user_name', '鈴木花子')
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.user')
            ->assertJsonMissingPath('data.reviews.0.user_id');
    }

    /** @test */
    public function 日時は国際標準の日付時刻形式で返る(): void
    {
        $book = Book::factory()->create();

        $createdAt = $this->getJson('/api/v1/books/'.$book->id)->json('data.created_at');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $createdAt);
    }

    /** @test */
    public function 存在しない書籍の詳細は404で日本語のメッセージが返る(): void
    {
        $this->get('/api/v1/books/9999')
            ->assertNotFound()
            ->assertExactJson(['message' => '書籍が見つかりません。']);
    }

    /** @test */
    public function 数値以外の指定も404で同じメッセージが返る(): void
    {
        $this->getJson('/api/v1/books/abc')
            ->assertNotFound()
            ->assertExactJson(['message' => '書籍が見つかりません。']);
    }

    /** @test */
    public function 存在しないパスは404になりメッセージが返る(): void
    {
        $this->get('/api/v1/unknown')
            ->assertNotFound()
            ->assertExactJson(['message' => 'リソースが見つかりません。']);
    }

    // ---- 登録 ----

    /** @test */
    public function 書籍を登録すると201で登録内容が返り登録者はトークンの所有者になる(): void
    {
        $user = $this->signIn();
        $genres = Genre::factory()->count(2)->create();

        $response = $this->postJson('/api/v1/books', $this->validInput([
            'genres' => $genres->pluck('id')->all(),
        ]))->assertCreated()
            ->assertJsonPath('data.title', 'リーダブルコード')
            ->assertJsonPath('data.isbn', '9784873115658')
            ->assertJsonPath('data.published_date', '2012-06-23')
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.reviews_count', 0)
            ->assertJsonCount(2, 'data.genres')
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.user');

        $book = Book::findOrFail($response->json('data.id'));
        $this->assertSame($user->id, $book->user_id);
        $this->assertEqualsCanonicalizing($genres->pluck('id')->all(), $book->genres->pluck('id')->all());
    }

    /** @test */
    public function 登録者_i_dを送っても無視されトークンの所有者が登録者になる(): void
    {
        $user = $this->signIn();
        $other = User::factory()->create();

        $response = $this->postJson('/api/v1/books', $this->validInput(['user_id' => $other->id]))->assertCreated();
        $this->postJson('/api/v1/books', $this->validInput(['user_id' => 9999, 'isbn' => '9784101010014']))->assertCreated();

        $this->assertSame($user->id, Book::findOrFail($response->json('data.id'))->user_id);
        $this->assertSame(2, Book::where('user_id', $user->id)->count());
    }

    /** @test */
    public function 任意項目を省略しても登録できる(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/books', $this->validInput(['description' => null, 'image_url' => null]))
            ->assertCreated()
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.image_url', null);
    }

    /**
     * @test
     *
     * @dataProvider 登録の入力不備
     *
     * @param  array<string, mixed>  $overrides
     */
    public function 入力不備のある登録は422になり日本語のエラーが返る(array $overrides, string $field, string $message): void
    {
        $this->signIn();

        $this->postJson('/api/v1/books', $this->validInput($overrides))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field => $message]);

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function 登録の入力不備(): array
    {
        return [
            'タイトル未入力' => [['title' => ''], 'title', 'タイトルは必須です。'],
            'タイトルが256文字' => [['title' => str_repeat('あ', 256)], 'title', 'タイトルは255文字以内で入力してください。'],
            '著者未入力' => [['author' => ''], 'author', '著者は必須です。'],
            'ISBNが12桁' => [['isbn' => '978487311565'], 'isbn', 'ISBNは13桁の数字で入力してください。'],
            'ISBN未入力' => [['isbn' => null], 'isbn', 'ISBNは必須です。'],
            '出版日が日付でない' => [['published_date' => 'abc'], 'published_date', '出版日は正しい日付で入力してください。'],
            '画像URLがURLでない' => [['image_url' => 'image.jpg'], 'image_url', '画像URLは正しいURL形式で入力してください。'],
            'ジャンル未指定' => [['genres' => []], 'genres', 'ジャンルは必須です。'],
            'ジャンルが配列でない' => [['genres' => 'abc'], 'genres', 'ジャンルは配列で指定してください。'],
            '存在しないジャンル' => [['genres' => [9999]], 'genres.0', 'ジャンルの選択が正しくありません。'],
        ];
    }

    /** @test */
    public function 書籍番号が重複する登録は422になる(): void
    {
        $this->signIn();
        Book::factory()->create(['isbn' => '9784873115658']);

        $this->postJson('/api/v1/books', $this->validInput())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['isbn' => 'このISBNは既に登録されています。']);
    }

    /** @test */
    public function 出版日なしでも登録でき出版日はnullで返る(): void
    {
        $this->signIn();
        $input = $this->validInput();
        unset($input['published_date']);

        $this->postJson('/api/v1/books', $input)
            ->assertCreated()
            ->assertJsonPath('data.published_date', null);
        $this->assertNull(Book::firstWhere('isbn', '9784873115658')->published_date);
    }

    /** @test */
    public function 出版日は年月日の形式の文字列で返る(): void
    {
        $book = Book::factory()->create(['published_date' => '2012-06-23']);

        $this->getJson('/api/v1/books')->assertJsonPath('data.0.published_date', '2012-06-23');
        $this->getJson('/api/v1/books/'.$book->id)->assertJsonPath('data.published_date', '2012-06-23');
    }

    /** @test */
    public function 登録の422にはメッセージとエラー一覧が日本語で含まれる(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/books', ['genres' => []])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['title', 'author', 'isbn', 'genres']])
            ->assertJsonMissingValidationErrors('user_id')
            ->assertJsonPath('message', 'タイトルは必須です。 （ほか 3 件のエラー）');
    }

    /** @test */
    public function ヘッダ指定がなくても入力不備はリダイレクトされず422になる(): void
    {
        $this->signIn();

        $this->post('/api/v1/books', [])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json');
    }

    // ---- 更新 ----

    /** @test */
    public function 書籍を更新すると200で更新後の内容が返る(): void
    {
        $user = $this->signIn();
        $book = Book::factory()->for($user)->create(['title' => '更新前']);
        $book->genres()->attach(Genre::factory()->create());
        $newGenre = Genre::factory()->create();

        $this->putJson('/api/v1/books/'.$book->id, $this->validInput(['title' => '更新後', 'genres' => [$newGenre->id]]))
            ->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', '更新後')
            ->assertJsonPath('data.genres', [['id' => $newGenre->id, 'name' => $newGenre->name]]);

        $this->assertSame('更新後', $book->fresh()->title);
        $this->assertSame([$newGenre->id], $book->fresh()->genres->pluck('id')->all());
    }

    /** @test */
    public function 更新で登録者_i_dを送っても登録者は変更されない(): void
    {
        $owner = $this->signIn();
        $book = Book::factory()->for($owner)->create();
        $other = User::factory()->create();

        $this->putJson('/api/v1/books/'.$book->id, $this->validInput(['user_id' => $other->id]))
            ->assertOk()
            ->assertJsonMissingPath('data.user_id');

        $this->assertSame($owner->id, $book->fresh()->user_id);
    }

    /** @test */
    public function 自分の書籍番号のままであれば更新できる(): void
    {
        $book = Book::factory()->for($this->signIn())->create(['isbn' => '9784873115658']);

        $this->putJson('/api/v1/books/'.$book->id, $this->validInput(['isbn' => '9784873115658']))->assertOk();
    }

    /** @test */
    public function 他の書籍と同じ書籍番号には更新できない(): void
    {
        Book::factory()->create(['isbn' => '9784873115658']);
        $book = Book::factory()->for($this->signIn())->create();

        $this->putJson('/api/v1/books/'.$book->id, $this->validInput())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['isbn' => 'このISBNは既に登録されています。']);
    }

    /** @test */
    public function 存在しない書籍の更新は404になる(): void
    {
        $this->signIn();

        $this->putJson('/api/v1/books/9999', $this->validInput())
            ->assertNotFound()
            ->assertExactJson(['message' => '書籍が見つかりません。']);
    }

    /** @test */
    public function 入力不備のある更新は422になり元の内容は変わらない(): void
    {
        $book = Book::factory()->for($this->signIn())->create(['title' => '更新前']);

        $this->putJson('/api/v1/books/'.$book->id, $this->validInput(['title' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title' => 'タイトルは必須です。']);

        $this->assertSame('更新前', $book->fresh()->title);
    }

    // ---- 削除 ----

    /** @test */
    public function 書籍を削除すると204でボディはなく関連データも削除される(): void
    {
        $user = $this->signIn();
        $book = Book::factory()->for($user)->create();
        $book->genres()->attach(Genre::factory()->create());
        $review = Review::factory()->for($book)->create();
        $review->likedByUsers()->attach($user);
        $user->favoriteBooks()->attach($book);

        $response = $this->deleteJson('/api/v1/books/'.$book->id)->assertNoContent();

        $this->assertSame('', $response->getContent());
        $this->assertModelMissing($book);
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('book_genre', 0);
        $this->assertDatabaseCount('favorites', 0);
        $this->assertDatabaseCount('review_likes', 0);
    }

    /** @test */
    public function 存在しない書籍の削除は404になる(): void
    {
        $this->signIn();

        $this->deleteJson('/api/v1/books/9999')
            ->assertNotFound()
            ->assertExactJson(['message' => '書籍が見つかりません。']);
    }

    /** @test */
    public function 削除済みの書籍を再度削除すると404になる(): void
    {
        $book = Book::factory()->for($this->signIn())->create();

        $this->deleteJson('/api/v1/books/'.$book->id)->assertNoContent();
        $this->deleteJson('/api/v1/books/'.$book->id)->assertNotFound();
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
