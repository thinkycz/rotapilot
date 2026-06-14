export interface SseParseResult {
    buffer: string;
    deltas: string[];
    done: boolean;
    eventTypes: string[];
    lastEventId: number | null;
    terminalType: string | null;
    error: string | null;
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
    const eventTypes: string[] = [];
    let lastEventId: number | null = null;
    let terminalType: string | null = null;
    let error: string | null = null;
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

            if (typeof parsed.type === 'string') {
                eventTypes.push(parsed.type);

                if (
                    parsed.type === 'run_completed' ||
                    parsed.type === 'run_failed' ||
                    parsed.type === 'run_cancelled'
                ) {
                    terminalType = parsed.type;
                }
            }

            if (typeof (parsed as { id?: unknown }).id === 'number') {
                lastEventId = (parsed as { id: number }).id;
            }

            if (
                parsed.type === 'text_delta' &&
                typeof parsed.delta === 'string'
            ) {
                deltas.push(parsed.delta);
            }

            if (
                parsed.type === 'run_failed' &&
                typeof (parsed as { error?: unknown }).error === 'string'
            ) {
                error = (parsed as { error: string }).error;
            }
        } catch {
            // Ignore malformed SSE rows and continue parsing later complete rows.
        }
    }

    return {
        buffer,
        deltas,
        done,
        eventTypes,
        lastEventId,
        terminalType,
        error,
    };
}
