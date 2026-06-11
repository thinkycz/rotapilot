<?php

declare(strict_types=1);

namespace App\Enums;

enum ConflictTypeEnum: string
{
    case Understaffed = 'understaffed';

    case UnavailableEmployee = 'unavailable_employee';

    case OverlappingShift = 'overlapping_shift';

    case OutsideBusinessHours = 'outside_business_hours';

    case MaxHoursExceeded = 'max_hours_exceeded';

    case MissingAvailability = 'missing_availability';

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
