<?php

declare(strict_types=1);

use App\Support\TimeOfDay;

\test('parses valid H:i strings', function (): void {
    \expect(TimeOfDay::from('09:30')->toString())->toBe('09:30');
    \expect(TimeOfDay::from('00:00')->toMinutes())->toBe(0);
    \expect(TimeOfDay::from('23:59')->toMinutes())->toBe(23 * 60 + 59);
});

\test('rejects invalid H:i strings', function (): void {
    \expect(fn(): TimeOfDay => TimeOfDay::from('25:00'))->toThrow(InvalidArgumentException::class);
    \expect(fn(): TimeOfDay => TimeOfDay::from('not a time'))->toThrow(InvalidArgumentException::class);
    \expect(TimeOfDay::tryFrom('24:00'))->toBeNull();
    \expect(TimeOfDay::tryFrom('9:30'))->toBeNull();
    \expect(TimeOfDay::tryFrom('09:30:00')?->toString())->toBe('09:30');
});

\test('computes duration between two times', function (): void {
    \expect(TimeOfDay::durationHours('09:00', '12:30'))->toBe(3.5);
    \expect(TimeOfDay::durationHours('22:00', '06:00'))->toBe(0.0);
    \expect(TimeOfDay::durationHours('not valid', '12:00'))->toBe(0.0);
});

\test('detects overlap between windows', function (): void {
    \expect(TimeOfDay::overlaps('09:00', '12:00', '11:00', '13:00'))->toBeTrue();
    \expect(TimeOfDay::overlaps('09:00', '11:00', '11:00', '13:00'))->toBeFalse();
    \expect(TimeOfDay::overlaps('09:00', '12:00', '13:00', '14:00'))->toBeFalse();
    \expect(TimeOfDay::overlaps('09:00', '12:00', '12:00', '14:00'))->toBeFalse();
});

\test('detects containment', function (): void {
    \expect(TimeOfDay::contains('08:00', '18:00', '09:00', '12:00'))->toBeTrue();
    \expect(TimeOfDay::contains('08:00', '18:00', '07:00', '09:00'))->toBeFalse();
});
