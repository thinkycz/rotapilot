<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Store;
use Carbon\CarbonInterface;

/**
 * Validates a shift's time window against a store's business hours.
 */
class BusinessHourGuardService
{
    /**
     * Check whether the given window fits inside the store's business hours.
     */
    public function isWithinBusinessHours(Store $store, string $date, string $startTime, string $endTime): bool
    {
        $dateCarbon = \Carbon\Carbon::parse($date);
        $dayOfWeek = (int) $dateCarbon->format('N');

        $hour = $store->businessHours()
            ->getQuery()
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if ($hour === null) {
            return false;
        }

        if ($hour->getIsClosed()) {
            return false;
        }

        $opensAt = $hour->getOpensAt();
        $closesAt = $hour->getClosesAt();

        if ($opensAt === null || $closesAt === null) {
            return false;
        }

        return $startTime >= $opensAt && $endTime <= $closesAt;
    }

    /**
     * Check whether the store is open (not closed) on the given date.
     */
    public function storeIsOpenOn(Store $store, CarbonInterface $date): bool
    {
        $dayOfWeek = (int) $date->format('N');

        $hour = $store->businessHours()
            ->getQuery()
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if ($hour === null) {
            return true;
        }

        return !$hour->getIsClosed();
    }
}
