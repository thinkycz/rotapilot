import { describe, expect, test } from 'vitest';
import { renderMarkdown, renderPlainText } from '@/lib/markdown';

describe('renderMarkdown', () => {
    test('renders bold text and fenced json code blocks', () => {
        const html = renderMarkdown(
            [
                '**Návrh změny**',
                '',
                'Přiřadím zaměstnance **Niki**.',
                '',
                '```json',
                '{',
                '  "actions": [{"type": "shift.assign"}]',
                '}',
                '```',
            ].join('\n'),
        );

        expect(html).toContain('<strong>Návrh změny</strong>');
        expect(html).toContain('<strong>Niki</strong>');
        expect(html).toContain('<pre><code>');
        expect(html).toContain('&quot;actions&quot;');
    });

    test('renders tables and safe links', () => {
        const html = renderMarkdown(
            [
                '| Name | Status |',
                '| --- | --- |',
                '| Niki | **Available** |',
                '',
                '[Open](https://example.com) [Bad](javascript:alert(1))',
            ].join('\n'),
        );

        expect(html).toContain('<table>');
        expect(html).toContain('<strong>Available</strong>');
        expect(html).toContain('href="https://example.com"');
        expect(html).toContain('href="#"');
    });

    test('escapes raw html in markdown and plain text', () => {
        expect(renderMarkdown('<script>alert(1)</script>')).toContain(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
        );
        expect(renderPlainText('<img src=x onerror=alert(1)>')).toContain(
            '&lt;img src=x onerror=alert(1)&gt;',
        );
    });
});
