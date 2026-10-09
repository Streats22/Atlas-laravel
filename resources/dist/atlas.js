/*!
 * Atlas visual editor — dependency-free, no build step.
 * Canvas = a sandboxed iframe holding the real server-rendered page; the editor
 * overlays selection/drag-and-drop on top of it.
 */
(function () {
  'use strict';

  var cfg = JSON.parse(document.getElementById('atlas-config').textContent);
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var defs = {};
  cfg.blocks.forEach(function (b) { defs[b.type] = b; });

  var I18N = cfg.i18n || {};
  function t(key, vars) {
    var v = I18N[key] != null ? I18N[key] : key;
    if (vars) Object.keys(vars).forEach(function (k) { v = v.replace(':' + k, vars[k]); });
    return v;
  }
  var COMMON_GROUPS = [
    ['group_animation', ['anim', 'anim_duration', 'anim_delay', 'anim_hover']],
    ['group_layout', ['visibility', 'margin_top', 'margin_bottom']],
    ['group_code', ['css_class', 'html_id', 'custom_css']]
  ];
  var COMMON = {};
  (cfg.common || []).forEach(function (f) { COMMON[f.name] = f; });
  var LOCALES = Object.keys(cfg.locales.available || {});
  var DEFAULT_LOCALE = cfg.locales.default;
  var MULTI = LOCALES.length > 1;

  /* ---------------------------------------------------------------- state */
  var state = {
    page: cfg.page,
    tree: normalize(cfg.page.content || []),
    selected: null,
    device: 'desktop',
    tab: 'blocks',
    dirty: false,
    saving: false,
    locale: cfg.locales.default,
    previewTheme: null
  };
  state.page.meta = Array.isArray(state.page.meta) ? {} : (state.page.meta || {});
  state.page.meta.titles = state.page.meta.titles || {};
  state.page.meta.descriptions = state.page.meta.descriptions || {};
  var history = [JSON.stringify(state.tree)];
  var hIndex = 0;
  var lastCoalesce = { key: null, at: 0 };

  /* -------------------------------------------------------------- helpers */
  function h(tag, attrs) {
    var e = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      var v = attrs[k];
      if (v == null || v === false) return;
      if (k === 'class') e.className = v;
      else if (k.slice(0, 2) === 'on') e.addEventListener(k.slice(2), v);
      else if (k === 'style' && typeof v === 'object') Object.assign(e.style, v);
      else if (k === 'value') e.value = v;
      else e.setAttribute(k, v === true ? '' : v);
    });
    for (var i = 2; i < arguments.length; i++) append(e, arguments[i]);
    return e;
  }
  function append(e, k) {
    if (k == null || k === false) return;
    if (Array.isArray(k)) return k.forEach(function (x) { append(e, x); });
    e.append(k.nodeType ? k : document.createTextNode(k));
  }
  function uid() {
    var s = '';
    do { s = Math.random().toString(36).slice(2, 10); } while (!s || (state && find(s)));
    return s;
  }
  // Translations live next to the default value as "name@locale".
  function tkey(name) { return state.locale === DEFAULT_LOCALE ? name : name + '@' + state.locale; }
  function clone(x) { return JSON.parse(JSON.stringify(x)); }
  function normalize(nodes) {
    return (Array.isArray(nodes) ? nodes : []).map(function (n) {
      return {
        id: n.id || uid(),
        type: n.type,
        props: (n.props && !Array.isArray(n.props)) ? n.props : {},
        children: normalize(n.children)
      };
    });
  }
  function find(id, list, parent) {
    list = list || state.tree;
    for (var i = 0; i < list.length; i++) {
      if (list[i].id === id) return { node: list[i], list: list, index: i, parent: parent || null };
      var r = find(id, list[i].children, list[i]);
      if (r) return r;
    }
    return null;
  }
  function instantiate(tpl) {
    var def = defs[tpl.type];
    var props = Object.assign({}, def ? def.template.props : {}, tpl.props || {});
    var node = { id: uid(), type: tpl.type, props: props, children: [] };
    // ids must be unique while we build siblings: register eagerly
    var kids = (tpl.children && tpl.children.length ? tpl.children : (def ? def.template.children : [])) || [];
    node.children = kids.map(instantiate);
    return node;
  }
  function isContainer(type) { return !!(defs[type] && defs[type].container); }
  function label(type) { return defs[type] ? defs[type].label : type; }
  function icon(type) { return defs[type] ? defs[type].icon : '?'; }
  function hint(n) {
    var p = n.props || {};
    var v = p.text || p.label || p.title || p.alt || '';
    return typeof v === 'string' ? v.slice(0, 40) : '';
  }
  function toast(msg, isError) {
    var t = h('div', { class: 'atlas-toast' + (isError ? ' atlas-toast--error' : '') }, msg);
    document.body.append(t);
    setTimeout(function () { t.remove(); }, isError ? 5000 : 1800);
  }
  function api(url, method, body, isForm) {
    var headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (!isForm) headers['Content-Type'] = 'application/json';
    return fetch(url, { method: method, headers: headers, body: isForm ? body : JSON.stringify(body), credentials: 'same-origin' });
  }

  /* -------------------------------------------------------------- history */
  function snapshot(coalesceKey) {
    var s = JSON.stringify(state.tree);
    var now = Date.now();
    if (coalesceKey && lastCoalesce.key === coalesceKey && now - lastCoalesce.at < 1200) {
      history[hIndex] = s;
    } else {
      history = history.slice(0, hIndex + 1);
      history.push(s);
      if (history.length > 100) history.shift();
      hIndex = history.length - 1;
    }
    lastCoalesce = { key: coalesceKey || null, at: now };
    syncUndo();
  }
  function restore(i) {
    if (i < 0 || i >= history.length) return;
    hIndex = i;
    state.tree = JSON.parse(history[i]);
    if (state.selected && !find(state.selected)) state.selected = null;
    lastCoalesce = { key: null, at: 0 };
    markDirty();
    refreshAll();
  }
  function markDirty() { state.dirty = true; setStatus(t('unsaved')); queueDraft(); }

  // ---- local draft (survives crashes / accidental tab closes)
  var DRAFT_KEY = 'atlas-draft-' + cfg.page.id, draftTimer;
  function queueDraft() { clearTimeout(draftTimer); draftTimer = setTimeout(storeDraft, 700); }
  function storeDraft() {
    try {
      var p = state.page;
      localStorage.setItem(DRAFT_KEY, JSON.stringify({ at: Date.now(), tree: state.tree, page: { title: p.title, slug: p.slug, status: p.status, css: p.css, js: p.js, head: p.head, meta: p.meta } }));
    } catch (e) {}
  }
  function clearDraft() { clearTimeout(draftTimer); try { localStorage.removeItem(DRAFT_KEY); } catch (e) {} }
  function offerDraft() {
    var d = null;
    try { d = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) {}
    if (!d || !d.tree || d.at / 1000 <= (cfg.page.updated_at || 0) || JSON.stringify(d.tree) === JSON.stringify(state.tree)) return;
    var bar = h('div', { class: 'atlas-banner' }, t('restore_draft', { time: new Date(d.at).toLocaleString() }),
      h('button', { class: 'atlas-btn atlas-btn--primary', onclick: function () {
        state.tree = normalize(d.tree);
        Object.assign(state.page, d.page || {});
        state.page.meta = state.page.meta || {}; state.page.meta.titles = state.page.meta.titles || {}; state.page.meta.descriptions = state.page.meta.descriptions || {};
        els.title.value = titleValue(); els.slug.value = state.page.slug; els.statusSel.value = state.page.status;
        state.selected = null; snapshot(); markDirty(); refreshAll(); bar.remove();
      } }, t('restore')),
      h('button', { class: 'atlas-btn', onclick: function () { clearDraft(); bar.remove(); } }, t('discard')));
    els.stage.append(bar);
  }

  /* ------------------------------------------------------------- mutation */
  // Structural change: snapshot, rerender canvas, refresh panels.
  function commit() { snapshot(); markDirty(); refreshAll(); }
  // Property change: coalesced snapshot, rerender canvas (inspector keeps focus).
  function touch(key) { snapshot(key); markDirty(); scheduleRender(); renderLayers(); }

  function insertNode(parentId, index, node) {
    var list = parentId ? find(parentId).node.children : state.tree;
    list.splice(index == null ? list.length : index, 0, node);
  }
  function removeNode(id) {
    var f = find(id);
    if (!f) return null;
    f.list.splice(f.index, 1);
    return f.node;
  }
  function addBlock(type, parentId, index) {
    var node = instantiate({ type: type });
    insertNode(parentId, index, node);
    state.selected = node.id;
    commit();
    if (phone.matches) closePanel(); // reveal the canvas so the new block is visible
  }
  function deleteSelected() {
    if (!state.selected) return;
    var f = find(state.selected);
    if (!f) return;
    removeNode(state.selected);
    state.selected = f.parent ? f.parent.id : null;
    commit();
  }
  function relabel(node) {
    node.id = uid();
    node.children.forEach(relabel);
  }
  function duplicateSelected() {
    var f = find(state.selected);
    if (!f) return;
    var copy = clone(f.node);
    relabel(copy);
    f.list.splice(f.index + 1, 0, copy);
    state.selected = copy.id;
    commit();
  }
  function moveSelected(delta) {
    var f = find(state.selected);
    if (!f) return;
    var to = f.index + delta;
    if (to < 0 || to >= f.list.length) return;
    f.list.splice(to, 0, f.list.splice(f.index, 1)[0]);
    commit();
  }
  // Copy / cut / paste (shared across pages through localStorage).
  function copySelected(cut) {
    var f = state.selected && find(state.selected);
    if (!f) return;
    try { localStorage.setItem('atlas-clip', JSON.stringify(f.node)); } catch (e) {}
    toast(t('copied'));
    if (cut) deleteSelected();
  }
  function pasteClipboard() {
    var node = null;
    try { node = JSON.parse(localStorage.getItem('atlas-clip') || 'null'); } catch (e) {}
    if (!node || !node.type) return;
    node = normalize([node])[0];
    relabel(node);
    var sel = state.selected && find(state.selected);
    if (sel && isContainer(sel.node.type)) sel.node.children.push(node);
    else if (sel) sel.list.splice(sel.index + 1, 0, node);
    else state.tree.push(node);
    state.selected = node.id;
    commit();
    toast(t('pasted'));
  }

  // Move a node relative to another one (used by the Layers panel).
  function moveNode(id, targetId, zone) {
    var dragged = find(id);
    if (!dragged || id === targetId || find(targetId, dragged.node.children)) return; // never into itself
    var node = removeNode(id);
    var target = find(targetId);
    if (!target) return;
    if (zone === 'inside') target.node.children.push(node);
    else target.list.splice(target.index + (zone === 'after' ? 1 : 0), 0, node);
    state.selected = node.id;
    commit();
  }

  // Click-to-insert from the palette (when not dragging).
  function insertDefault(type) {
    var sel = state.selected && find(state.selected);
    if (sel && isContainer(sel.node.type)) return addBlock(type, sel.node.id, null);
    if (sel) return addBlock(type, sel.parent ? sel.parent.id : null, sel.index + 1);
    addBlock(type, null, null);
  }

  /* ------------------------------------------------------------- layout UI */
  var root = document.getElementById('atlas-root');
  var els = {};

  function buildShell() {
    var p = state.page;
    els.title = h('input', { class: 'atlas-input atlas-top__title', value: titleValue(), placeholder: t('page_title'), oninput: function (e) { setTitle(e.target.value); markDirty(); } });
    els.slug = h('input', { class: 'atlas-input atlas-top__slug', value: p.slug, title: t('slug'), oninput: function (e) { p.slug = e.target.value; markDirty(); } });
    els.statusSel = h('select', { class: 'atlas-select', style: { width: 'auto' }, onchange: function (e) { p.status = e.target.value; markDirty(); } },
      h('option', { value: 'draft' }, t('draft')), h('option', { value: 'published' }, t('published')));
    els.statusSel.value = p.status;
    els.status = h('span', { class: 'atlas-status' }, t('saved'));
    els.undo = h('button', { class: 'atlas-btn', title: t('undo'), onclick: function () { restore(hIndex - 1); } }, '↶');
    els.redo = h('button', { class: 'atlas-btn', title: t('redo'), onclick: function () { restore(hIndex + 1); } }, '↷');
    els.devices = {};
    var devices = h('div', { class: 'atlas-top__group atlas-top__devices' }, [['desktop', '🖥'], ['tablet', '▭'], ['mobile', '📱']].map(function (d) {
      return els.devices[d[0]] = h('button', { class: 'atlas-btn', title: t('device_' + d[0]), onclick: function () { setDevice(d[0]); } }, d[1]);
    }));
    els.save = h('button', { class: 'atlas-btn atlas-btn--primary', onclick: function () { save(); } }, t('save'));
    els.viewLink = h('a', { class: 'atlas-btn atlas-top__view', href: viewHref(cfg.urls.public), onclick: function (e) {
      // Same tab: the live page shows an Atlas toolbar with a way back. Save first so it shows the latest content.
      if (e.metaKey || e.ctrlKey || e.shiftKey) return;
      e.preventDefault();
      var go = function (ok) { if (ok) location.href = els.viewLink.href; };
      if (state.dirty) save().then(go); else go(true);
    } }, t('view'));

    // content language (only when more than one locale is configured)
    els.localeSel = MULTI ? h('select', { class: 'atlas-select', style: { width: 'auto' }, title: t('language'), onchange: function (e) { setLocale(e.target.value); } },
      LOCALES.map(function (c) { return h('option', { value: c }, '🌐 ' + cfg.locales.available[c] + (c === DEFAULT_LOCALE ? ' (' + t('default_lang') + ')' : '')); })) : null;

    // canvas light/dark preview + editor UI theme
    els.canvasTheme = h('button', { class: 'atlas-btn atlas-top__theme', title: t('canvas_theme'), onclick: toggleCanvasTheme }, '◐');
    els.uiTheme = h('button', { class: 'atlas-btn atlas-top__theme', title: t('ui_theme'), onclick: toggleUiTheme }, '☾');

    var top = h('div', { class: 'atlas-top' },
      h('a', { class: 'atlas-btn atlas-top__back', href: cfg.urls.pages, title: t('all_pages'), onclick: leaveGuard }, t('back')),
      els.title, els.slug, els.statusSel, els.localeSel,
      h('span', { class: 'atlas-top__spacer' }),
      els.status, els.undo, els.redo, devices, els.canvasTheme, els.uiTheme,
      h('button', { class: 'atlas-btn atlas-top__preview', onclick: preview }, t('preview')),
      els.viewLink, els.save);

    els.leftBody = h('div', { class: 'atlas-scroll' });
    els.tabs = {};
    var tabDefs = [['blocks', t('blocks')], ['layers', t('layers')], ['page', t('page')]];
    var tabs = h('div', { class: 'atlas-tabs' }, tabDefs.map(function (d) {
      return els.tabs[d[0]] = h('button', { class: 'atlas-tab', onclick: function () { setTab(d[0]); } }, d[1]);
    }));
    els.left = h('aside', { class: 'atlas-side' }, tabs, els.leftBody);
    var left = els.left;

    els.iframe = h('iframe', { class: 'atlas-frame', sandbox: 'allow-same-origin', title: 'Canvas' });
    els.overlay = h('div', { class: 'atlas-overlay' });
    els.stage = h('main', { class: 'atlas-stage' }, els.iframe, els.overlay);

    els.inspector = h('div', { class: 'atlas-scroll' });
    els.right = h('aside', { class: 'atlas-side atlas-side--right' }, els.inspector);
    var right = els.right;

    // Phone layout: the side panels slide up as sheets, opened from this bar.
    els.navBtns = {};
    els.nav = h('nav', { class: 'atlas-nav' }, [['blocks', '＋', t('blocks')], ['layers', '☰', t('layers')], ['page', '⚙', t('page')], ['inspector', '✎', t('edit')]].map(function (d) {
      return els.navBtns[d[0]] = h('button', { class: 'atlas-nav__btn', onclick: function () { togglePanel(d[0]); } }, h('span', { class: 'atlas-nav__ico' }, d[1]), h('span', null, d[2]));
    }));

    root.append(h('div', { class: 'atlas-shell' }, top, h('div', { class: 'atlas-body' }, left, els.stage, right), els.nav));

    els.hoverBox = h('div', { class: 'atlas-box atlas-box--hover', hidden: true }, els.hoverTag = h('span', { class: 'atlas-hover-tag' }));
    els.selBox = h('div', { class: 'atlas-box atlas-box--sel', hidden: true });
    els.dropLine = h('div', { class: 'atlas-drop', hidden: true });
    els.overlay.append(els.hoverBox, els.selBox, els.dropLine);

    els.iframe.addEventListener('load', onFrameLoad);
    window.addEventListener('resize', updateOverlay);
    if (window.ResizeObserver) new ResizeObserver(updateOverlay).observe(els.iframe);
  }

  function setDevice(d) {
    state.device = d;
    els.iframe.style.width = d === 'desktop' ? '100%' : (d === 'tablet' ? '768px' : '390px');
    Object.keys(els.devices).forEach(function (k) { els.devices[k].classList.toggle('atlas-btn--active', k === d); });
    setTimeout(updateOverlay, 230);
  }
  function setTab(t) {
    state.tab = t;
    Object.keys(els.tabs).forEach(function (k) { els.tabs[k].classList.toggle('atlas-tab--on', k === t); });
    renderLeft();
  }
  /* Bottom-sheet panels (phones only; on wider screens the panels are always visible) */
  var phone = window.matchMedia('(max-width: 800px)');
  function togglePanel(name) {
    if (state.sheet === name) return closePanel();
    state.sheet = name;
    if (name === 'inspector') renderInspector(); else setTab(name);
    syncPanels();
  }
  function closePanel() { state.sheet = null; syncPanels(); }
  function syncPanels() {
    var open = phone.matches ? state.sheet : null;
    els.left.classList.toggle('atlas-side--open', open === 'blocks' || open === 'layers' || open === 'page');
    els.right.classList.toggle('atlas-side--open', open === 'inspector');
    Object.keys(els.navBtns).forEach(function (k) { els.navBtns[k].classList.toggle('atlas-nav__btn--on', open === k); });
    els.navBtns.inspector.classList.toggle('atlas-nav__btn--has', !!state.selected);
    setTimeout(updateOverlay, 260);
  }
  phone.addEventListener ? phone.addEventListener('change', syncPanels) : phone.addListener(syncPanels);
  function setStatus(s) { if (els.status) els.status.textContent = s; }

  // ---- content language & title
  function titleValue() {
    return state.locale === DEFAULT_LOCALE ? state.page.title : (state.page.meta.titles[state.locale] || '');
  }
  function setTitle(v) {
    if (state.locale === DEFAULT_LOCALE) state.page.title = v; else state.page.meta.titles[state.locale] = v;
  }
  function setLocale(code) {
    state.locale = code;
    els.title.value = titleValue();
    els.title.placeholder = state.locale === DEFAULT_LOCALE ? t('page_title') : state.page.title;
    renderInspector();
    if (state.tab === 'page') renderLeft();
    scheduleRender(0);
  }

  // ---- themes
  function toggleUiTheme() {
    var cur = document.documentElement.getAttribute('data-theme');
    var dark = cur === 'dark' || (cur !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    var next = dark ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('atlas-ui-theme', next); } catch (e) {}
  }
  function toggleCanvasTheme() {
    var doc = frameDoc();
    var cur = state.previewTheme || (doc && doc.documentElement.getAttribute('data-atlas-theme')) || 'auto';
    var dark = cur === 'dark' || (cur !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    state.previewTheme = dark ? 'light' : 'dark';
    applyCanvasTheme();
  }
  function applyCanvasTheme() {
    var doc = frameDoc();
    if (doc && state.previewTheme) doc.documentElement.setAttribute('data-atlas-theme', state.previewTheme);
    els.canvasTheme.classList.toggle('atlas-btn--active', !!state.previewTheme);
  }
  function syncUndo() {
    els.undo.disabled = hIndex <= 0;
    els.redo.disabled = hIndex >= history.length - 1;
  }
  function refreshAll() {
    renderLeft();
    renderInspector();
    scheduleRender(30);
    syncUndo();
  }

  /* ---------------------------------------------------------- left panels */
  function renderLeft() {
    if (state.tab === 'blocks') renderPalette();
    else if (state.tab === 'layers') renderLayers();
    else renderPageCode();
  }

  function renderPalette() {
    var q = '';
    var search = h('input', { class: 'atlas-input', placeholder: t('search_blocks'), style: { marginBottom: '12px' }, oninput: function (e) { q = e.target.value.toLowerCase(); draw(); } });
    var holder = h('div');
    els.leftBody.replaceChildren(search, holder);
    function draw() {
      var cats = {}, order = [];
      cfg.blocks.forEach(function (b) {
        if (b.custom || (q && b.label.toLowerCase().indexOf(q) === -1)) return; // builder blocks have their own section
        if (!cats[b.category]) { cats[b.category] = []; order.push(b.category); }
        cats[b.category].push(b);
      });
      var out = order.map(function (c) {
        return h('div', null,
          h('div', { class: 'atlas-cat' }, c),
          h('div', { class: 'atlas-palette' }, cats[c].map(function (b) {
            return h('div', { class: 'atlas-block-item', title: t('drag_hint'), onpointerdown: function (e) { if (e.button === 0) beginPress(e, { kind: 'new', type: b.type }); } },
              h('span', { class: 'atlas-block-item__icon' }, b.icon), b.label);
          })));
      });
      if (cfg.customCode) {
        out.push(h('div', null, h('div', { class: 'atlas-cat' }, t('custom_blocks')),
          (cfg.customBlocks || []).map(function (cb) {
            return h('div', { class: 'atlas-custom-item' },
              h('div', { class: 'atlas-block-item', style: { cursor: 'grab' }, onpointerdown: function (e) { if (e.button === 0) beginPress(e, { kind: 'new', type: cb.type }); } }, h('span', { class: 'atlas-block-item__icon' }, cb.icon || '◇'), cb.label),
              h('button', { class: 'atlas-btn', title: t('edit_block'), onclick: function () { openBuilder(cb); } }, '✎'));
          }),
          h('button', { class: 'atlas-btn', style: { width: '100%', marginTop: '8px', justifyContent: 'center' }, onclick: function () { openBuilder(null); } }, t('new_block'))));
      }
      holder.replaceChildren.apply(holder, out);
    }
    draw();
  }

  function renderLayers() {
    if (state.tab !== 'layers') return;
    var box = h('div'), dragId = null;
    function zoneOf(e, row, container) {
      var r = row.getBoundingClientRect(), y = (e.clientY - r.top) / r.height;
      if (container && y > 0.28 && y < 0.72) return 'inside';
      return y < 0.5 ? 'before' : 'after';
    }
    function clear() { box.querySelectorAll('.atlas-layer').forEach(function (r) { r.classList.remove('atlas-layer--before', 'atlas-layer--after', 'atlas-layer--inside'); }); }
    (function walk(list, depth) {
      list.forEach(function (n) {
        var row = h('div', {
          class: 'atlas-layer' + (n.id === state.selected ? ' atlas-layer--sel' : ''),
          style: { paddingLeft: (6 + depth * 14) + 'px' },
          draggable: 'true',
          title: t('drag_to_move'),
          onclick: function () { select(n.id); },
          onmouseenter: function () { hoverId(n.id); },
          onmouseleave: function () { hoverId(null); },
          ondragstart: function (e) { dragId = n.id; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', n.id); },
          ondragover: function (e) { if (!dragId) return; e.preventDefault(); clear(); row.classList.add('atlas-layer--' + zoneOf(e, row, isContainer(n.type))); },
          ondragleave: function () { row.classList.remove('atlas-layer--before', 'atlas-layer--after', 'atlas-layer--inside'); },
          ondrop: function (e) { e.preventDefault(); var z = zoneOf(e, row, isContainer(n.type)), id = dragId; dragId = null; clear(); if (id) moveNode(id, n.id, z); },
          ondragend: function () { dragId = null; clear(); }
        }, h('span', null, icon(n.type)), label(n.type), h('span', { class: 'atlas-layer__hint' }, hint(n)));
        box.append(row);
        walk(n.children, depth + 1);
      });
    })(state.tree, 0);
    if (!state.tree.length) box.append(h('p', { class: 'atlas-hint' }, t('nothing_here')));
    els.leftBody.replaceChildren(box);
  }

  function renderPageCode() {
    var p = state.page, meta = p.meta, th = cfg.theme, dirty = function () { markDirty(); scheduleRender(300); };
    function field(label, input) { return h('div', { class: 'atlas-field' }, h('label', null, label), input); }
    function sel(key, options, cur, cb) {
      var el = h('select', { class: 'atlas-select', onchange: function (e) { cb(e.target.value); dirty(); } }, options.map(function (o) { return h('option', { value: o[0] }, o[1]); }));
      el.value = cur; return el;
    }
    function color(key, fallback) {
      var cur = meta[key] || fallback;
      var txt = h('input', { class: 'atlas-input', value: meta[key] || '', placeholder: fallback, oninput: function (e) { meta[key] = e.target.value; if (/^#[0-9a-f]{6}$/i.test(e.target.value)) sw.value = e.target.value; dirty(); } });
      var sw = h('input', { class: 'atlas-swatch', type: 'color', value: /^#[0-9a-f]{6}$/i.test(cur) ? cur : '#4f46e5', oninput: function (e) { txt.value = e.target.value; meta[key] = e.target.value; dirty(); } });
      return h('div', { class: 'atlas-row' }, sw, txt);
    }
    function code(name, text, rerender) {
      var ta = h('textarea', { class: 'atlas-textarea atlas-code', spellcheck: 'false', value: p[name] || '', oninput: function (e) { p[name] = e.target.value; markDirty(); if (rerender) scheduleRender(400); } });
      tabKeys(ta);
      return h('div', { class: 'atlas-field' }, h('label', null, text, ' ', h('a', { href: '#', onclick: function (e) { e.preventDefault(); openCode(text, ta.value, function (v) { ta.value = v; ta.dispatchEvent(new Event('input')); }); } }, t('expand'))), ta);
    }
    var loc = state.locale;
    var desc = h('textarea', { class: 'atlas-textarea', style: { minHeight: '60px' }, value: loc === DEFAULT_LOCALE ? (meta.description || '') : (meta.descriptions[loc] || ''), oninput: function (e) { if (loc === DEFAULT_LOCALE) meta.description = e.target.value; else meta.descriptions[loc] = e.target.value; markDirty(); } });
    var fonts = [['system', t('font_system')], ['serif', t('font_serif')], ['mono', t('font_mono')], ['rounded', t('font_rounded')]];
    var body = [
      h('div', { class: 'atlas-cat' }, t('page_settings')),
      field(t('meta_description') + (MULTI ? ' (' + loc + ')' : ''), desc),
      field(t('og_image'), h('input', { class: 'atlas-input', value: meta.og_image || '', placeholder: 'https://…', oninput: function (e) { meta.og_image = e.target.value; markDirty(); } })),
      h('div', { class: 'atlas-cat' }, t('theme')),
      field(t('theme_mode'), sel('theme', [['auto', t('mode_auto')], ['light', t('mode_light')], ['dark', t('mode_dark')]], meta.theme || th.mode, function (v) { meta.theme = v; state.previewTheme = null; })),
      h('div', { class: 'atlas-field atlas-field--check' }, h('label', null, (function () { var c = h('input', { type: 'checkbox', onchange: function (e) { meta.theme_toggle = e.target.checked; markDirty(); } }); c.checked = meta.theme_toggle != null ? !!meta.theme_toggle : !!th.toggle; return c; })(), t('show_toggle'))),
      field(t('accent'), color('accent', th.accent)),
      field(t('accent_dark'), color('accent_dark', th.accent_dark)),
      field(t('spacing'), sel('spacing', [['compact', t('spacing_compact')], ['comfortable', t('spacing_comfortable')], ['spacious', t('spacing_spacious')]], meta.spacing || th.spacing, function (v) { meta.spacing = v; })),
      field(t('font'), sel('font', fonts, meta.font || th.font, function (v) { meta.font = v; })),
      field(t('heading_font'), sel('heading_font', [['same', t('font_same')]].concat(fonts), meta.heading_font || th.heading_font, function (v) { meta.heading_font = v; }))
    ];
    if (cfg.customCode) {
      body.push(h('div', { class: 'atlas-cat' }, t('page_code')), code('css', t('page_css'), true), code('js', t('page_js'), false), code('head', t('page_head'), true), h('p', { class: 'atlas-hint' }, t('scripts_note')));
    }
    els.leftBody.replaceChildren.apply(els.leftBody, body);
  }

  /* ------------------------------------------------------------ inspector */
  function renderInspector() {
    var f = state.selected && find(state.selected);
    if (!f) {
      els.inspector.replaceChildren(h('p', { class: 'atlas-hint' }, t('select_hint')), h('p', { class: 'atlas-hint' }, t('shortcuts')));
      return;
    }
    var n = f.node;
    var def = defs[n.type];
    var body = [h('div', { class: 'atlas-insp-head' },
      h('h3', null, icon(n.type) + '  ' + label(n.type)),
      h('div', { class: 'atlas-row' },
        h('button', { class: 'atlas-btn', title: t('duplicate'), onclick: duplicateSelected }, '⧉'),
        h('button', { class: 'atlas-btn atlas-btn--danger', title: t('delete'), onclick: deleteSelected }, '✕')))];
    if (MULTI && state.locale !== DEFAULT_LOCALE) body.push(h('p', { class: 'atlas-hint' }, '🌐 ' + t('translating', { locale: cfg.locales.available[state.locale] })));
    if (!def) body.push(h('p', { class: 'atlas-hint' }, t('unknown_block')));
    (def ? def.fields : []).forEach(function (fd) { body.push(fieldEl(n.props, fd, n.id)); });

    COMMON_GROUPS.forEach(function (g, gi) {
      var fields = g[1].filter(function (name) { return COMMON[name]; });
      if (!fields.length) return;
      body.push(h('details', { class: 'atlas-group', open: gi === 0 && n.props.anim && n.props.anim !== 'none' ? true : null },
        h('summary', null, t(g[0])), fields.map(function (name) { return fieldEl(n.props, COMMON[name], n.id); })));
    });
    els.inspector.replaceChildren.apply(els.inspector, body);
  }

  function fieldEl(props, fd, scope) {
    var translating = !!fd.translatable && state.locale !== DEFAULT_LOCALE;
    var name = translating ? fd.name + '@' + state.locale : fd.name;
    var cur = props[name];
    if (cur === undefined) cur = translating ? '' : fd.default;
    var key = scope + ':' + name;
    function set(v) { props[name] = v; touch(key); }
    var badge = fd.translatable && MULTI ? h('span', { class: 'atlas-tr' }, '🌐') : null;
    var ph = translating ? String(props[fd.name] == null ? '' : props[fd.name]).slice(0, 80) : fd.placeholder;
    var input;

    switch (fd.type) {
      case 'repeater':
        return repeaterEl(props, fd, scope);
      case 'checkbox':
        input = h('input', { type: 'checkbox', onchange: function (e) { set(e.target.checked); } });
        input.checked = !!cur;
        return h('div', { class: 'atlas-field atlas-field--check' }, h('label', null, input, fd.label));
      case 'select':
        input = h('select', { class: 'atlas-select', onchange: function (e) { set(e.target.value); } },
          Object.keys(fd.options).map(function (v) { return h('option', { value: v }, fd.options[v]); }));
        input.value = cur;
        break;
      case 'number':
        input = h('input', { class: 'atlas-input', type: 'number', value: cur == null ? '' : cur, placeholder: fd.nullable ? t('theme_default') : null, min: fd.min, max: fd.max, step: fd.step, oninput: function (e) { set(e.target.value === '' ? (fd.nullable ? null : 0) : Number(e.target.value)); } });
        break;
      case 'color':
        var txt = h('input', { class: 'atlas-input', value: cur || '', placeholder: 'none', oninput: function (e) { sw.value = /^#[0-9a-f]{6}$/i.test(e.target.value) ? e.target.value : sw.value; set(e.target.value); } });
        var sw = h('input', { class: 'atlas-swatch', type: 'color', value: /^#[0-9a-f]{6}$/i.test(cur || '') ? cur : '#000000', oninput: function (e) { txt.value = e.target.value; set(e.target.value); } });
        input = h('div', { class: 'atlas-row' }, sw, txt);
        break;
      case 'textarea':
        input = h('textarea', { class: 'atlas-textarea', value: cur || '', placeholder: ph, oninput: function (e) { set(e.target.value); } });
        break;
      case 'code':
        var ta = h('textarea', { class: 'atlas-textarea atlas-code', spellcheck: 'false', value: cur || '', oninput: function (e) { set(e.target.value); } });
        tabKeys(ta);
        return h('div', { class: 'atlas-field' }, h('label', null, fd.label, ' ', h('a', { href: '#', onclick: function (e) { e.preventDefault(); openCode(fd.label, ta.value, function (v) { ta.value = v; set(v); }); } }, t('expand'))), ta);
      case 'image':
        var url = h('input', { class: 'atlas-input', value: cur || '', placeholder: 'https://… / upload', oninput: function (e) { set(e.target.value); } });
        var file = h('input', { type: 'file', accept: 'image/*', hidden: true, onchange: function (e) { upload(e.target.files[0], function (u) { url.value = u; set(u); }); } });
        input = h('div', null, h('div', { class: 'atlas-row' }, url, h('button', { class: 'atlas-btn', onclick: function () { file.click(); } }, t('upload'))), file);
        break;
      default:
        input = h('input', { class: 'atlas-input', value: cur == null ? '' : cur, placeholder: ph, oninput: function (e) { set(e.target.value); } });
    }
    return h('div', { class: 'atlas-field' }, h('label', null, fd.label, badge), input);
  }

  // A list of items, each with sub-fields (portfolio projects, slides, …).
  function repeaterEl(props, fd, scope) {
    var list = Array.isArray(props[fd.name]) ? props[fd.name] : (props[fd.name] = clone(fd.default || []));
    var wrap = h('div', { class: 'atlas-field' });
    function titleOf(item, n) {
      var v = item[fd.item_label];
      return (typeof v === 'string' && v ? v : t('item')) + ' ' + (typeof v === 'string' && v ? '' : n + 1);
    }
    function draw() {
      var rows = list.map(function (item, i) {
        var det = h('details', { class: 'atlas-rep' },
          h('summary', null,
            h('span', { class: 'atlas-rep__title' }, titleOf(item, i)),
            h('button', { class: 'atlas-mini', title: t('move_up'), onclick: function (e) { e.preventDefault(); move(i, -1); } }, '↑'),
            h('button', { class: 'atlas-mini', title: t('move_down'), onclick: function (e) { e.preventDefault(); move(i, 1); } }, '↓'),
            h('button', { class: 'atlas-mini', title: t('duplicate'), onclick: function (e) { e.preventDefault(); list.splice(i + 1, 0, clone(item)); touch(); draw(); } }, '⧉'),
            h('button', { class: 'atlas-mini', title: t('remove'), onclick: function (e) { e.preventDefault(); list.splice(i, 1); touch(); draw(); } }, '✕')),
          h('div', { class: 'atlas-rep__body' }, fd.fields.map(function (sub) { return fieldEl(item, sub, scope + ':' + fd.name + i); })));
        return det;
      });
      var addBtn = h('button', { class: 'atlas-btn', style: { width: '100%', justifyContent: 'center' }, onclick: function () {
        var item = {}; fd.fields.forEach(function (sub) { item[sub.name] = clone(sub.default == null ? '' : sub.default); });
        list.push(item); touch(); draw();
        var last = wrap.querySelectorAll('.atlas-rep'); if (last.length) last[last.length - 1].open = true;
      } }, t('add_item'));
      wrap.replaceChildren.apply(wrap, [h('label', null, fd.label)].concat(rows, [addBtn]));
    }
    function move(i, d) { var j = i + d; if (j < 0 || j >= list.length) return; list.splice(j, 0, list.splice(i, 1)[0]); touch(); draw(); }
    function touch() { snapshot(null); markDirty(); scheduleRender(); renderLayers(); }
    draw();
    return wrap;
  }

  function upload(file, done) {
    if (!file) return;
    var fd = new FormData();
    fd.append('file', file);
    api(cfg.urls.upload, 'POST', fd, true).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (r) {
        if (!r.ok) return toast((r.j.errors && r.j.errors.file && r.j.errors.file[0]) || t('upload_failed'), true);
        done(r.j.url);
      }).catch(function () { toast(t('upload_failed'), true); });
  }

  function tabKeys(ta) {
    ta.addEventListener('keydown', function (e) {
      if (e.key === 'Tab' && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
        e.preventDefault();
        ta.setRangeText('  ', ta.selectionStart, ta.selectionEnd, 'end');
        ta.dispatchEvent(new Event('input'));
      }
    });
  }

  function openCode(title, value, done) {
    var ta = h('textarea', { class: 'atlas-textarea atlas-code', spellcheck: 'false', value: value });
    tabKeys(ta);
    var modal = h('div', { class: 'atlas-modal' }, h('div', { class: 'atlas-modal__card' },
      h('div', { class: 'atlas-modal__head' }, title, h('button', { class: 'atlas-btn atlas-btn--primary', onclick: close }, t('done'))), ta));
    function close() { done(ta.value); modal.remove(); }
    modal.addEventListener('mousedown', function (e) { if (e.target === modal) close(); });
    document.body.append(modal);
    ta.focus();
  }

  /* ------------------------------------------------ custom block builder */
  var FIELD_TYPES = ['text', 'textarea', 'number', 'select', 'color', 'checkbox', 'image', 'code', 'repeater'];
  function slugify(v) { return String(v).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').replace(/^(\d)/, 'b-$1'); }
  function optionsToText(o) { return Object.keys(o || {}).map(function (k) { return k + ':' + o[k]; }).join(', '); }
  function textToOptions(txt) {
    var o = {};
    String(txt || '').split(',').forEach(function (p) {
      p = p.trim(); if (!p) return;
      var i = p.indexOf(':'); var v = i < 0 ? p : p.slice(0, i).trim(); o[v] = i < 0 ? p : p.slice(i + 1).trim();
    });
    return o;
  }
  function subToText(fields) { return (fields || []).map(function (f) { return f.name + ':' + f.type; }).join(', '); }
  function textToSub(txt) {
    return String(txt || '').split(',').map(function (p) {
      var bits = p.trim().split(':'); var n = slugify(bits[0] || '').replace(/-/g, '_');
      if (!n) return null;
      var ty = FIELD_TYPES.indexOf(bits[1]) > 0 && bits[1] !== 'repeater' && bits[1] !== 'code' ? bits[1] : 'text';
      return { name: n, label: n.replace(/_/g, ' '), type: ty, default: ty === 'checkbox' ? false : (ty === 'number' ? 0 : '') };
    }).filter(Boolean);
  }

  function openBuilder(existing) {
    var m = existing ? clone(existing) : { type: '', label: '', category: 'Custom', icon: '◇', container: false, fields: [{ name: 'title', label: 'Title', type: 'text', default: 'Hello' }], html: '<div class="my-block">\n  <h3>{{ title }}</h3>\n</div>', css: '{{selector}} .my-block { padding: 1rem; }', js: '' };
    var typeTouched = !!existing;
    var err = h('div', { class: 'atlas-error-text' });
    function inp(prop, ph, extra) { return h('input', Object.assign({ class: 'atlas-input', value: m[prop] || '', placeholder: ph || '', oninput: function (e) { m[prop] = e.target.value; if (prop === 'label' && !typeTouched) { m.type = slugify(m.label); typeIn.value = m.type; } } }, extra || {})); }
    var typeIn = h('input', { class: 'atlas-input', value: m.type, disabled: existing ? true : null, oninput: function (e) { typeTouched = true; m.type = e.target.value; } });
    var rows = h('div');
    function drawFields() {
      rows.replaceChildren.apply(rows, m.fields.map(function (f, i) {
        var opts = h('input', { class: 'atlas-input wide', placeholder: t('field_options'), value: optionsToText(f.options), oninput: function (e) { f.options = textToOptions(e.target.value); } });
        var sub = h('input', { class: 'atlas-input wide', placeholder: t('field_sub'), value: subToText(f.fields), oninput: function (e) { f.fields = textToSub(e.target.value); } });
        var tr = h('label', { class: 'wide', style: { fontSize: '12.5px' } }, (function () { var c = h('input', { type: 'checkbox', onchange: function (e) { f.translatable = e.target.checked; } }); c.checked = !!f.translatable; return c; })(), ' ' + t('field_translatable'));
        var type = h('select', { class: 'atlas-select', onchange: function (e) { f.type = e.target.value; drawFields(); } }, FIELD_TYPES.map(function (x) { return h('option', { value: x }, x); }));
        type.value = f.type;
        return h('div', { class: 'atlas-frow' },
          h('input', { class: 'atlas-input', placeholder: t('field_name'), value: f.name, oninput: function (e) { f.name = e.target.value; } }),
          h('input', { class: 'atlas-input', placeholder: t('field_label'), value: f.label || '', oninput: function (e) { f.label = e.target.value; } }),
          type,
          h('button', { class: 'atlas-btn atlas-btn--danger', title: t('remove'), onclick: function () { m.fields.splice(i, 1); drawFields(); } }, '✕'),
          f.type === 'checkbox' ? null : h('input', { class: 'atlas-input wide', placeholder: t('field_default'), value: f.default == null || typeof f.default === 'object' ? '' : f.default, oninput: function (e) { f.default = e.target.value; }, style: f.type === 'repeater' ? { display: 'none' } : {} }),
          f.type === 'select' ? opts : null, f.type === 'repeater' ? sub : null,
          f.type === 'text' || f.type === 'textarea' ? tr : null);
      }));
    }
    drawFields();
    function ta(prop, label, rows_) {
      var el = h('textarea', { class: 'atlas-textarea atlas-code', spellcheck: 'false', value: m[prop] || '', style: { minHeight: rows_ + 'px' }, oninput: function (e) { m[prop] = e.target.value; } });
      tabKeys(el);
      return h('div', { class: 'atlas-field' }, h('label', null, label), el);
    }
    var cont = h('input', { type: 'checkbox', onchange: function (e) { m.container = e.target.checked; } }); cont.checked = !!m.container;
    function close() { modal.remove(); }
    function saveBlock() {
      err.textContent = '';
      var body = { label: m.label, type: m.type, category: m.category || 'Custom', icon: m.icon || '', container: !!m.container, html: m.html, css: m.css, js: m.js,
        fields: m.fields.map(function (f) {
          var o = { name: f.name, label: f.label, type: f.type, default: f.default, translatable: !!f.translatable };
          if (f.type === 'select') o.options = f.options || {};
          if (f.type === 'repeater') o.fields = (f.fields && f.fields.length ? f.fields : [{ name: 'title', label: 'Title', type: 'text', default: '' }]);
          return o;
        }) };
      var url = existing ? cfg.urls.blockBase + '/' + existing.id : cfg.urls.blocks;
      api(url, existing ? 'PUT' : 'POST', body).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); }).then(function (r) {
        if (!r.ok) { err.textContent = r.j.errors ? r.j.errors[Object.keys(r.j.errors)[0]][0] : (r.j.message || t('save_failed')); return; }
        var def = r.j.definition;
        defs[def.type] = def;
        var bi = cfg.blocks.findIndex(function (b) { return b.type === def.type; });
        if (bi >= 0) cfg.blocks[bi] = def; else cfg.blocks.push(def);
        var ci = cfg.customBlocks.findIndex(function (b) { return b.id === r.j.block.id; });
        if (ci >= 0) cfg.customBlocks[ci] = r.j.block; else cfg.customBlocks.push(r.j.block);
        toast(t('block_saved')); close();
        if (state.tab === 'blocks') renderPalette();
        scheduleRender(0);
      }).catch(function () { err.textContent = t('save_failed'); });
    }
    function del() {
      if (!existing || !confirm(t('delete_block_confirm'))) return;
      api(cfg.urls.blockBase + '/' + existing.id, 'DELETE').then(function () {
        delete defs[existing.type];
        cfg.blocks = cfg.blocks.filter(function (b) { return b.type !== existing.type; });
        cfg.customBlocks = cfg.customBlocks.filter(function (b) { return b.id !== existing.id; });
        toast(t('block_deleted')); close();
        if (state.tab === 'blocks') renderPalette();
        scheduleRender(0);
      });
    }
    var modal = h('div', { class: 'atlas-modal' }, h('div', { class: 'atlas-modal__card atlas-builder' },
      h('div', { class: 'atlas-modal__head' }, t('block_builder'),
        h('div', { class: 'atlas-row' }, existing ? h('button', { class: 'atlas-btn atlas-btn--danger', onclick: del }, t('delete')) : null,
          h('button', { class: 'atlas-btn', onclick: close }, t('cancel')), h('button', { class: 'atlas-btn atlas-btn--primary', onclick: saveBlock }, t('save')))),
      h('div', { class: 'atlas-builder__cols' },
        h('div', null,
          h('div', { class: 'atlas-field' }, h('label', null, t('block_label')), inp('label')),
          h('div', { class: 'atlas-field' }, h('label', null, t('block_type')), typeIn),
          h('div', { class: 'atlas-row' }, h('div', { class: 'atlas-field', style: { flex: 1 } }, h('label', null, t('block_category')), inp('category')), h('div', { class: 'atlas-field', style: { width: '90px' } }, h('label', null, t('block_icon')), inp('icon'))),
          h('div', { class: 'atlas-field atlas-field--check' }, h('label', null, cont, t('block_container'))),
          h('div', { class: 'atlas-cat' }, t('fields')), rows,
          h('button', { class: 'atlas-btn', onclick: function () { m.fields.push({ name: 'field_' + (m.fields.length + 1), label: '', type: 'text', default: '' }); drawFields(); } }, t('add_field')),
          err),
        h('div', null, ta('html', t('tpl_html'), 200), ta('css', t('tpl_css'), 110), ta('js', t('tpl_js'), 110), h('p', { class: 'atlas-hint' }, t('tpl_help'))))));
    modal.addEventListener('mousedown', function (e) { if (e.target === modal) { /* keep open: avoid losing work */ } });
    document.body.append(modal);
  }

  /* -------------------------------------------------------------- canvas */
  var renderTimer, renderSeq = 0, savedScroll = 0;

  function scheduleRender(delay) {
    clearTimeout(renderTimer);
    renderTimer = setTimeout(doRender, delay == null ? 160 : delay);
  }
  function doRender() {
    var seq = ++renderSeq;
    var win = els.iframe.contentWindow;
    if (win && win.document && win.document.body) savedScroll = win.scrollY;
    api(cfg.urls.render, 'POST', { title: titleValue() || state.page.title, content: state.tree, css: state.page.css, head: state.page.head, meta: metaPayload(), locale: state.locale })
      .then(function (r) { return r.text().then(function (t) { return { ok: r.ok, t: t }; }); })
      .then(function (r) {
        if (seq !== renderSeq) return;
        if (!r.ok) return toast(t('canvas_failed') + ' (' + r.t.slice(0, 120) + ')', true);
        els.iframe.srcdoc = r.t;
      }).catch(function () { toast(t('canvas_failed'), true); });
  }

  function frameDoc() { try { return els.iframe.contentDocument; } catch (e) { return null; } }

  function onFrameLoad() {
    var doc = frameDoc();
    if (!doc) return;
    applyCanvasTheme();
    els.iframe.contentWindow.scrollTo(0, savedScroll);
    doc.addEventListener('mousemove', function (e) {
      if (drag) return;
      var w = e.target.closest && e.target.closest('[data-atlas-id]');
      hoverId(w ? w.dataset.atlasId : null);
    });
    doc.addEventListener('mouseleave', function () { hoverId(null); });
    doc.addEventListener('click', function (e) {
      e.preventDefault();
      var w = e.target.closest && e.target.closest('[data-atlas-id]');
      if (state.sheet) closePanel();
      select(w ? w.dataset.atlasId : null);
    });
    doc.addEventListener('submit', function (e) { e.preventDefault(); });
    doc.addEventListener('keydown', onKey);
    doc.addEventListener('scroll', updateOverlay, true);
    doc.querySelectorAll('img').forEach(function (i) { i.addEventListener('load', updateOverlay); });
    if (state.selected) { var el = elOf(state.selected); if (!el) { state.selected = null; renderInspector(); } }
    updateOverlay();
  }

  function elOf(id) {
    var doc = frameDoc();
    return id && doc ? doc.querySelector('[data-atlas-id="' + id + '"]') : null;
  }
  function frameOffset() {
    var f = els.iframe.getBoundingClientRect(), s = els.stage.getBoundingClientRect();
    return { x: f.left - s.left, y: f.top - s.top };
  }
  function stageRect(el) {
    var r = el.getBoundingClientRect(), o = frameOffset();
    return { left: r.left + o.x, top: r.top + o.y, width: r.width, height: r.height };
  }
  function place(box, r) {
    box.style.left = r.left + 'px'; box.style.top = r.top + 'px';
    box.style.width = r.width + 'px'; box.style.height = r.height + 'px';
  }

  var hovered = null;
  function hoverId(id) { hovered = id; updateOverlay(); }
  function select(id) {
    var changed = id !== state.selected;
    state.selected = id;
    if (changed) renderInspector();
    renderLayers();
    updateOverlay();
  }

  function updateOverlay() {
    if (els.navBtns) els.navBtns.inspector.classList.toggle('atlas-nav__btn--has', !!state.selected);
    // hover
    var he = hovered && hovered !== state.selected ? elOf(hovered) : null;
    els.hoverBox.hidden = !he;
    if (he) { place(els.hoverBox, stageRect(he)); els.hoverTag.textContent = label(he.dataset.atlasType); }

    // selection
    var se = state.selected ? elOf(state.selected) : null;
    els.selBox.hidden = !se;
    if (!se) return;
    var r = stageRect(se);
    place(els.selBox, r);
    var f = find(state.selected);
    els.selBox.replaceChildren(h('div', { class: 'atlas-box__bar' + (r.top < 34 ? ' atlas-box__bar--in' : '') },
      h('span', { class: 'atlas-grip', title: t('drag_to_move'), onpointerdown: function (e) { if (e.button === 0) beginPress(e, { kind: 'move', id: state.selected }); } }, '⠿ '),
      h('span', { class: 'atlas-box__label' }, f ? label(f.node.type) : ''),
      h('button', { title: t('move_up'), onclick: function () { moveSelected(-1); } }, '↑'),
      h('button', { title: t('move_down'), onclick: function () { moveSelected(1); } }, '↓'),
      f && f.parent ? h('button', { title: t('select_parent'), onclick: function () { select(f.parent.id); } }, '↰') : null,
      h('button', { title: t('duplicate'), onclick: duplicateSelected }, '⧉'),
      h('button', { title: t('delete'), onclick: deleteSelected }, '✕')));
  }

  /* ---------------------------------------------------------- drag & drop */
  var press = null, drag = null;

  // A press becomes a drag after a few pixels; otherwise it is a click.
  function beginPress(e, payload) {
    e.preventDefault();
    press = { payload: payload, x: e.clientX, y: e.clientY };
    window.addEventListener('pointermove', onPressMove);
    window.addEventListener('pointerup', onPressUp);
    window.addEventListener('pointercancel', cleanupPress);
  }
  function onPressMove(e) {
    if (!press) return;
    if (Math.abs(e.clientX - press.x) + Math.abs(e.clientY - press.y) < 5) return;
    var payload = press.payload;
    cleanupPress();
    startDrag(payload, e);
  }
  function onPressUp() {
    var payload = press && press.payload;
    cleanupPress();
    if (payload && payload.kind === 'new') insertDefault(payload.type);
  }
  function cleanupPress() {
    press = null;
    window.removeEventListener('pointermove', onPressMove);
    window.removeEventListener('pointerup', onPressUp);
    window.removeEventListener('pointercancel', cleanupPress);
  }

  function startDrag(payload, e) {
    var name = payload.kind === 'new' ? '＋ ' + label(payload.type) : '⠿ ' + label((find(payload.id) || { node: {} }).node.type);
    drag = { payload: payload, target: null, ghost: h('div', { class: 'atlas-ghost' }, name), shield: h('div', { class: 'atlas-shield' }), x: e.clientX, y: e.clientY, scroll: 0 };
    document.body.append(drag.ghost);
    els.stage.append(drag.shield);
    if (payload.kind === 'move') { var el = elOf(payload.id); if (el) el.classList.add('atlas-dragging'); }
    drag.timer = setInterval(function () {
      if (drag.scroll) { els.iframe.contentWindow.scrollBy(0, drag.scroll); updateDrop(); }
    }, 16);
    window.addEventListener('pointermove', onDragMove);
    window.addEventListener('pointerup', onDragEnd);
    window.addEventListener('pointercancel', onDragCancel);
    window.addEventListener('keydown', onDragKey, true);
    onDragMove(e);
  }
  function onDragMove(e) {
    drag.x = e.clientX; drag.y = e.clientY;
    drag.ghost.style.left = e.clientX + 'px'; drag.ghost.style.top = e.clientY + 'px';
    var fr = els.iframe.getBoundingClientRect();
    drag.scroll = 0;
    if (e.clientX >= fr.left && e.clientX <= fr.right) {
      if (e.clientY - fr.top < 50) drag.scroll = -12;
      else if (fr.bottom - e.clientY < 50) drag.scroll = 12;
    }
    updateDrop();
  }
  function onDragKey(e) { if (e.key === 'Escape') { e.stopPropagation(); endDrag(false); } }
  function onDragEnd() { endDrag(true); }
  function onDragCancel() { endDrag(false); }

  function endDrag(apply) {
    var d = drag;
    drag = null;
    clearInterval(d.timer);
    d.ghost.remove(); d.shield.remove();
    window.removeEventListener('pointermove', onDragMove);
    window.removeEventListener('pointerup', onDragEnd);
    window.removeEventListener('pointercancel', onDragCancel);
    window.removeEventListener('keydown', onDragKey, true);
    els.dropLine.hidden = true;
    var el = d.payload.kind === 'move' ? elOf(d.payload.id) : null;
    if (el) el.classList.remove('atlas-dragging');
    if (!apply || !d.target) return;

    var t = d.target;
    if (d.payload.kind === 'new') {
      addBlock(d.payload.type, t.parentId, t.index);
    } else {
      var node = removeNode(d.payload.id);
      if (!node) return;
      insertNode(t.parentId, t.index, node);
      state.selected = node.id;
      commit();
    }
  }

  function parentWrapper(el) { var p = el.parentElement; return p ? p.closest('[data-atlas-id]') : null; }
  function wrappersIn(containerEl, doc, skipId) {
    var scope = containerEl || doc.body;
    return Array.prototype.filter.call(scope.querySelectorAll('[data-atlas-id]'), function (c) {
      return parentWrapper(c) === containerEl && c.dataset.atlasId !== skipId;
    });
  }
  function overlapsRow(a, b) { return a.top < b.bottom && b.top < a.bottom; }

  // Work out where the pointer would drop: { parentId, index } + an indicator.
  function updateDrop() {
    var doc = frameDoc();
    var fr = els.iframe.getBoundingClientRect();
    var x = drag.x - fr.left, y = drag.y - fr.top;
    drag.target = null;
    els.dropLine.hidden = true;
    if (!doc || x < 0 || y < 0 || x > fr.width || y > fr.height) return;

    var skip = drag.payload.kind === 'move' ? drag.payload.id : null;
    var hit = doc.elementFromPoint(x, y);
    var w = hit && hit.closest ? hit.closest('[data-atlas-id]') : null;
    var containerEl = null, mode = 'inside', ref = null, before = true;

    if (w) {
      var r = w.getBoundingClientRect();
      var edge = Math.min(14, r.height / 4);
      var nested = w.hasAttribute('data-atlas-container');
      if (nested && y > r.top + edge && y < r.bottom - edge) {
        containerEl = w;
      } else {
        containerEl = parentWrapper(w);
        ref = w;
        var sibs = wrappersIn(containerEl, doc, skip);
        var horizontal = sibs.some(function (s) { return s !== w && overlapsRow(s.getBoundingClientRect(), r) && s.getBoundingClientRect().left !== r.left; });
        before = horizontal ? x < r.left + r.width / 2 : y < r.top + r.height / 2;
        mode = 'sibling';
        if (horizontal) mode = 'sibling-h';
      }
    }

    var siblings = wrappersIn(containerEl, doc, skip);
    var index;
    if (mode === 'inside') {
      if (!siblings.length) {
        index = 0;
      } else {
        // nearest child by distance, before/after by pointer position
        var best = null, bd = Infinity;
        siblings.forEach(function (s) {
          var b = s.getBoundingClientRect();
          var dx = Math.max(b.left - x, 0, x - b.right), dy = Math.max(b.top - y, 0, y - b.bottom);
          var dist = dx * dx + dy * dy;
          if (dist < bd) { bd = dist; best = s; }
        });
        var br = best.getBoundingClientRect();
        var row = siblings.some(function (s) { return s !== best && overlapsRow(s.getBoundingClientRect(), br) && s.getBoundingClientRect().left !== br.left; });
        before = row ? x < br.left + br.width / 2 : y < br.top + br.height / 2;
        ref = best;
        mode = row ? 'sibling-h' : 'sibling';
      }
    }
    if (ref) index = siblings.indexOf(ref) + (before ? 0 : 1);

    var parentId = containerEl ? containerEl.dataset.atlasId : null;
    drag.target = { parentId: parentId, index: index };

    // indicator
    var o = frameOffset();
    var line = els.dropLine;
    line.hidden = false;
    line.classList.remove('atlas-drop--box');
    if (!ref) {
      var cr = (containerEl || doc.body).getBoundingClientRect();
      if (!containerEl) cr = { left: 0, top: 0, width: fr.width, height: fr.height };
      line.classList.add('atlas-drop--box');
      place(line, { left: cr.left + o.x, top: cr.top + o.y, width: cr.width, height: cr.height });
    } else {
      var rr = ref.getBoundingClientRect();
      if (mode === 'sibling-h') {
        place(line, { left: (before ? rr.left : rr.right) + o.x - 2, top: rr.top + o.y, width: 4, height: rr.height });
      } else {
        place(line, { left: rr.left + o.x, top: (before ? rr.top : rr.bottom) + o.y - 2, width: rr.width, height: 4 });
      }
    }
  }

  /* ------------------------------------------------------------ save etc. */
  function metaPayload() {
    var m = state.page.meta;
    return { description: m.description || null, theme: m.theme || null, theme_toggle: m.theme_toggle == null ? null : !!m.theme_toggle, accent: m.accent || null, accent_dark: m.accent_dark || null, font: m.font || null, heading_font: m.heading_font || null, spacing: m.spacing || null, og_image: m.og_image || null, titles: m.titles || {}, descriptions: m.descriptions || {} };
  }
  function payload() {
    var p = state.page;
    return { title: p.title, slug: p.slug, status: p.status, content: state.tree, css: p.css, js: p.js, head: p.head, meta: metaPayload() };
  }
  function save() {
    if (state.saving) return Promise.resolve(false);
    state.saving = true;
    els.save.disabled = true;
    setStatus(t('saving'));
    return api(cfg.urls.save, 'PUT', payload()).then(function (r) {
      return r.json().then(function (j) { return { ok: r.ok, j: j }; });
    }).then(function (r) {
      if (!r.ok) {
        var msg = r.j.message || t('save_failed');
        if (r.j.errors) msg = r.j.errors[Object.keys(r.j.errors)[0]][0];
        toast(msg, true); setStatus(t('not_saved'));
        return false;
      }
      state.dirty = false;
      clearDraft();
      state.page.slug = r.j.slug;
      els.slug.value = r.j.slug;
      els.viewLink.href = viewHref(r.j.url);
      setStatus(t('saved')); toast(t('saved'));
      return true;
    }).catch(function () { toast(t('save_failed'), true); setStatus(t('not_saved')); return false; })
      .then(function (ok) { state.saving = false; els.save.disabled = false; return ok; });
  }
  // Drafts have no public URL (it 404s), so "View" shows the preview instead.
  function viewHref(publicUrl) { return state.page.status === 'published' ? publicUrl : cfg.urls.preview; }
  function preview() {
    var w = window.open('', '_blank');
    var go = function (ok) { if (w) { if (ok) w.location = cfg.urls.preview; else w.close(); } };
    if (state.dirty) save().then(go); else go(true);
  }
  function leaveGuard(e) {
    if (state.dirty && !confirm(t('unsaved_confirm'))) e.preventDefault();
  }
  window.addEventListener('beforeunload', function (e) { if (state.dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* ------------------------------------------------------------ shortcuts */
  function onKey(e) {
    var t = e.target;
    var typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable);
    var mod = e.ctrlKey || e.metaKey;
    var k = e.key.toLowerCase();
    if (mod && k === 's') { e.preventDefault(); save(); return; }
    if (typing) return;
    if (mod && k === 'z') { e.preventDefault(); restore(e.shiftKey ? hIndex + 1 : hIndex - 1); }
    else if (mod && k === 'y') { e.preventDefault(); restore(hIndex + 1); }
    else if (mod && k === 'd') { e.preventDefault(); duplicateSelected(); }
    else if (mod && k === 'c') { if (state.selected) { e.preventDefault(); copySelected(false); } }
    else if (mod && k === 'x') { if (state.selected) { e.preventDefault(); copySelected(true); } }
    else if (mod && k === 'v') { e.preventDefault(); pasteClipboard(); }
    else if (e.key === 'Delete' || e.key === 'Backspace') { if (state.selected) { e.preventDefault(); deleteSelected(); } }
    else if (e.key === 'Escape') select(null);
  }
  document.addEventListener('keydown', onKey);

  /* ----------------------------------------------------------------- boot */
  buildShell();
  setDevice('desktop');
  setTab('blocks');
  renderInspector();
  syncUndo();
  scheduleRender(0);
  offerDraft();

  // Small hook for tests / power users.
  window.AtlasEditor = { state: state, save: save, select: select, addBlock: addBlock };
})();
