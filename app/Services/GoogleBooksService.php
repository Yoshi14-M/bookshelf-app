<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Books API から ISBN で書籍情報を取得する。
 */
class GoogleBooksService
{
    /**
     * 1 回の検索で取得する件数。`isbn:` 検索は ISBN を正規化して曖昧に一致させるため、候補から入力と一致するものを選ぶ。
     */
    private const MAX_RESULTS = 5;

    /**
     * ISBN-13 に該当する書籍情報を取得して、書籍フォームに入れる形に整形する。
     *
     * @return array{title: string, author: string, description: string, image_url: ?string, published_date: ?string}|null 該当なしは null
     *
     * @throws GoogleBooksQuotaException 利用上限を超過したとき（429）
     * @throws GoogleBooksException 通信失敗・エラーレスポンスのとき
     */
    public function findByIsbn(string $isbn): ?array
    {
        // チェックディジットが不正な番号は実在しないので、外部 API を呼ばずに該当なしとする
        if (! $this->hasValidIsbn13Checksum($isbn)) {
            return null;
        }

        $volumeInfo = $this->findMatchingVolumeInfo($this->fetch($isbn), $isbn);

        if ($volumeInfo === null) {
            return null;
        }

        return [
            'title' => (string) Arr::get($volumeInfo, 'title', ''),
            'author' => implode('、', Arr::wrap(Arr::get($volumeInfo, 'authors', []))),
            'description' => (string) Arr::get($volumeInfo, 'description', ''),
            'image_url' => $this->normalizeImageUrl(Arr::get($volumeInfo, 'imageLinks.thumbnail')),
            'published_date' => $this->normalizePublishedDate(Arr::get($volumeInfo, 'publishedDate')),
        ];
    }

    /**
     * Google Books API を呼び出して、レスポンスの JSON を配列で返す。
     *
     * @return array<string, mixed>
     *
     * @throws GoogleBooksQuotaException
     * @throws GoogleBooksException
     */
    private function fetch(string $isbn): array
    {
        $query = array_filter([
            'q' => 'isbn:'.$isbn,
            'maxResults' => self::MAX_RESULTS,
            'key' => config('services.google_books.key'),
        ]);

        try {
            $response = Http::acceptJson()->get(config('services.google_books.url'), $query);
        } catch (ConnectionException $e) {
            Log::warning('Google Books API への接続に失敗しました。', ['message' => $e->getMessage()]);

            throw new GoogleBooksException('Google Books API に接続できませんでした。', previous: $e);
        }

        if ($response->failed()) {
            Log::warning('Google Books API がエラーを返しました。', ['status' => $response->status()]);

            if ($response->status() === 429) {
                throw new GoogleBooksQuotaException('Google Books API のクォータを超過しました。');
            }

            throw new GoogleBooksException('Google Books API がエラーを返しました。（'.$response->status().'）');
        }

        return $response->json() ?? [];
    }

    /**
     * 検索結果のうち、入力した ISBN と一致する書籍の `volumeInfo` を返す（無ければ null）。
     *
     * Google の `isbn:` 検索は、存在しない番号でも近い番号の別の書籍を返すことがあるため、
     * `industryIdentifiers` の ISBN-13（無ければ同じ書籍の ISBN-10）が入力と一致するものだけを採用する。
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    private function findMatchingVolumeInfo(array $response, string $isbn): ?array
    {
        $acceptable = array_filter([$isbn, $this->toIsbn10($isbn)]);

        return collect(Arr::get($response, 'items', []))
            ->map(fn (mixed $item) => Arr::get($item, 'volumeInfo'))
            ->filter(fn (mixed $volumeInfo) => is_array($volumeInfo))
            ->first(fn (array $volumeInfo) => collect(Arr::get($volumeInfo, 'industryIdentifiers', []))
                ->pluck('identifier')
                ->intersect($acceptable)
                ->isNotEmpty());
    }

    /**
     * ISBN-13 のチェックディジット（先頭 12 桁に重み 1・3 を交互に掛けた和から求める）が正しいか。
     */
    private function hasValidIsbn13Checksum(string $isbn13): bool
    {
        $sum = collect(str_split(substr($isbn13, 0, 12)))
            ->map(fn (string $digit, int $index) => (int) $digit * ($index % 2 === 0 ? 1 : 3))
            ->sum();

        return (10 - $sum % 10) % 10 === (int) $isbn13[12];
    }

    /**
     * 978 始まりの ISBN-13 を ISBN-10 に変換する（979 始まりは対応する ISBN-10 が無いので null）。
     */
    private function toIsbn10(string $isbn13): ?string
    {
        if (! str_starts_with($isbn13, '978')) {
            return null;
        }

        $body = substr($isbn13, 3, 9);
        $sum = collect(str_split($body))->map(fn (string $digit, int $index) => (int) $digit * (10 - $index))->sum();
        $check = (11 - $sum % 11) % 11;

        return $body.($check === 10 ? 'X' : (string) $check);
    }

    /**
     * サムネイルの `http://` を `https://` に変換する（取得できなければ null）。
     */
    private function normalizeImageUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return preg_replace('#^http://#i', 'https://', $url);
    }

    /**
     * `YYYY` / `YYYY-MM` / `YYYY-MM-DD` を `Y-m-d` に補完する（不正な日付や取得できなければ null）。
     */
    private function normalizePublishedDate(mixed $date): ?string
    {
        if (! is_string($date) || ! preg_match('/^(\d{4})(?:-(\d{2}))?(?:-(\d{2}))?$/', $date, $matches)) {
            return null;
        }

        [$year, $month, $day] = [(int) $matches[1], (int) ($matches[2] ?? 1), (int) ($matches[3] ?? 1)];

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day)->format('Y-m-d');
    }
}
