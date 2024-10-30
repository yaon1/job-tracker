<?php

declare(strict_types=1);

namespace JobTracker;

enum Status: string
{
    case Wishlist = 'wishlist';
    case Applied = 'applied';
    case Interview = 'interview';
    case Offer = 'offer';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Wishlist => 'Хочу откликнуться',
            self::Applied => 'Отклик отправлен',
            self::Interview => 'Собеседование',
            self::Offer => 'Оффер',
            self::Rejected => 'Отказ',
        };
    }

    /** Statuses that count as "reached this stage or further" in the funnel. */
    public function rank(): int
    {
        return match ($this) {
            self::Wishlist => 0,
            self::Applied, self::Rejected => 1,
            self::Interview => 2,
            self::Offer => 3,
        };
    }
}
