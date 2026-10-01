/**
 * Visual feedback: loading / success / error messages announced to
 * screen readers through the #feedback live region, plus button
 * loading states. No external libraries.
 */

const TYPE_CLASSES = {
    loading: 'feedback--loading',
    success: 'feedback--success',
    error: 'feedback--error',
};

const TYPE_MESSAGES = {
    loading: 'Cargando…',
    success: 'Operación completada.',
    error: 'Ha ocurrido un error.',
};

function container() {
    return document.getElementById('feedback');
}

export function showFeedback(type, message = null) {
    const element = container();
    if (!element) return;

    element.hidden = false;
    element.className = `feedback ${TYPE_CLASSES[type] ?? TYPE_CLASSES.error}`;
    element.textContent = message ?? TYPE_MESSAGES[type];
}

export function hideFeedback() {
    const element = container();
    if (!element) return;

    element.hidden = true;
    element.textContent = '';
}

export function showSuccess(message) {
    showFeedback('success', message);
}

export function showError(message) {
    showFeedback('error', message ?? TYPE_MESSAGES.error);
}

export function showLoading(message = null) {
    showFeedback('loading', message);
}

export function setButtonLoading(button, isLoading, loadingText = 'Procesando…') {
    if (!button) return;

    if (isLoading) {
        button.dataset.originalText = button.textContent;
        button.textContent = loadingText;
        button.classList.add('is-loading');
        button.disabled = true;
    } else {
        button.textContent = button.dataset.originalText ?? button.textContent;
        button.classList.remove('is-loading');
        button.disabled = false;
    }
}
