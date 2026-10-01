/**
 * Frontend entry point. Every module initialises itself only when the
 * element it drives exists on the current page (list vs detail).
 */

import { initFilters } from './filters.js';
import { initReservationForm } from './reservation-form.js';
import { initCancelButtons } from './reservation-list.js';
import { initDetailActions } from './reservation-detail.js';

initFilters();
initCancelButtons();
initReservationForm();
initDetailActions();
