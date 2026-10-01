/**
 * Listing filters: reads the filter form, calls the API without
 * reloading the page and repaints the table plus the pagination.
 */

import { api } from './api.js';
import { hideFeedback, showError, showLoading } from './feedback.js';
import { renderRows } from './reservation-list.js';

const DEBOUNCE_MS = 300;

const state = {
    page: 1,
    totalPages: 1,
};

let debounceTimer = null;

function readFilters() {
    return {
        status: document.getElementById('filter-status')?.value ?? '',
        from: document.getElementById('filter-from')?.value ?? '',
        to: document.getElementById('filter-to')?.value ?? '',
        guest: document.getElementById('filter-guest')?.value.trim() ?? '',
    };
}

function buildParams({ status, from, to, guest }, page) {
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (from) params.set('from', from);
    if (to) params.set('to', to);
    if (guest) params.set('guest', guest);
    if (page > 1) params.set('page', String(page));
    return params;
}

function syncUrl(params) {
    const query = params.toString();
    window.history.replaceState(null, '', query ? `/?${query}` : '/');
}

function updateMeta(meta) {
    state.page = meta.page ?? 1;
    state.totalPages = meta.total_pages ?? 1;

    const totalCount = document.getElementById('total-count');
    if (totalCount) totalCount.textContent = String(meta.total ?? 0);

    const pageInfo = document.getElementById('page-info');
    if (pageInfo) {
        pageInfo.textContent = `Página ${state.page} de ${Math.max(state.totalPages, 1)}`;
    }

    const prevButton = document.getElementById('page-prev');
    const nextButton = document.getElementById('page-next');
    if (prevButton) prevButton.disabled = state.page <= 1;
    if (nextButton) nextButton.disabled = state.page >= state.totalPages;

    const pagination = document.getElementById('pagination');
    if (pagination) pagination.hidden = state.totalPages <= 1;
}

/**
 * Re-fetches the list with the current form filters and repaints it.
 * @param {number} page 1-based page to display
 */
export async function refresh(page = 1) {
    const filters = readFilters();
    const params = buildParams(filters, page);

    showLoading('Cargando reservas…');
    syncUrl(params);

    try {
        const { items, meta } = await api.listReservations(params);
        renderRows(items);
        updateMeta(meta);
        hideFeedback();
    } catch (error) {
        renderRows([]);
        updateMeta({ page: 1, total: 0, total_pages: 0 });
        showError(error.message);
    }
}

function debounceRefresh() {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(() => {
        void refresh(1);
    }, DEBOUNCE_MS);
}

export function initFilters() {
    const form = document.getElementById('filters-form');
    if (!form) return;

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        window.clearTimeout(debounceTimer);
        void refresh(1);
    });

    form.addEventListener('input', debounceRefresh);
    form.addEventListener('change', debounceRefresh);

    document.getElementById('filters-reset')?.addEventListener('click', () => {
        form.reset();
        window.clearTimeout(debounceTimer);
        void refresh(1);
    });

    document.getElementById('page-prev')?.addEventListener('click', () => {
        if (state.page > 1) void refresh(state.page - 1);
    });

    document.getElementById('page-next')?.addEventListener('click', () => {
        if (state.page < state.totalPages) void refresh(state.page + 1);
    });

    // After a reservation is created from the form below, repaint the table.
    document.addEventListener('reservations:changed', () => {
        void refresh(1);
    });
}
