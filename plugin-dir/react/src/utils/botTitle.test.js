import {
    formatBotTitle,
    MAX_BOT_TITLE_LENGTH,
    TRUNCATED_BOT_TITLE_LENGTH
} from './botTitle';

describe('formatBotTitle', () => {
    test('keeps titles with length up to 22 symbols unchanged', () => {
        const title = '1234567890123456789012';

        expect(title).toHaveLength(MAX_BOT_TITLE_LENGTH);
        expect(formatBotTitle(title)).toBe(title);
    });

    test('truncates titles longer than 22 symbols to 19 plus ellipsis', () => {
        const title = '12345678901234567890123';

        expect(formatBotTitle(title)).toBe('1234567890123456789...');
        expect(formatBotTitle(title)).toHaveLength(TRUNCATED_BOT_TITLE_LENGTH + 3);
    });

    test('counts grapheme clusters instead of code points when segmenter is available', () => {
        const title = '👨‍👩‍👧‍👦'.repeat(23);
        const expected = `${'👨‍👩‍👧‍👦'.repeat(19)}...`;

        expect(formatBotTitle(title)).toBe(expected);
    });
});
