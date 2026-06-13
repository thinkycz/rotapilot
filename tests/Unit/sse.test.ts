import { describe, expect, test } from 'vitest';
import { parseTextDeltaSseChunk } from '@/lib/sse';

describe('parseTextDeltaSseChunk', () => {
    test('handles chunk-split data rows', () => {
        const first = parseTextDeltaSseChunk('data: {"type":"text_delta","del');
        expect(first.deltas).toEqual([]);
        expect(first.done).toBe(false);

        const second = parseTextDeltaSseChunk('ta":"Hello"}\n\n', first.buffer);
        expect(second.deltas).toEqual(['Hello']);
        expect(second.buffer).toBe('');
    });

    test('ignores malformed rows and keeps later valid deltas', () => {
        const parsed = parseTextDeltaSseChunk(
            'data: {nope}\n\ndata: {"type":"text_delta","delta":" world"}\n\n',
        );

        expect(parsed.deltas).toEqual([' world']);
        expect(parsed.done).toBe(false);
    });

    test('detects done sentinel', () => {
        const parsed = parseTextDeltaSseChunk('data: [DONE]\n\n');

        expect(parsed.done).toBe(true);
    });
});
