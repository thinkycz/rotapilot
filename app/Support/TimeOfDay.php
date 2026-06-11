<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Immutable value object for a time-of-day in `H:i` (24-hour) format.
 *
 * Owns the parse / format / overlap / duration helpers that were
 * previously duplicated across `ScheduleGeneratorService`,
 * `ConflictDetectionService`, `AvailabilityMatcherService`, and
 * `OverlapDetectorService`.
 */
final class TimeOfDay
{
    /**
     * Construct from an `H:i` string.
     */
    private function __construct(private readonly string $value) {}

    /**
     * Build from an `H:i` string. Throws if the string is not a valid
     * 24-hour time.
     */
    public static function from(string $value): self
    {
        if (\preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $value) !== 1) {
            throw new InvalidArgumentException("Invalid time-of-day: {$value}");
        }

        return new self($value);
    }

    /**
     * Try to build from a string. Returns null on failure.
     */
    public static function tryFrom(string|null $value): self|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (\preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $value) === 1) {
            return new self($value);
        }

        if (\preg_match('/^([01]\\d|2[0-3]):[0-5]\\d:[0-5]\\d$/', $value) === 1) {
            return new self(\mb_substr($value, 0, 5));
        }

        return null;
    }

    /**
     * Convert a `H:i` string to minutes since midnight. Returns 0 on
     * invalid input — matches the legacy behaviour of the duplicated
     * implementations.
     */
    public static function parseMinutes(string|null $value): int
    {
        $time = self::tryFrom($value);

        return $time?->toMinutes() ?? 0;
    }

    /**
     * Hours between two `H:i` strings. Returns 0 if either is invalid
     * or if the end is not strictly after the start.
     */
    public static function durationHours(string $start, string $end): float
    {
        $a = self::tryFrom($start);
        $b = self::tryFrom($end);

        if ($a === null || $b === null) {
            return 0.0;
        }

        $diff = $b->toMinutes() - $a->toMinutes();

        return $diff > 0 ? $diff / 60.0 : 0.0;
    }

    /**
     * Whether the two windows [aStart, aEnd) and [bStart, bEnd) overlap.
     */
    public static function overlaps(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        $aS = self::tryFrom($aStart);
        $aE = self::tryFrom($aEnd);
        $bS = self::tryFrom($bStart);
        $bE = self::tryFrom($bEnd);

        if ($aS === null || $aE === null || $bS === null || $bE === null) {
            return false;
        }

        return $aS->toMinutes() < $bE->toMinutes() && $bS->toMinutes() < $aE->toMinutes();
    }

    /**
     * Whether `$inner` is fully contained in `$outer`.
     */
    public static function contains(string $outerStart, string $outerEnd, string $innerStart, string $innerEnd): bool
    {
        $oS = self::tryFrom($outerStart);
        $oE = self::tryFrom($outerEnd);
        $iS = self::tryFrom($innerStart);
        $iE = self::tryFrom($innerEnd);

        if ($oS === null || $oE === null || $iS === null || $iE === null) {
            return false;
        }

        return $oS->toMinutes() <= $iS->toMinutes() && $iE->toMinutes() <= $oE->toMinutes();
    }

    /**
     * String value in `H:i` format.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Total minutes since midnight.
     */
    public function toMinutes(): int
    {
        [$h, $m] = \explode(':', $this->value);

        return ((int) $h) * 60 + ((int) $m);
    }

    /**
     * Build a Carbon today-at-this-time instance. Useful for tests.
     */
    public function today(): Carbon
    {
        return Carbon::createFromFormat('H:i', $this->value) ?? Carbon::now()->setTime(0, 0);
    }
}
