<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Store;
use Carbon\CarbonImmutable;

class ScheduleTitle
{
    /**
     * Generate a schedule title from the store and month.
     */
    public static function generate(Store $store, CarbonImmutable $periodStart): string
    {
        return $store->getName() . ' - ' . $periodStart->translatedFormat('F Y');
    }
}
