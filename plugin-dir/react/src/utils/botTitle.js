export const MAX_BOT_TITLE_LENGTH = 22;
export const TRUNCATED_BOT_TITLE_LENGTH = 19;

const splitGraphemes = (value) => {
    if ('undefined' !== typeof Intl && 'function' === typeof Intl.Segmenter) {
        return Array.from(
            new Intl.Segmenter(undefined, {granularity: 'grapheme'}).segment(value),
            ({segment}) => segment
        );
    }

    return Array.from(value);
};

export const formatBotTitle = (title = '') => {
    const normalizedTitle = String(title ?? '');
    const characters = splitGraphemes(normalizedTitle);

    if (characters.length <= MAX_BOT_TITLE_LENGTH) {
        return normalizedTitle;
    }

    return `${characters.slice(0, TRUNCATED_BOT_TITLE_LENGTH).join('')}...`;
};
