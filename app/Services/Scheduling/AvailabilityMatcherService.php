<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\AvailabilityTypeEnum;
use App\Models\EmployeeAvailability;

/**
 * Deterministically checks whether an employee is available for a shift.
 */
class AvailabilityMatcherService
{
    /**
     * Check availability for an employee on a date.
     *
     * @param array<int, EmployeeAvailability> $availabilities
     */
    public function check(
        array $availabilities,
        string $date,
        string $startTime,
        string $endTime,
    ): AvailabilityVerdict {
        $hasUnavailableAllDay = false;
        $hasBlockingUnavailable = false;
        $hasMatchingWindow = false;

        foreach ($availabilities as $a) {
            if ($date !== $a->getDate()) {
                continue;
            }

            $type = $a->getType();
            $start = $a->getStartTime();
            $end = $a->getEndTime();

            if ($type === AvailabilityTypeEnum::Unavailable) {
                if ($start === null && $end === null) {
                    $hasUnavailableAllDay = true;
                } else {
                    if ($this->overlaps($startTime, $endTime, (string) $start, (string) $end)) {
                        $hasBlockingUnavailable = true;
                    }
                }
            } elseif ($type === AvailabilityTypeEnum::Available || $type === AvailabilityTypeEnum::Backup) {
                if ($start !== null && $end !== null && $this->isContained($startTime, $endTime, (string) $start, (string) $end)) {
                    $hasMatchingWindow = true;
                }
            }
        }

        if ($hasUnavailableAllDay || $hasBlockingUnavailable) {
            return AvailabilityVerdict::Unavailable;
        }

        if ($hasMatchingWindow) {
            return AvailabilityVerdict::Available;
        }

        // No record at all → missing.
        if (\count($availabilities) === 0) {
            return AvailabilityVerdict::Missing;
        }

        // Records exist for this date but none cover the shift window → unavailable.
        return AvailabilityVerdict::Unavailable;
    }

    /**
     * Two time ranges overlap.
     */
    /**
     * Check whether two time ranges overlap.
     */
    private function overlaps(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        return $aStart < $bEnd && $bStart < $aEnd;
    }

    /**
     * Check whether the shift window is fully contained in the available window.
     */
    private function isContained(string $shiftStart, string $shiftEnd, string $windowStart, string $windowEnd): bool
    {
        return $shiftStart >= $windowStart && $shiftEnd <= $windowEnd;
    }
}
