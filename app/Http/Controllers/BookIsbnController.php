<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookIsbnRequest;
use App\Services\GoogleBooksException;
use App\Services\GoogleBooksQuotaException;
use App\Services\GoogleBooksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BookIsbnController extends Controller
{
    /**
     * ISBN-13 から書籍情報を検索する（書籍の登録・編集画面の JS が呼ぶ。認証不要）。
     *
     * 成功 200（書籍フォームの項目）／ 桁数不正 400 ／ 該当なし 404 ／ クォータ超過 429 ／ 通信エラー 500。
     * 失敗時は画面に表示するため `{error}` を返す。
     */
    public function show(BookIsbnRequest $request, GoogleBooksService $googleBooks): JsonResponse
    {
        try {
            $book = $googleBooks->findByIsbn($request->validated('isbn'));
        } catch (GoogleBooksQuotaException) {
            return response()->json(
                ['error' => 'Google Books API のクォータを超過しました。.env に GOOGLE_BOOKS_API_KEY を設定してください。'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        } catch (GoogleBooksException) {
            return response()->json(
                ['error' => 'API通信エラーが発生しました。'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        if ($book === null) {
            return response()->json(['error' => '書籍が見つかりませんでした。'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($book);
    }
}
