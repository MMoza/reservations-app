/**
 * Thin client for the JSON API.
 *
 * - Sends the anti-CSRF headers required by the server
 *   (X-Requested-With + application/json on mutations).
 * - Normalises every failure (HTTP error, network error, timeout)
 *   into a single ApiError carrying status, code, message and
 *   per-field validation errors.
 */

const API_BASE = '/api.php';
const TIMEOUT_MS = 10000;

export class ApiError extends Error {
    constructor(status, code, message, fields = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.code = code;
        this.fields = fields;
    }
}

async function request(path, { method = 'GET', body = null, signal = null } = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (body !== null) {
        headers['Content-Type'] = 'application/json';
    }

    const timeoutController = new AbortController();
    const timeoutId = setTimeout(() => timeoutController.abort(), TIMEOUT_MS);
    if (signal) {
        if (signal.aborted) {
            timeoutController.abort();
        } else {
            signal.addEventListener('abort', () => timeoutController.abort(), { once: true });
        }
    }

    let response;
    try {
        response = await fetch(API_BASE + path, {
            method,
            headers,
            body: body !== null ? JSON.stringify(body) : undefined,
            signal: timeoutController.signal,
        });
    } catch (error) {
        if (error.name === 'AbortError') {
            throw new ApiError(0, 'TIMEOUT', 'La petición ha tardado demasiado. Inténtalo de nuevo.');
        }
        throw new ApiError(0, 'NETWORK_ERROR', 'No se ha podido conectar con el servidor. Comprueba tu conexión.');
    } finally {
        clearTimeout(timeoutId);
    }

    const contentType = response.headers.get('content-type') || '';
    let payload = null;
    if (contentType.includes('application/json')) {
        try {
            payload = await response.json();
        } catch {
            payload = null;
        }
    }

    if (!response.ok) {
        const errorData = payload && payload.error ? payload.error : {};
        throw new ApiError(
            response.status,
            errorData.code || 'HTTP_ERROR',
            errorData.message || `El servidor ha devuelto un error (${response.status}).`,
            errorData.fields || null,
        );
    }

    return payload;
}

export const api = {
    /**
     * @param {URLSearchParams} params filters, page and limit
     * @returns {Promise<{items: Array, meta: object}>}
     */
    async listReservations(params) {
        const payload = await request(`/reservations?${params.toString()}`);
        return { items: payload.data ?? [], meta: payload.meta ?? {} };
    },

    /** @returns {Promise<object>} reservation with its events */
    async getReservation(id) {
        const payload = await request(`/reservations/${id}`);
        return payload.data;
    },

    /** @returns {Promise<object>} the created reservation */
    async createReservation(body) {
        const payload = await request('/reservations', { method: 'POST', body });
        return payload.data;
    },

    /** @returns {Promise<object>} the updated reservation */
    async changeStatus(id, status) {
        const payload = await request(`/reservations/${id}/status`, {
            method: 'PATCH',
            body: { status },
        });
        return payload.data;
    },
};
