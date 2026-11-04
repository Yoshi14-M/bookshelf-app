<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BookController extends Controller
{
    /**
     * 書籍一覧（キーワード・ジャンル絞り込み、ページネーション）。
     */
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $books = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->search($request->validated('keyword'))
            ->inGenre($request->validated('genre_id'))
            ->latest()
            ->latest('id')
            ->paginate($request->validated('per_page') ?? config('bookshelf.api_per_page'))
            ->withQueryString();

        return BookResource::collection($books);
    }

    /**
     * 書籍詳細（ジャンルとレビューつき）。
     */
    public function show(Book $book): BookResource
    {
        $book->load([
            'genres',
            'reviews' => fn ($query) => $query->latest()->latest('id'),
            'reviews.user',
        ])->loadAvg('reviews', 'rating')->loadCount('reviews');

        return new BookResource($book);
    }

    /**
     * 書籍を登録する（要トークン）。登録者はトークンの所有者で、レスポンスには出さない。
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $book = Book::create([
            ...$request->safe()->except('genres'),
            'user_id' => $request->user()->id,
        ]);
        $book->genres()->sync($request->validated('genres'));

        return (new BookResource($this->loadForResponse($book)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * 書籍を更新する（要トークン。登録者のみ。書籍の登録者は変更しない）。
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $this->authorize('update', $book);

        $book->update($request->safe()->except('genres'));
        $book->genres()->sync($request->validated('genres'));

        return new BookResource($this->loadForResponse($book));
    }

    /**
     * 書籍を削除する（要トークン。登録者のみ。関連データは FK の CASCADE で削除される）。
     */
    public function destroy(Book $book): Response
    {
        $this->authorize('delete', $book);

        $book->delete();

        return response()->noContent();
    }

    /**
     * 登録・更新後のレスポンスに必要な関連と集計を読み込む（日付は DB の保存値に正規化される）。
     */
    private function loadForResponse(Book $book): Book
    {
        return $book->refresh()->load('genres')->loadAvg('reviews', 'rating')->loadCount('reviews');
    }
}
