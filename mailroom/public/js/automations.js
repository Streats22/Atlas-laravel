// Show the tag field only for the tag trigger; presets pick a sensible trigger.
(function () {
    'use strict';
    document.querySelectorAll('.trigger-select').forEach((select) => {
        const tag = select.closest('form').querySelector('.trigger-tag');
        const sync = () => { tag.hidden = select.value !== 'tag_added'; };
        select.addEventListener('change', sync);
        sync();
        select.closest('form').querySelectorAll('[name=preset]').forEach((radio) => radio.addEventListener('change', () => {
            select.value = radio.dataset.trigger;
            sync();
        }));
    });
})();
