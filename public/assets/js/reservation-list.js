/**
 * Reservation table: renders rows client-side (mirroring the Twig row
 * partial) and handles the "cancel" action with confirmation.
 *
 * All user data is written with textContent — never innerHTML — so
 * names/emails coming from the API cannot inject markup (XSS).
 */

import { api } from './api.js';
import { showError, showLoading, showSuccess, setButtonLoading } from './feedback.js';
import { formatAmount, formatDate, statusLabel } from './format.js';

export function renderRows(reservations) {
    const tbody = document.getElementById('reservation-rows');
    if (!tbody) return;

    tbody.textContent = '';

    if (reservations.length === 0) {
        tbody.appendChild(buildEmptyRow());
        return;
    }

    const fragment = document.createDocumentFragment();
    for (const reservation of reservations) {
        fragment.appendChild(buildRow(reservation));
    }
    tbody.appendChild(fragment);
}

export function buildRow(reservation) {
    const row = document.createElement('tr');
    row.dataset.reservationId = String(reservation.id);
    row.dataset.status = reservation.status;

    row.appendChild(cell(String(reservation.id)));

    const guestCell = document.createElement('td');
    const guestName = document.createElement('strong');
    guestName.textContent = reservation.guest_name;
    const emailLink = document.createElement('a');
    emailLink.className = 'muted small';
    emailLink.href = `mailto:${reservation.guest_email}`;
    emailLink.textContent = reservation.guest_email;
    guestCell.append(guestName, document.createElement('br'), emailLink);
    row.appendChild(guestCell);

    row.appendChild(cell(reservation.accommodation_name));
    row.appendChild(cell(formatDate(reservation.check_in_date)));
    row.appendChild(cell(formatDate(reservation.check_out_date)));

    const statusCell = document.createElement('td');
    statusCell.appendChild(buildBadge(reservation.status));
    row.appendChild(statusCell);

    const amountCell = cell(formatAmount(reservation.amount));
    amountCell.classList.add('num');
    row.appendChild(amountCell);

    row.appendChild(buildActionsCell(reservation));

    return row;
}

function buildActionsCell(reservation) {
    const actionsCell = document.createElement('td');
    actionsCell.className = 'actions-cell';

    const detailLink = document.createElement('a');
    detailLink.className = 'btn btn--small';
    detailLink.href = `/reservations/${reservation.id}`;
    detailLink.textContent = 'Ver detalle';
    actionsCell.appendChild(detailLink);

    if (reservation.status !== 'CANCELLED') {
        const cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'btn btn--small btn--danger js-cancel-reservation';
        cancelButton.dataset.reservationId = String(reservation.id);
        cancelButton.textContent = 'Cancelar';
        actionsCell.appendChild(cancelButton);
    }

    return actionsCell;
}

export function buildBadge(status) {
    const badge = document.createElement('span');
    badge.className = `badge badge--${String(status).toLowerCase()}`;
    badge.textContent = statusLabel(status);
    return badge;
}

function cell(text) {
    const element = document.createElement('td');
    element.textContent = text;
    return element;
}

function buildEmptyRow() {
    const row = document.createElement('tr');
    row.className = 'empty-row';
    const messageCell = cell('No hay reservas que coincidan con los filtros.');
    messageCell.colSpan = 8;
    row.appendChild(messageCell);
    return row;
}

function updateRowAfterCancel(row, status) {
    row.dataset.status = status;

    const badge = row.querySelector('.badge');
    if (badge) {
        badge.className = `badge badge--${String(status).toLowerCase()}`;
        badge.textContent = statusLabel(status);
    }

    const cancelButton = row.querySelector('.js-cancel-reservation');
    cancelButton?.remove();
}

function confirmCancellation(reservationId) {
    const confirmed = window.confirm(
        `¿Seguro que quieres cancelar la reserva #${reservationId}?\nEsta acción no se puede deshacer.`,
    );
    if (!confirmed) return;

    const row = document.querySelector(`tr[data-reservation-id="${reservationId}"]`);
    const button = row?.querySelector('.js-cancel-reservation') ?? null;
    setButtonLoading(button, true, 'Cancelando…');
    showLoading('Cancelando la reserva…');

    api
        .changeStatus(reservationId, 'CANCELLED')
        .then((reservation) => {
            if (row) {
                updateRowAfterCancel(row, reservation.status);
            }
            showSuccess(`Reserva #${reservationId} cancelada.`);
        })
        .catch((error) => {
            setButtonLoading(button, false);
            showError(error.message);
        });
}

export function initCancelButtons() {
    const tbody = document.getElementById('reservation-rows');
    if (!tbody) return;

    tbody.addEventListener('click', (event) => {
        const button = event.target.closest('.js-cancel-reservation');
        if (!button) return;

        confirmCancellation(button.dataset.reservationId);
    });
}
