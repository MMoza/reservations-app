/**
 * Formatting helpers shared by the list and the detail page.
 * Dates arrive as "YYYY-MM-DD" and amounts as decimal strings "480.00".
 */

const STATUS_LABELS = {
    PENDING: 'Pendiente',
    CONFIRMED: 'Confirmada',
    CANCELLED: 'Cancelada',
};

export function formatDate(isoDate) {
    if (!isoDate) return '—';
    const [year, month, day] = isoDate.split('-');
    return `${day}/${month}/${year}`;
}

export function formatDateTime(dateTime) {
    if (!dateTime) return '—';
    const [date, time = '00:00'] = dateTime.split(' ');
    return `${formatDate(date)} ${time.slice(0, 5)}`;
}

export function formatAmount(amount) {
    const value = Number(amount);
    if (Number.isNaN(value)) return '—';
    return `${value.toLocaleString('es-ES', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} €`;
}

export function statusLabel(status) {
    return STATUS_LABELS[status] ?? status;
}
