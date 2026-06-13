function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function safeUrl(value: string): string {
    const trimmed = value.trim();

    if (
        trimmed.startsWith('http://') ||
        trimmed.startsWith('https://') ||
        trimmed.startsWith('mailto:')
    ) {
        return escapeHtml(trimmed);
    }

    return '#';
}

function renderInline(value: string): string {
    return escapeHtml(value)
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\*([^*]+)\*/g, '<em>$1</em>')
        .replace(
            /\[([^\]]+)]\(([^)\s]+)\)/g,
            (_match: string, label: string, url: string) =>
                `<a href="${safeUrl(url)}" target="_blank" rel="noopener noreferrer">${label}</a>`,
        );
}

function isTableDivider(line: string): boolean {
    return /^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/.test(line);
}

function tableCells(line: string): string[] {
    return line
        .trim()
        .replace(/^\|/, '')
        .replace(/\|$/, '')
        .split('|')
        .map((cell) => cell.trim());
}

function renderParagraph(lines: string[]): string {
    return `<p>${lines.map(renderInline).join('<br>')}</p>`;
}

function renderList(items: string[], ordered: boolean): string {
    const tag = ordered ? 'ol' : 'ul';
    const rendered = items
        .map((item) => item.replace(ordered ? /^\d+\.\s+/ : /^[-*]\s+/, ''))
        .map((item) => `<li>${renderInline(item)}</li>`)
        .join('');

    return `<${tag}>${rendered}</${tag}>`;
}

function renderTable(lines: string[]): string {
    const headers = tableCells(lines[0] ?? '');
    const body = lines.slice(2).map(tableCells);

    return [
        '<div class="markdown-table-wrap"><table>',
        '<thead><tr>',
        headers.map((cell) => `<th>${renderInline(cell)}</th>`).join(''),
        '</tr></thead>',
        '<tbody>',
        body
            .map(
                (row) =>
                    `<tr>${row.map((cell) => `<td>${renderInline(cell)}</td>`).join('')}</tr>`,
            )
            .join(''),
        '</tbody></table></div>',
    ].join('');
}

export function renderMarkdown(value: string): string {
    const lines = value.replace(/\r\n/g, '\n').split('\n');
    const blocks: string[] = [];
    let paragraph: string[] = [];
    let list: string[] = [];
    let orderedList = false;
    let code: string[] = [];
    let inCode = false;

    const flushParagraph = (): void => {
        if (paragraph.length === 0) return;
        blocks.push(renderParagraph(paragraph));
        paragraph = [];
    };

    const flushList = (): void => {
        if (list.length === 0) return;
        blocks.push(renderList(list, orderedList));
        list = [];
    };

    for (let index = 0; index < lines.length; index += 1) {
        const line = lines[index] ?? '';

        if (line.trim().startsWith('```')) {
            if (inCode) {
                blocks.push(
                    `<pre><code>${escapeHtml(code.join('\n'))}</code></pre>`,
                );
                code = [];
                inCode = false;
            } else {
                flushParagraph();
                flushList();
                inCode = true;
            }

            continue;
        }

        if (inCode) {
            code.push(line);
            continue;
        }

        if (
            line.includes('|') &&
            lines[index + 1] !== undefined &&
            isTableDivider(lines[index + 1] ?? '')
        ) {
            flushParagraph();
            flushList();

            const tableLines = [line, lines[index + 1] ?? ''];
            index += 2;

            while (index < lines.length && (lines[index] ?? '').includes('|')) {
                tableLines.push(lines[index] ?? '');
                index += 1;
            }

            index -= 1;
            blocks.push(renderTable(tableLines));
            continue;
        }

        if (line.trim() === '') {
            flushParagraph();
            flushList();
            continue;
        }

        if (/^[-*]\s+/.test(line)) {
            flushParagraph();
            if (list.length > 0 && orderedList) flushList();
            orderedList = false;
            list.push(line);
            continue;
        }

        if (/^\d+\.\s+/.test(line)) {
            flushParagraph();
            if (list.length > 0 && !orderedList) flushList();
            orderedList = true;
            list.push(line);
            continue;
        }

        flushList();
        paragraph.push(line);
    }

    if (inCode) {
        blocks.push(`<pre><code>${escapeHtml(code.join('\n'))}</code></pre>`);
    }

    flushParagraph();
    flushList();

    return blocks.join('');
}

export function renderPlainText(value: string): string {
    return escapeHtml(value).replace(/\r?\n/g, '<br>');
}
