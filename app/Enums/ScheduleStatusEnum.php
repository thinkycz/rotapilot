<?php

declare(strict_types=1);

namespace App\Enums;

enum ScheduleStatusEnum: string
{
    case Draft = 'draft';

    case Published = 'published';

    case Archived = 'archived';

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
