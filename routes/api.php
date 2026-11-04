<?php

use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// 公開 API。参照（一覧・詳細）は認証不要。書き込み系（登録・更新・削除）は Sanctum のトークン認証が必要で、
// 更新・削除は登録者のみ（BookPolicy）。登録者は入力ではなくトークンの所有者になる
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::apiResource('books', BookController::class)->only(['index', 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('books', BookController::class)->only(['store', 'update', 'destroy']);
    });
});
