const ISO_DATE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;

export function parseIsoDate(value: string | null | undefined): Date | null {
    if (!value) return null;

    const match = ISO_DATE_PATTERN.exec(value);
    if (!match) return null;

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const date = new Date(year, month - 1, day);

    if (
        date.getFullYear() !== year ||
        date.getMonth() !== month - 1 ||
        date.getDate() !== day
    ) {
        return null;
    }

    return date;
}

export function formatDate(
    value: string | null | undefined,
    fallback = '—',
): string {
    const date = parseIsoDate(value);
    if (!date) return fallback;

    return `${date.getDate()}.${date.getMonth() + 1}.${date.getFullYear()}`;
}

export function formatDateRange(
    start: string | null | undefined,
    end: string | null | undefined,
    fallback = '—',
): string {
    const formattedStart = formatDate(start, fallback);
    const formattedEnd = formatDate(end, fallback);

    if (formattedStart === fallback || formattedEnd === fallback) {
        return fallback;
    }

    return `${formattedStart} - ${formattedEnd}`;
}
