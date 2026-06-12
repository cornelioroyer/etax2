import './bootstrap';

import Alpine from 'alpinejs';

import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import 'flatpickr/dist/flatpickr.css';

window.Alpine = Alpine;

Alpine.start();

flatpickr.localize(Spanish);

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-date:not([disabled])').forEach((el) => {
        flatpickr(el, {
            dateFormat: 'Y-m-d',
            locale: { firstDayOfWeek: 1 },
            allowInput: true,
        });
    });
});
