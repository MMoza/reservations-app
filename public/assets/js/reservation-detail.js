/**
 * Detail page: confirm / cancel actions with visual feedback. The
 * reservation badge, the action buttons and the audit timeline are
 * updated in place — no page reload.
 */

import { api } from './api.js';
import { showError, setButtonLoading, showLoading, showSuccess } from './feedback.js';
import { formatDateTime, statusLabel } from './format.js';
import { buildBadge } from './reservation-list.js';

const EVENT_TYPE_LABELS = {
    CREATED: 'Creación',
    STATUS_CHANGED: 'Cambio de estado',
};

function rebuildActions(container, status) {
    container.textContent = '';
    container.dataset.status = status;

    if (status === 'CANCELLED') return;

    if (status === 'PENDING') {
        container.appendChild(actionButton('CONFIRMED', 'Confirmar reserva', 'btn btn--primary js-change-status'));
    }
    container.appendChild(actionButton('CANCELLED', 'Cancelar reserva', 'btn btn--danger js-change-status'));
}

function actionButton(status, label, className) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.dataset.status = status;
    button.textContent = label;
    return button;
}

function appendTimelineItem(previousStatus, newStatus, changedAt) {
    const timeline = document.getElementById('events-timeline');
    if (!timeline) return;

    const hasPlaceholder = timeline.children.length === 1
        && timeline.children[0].textContent.includes('No hay eventos registrados');
    if (hasPlaceholder) timeline.textContent = '';

    const item = document.createElement('li');
    item.className = 'timeline__item';

    const type = document.createElement('span');
    type.className = 'timeline__type';
    type.textContent = EVENT_TYPE_LABELS.STATUS_CHANGED;

    const description = document.createElement('p');
    description.className = 'timeline__description';
    description.textContent = `Estado cambiado de ${previousStatus} a ${newStatus}`;

    const time = document.createElement('time');
    time.className = 'timeline__date';
    time.textContent = formatDateTime(changedAt);

    item.append(type, description, time);
    timeline.appendChild(item);
}

async function changeStatus(actionsContainer, button) {
    const reservationId = actionsContainer.dataset.reservationId;
    const targetStatus = button.dataset.status;
    const previousStatus = actionsContainer.dataset.status;

    const isCancellation = targetStatus === 'CANCELLED';
    const confirmed = !isCancellation || window.confirm(
        `¿Seguro que quieres cancelar la reserva #${reservationId}?\nEsta acción no se puede deshacer.`,
    );
    if (!confirmed) return;

    setButtonLoading(button, true, isCancellation ? 'Cancelando…' : 'Confirmando…');
    showLoading(isCancellation ? 'Cancelando la reserva…' : 'Confirmando la reserva…');

    try {
        const reservation = await api.changeStatus(reservationId, targetStatus);

        const badge = document.getElementById('reservation-status-badge');
        if (badge) {
            badge.className = `badge badge--${reservation.status.toLowerCase()}`;
            badge.textContent = statusLabel(reservation.status);
        }

        rebuildActions(actionsContainer, reservation.status);
        appendTimelineItem(previousStatus, reservation.status, reservation.updated_at);

        showSuccess(
            isCancellation
                ? `Reserva #${reservationId} cancelada.`
                : `Reserva #${reservationId} confirmada.`,
        );
    } catch (error) {
        showError(error.message);
        setButtonLoading(button, false);
    }
}

export function initDetailActions() {
    const container = document.getElementById('detail-actions');
    if (!container) return;

    container.addEventListener('click', (event) => {
        const button = event.target.closest('.js-change-status');
        if (!button) return;

        void changeStatus(container, button);
    });
}
