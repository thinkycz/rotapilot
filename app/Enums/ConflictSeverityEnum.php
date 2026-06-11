<?php

declare(strict_types=1);

namespace App\Enums;

enum ConflictSeverityEnum: string
{
    case Info = 'info';

    case Warning = 'warning';

    case Critical = 'critical';

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
