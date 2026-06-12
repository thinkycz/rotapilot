import { describe, expect, test } from 'vitest';
import { formatDate, formatDateRange } from '@/lib/date';

describe('date formatting', () => {
    test('formats ISO dates as j.n.Y', () => {
        expect(formatDate('2026-06-01')).toBe('1.6.2026');
        expect(formatDate('2028-02-29')).toBe('29.2.2028');
    });

    test('returns fallback for invalid or empty values', () => {
        expect(formatDate('')).toBe('—');
        expect(formatDate('not-a-date')).toBe('—');
        expect(formatDate('2026-02-31')).toBe('—');
    });

    test('formats date ranges as j.n.Y - j.n.Y', () => {
        expect(formatDateRange('2026-06-01', '2026-06-30')).toBe(
            '1.6.2026 - 30.6.2026',
        );
    });
});
