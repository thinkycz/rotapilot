export interface SseParseResult {
    buffer: string;
    deltas: string[];
    done: boolean;
}

export function parseTextDeltaSseChunk(
    chunk: string,
    previousBuffer = '',
): SseParseResult {
    const combined = previousBuffer + chunk;
    const lines = combined.split(/\r?\n/);
    const buffer =
        combined.endsWith('\n') || combined.endsWith('\r')
            ? ''
            : (lines.pop() ?? '');
    const deltas: string[] = [];
    let done = false;

    for (const line of lines) {
        const trimmed = line.trim();

        if (!trimmed.startsWith('data: ')) {
            continue;
        }

        const data = trimmed.slice(6);

        if (data === '[DONE]') {
            done = true;
            continue;
        }

        try {
            const parsed = JSON.parse(data) as {
                type?: unknown;
                delta?: unknown;
            };

            if (
                parsed.type === 'text_delta' &&
                typeof parsed.delta === 'string'
            ) {
                deltas.push(parsed.delta);
            }
        } catch {
            // Ignore malformed SSE rows and continue parsing later complete rows.
        }
    }

    return { buffer, deltas, done };
}
