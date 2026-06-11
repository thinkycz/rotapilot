<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\ShiftRequirement;

/**
 * Detects overlapping shift assignments for an employee on a given date.
 */
class OverlapDetectorService
{
    /**
     * @param array<int, ShiftRequirement> $requirements
     */
    public function hasOverlap(
        array $requirements,
        int $excludeRequirementId,
        string $date,
        string $startTime,
        string $endTime,
    ): bool {
        foreach ($requirements as $r) {
            if ($excludeRequirementId === $r->getKey()) {
                continue;
            }

            if ($date !== $r->getDate()) {
                continue;
            }

            if ($startTime < $r->getEndTime() && $endTime > $r->getStartTime()) {
                return true;
            }
        }

        return false;
    }
}
