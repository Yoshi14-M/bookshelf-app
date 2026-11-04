<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\BookIsbnController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReadingPlanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// 書籍一覧・詳細（ゲストも閲覧可）。`/` と `/books` は同じ一覧を表示する（URL は維持）
Route::get('/', [BookController::class, 'index']);
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/{book}', [BookController::class, 'show'])->whereNumber('book')->name('books.show');

// レビュー平均評価ランキング（ゲストも閲覧可）
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

// ISBN から書籍情報を取得（認証不要。登録・編集画面の JS が直接呼ぶ JSON エンドポイント）
Route::get('/books/isbn/{isbn}', [BookIsbnController::class, 'show'])->name('books.isbn');

// 書籍の登録・編集・削除（要ログイン。編集・削除は登録者のみ）
Route::resource('books', BookController::class)
    ->except(['index', 'show'])
    ->middleware('auth')
    ->where(['book' => '[0-9]+']);

// ジャンル管理（要ログイン。共有マスタのため登録済みなら誰でも操作可）
Route::resource('genres', GenreController::class)
    ->middleware('auth')
    ->where(['genre' => '[0-9]+']);

// レビュー（要ログイン。編集・削除は投稿者のみ）。投稿は書籍詳細のフォームから `books/{book}/reviews` へ送る
Route::middleware('auth')->group(function () {
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->whereNumber('book')->name('reviews.store');
    Route::resource('reviews', ReviewController::class)
        ->only(['edit', 'update', 'destroy'])
        ->where(['review' => '[0-9]+']);

    // いいね・お気に入りのトグル（自分のレビュー・自分の書籍にも可なので Policy なし）
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->whereNumber('review')->name('reviews.like');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->whereNumber('book')->name('favorites.toggle');

    // マイ読書レポート（自分のレビューの集計）
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // 読書計画（自分の計画のみ。編集・読了は所有者のみで、完了した計画は編集不可）
    Route::resource('reading-plans', ReadingPlanController::class)
        ->except(['show'])
        ->where(['reading_plan' => '[0-9]+']);
    Route::post('/reading-plans/{reading_plan}/complete', [ReadingPlanController::class, 'complete'])
        ->whereNumber('reading_plan')
        ->name('reading-plans.complete');
});

/*
|--------------------------------------------------------------------------
| 暫定ルート（応用フェーズ）
|--------------------------------------------------------------------------
|
| Advanced Blade が route() で参照するが、まだ実装していない画面のルート。これが無いと
| ナビを含む全画面が RouteNotFoundException になるため、名前・メソッド・URI・認証だけを
| 確定させて「準備中」(501) を返す。各機能の実装時に本実装へ置き換えて削除する。
|   通知 → #15
|
*/
$comingSoon = fn () => abort(501, 'この機能は準備中です。');

Route::middleware('auth')->group(function () use ($comingSoon) {
    Route::get('/notifications', $comingSoon)->name('notifications.index');
    Route::post('/notifications/{notification}/read', $comingSoon)->name('notifications.read');
});
