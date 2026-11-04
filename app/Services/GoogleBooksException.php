<?php

namespace App\Services;

use RuntimeException;

/**
 * Google Books API との通信に失敗した（接続エラー・エラーレスポンス）。
 */
class GoogleBooksException extends RuntimeException {}
