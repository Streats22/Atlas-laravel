// Drag & drop email builder. State lives in the hidden #design input as JSON; the server
// normalises and renders it (POST builder/preview), so the preview is exactly what gets sent.
(function () {
    'use strict';
    const root = document.getElementById('builder');
    if (!root) return;

    const input = document.getElementById('design');
    const frame = document.getElementById('builder-preview');
    const outline = document.getElementById('outline');
    const inspector = document.getElementById('inspector');
    const LABELS = JSON.parse(root.dataset.blocks);
    const DEFAULTS = JSON.parse(root.dataset.defaults);
    const SOCIAL = JSON.parse(root.dataset.social);
    const csrf = document.querySelector('meta[name=csrf-token]').content;

    let state = parseState(input.value);
    let selected = null;

    function parseState(json) {
        try {
            const s = JSON.parse(json || '{}');
            return { settings: s.settings || {}, blocks: Array.isArray(s.blocks) ? s.blocks : [] };
        } catch (e) {
            return { settings: {}, blocks: [] };
        }
    }

    const uid = () => 'b' + Math.random().toString(36).slice(2, 10);
    const clone = (o) => JSON.parse(JSON.stringify(o));
    const find = (id) => state.blocks.find((b) => b.id === id);
    const indexOf = (id) => state.blocks.findIndex((b) => b.id === id);

    function el(tag, attrs, ...children) {
        const node = document.createElement(tag);
        Object.entries(attrs || {}).forEach(([k, v]) => {
            if (k === 'class') node.className = v;
            else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
            else if (v !== false && v !== null && v !== undefined) node.setAttribute(k, v === true ? '' : v);
        });
        children.flat().forEach((c) => c !== null && c !== undefined && node.append(c));
        return node;
    }

    // ---- State changes -------------------------------------------------------------------------

    let previewTimer;
    function changed(structural) {
        input.value = JSON.stringify(state);
        renderOutline();
        if (structural) renderInspector();
        clearTimeout(previewTimer);
        previewTimer = setTimeout(renderPreview, structural ? 0 : 300);
    }

    function addBlock(type, at) {
        const block = { id: uid(), type, props: clone(DEFAULTS[type] || {}) };
        const index = at ?? (selected ? indexOf(selected) + 1 : state.blocks.length);
        state.blocks.splice(index, 0, block);
        selected = block.id;
        changed(true);
    }

    function moveBlock(id, to) {
        const from = indexOf(id);
        if (from < 0) return;
        const [block] = state.blocks.splice(from, 1);
        state.blocks.splice(to > from ? to - 1 : to, 0, block);
        changed(true);
    }

    function select(id) {
        selected = id;
        renderOutline();
        renderInspector();
        highlight();
    }

    // ---- Preview -------------------------------------------------------------------------------

    async function renderPreview() {
        const body = new FormData();
        body.append('design', input.value);
        const res = await fetch(root.dataset.previewUrl, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf } });
        const scroll = frame.contentWindow?.scrollY || 0;
        frame.srcdoc = await res.text();
        frame.onload = () => {
            frame.contentWindow.scrollTo(0, scroll);
            wireCanvas();
            highlight();
        };
    }

    function highlight() {
        const doc = frame.contentDocument;
        if (!doc) return;
        doc.querySelectorAll('[data-block]').forEach((td) => td.classList.toggle('mr-selected', td.dataset.block === selected));
    }

    // The canvas iframe runs no scripts (sandbox without allow-scripts); we attach listeners from here.
    function wireCanvas() {
        const doc = frame.contentDocument;
        if (!doc) return;
        doc.addEventListener('click', (e) => {
            e.preventDefault();
            const td = e.target.closest('[data-block]');
            select(td ? td.dataset.block : null);
        });
        let marker = null;
        const clearMarker = () => { marker?.remove(); marker = null; };
        doc.addEventListener('dragover', (e) => {
            e.preventDefault();
            const { index, row } = dropTarget(doc, e.clientY);
            clearMarker();
            marker = doc.createElement('div');
            marker.style.cssText = 'height:4px;background:#4f46e5;margin:-2px 0;position:relative;z-index:10';
            if (row) row.before(marker); else doc.querySelector('[data-block]')?.closest('table')?.append(marker);
            marker.dataset.index = index;
        });
        doc.addEventListener('dragleave', (e) => { if (!e.relatedTarget) clearMarker(); });
        doc.addEventListener('drop', (e) => {
            e.preventDefault();
            const { index } = dropTarget(doc, e.clientY);
            clearMarker();
            handleDrop(e.dataTransfer, index);
        });
    }

    function dropTarget(doc, y) {
        const cells = [...doc.querySelectorAll('[data-block]')];
        for (let i = 0; i < cells.length; i++) {
            const r = cells[i].getBoundingClientRect();
            if (y < r.top + r.height / 2) return { index: i, row: cells[i].parentElement };
        }
        return { index: cells.length, row: null };
    }

    function handleDrop(dt, index) {
        const add = dt.getData('text/x-mr-add');
        const move = dt.getData('text/x-mr-move');
        if (add && LABELS[add]) addBlock(add, index);
        else if (move) moveBlock(move, index);
    }

    // ---- Palette & outline ---------------------------------------------------------------------

    root.querySelectorAll('[data-add]').forEach((btn) => {
        btn.addEventListener('click', () => addBlock(btn.dataset.add));
        btn.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/x-mr-add', btn.dataset.add);
            e.dataTransfer.effectAllowed = 'copy';
        });
    });

    function summary(block) {
        const p = block.props || {};
        const text = p.text || p.label || p.alt || (p.html || p.left || '').replace(/<[^>]*>/g, ' ');
        return (text || '').trim().slice(0, 40);
    }

    function renderOutline() {
        outline.replaceChildren(...state.blocks.map((block, i) => {
            const li = el('li', {
                class: 'outline-item' + (block.id === selected ? ' selected' : ''),
                draggable: 'true',
                tabindex: '0',
                onclick: () => select(block.id),
                onkeydown: (e) => {
                    if (e.key === 'Enter') select(block.id);
                    if (e.altKey && e.key === 'ArrowUp' && i > 0) { e.preventDefault(); moveBlock(block.id, i - 1); }
                    if (e.altKey && e.key === 'ArrowDown') { e.preventDefault(); moveBlock(block.id, i + 2); }
                },
                ondragstart: (e) => { e.dataTransfer.setData('text/x-mr-move', block.id); e.dataTransfer.effectAllowed = 'move'; },
                ondragover: (e) => { e.preventDefault(); li.classList.add('drop-before'); },
                ondragleave: () => li.classList.remove('drop-before'),
                ondrop: (e) => { e.preventDefault(); li.classList.remove('drop-before'); handleDrop(e.dataTransfer, i); },
            }, el('span', { class: 'grip', 'aria-hidden': 'true' }, '⋮⋮'), el('strong', {}, LABELS[block.type]), el('span', { class: 'muted' }, summary(block)));
            return li;
        }));
        outline.ondragover = (e) => e.preventDefault();
        outline.ondrop = (e) => { if (e.target === outline) { e.preventDefault(); handleDrop(e.dataTransfer, state.blocks.length); } };
        if (!state.blocks.length) outline.append(el('li', { class: 'muted small' }, 'Drag blocks here or click one above.'));
    }

    // ---- Inspector -----------------------------------------------------------------------------

    function field(label, control) {
        return el('label', {}, label, control);
    }

    function text(obj, key, label, opts = {}) {
        const inputEl = el('input', { type: opts.type || 'text', value: obj[key] ?? '', placeholder: opts.placeholder || '' });
        inputEl.addEventListener('input', () => { obj[key] = inputEl.value; changed(false); });
        return field(label, inputEl);
    }

    function select_(obj, key, label, options) {
        const s = el('select', {}, Object.entries(options).map(([v, l]) => el('option', { value: v, selected: String(obj[key]) === String(v) }, l)));
        s.addEventListener('change', () => { obj[key] = isNaN(+s.value) || s.value === '' ? s.value : +s.value; changed(false); });
        return field(label, s);
    }

    function range(obj, key, label, min, max, unit) {
        const out = el('output', {}, (obj[key] ?? min) + unit);
        const r = el('input', { type: 'range', min, max, value: obj[key] ?? min });
        r.addEventListener('input', () => { obj[key] = +r.value; out.textContent = r.value + unit; changed(false); });
        return field(el('span', { class: 'row-between' }, label, out), r);
    }

    function color(obj, key, label, fallback) {
        const c = el('input', { type: 'color', value: obj[key] || fallback });
        c.addEventListener('input', () => { obj[key] = c.value; changed(false); });
        const reset = el('button', { type: 'button', class: 'link small', onclick: () => { obj[key] = ''; c.value = fallback; changed(false); } }, 'reset');
        return field(el('span', { class: 'row-between' }, label, reset), c);
    }

    function check(obj, key, label) {
        const c = el('input', { type: 'checkbox', checked: !!obj[key] });
        c.addEventListener('change', () => { obj[key] = c.checked; changed(false); });
        return el('label', { class: 'check' }, c, label);
    }

    function align(obj) {
        return select_(obj, 'align', 'Alignment', { left: 'Left', center: 'Center', right: 'Right' });
    }

    function rich(obj, key, label) {
        const area = el('div', { class: 'rich', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', 'aria-label': label });
        area.innerHTML = obj[key] || '';
        area.addEventListener('input', () => { obj[key] = area.innerHTML; changed(false); });
        const cmd = (name, value) => () => { area.focus(); document.execCommand(name, false, value); obj[key] = area.innerHTML; changed(false); };
        const tool = (txt, title, fn) => el('button', { type: 'button', title, 'aria-label': title, onmousedown: (e) => e.preventDefault(), onclick: fn }, txt);
        const bar = el('div', { class: 'rich-toolbar' },
            tool('B', 'Bold', cmd('bold')), tool('I', 'Italic', cmd('italic')), tool('U', 'Underline', cmd('underline')),
            tool('• List', 'Bulleted list', cmd('insertUnorderedList')),
            tool('Link', 'Insert link', () => { const url = prompt('Link URL (https://…)'); if (url) cmd('createLink', url)(); }),
            tool('Clear', 'Remove formatting', cmd('removeFormat')));
        return el('div', { class: 'rich-field' }, el('span', { class: 'label' }, label), bar, area);
    }

    function image(obj) {
        const preview = el('img', { class: 'thumb', src: obj.src || '', alt: '', hidden: !obj.src });
        const url = el('input', { type: 'url', value: obj.src || '', placeholder: 'https://…' });
        const set = (src) => { obj.src = src; url.value = src; preview.src = src; preview.hidden = !src; changed(false); };
        url.addEventListener('input', () => set(url.value));
        return el('div', { class: 'stack-sm' }, preview, field('Image URL', url),
            el('button', { type: 'button', class: 'btn', onclick: () => openMedia(set) }, 'Choose or upload image'));
    }

    function renderInspector() {
        const block = selected && find(selected);
        if (!block) {
            const s = state.settings;
            inspector.replaceChildren(el('h3', {}, 'Email design'),
                color(s, 'background', 'Page background', '#f4f4f5'),
                color(s, 'content_background', 'Content background', '#ffffff'),
                color(s, 'text_color', 'Text colour', '#1f2937'),
                color(s, 'accent', 'Accent / link colour', '#4f46e5'),
                select_(s, 'font', 'Font', {
                    'Helvetica, Arial, sans-serif': 'Helvetica', 'Georgia, serif': 'Georgia', '"Trebuchet MS", sans-serif': 'Trebuchet',
                    'Verdana, sans-serif': 'Verdana', '"Courier New", monospace': 'Courier',
                }),
                range(s, 'width', 'Content width', 480, 800, 'px'),
                el('p', { class: 'muted small' }, 'Select a block in the preview or the layout list to edit it.'));
            return;
        }
        const p = block.props = block.props || {};
        const fields = {
            heading: () => [text(p, 'text', 'Text'), select_(p, 'level', 'Size', { 1: 'Large', 2: 'Medium', 3: 'Small' }), align(p), color(p, 'color', 'Colour', '#1f2937')],
            text: () => [rich(p, 'html', 'Text'), align(p)],
            button: () => [text(p, 'label', 'Label'), text(p, 'url', 'Link', { type: 'url', placeholder: 'https://…' }), align(p), color(p, 'color', 'Button colour', '#4f46e5'), color(p, 'text_color', 'Text colour', '#ffffff'), check(p, 'full_width', 'Full width')],
            image: () => [image(p), text(p, 'alt', 'Alt text (for screen readers and blocked images)'), text(p, 'url', 'Link (optional)', { type: 'url' }), range(p, 'width', 'Width', 10, 100, '%'), align(p)],
            columns: () => [rich(p, 'left', 'Left column'), rich(p, 'right', 'Right column')],
            divider: () => [color(p, 'color', 'Colour', '#e5e7eb'), range(p, 'thickness', 'Thickness', 1, 8, 'px')],
            spacer: () => [range(p, 'height', 'Height', 4, 200, 'px')],
            social: () => { p.links = p.links || {}; return [...Object.entries(SOCIAL).map(([k, l]) => text(p.links, k, l, { type: 'url', placeholder: 'https://…' })), align(p)]; },
            footer: () => [rich(p, 'html', 'Footer text'), align(p), el('p', { class: 'muted small' }, 'Include your postal address; an unsubscribe link is added automatically.')],
        }[block.type] || (() => []);

        const i = indexOf(block.id);
        inspector.replaceChildren(
            el('div', { class: 'row-between' }, el('h3', {}, LABELS[block.type]), el('button', { type: 'button', class: 'link small', onclick: () => select(null) }, 'Design settings')),
            el('div', { class: 'row block-actions' },
                el('button', { type: 'button', class: 'btn btn-ghost', disabled: i === 0, onclick: () => moveBlock(block.id, i - 1), title: 'Move up' }, '↑'),
                el('button', { type: 'button', class: 'btn btn-ghost', disabled: i === state.blocks.length - 1, onclick: () => moveBlock(block.id, i + 2), title: 'Move down' }, '↓'),
                el('button', { type: 'button', class: 'btn btn-ghost', onclick: () => { const copy = clone(block); copy.id = uid(); state.blocks.splice(i + 1, 0, copy); selected = copy.id; changed(true); } }, 'Duplicate'),
                el('button', { type: 'button', class: 'btn btn-ghost danger', onclick: () => { state.blocks.splice(i, 1); selected = null; changed(true); } }, 'Delete')),
            ...fields());
    }

    // ---- Media library -------------------------------------------------------------------------

    function openMedia(onPick) {
        const grid = el('div', { class: 'media-grid' }, el('p', { class: 'muted' }, 'Loading…'));
        const upload = el('input', { type: 'file', accept: 'image/png,image/jpeg,image/gif,image/webp' });
        const status = el('p', { class: 'muted small' });
        const dialog = el('dialog', { class: 'media-dialog' },
            el('div', { class: 'row-between' }, el('h3', {}, 'Content studio'), el('button', { type: 'button', class: 'link', onclick: () => dialog.close() }, 'Close')),
            el('label', {}, 'Upload image (max 5 MB)', upload), status, grid);
        dialog.addEventListener('close', () => dialog.remove());
        document.body.append(dialog);
        dialog.showModal();

        const pick = (item) => { onPick(item.url); dialog.close(); };
        const load = async () => {
            const items = await (await fetch(root.dataset.mediaUrl, { headers: { Accept: 'application/json' } })).json();
            grid.replaceChildren(...(items.length ? items.map((m) => el('button', { type: 'button', class: 'media-item', onclick: () => pick(m), title: m.name },
                el('img', { src: m.url, alt: m.name, loading: 'lazy' }))) : [el('p', { class: 'muted' }, 'No images yet. Upload one above.')]));
        };
        upload.addEventListener('change', async () => {
            if (!upload.files[0]) return;
            status.textContent = 'Uploading…';
            const body = new FormData();
            body.append('file', upload.files[0]);
            const res = await fetch(root.dataset.uploadUrl, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
            if (!res.ok) { status.textContent = 'Upload failed: use a JPG, PNG, GIF or WebP under 5 MB.'; return; }
            pick(await res.json());
        });
        load();
    }

    // ---- Boot ----------------------------------------------------------------------------------
    state.blocks.forEach((b) => { if (!b.id) b.id = uid(); });
    input.value = JSON.stringify(state);
    renderOutline();
    renderInspector();
    renderPreview();
})();
