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

// 公開 API（基本フェーズは認証なし。応用フェーズで書き込み系に auth:sanctum を追加する）
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::apiResource('books', BookController::class);
});
