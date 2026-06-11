<?php

declare(strict_types=1);

namespace App\Enums;

enum AvailabilitySourceEnum: string
{
    case Manager = 'manager';

    case Employee = 'employee';

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
