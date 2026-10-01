/**
 * Creation form: client-side validation first (fast feedback), then the
 * server response. Field errors returned by the API (400) are painted
 * under the matching input.
 */

import { api, ApiError } from './api.js';
import { showError, setButtonLoading, showLoading, showSuccess } from './feedback.js';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const AMOUNT_PATTERN = /^\d{1,8}(\.\d{1,2})?$/;

function field(name) {
    return document.getElementById(name);
}

function setFieldError(name, message = null) {
    const input = field(name);
    const errorElement = document.getElementById(`error-${name}`);
    if (!input || !errorElement) return;

    errorElement.hidden = !message;
    errorElement.textContent = message ?? '';
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
}

function clearErrors(form) {
    for (const input of form.querySelectorAll('input, textarea')) {
        setFieldError(input.name, null);
    }
}

function todayIso() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${now.getFullYear()}-${month}-${day}`;
}

/**
 * @returns {object} values keyed like the API expects
 *                   (empty object when something is invalid)
 */
function readAndValidate(form) {
    const values = {
        guest_name: field('guest_name').value.trim(),
        guest_email: field('guest_email').value.trim(),
        accommodation_name: field('accommodation_name').value.trim(),
        check_in_date: field('check_in_date').value,
        check_out_date: field('check_out_date').value,
        amount: field('amount').value.trim(),
        notes: field('notes').value.trim(),
    };

    let valid = true;
    const fail = (name, message) => {
        setFieldError(name, message);
        valid = false;
    };

    if (values.guest_name.length < 2) {
        fail('guest_name', 'El nombre del huésped es obligatorio (mínimo 2 caracteres).');
    }
    if (!EMAIL_PATTERN.test(values.guest_email)) {
        fail('guest_email', 'El email no tiene un formato válido.');
    }
    if (values.accommodation_name.length < 2) {
        fail('accommodation_name', 'El nombre del alojamiento es obligatorio (mínimo 2 caracteres).');
    }
    if (!values.check_in_date) {
        fail('check_in_date', 'La fecha de entrada es obligatoria.');
    } else if (values.check_in_date < todayIso()) {
        fail('check_in_date', 'La fecha de entrada no puede ser anterior a hoy.');
    }
    if (!values.check_out_date) {
        fail('check_out_date', 'La fecha de salida es obligatoria.');
    } else if (values.check_in_date && values.check_out_date <= values.check_in_date) {
        fail('check_out_date', 'La fecha de salida debe ser posterior a la de entrada.');
    }
    if (!AMOUNT_PATTERN.test(values.amount)) {
        fail('amount', 'El importe debe ser un número positivo con como máximo 2 decimales.');
    }
    if (values.notes.length > 1000) {
        fail('notes', 'Las notas no pueden superar 1000 caracteres.');
    }

    return valid ? values : {};
}

function paintServerErrors(fields) {
    for (const [name, messages] of Object.entries(fields)) {
        setFieldError(name, Array.isArray(messages) ? messages[0] : messages);
    }

    const firstInvalid = document.querySelector('[aria-invalid="true"]');
    firstInvalid?.focus();
}

export function initReservationForm() {
    const form = document.getElementById('reservation-form');
    if (!form) return;

    const submitButton = document.getElementById('submit-reservation');

    // Live feedback: clear a field error as soon as the user edits it.
    form.addEventListener('input', (event) => {
        if (event.target.name) setFieldError(event.target.name, null);
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        clearErrors(form);

        const values = readAndValidate(form);
        if (Object.keys(values).length === 0) {
            showError('Revisa los campos marcados antes de enviar.');
            return;
        }

        setButtonLoading(submitButton, true, 'Creando…');
        showLoading('Creando la reserva…');

        api
            .createReservation(values)
            .then((reservation) => {
                form.reset();
                showSuccess(`Reserva #${reservation.id} creada correctamente.`);
                document.dispatchEvent(new CustomEvent('reservations:changed'));
            })
            .catch((error) => {
                if (error instanceof ApiError && error.fields) {
                    paintServerErrors(error.fields);
                    showError(error.message);
                } else {
                    showError(error.message);
                }
            })
            .finally(() => {
                setButtonLoading(submitButton, false);
            });
    });
}
