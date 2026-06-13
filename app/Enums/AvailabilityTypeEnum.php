<?php

declare(strict_types=1);

namespace App\Enums;

enum AvailabilityTypeEnum: string
{
    case Available = 'available';

    case Unavailable = 'unavailable';

    case Backup = 'backup';

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
