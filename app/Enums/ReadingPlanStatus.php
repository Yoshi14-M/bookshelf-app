<?php

namespace App\Enums;

/**
 * 読書計画の状態。作成時は常に進行中で、読了で完了、期日を過ぎると（日次バッチで）期限切れになる。
 */
enum ReadingPlanStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Expired = 'expired';

    /**
     * 画面に表示する名前。
     */
    public function label(): string
    {
        return match ($this) {
            self::InProgress => '進行中',
            self::Completed => '完了',
            self::Expired => '期限切れ',
        };
    }

    /**
     * 状態バッジの Tailwind クラス（`tailwind.config.js` で `app/Enums` を走査対象にしている）。
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::InProgress => 'bg-blue-100 text-blue-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Expired => 'bg-red-100 text-red-800',
        };
    }
}
