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

  var COMMON_FIELDS = [{ type: 'text', name: 'css_class', label: 'CSS classes', default: '' }];
  if (cfg.customCode) {
    COMMON_FIELDS.push(
      { type: 'text', name: 'html_id', label: 'HTML id', default: '' },
      { type: 'code', name: 'custom_css', label: 'Custom CSS  ({{selector}} = this block)', default: '', language: 'css' }
    );
  }

  /* ---------------------------------------------------------------- state */
  var state = {
    page: cfg.page,
    tree: normalize(cfg.page.content || []),
    selected: null,
    device: 'desktop',
    tab: 'blocks',
    dirty: false,
    saving: false
  };
  state.page.meta = Array.isArray(state.page.meta) ? {} : (state.page.meta || {});
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
  function markDirty() { state.dirty = true; setStatus('Unsaved changes'); }

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
    els.title = h('input', { class: 'atlas-input atlas-top__title', value: p.title, placeholder: 'Page title', oninput: function (e) { p.title = e.target.value; markDirty(); } });
    els.slug = h('input', { class: 'atlas-input atlas-top__slug', value: p.slug, title: 'URL slug', oninput: function (e) { p.slug = e.target.value; markDirty(); } });
    els.statusSel = h('select', { class: 'atlas-select', style: { width: 'auto' }, onchange: function (e) { p.status = e.target.value; markDirty(); } },
      h('option', { value: 'draft' }, 'Draft'), h('option', { value: 'published' }, 'Published'));
    els.statusSel.value = p.status;
    els.status = h('span', { class: 'atlas-status' }, 'Saved');
    els.undo = h('button', { class: 'atlas-btn', title: 'Undo (Ctrl+Z)', onclick: function () { restore(hIndex - 1); } }, '↶');
    els.redo = h('button', { class: 'atlas-btn', title: 'Redo (Ctrl+Shift+Z)', onclick: function () { restore(hIndex + 1); } }, '↷');
    els.devices = {};
    var devices = h('div', { class: 'atlas-top__group' }, [['desktop', '🖥'], ['tablet', '▭'], ['mobile', '📱']].map(function (d) {
      return els.devices[d[0]] = h('button', { class: 'atlas-btn', title: d[0], onclick: function () { setDevice(d[0]); } }, d[1]);
    }));
    els.save = h('button', { class: 'atlas-btn atlas-btn--primary', onclick: function () { save(); } }, 'Save');
    els.viewLink = h('a', { class: 'atlas-btn', href: cfg.urls.public, target: '_blank', rel: 'noopener' }, 'View ↗');

    var top = h('div', { class: 'atlas-top' },
      h('a', { class: 'atlas-btn', href: cfg.urls.pages, title: 'All pages', onclick: leaveGuard }, '← Pages'),
      els.title, els.slug, els.statusSel,
      h('span', { class: 'atlas-top__spacer' }),
      els.status, els.undo, els.redo, devices,
      h('button', { class: 'atlas-btn', onclick: preview }, 'Preview ▶'),
      els.viewLink, els.save);

    // Left panel
    els.leftBody = h('div', { class: 'atlas-scroll' });
    els.tabs = {};
    var tabDefs = [['blocks', 'Blocks'], ['layers', 'Layers']];
    if (cfg.customCode) tabDefs.push(['page', 'Page code']);
    var tabs = h('div', { class: 'atlas-tabs' }, tabDefs.map(function (t) {
      return els.tabs[t[0]] = h('button', { class: 'atlas-tab', onclick: function () { setTab(t[0]); } }, t[1]);
    }));
    var left = h('aside', { class: 'atlas-side' }, tabs, els.leftBody);

    // Stage
    els.iframe = h('iframe', { class: 'atlas-frame', sandbox: 'allow-same-origin', title: 'Canvas' });
    els.overlay = h('div', { class: 'atlas-overlay' });
    els.stage = h('main', { class: 'atlas-stage' }, els.iframe, els.overlay);

    // Right
    els.inspector = h('div', { class: 'atlas-scroll' });
    var right = h('aside', { class: 'atlas-side atlas-side--right' }, els.inspector);

    root.append(h('div', { class: 'atlas-shell' }, top, h('div', { class: 'atlas-body' }, left, els.stage, right)));

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
  function setStatus(s) { if (els.status) els.status.textContent = s; }
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
    var search = h('input', { class: 'atlas-input', placeholder: 'Search blocks…', style: { marginBottom: '12px' }, oninput: function (e) { q = e.target.value.toLowerCase(); draw(); } });
    var holder = h('div');
    els.leftBody.replaceChildren(search, holder);
    function draw() {
      var cats = {};
      cfg.blocks.forEach(function (b) {
        if (q && b.label.toLowerCase().indexOf(q) === -1) return;
        (cats[b.category] = cats[b.category] || []).push(b);
      });
      holder.replaceChildren.apply(holder, Object.keys(cats).map(function (c) {
        return h('div', null,
          h('div', { class: 'atlas-cat' }, c),
          h('div', { class: 'atlas-palette' }, cats[c].map(function (b) {
            return h('div', { class: 'atlas-block-item', title: 'Drag onto the canvas, or click to add', onmousedown: function (e) { if (e.button === 0) beginPress(e, { kind: 'new', type: b.type }); } },
              h('span', { class: 'atlas-block-item__icon' }, b.icon), b.label);
          })));
      }));
    }
    draw();
  }

  function renderLayers() {
    if (state.tab !== 'layers') return;
    var box = h('div');
    (function walk(list, depth) {
      list.forEach(function (n) {
        box.append(h('div', {
          class: 'atlas-layer' + (n.id === state.selected ? ' atlas-layer--sel' : ''),
          style: { paddingLeft: (6 + depth * 14) + 'px' },
          onclick: function () { select(n.id); },
          onmouseenter: function () { hoverId(n.id); },
          onmouseleave: function () { hoverId(null); }
        }, h('span', null, icon(n.type)), label(n.type), h('span', { class: 'atlas-layer__hint' }, hint(n))));
        walk(n.children, depth + 1);
      });
    })(state.tree, 0);
    if (!state.tree.length) box.append(h('p', { class: 'atlas-hint' }, 'Nothing here yet — add a block from the Blocks tab.'));
    els.leftBody.replaceChildren(box);
  }

  function renderPageCode() {
    var p = state.page;
    function code(name, text, lang, rerender) {
      var ta = h('textarea', { class: 'atlas-textarea atlas-code', spellcheck: 'false', value: p[name] || '', oninput: function (e) { p[name] = e.target.value; markDirty(); if (rerender) scheduleRender(400); } });
      tabKeys(ta);
      return h('div', { class: 'atlas-field' }, h('label', null, text, ' ', h('a', { href: '#', onclick: function (e) { e.preventDefault(); openCode(text, ta.value, function (v) { ta.value = v; ta.dispatchEvent(new Event('input')); }); } }, 'expand')), ta);
    }
    els.leftBody.replaceChildren(
      h('div', { class: 'atlas-field' }, h('label', null, 'Meta description'),
        h('textarea', { class: 'atlas-textarea', style: { minHeight: '50px' }, value: state.page.meta.description || '', oninput: function (e) { state.page.meta.description = e.target.value; markDirty(); } })),
      code('css', 'Page CSS', 'css', true),
      code('js', 'Page JavaScript (runs on the live page and Preview)', 'js', false),
      code('head', 'Extra <head> HTML', 'html', true),
      h('p', { class: 'atlas-hint' }, 'Scripts do not run inside the editor canvas. Use Preview ▶ to test JavaScript.')
    );
  }

  /* ------------------------------------------------------------ inspector */
  function renderInspector() {
    var f = state.selected && find(state.selected);
    if (!f) {
      els.inspector.replaceChildren(h('p', { class: 'atlas-hint' }, 'Select a block on the canvas to edit it.'), h('p', { class: 'atlas-hint' }, 'Shortcuts: Ctrl+S save · Ctrl+Z undo · Ctrl+D duplicate · Del delete'));
      return;
    }
    var n = f.node;
    var def = defs[n.type];
    var body = [h('div', { class: 'atlas-insp-head' },
      h('h3', null, icon(n.type) + '  ' + label(n.type)),
      h('div', { class: 'atlas-row' },
        h('button', { class: 'atlas-btn', title: 'Duplicate', onclick: duplicateSelected }, '⧉'),
        h('button', { class: 'atlas-btn atlas-btn--danger', title: 'Delete', onclick: deleteSelected }, '✕')))];
    if (!def) body.push(h('p', { class: 'atlas-hint' }, 'This block type is not registered. Its data is preserved.'));
    (def ? def.fields : []).forEach(function (fd) { body.push(fieldEl(n, fd)); });
    body.push(h('div', { class: 'atlas-section-title' }, 'Advanced'));
    COMMON_FIELDS.forEach(function (fd) { body.push(fieldEl(n, fd)); });
    els.inspector.replaceChildren.apply(els.inspector, body);
  }

  function fieldEl(node, fd) {
    var cur = node.props[fd.name];
    if (cur === undefined) cur = fd.default;
    var key = node.id + ':' + fd.name;
    function set(v) { node.props[fd.name] = v; touch(key); }
    var input;

    switch (fd.type) {
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
        input = h('input', { class: 'atlas-input', type: 'number', value: cur, min: fd.min, max: fd.max, step: fd.step, oninput: function (e) { set(e.target.value === '' ? 0 : Number(e.target.value)); } });
        break;
      case 'color':
        var txt = h('input', { class: 'atlas-input', value: cur || '', placeholder: 'none', oninput: function (e) { sw.value = /^#[0-9a-f]{6}$/i.test(e.target.value) ? e.target.value : sw.value; set(e.target.value); } });
        var sw = h('input', { class: 'atlas-swatch', type: 'color', value: /^#[0-9a-f]{6}$/i.test(cur || '') ? cur : '#000000', oninput: function (e) { txt.value = e.target.value; set(e.target.value); } });
        input = h('div', { class: 'atlas-row' }, sw, txt);
        break;
      case 'textarea':
        input = h('textarea', { class: 'atlas-textarea', value: cur || '', oninput: function (e) { set(e.target.value); } });
        break;
      case 'code':
        var ta = h('textarea', { class: 'atlas-textarea atlas-code', spellcheck: 'false', value: cur || '', oninput: function (e) { set(e.target.value); } });
        tabKeys(ta);
        return h('div', { class: 'atlas-field' }, h('label', null, fd.label, ' ', h('a', { href: '#', onclick: function (e) { e.preventDefault(); openCode(fd.label, ta.value, function (v) { ta.value = v; set(v); }); } }, 'expand')), ta);
      case 'image':
        var url = h('input', { class: 'atlas-input', value: cur || '', placeholder: 'https://… or upload', oninput: function (e) { set(e.target.value); } });
        var file = h('input', { type: 'file', accept: 'image/*', hidden: true, onchange: function (e) { upload(e.target.files[0], function (u) { url.value = u; set(u); }); } });
        input = h('div', null, h('div', { class: 'atlas-row' }, url, h('button', { class: 'atlas-btn', onclick: function () { file.click(); } }, 'Upload')), file);
        break;
      default:
        input = h('input', { class: 'atlas-input', value: cur == null ? '' : cur, placeholder: fd.placeholder, oninput: function (e) { set(e.target.value); } });
    }
    return h('div', { class: 'atlas-field' }, h('label', null, fd.label), input);
  }

  function upload(file, done) {
    if (!file) return;
    var fd = new FormData();
    fd.append('file', file);
    api(cfg.urls.upload, 'POST', fd, true).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (r) {
        if (!r.ok) return toast((r.j.errors && r.j.errors.file && r.j.errors.file[0]) || 'Upload failed', true);
        done(r.j.url);
      }).catch(function () { toast('Upload failed', true); });
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
      h('div', { class: 'atlas-modal__head' }, title, h('button', { class: 'atlas-btn atlas-btn--primary', onclick: close }, 'Done')), ta));
    function close() { done(ta.value); modal.remove(); }
    modal.addEventListener('mousedown', function (e) { if (e.target === modal) close(); });
    document.body.append(modal);
    ta.focus();
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
    api(cfg.urls.render, 'POST', { title: state.page.title, content: state.tree, css: state.page.css, head: state.page.head })
      .then(function (r) { return r.text().then(function (t) { return { ok: r.ok, t: t }; }); })
      .then(function (r) {
        if (seq !== renderSeq) return;
        if (!r.ok) return toast('Canvas render failed (' + r.t.slice(0, 120) + ')', true);
        els.iframe.srcdoc = r.t;
      }).catch(function () { toast('Canvas render failed', true); });
  }

  function frameDoc() { try { return els.iframe.contentDocument; } catch (e) { return null; } }

  function onFrameLoad() {
    var doc = frameDoc();
    if (!doc) return;
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
      h('span', { class: 'atlas-grip', title: 'Drag to move', onmousedown: function (e) { if (e.button === 0) beginPress(e, { kind: 'move', id: state.selected }); } }, '⠿ '),
      h('span', { class: 'atlas-box__label' }, f ? label(f.node.type) : ''),
      h('button', { title: 'Move up', onclick: function () { moveSelected(-1); } }, '↑'),
      h('button', { title: 'Move down', onclick: function () { moveSelected(1); } }, '↓'),
      f && f.parent ? h('button', { title: 'Select parent', onclick: function () { select(f.parent.id); } }, '↰') : null,
      h('button', { title: 'Duplicate', onclick: duplicateSelected }, '⧉'),
      h('button', { title: 'Delete', onclick: deleteSelected }, '✕')));
  }

  /* ---------------------------------------------------------- drag & drop */
  var press = null, drag = null;

  // A press becomes a drag after a few pixels; otherwise it is a click.
  function beginPress(e, payload) {
    e.preventDefault();
    press = { payload: payload, x: e.clientX, y: e.clientY };
    window.addEventListener('mousemove', onPressMove);
    window.addEventListener('mouseup', onPressUp);
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
    window.removeEventListener('mousemove', onPressMove);
    window.removeEventListener('mouseup', onPressUp);
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
    window.addEventListener('mousemove', onDragMove);
    window.addEventListener('mouseup', onDragEnd);
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

  function endDrag(apply) {
    var d = drag;
    drag = null;
    clearInterval(d.timer);
    d.ghost.remove(); d.shield.remove();
    window.removeEventListener('mousemove', onDragMove);
    window.removeEventListener('mouseup', onDragEnd);
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
  function payload() {
    var p = state.page;
    return { title: p.title, slug: p.slug, status: p.status, content: state.tree, css: p.css, js: p.js, head: p.head, meta: { description: p.meta.description || null } };
  }
  function save() {
    if (state.saving) return Promise.resolve(false);
    state.saving = true;
    els.save.disabled = true;
    setStatus('Saving…');
    return api(cfg.urls.save, 'PUT', payload()).then(function (r) {
      return r.json().then(function (j) { return { ok: r.ok, j: j }; });
    }).then(function (r) {
      if (!r.ok) {
        var msg = r.j.message || 'Save failed';
        if (r.j.errors) msg = r.j.errors[Object.keys(r.j.errors)[0]][0];
        toast(msg, true); setStatus('Not saved');
        return false;
      }
      state.dirty = false;
      state.page.slug = r.j.slug;
      els.slug.value = r.j.slug;
      els.viewLink.href = r.j.url;
      setStatus('Saved'); toast('Saved');
      return true;
    }).catch(function () { toast('Save failed — network error', true); setStatus('Not saved'); return false; })
      .then(function (ok) { state.saving = false; els.save.disabled = false; return ok; });
  }
  function preview() {
    var w = window.open('', '_blank');
    var go = function (ok) { if (w) { if (ok) w.location = cfg.urls.preview; else w.close(); } };
    if (state.dirty) save().then(go); else go(true);
  }
  function leaveGuard(e) {
    if (state.dirty && !confirm('You have unsaved changes. Leave anyway?')) e.preventDefault();
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

  // Small hook for tests / power users.
  window.AtlasEditor = { state: state, save: save, select: select, addBlock: addBlock };
})();
