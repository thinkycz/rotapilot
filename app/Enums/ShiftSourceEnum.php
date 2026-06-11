<?php

declare(strict_types=1);

namespace App\Enums;

enum ShiftSourceEnum: string
{
    case Manual = 'manual';

    case Ai = 'ai';

    /**
     * Get possible values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return \array_column(self::cases(), 'value');
    }
}
