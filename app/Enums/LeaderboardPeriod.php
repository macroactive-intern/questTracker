<?php

namespace App\Enums;

enum LeaderboardPeriod: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case AllTime = 'alltime';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $period): string => $period->value,
            self::cases(),
        );
    }
}
