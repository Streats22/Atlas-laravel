// Show the tag field only for the tag trigger; presets pick a sensible trigger.
(function () {
    'use strict';
    document.querySelectorAll('.trigger-select').forEach((select) => {
        const form = select.closest('form');
        const tag = form.querySelector('.trigger-tag');
        const date = form.querySelector('.trigger-date');
        const audience = form.querySelector('[name=audience_id]');
        const sync = () => {
            tag.hidden = select.value !== 'tag_added';
            date.hidden = select.value !== 'date';
            if (audience) {
                date.querySelectorAll('option[data-audience]').forEach((o) => { o.hidden = o.dataset.audience !== audience.value; });
            }
        };
        audience?.addEventListener('change', sync);
        select.addEventListener('change', sync);
        sync();
        select.closest('form').querySelectorAll('[name=preset]').forEach((radio) => radio.addEventListener('change', () => {
            select.value = radio.dataset.trigger;
            sync();
        }));
    });
})();
