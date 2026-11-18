// Campaign editor: live preview, merge-tag insertion, device toggle and audience-scoped selects.
(function () {
    'use strict';
    const html = document.getElementById('html');
    const frame = document.getElementById('preview');
    if (!html || !frame) return;

    const sample = { first_name: 'Jane', last_name: 'Doe', full_name: 'Jane Doe', email: 'jane@example.com', unsubscribe_url: '#', audience_name: 'Your audience' };
    const render = () => {
        frame.srcdoc = html.value.replace(/\{\{\s*([a-z_]+)(?:\s*\|\s*([^}]*?))?\s*\}\}/g, (m, key, fallback) =>
            key in sample ? (sample[key] || fallback || '') : m);
    };
    let timer;
    html.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(render, 250); });
    render();

    document.querySelectorAll('[data-tag]').forEach((chip) => chip.addEventListener('click', () => {
        const token = '{{ ' + chip.dataset.tag + ' }}';
        const target = document.activeElement === document.getElementById('subject') ? document.getElementById('subject') : html;
        const start = target.selectionStart ?? target.value.length;
        target.setRangeText(token, start, target.selectionEnd ?? start, 'end');
        target.focus();
        render();
    }));
    // Keep the subject field focused-state when clicking a chip.
    document.querySelectorAll('[data-tag]').forEach((chip) => chip.addEventListener('mousedown', (e) => e.preventDefault()));

    document.querySelectorAll('.device-toggle button').forEach((btn) => btn.addEventListener('click', () => {
        document.querySelectorAll('.device-toggle button').forEach((b) => b.classList.toggle('active', b === btn));
        frame.style.maxWidth = btn.dataset.width;
    }));

    const audience = document.getElementById('audience');
    const scope = () => {
        ['segment', 'tag'].forEach((id) => {
            const select = document.getElementById(id);
            select.querySelectorAll('option[data-audience]').forEach((opt) => {
                const visible = opt.dataset.audience === audience.value;
                opt.hidden = !visible;
                opt.disabled = !visible;
                if (!visible && opt.selected) select.value = '';
            });
        });
    };
    audience.addEventListener('change', scope);
    scope();
})();
