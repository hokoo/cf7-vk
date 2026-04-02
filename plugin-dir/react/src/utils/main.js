export function copyWithTooltip(element, textToCopy = null) {
    const text = textToCopy ?? element.innerText;

    const onCopied = () => {
        element.classList.add('copied');
        setTimeout(() => element.classList.remove('copied'), 1500);
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text)
            .then(onCopied)
            .catch((error) => {
                console.error('Clipboard error:', error);
            });
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    try {
        document.execCommand('copy');
        onCopied();
    } catch (error) {
        console.error('Fallback copy failed:', error);
    } finally {
        document.body.removeChild(textarea);
    }
}

export function sprintf(template, ...args) {
    let index = 0;

    return template.replace(/%s/g, () => args[index++] ?? '');
}
