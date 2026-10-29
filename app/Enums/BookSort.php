<?php

namespace App\Enums;

/**
 * 書籍一覧の並び順。
 */
enum BookSort: string
{
    case Latest = 'latest';
    case Oldest = 'oldest';
    case Title = 'title';
    case Rating = 'rating';

    /**
     * リクエストの値から並び順を決める。Advanced Blade の `newest` は `latest` として受理し、
     * 未指定・未知の値は既定（`latest`）にフォールバックする。
     */
    public static function fromInput(?string $value): self
    {
        return match ($value) {
            'newest' => self::Latest,
            default => self::tryFrom((string) $value) ?? self::Latest,
        };
    }
}
