// Campaign editor: code-mode live preview, merge-tag insertion, device toggles, editor-mode switching
// and audience-scoped segment/tag selects.
(function () {
    'use strict';
    const form = document.getElementById('campaign-form');
    if (!form) return;

    // Remember the last focused text field so merge-tag chips insert where the user was typing.
    let lastField = null;
    document.addEventListener('focusin', (e) => {
        const el = e.target;
        if (el.matches('input[type=text], input:not([type]), textarea, [contenteditable=true]')) lastField = el;
    });
    document.querySelectorAll('[data-tag]').forEach((chip) => {
        chip.addEventListener('mousedown', (e) => e.preventDefault());
        chip.addEventListener('click', () => {
            const token = '{{ ' + chip.dataset.tag + ' }}';
            const target = lastField || document.getElementById('html') || document.getElementById('subject');
            if (!target) return;
            target.focus();
            if (target.isContentEditable) {
                document.execCommand('insertText', false, token);
            } else {
                const start = target.selectionStart ?? target.value.length;
                target.setRangeText(token, start, target.selectionEnd ?? start, 'end');
            }
            target.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    document.querySelectorAll('.device-toggle').forEach((group) => {
        const frame = group.parentElement.querySelector('iframe');
        group.querySelectorAll('button').forEach((btn) => btn.addEventListener('click', () => {
            group.querySelectorAll('button').forEach((b) => b.classList.toggle('active', b === btn));
            frame.style.maxWidth = btn.dataset.width;
        }));
    });

    // Code mode: client-side preview with sample merge values.
    const html = document.getElementById('html');
    const frame = document.getElementById('preview');
    if (html && frame) {
        const sample = { first_name: 'Jane', last_name: 'Doe', full_name: 'Jane Doe', email: 'jane@example.com', unsubscribe_url: '#', audience_name: 'Your audience' };
        const render = () => {
            frame.srcdoc = html.value.replace(/\{\{\s*([a-z_]+)(?:\s*\|\s*([^}]*?))?\s*\}\}/g, (m, key, fallback) =>
                key in sample ? (sample[key] || fallback || '') : m);
        };
        let timer;
        html.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(render, 250); });
        render();
    }

    const hidden = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = name; input.value = value;
        form.appendChild(input);
    };

    document.getElementById('to-builder')?.addEventListener('click', (e) => {
        if (!confirm('Start with the drag & drop builder? Your current HTML will be replaced.')) return;
        html.removeAttribute('name');
        html.removeAttribute('required');
        hidden('design', e.currentTarget.dataset.starter);
        form.requestSubmit();
    });

    document.getElementById('to-code')?.addEventListener('click', async () => {
        if (!confirm('Switch to the code editor? You can edit the HTML freely, but you can\'t go back to the visual builder without starting over.')) return;
        const design = document.getElementById('design');
        const body = new FormData();
        body.append('design', design.value);
        body.append('editor', '0');
        const res = await fetch(document.getElementById('builder').dataset.previewUrl, {
            method: 'POST', body, headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        });
        design.remove();
        hidden('html', await res.text());
        form.requestSubmit();
    });

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
