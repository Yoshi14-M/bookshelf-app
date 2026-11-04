<?php

namespace App\Services;

/**
 * Google Books API の利用上限（クォータ）を超過した（HTTP 429）。
 */
class GoogleBooksQuotaException extends GoogleBooksException {}
