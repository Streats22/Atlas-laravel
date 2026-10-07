/*! Atlas runtime — animations, counters, typewriter, carousel, portfolio filter, lightbox, parallax, theme toggle, Lottie. */
(function () {
  'use strict';

  var d = document, root = d.documentElement;
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var darkMq = window.matchMedia ? matchMedia('(prefers-color-scheme: dark)') : { matches: false };

  function qsa(sel, scope) { return Array.prototype.slice.call((scope || d).querySelectorAll(sel)); }
  function once(el, key) { if (el['__atlas_' + key]) return false; el['__atlas_' + key] = true; return true; }
  function emit(name, detail) { d.dispatchEvent(new CustomEvent('atlas:' + name, { detail: detail })); }

  /* ----------------------------------------------- reveal on scroll */
  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (!e.isIntersecting) return;
      io.unobserve(e.target);
      reveal(e.target);
    });
  }, { threshold: 0.15, rootMargin: '0px 0px -6% 0px' }) : null;

  function reveal(el) {
    el.classList.add('is-in');
    qsa('[data-atlas-count]', el).forEach(countUp);
    if (el.hasAttribute('data-atlas-count')) countUp(el);
    emit('reveal', { el: el });
  }
  function watch(el) { if (!io) return reveal(el); io.observe(el); }

  /* ---------------------------------------------------- counters */
  function countUp(el) {
    if (!once(el, 'count')) return;
    var to = parseFloat(el.getAttribute('data-atlas-count')) || 0;
    var dur = parseInt(el.getAttribute('data-duration'), 10) || 1800;
    var dec = (String(el.getAttribute('data-atlas-count')).split('.')[1] || '').length;
    var lang = root.getAttribute('lang') || undefined;
    function fmt(v) { try { return v.toLocaleString(lang, { minimumFractionDigits: dec, maximumFractionDigits: dec }); } catch (e) { return v.toFixed(dec); } }
    if (reduce) { el.textContent = fmt(to); return; }
    var start = null;
    (function step(ts) {
      if (start === null) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      el.textContent = fmt(to * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(step);
    })(performance.now());
  }

  /* -------------------------------------------------- typewriter */
  function typewriter(el) {
    if (!once(el, 'tw')) return;
    var words = [];
    try { words = JSON.parse(el.getAttribute('data-words') || '[]'); } catch (e) {}
    var target = el.querySelector('.atlas-typewriter__word');
    if (!words.length || !target || reduce) return;
    var speed = parseInt(el.getAttribute('data-speed'), 10) || 70;
    var pause = parseInt(el.getAttribute('data-pause'), 10) || 1400;
    var loop = el.getAttribute('data-loop') !== '0';
    var w = 0, i = 0, del = false;
    target.textContent = '';
    (function tick() {
      var word = words[w];
      if (!del) {
        target.textContent = word.slice(0, ++i);
        if (i === word.length) {
          if (!loop && w === words.length - 1) return;
          del = true; return setTimeout(tick, pause);
        }
      } else {
        target.textContent = word.slice(0, --i);
        if (i === 0) { del = false; w = (w + 1) % words.length; }
      }
      setTimeout(tick, del ? speed / 2 : speed);
    })();
  }

  /* ---------------------------------------------------- carousel */
  function carousel(el) {
    if (!once(el, 'car')) return;
    var track = el.querySelector('.atlas-carousel__track');
    var slides = qsa('.atlas-carousel__slide', el);
    if (!track || slides.length < 2) return;
    var dots = el.querySelector('.atlas-carousel__dots');
    var rtl = getComputedStyle(track).direction === 'rtl';
    var idx = 0, timer = null;

    function go(i) {
      idx = (i + slides.length) % slides.length;
      track.scrollTo({ left: (rtl ? -1 : 1) * idx * track.clientWidth, behavior: reduce ? 'auto' : 'smooth' });
      mark();
    }
    function mark() {
      if (!dots) return;
      qsa('button', dots).forEach(function (b, n) { b.classList.toggle('is-active', n === idx); b.setAttribute('aria-current', n === idx ? 'true' : 'false'); });
    }
    if (dots) {
      slides.forEach(function (s, n) {
        var b = d.createElement('button');
        b.type = 'button'; b.setAttribute('aria-label', 'Slide ' + (n + 1));
        b.addEventListener('click', function () { go(n); });
        dots.appendChild(b);
      });
      mark();
    }
    var prev = el.querySelector('[data-prev]'), next = el.querySelector('[data-next]');
    if (prev) prev.addEventListener('click', function () { go(idx - 1); });
    if (next) next.addEventListener('click', function () { go(idx + 1); });

    var st;
    track.addEventListener('scroll', function () {
      clearTimeout(st);
      st = setTimeout(function () { idx = Math.round(Math.abs(track.scrollLeft) / (track.clientWidth || 1)); mark(); }, 80);
    }, { passive: true });

    var every = parseInt(el.getAttribute('data-autoplay'), 10) || 0;
    if (every > 0 && !reduce) {
      var start = function () { stop(); timer = setInterval(function () { if (!d.hidden) go(idx + 1); }, every); };
      var stop = function () { if (timer) clearInterval(timer); timer = null; };
      el.addEventListener('mouseenter', stop); el.addEventListener('mouseleave', start);
      el.addEventListener('focusin', stop); el.addEventListener('focusout', start);
      el.addEventListener('touchstart', stop, { passive: true });
      start();
    }
  }

  /* --------------------------------------------- portfolio filter */
  function portfolio(el) {
    if (!once(el, 'pf')) return;
    var buttons = qsa('[data-filter]', el), items = qsa('.atlas-pf__item', el);
    buttons.forEach(function (b) {
      b.addEventListener('click', function () {
        var f = b.getAttribute('data-filter');
        buttons.forEach(function (x) { x.classList.toggle('is-active', x === b); x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        items.forEach(function (it) { it.hidden = !(f === '*' || it.getAttribute('data-category') === f); });
        emit('filter', { el: el, filter: f });
      });
    });
  }

  /* ---------------------------------------------------- lightbox */
  var lb = null;
  function openLightbox(link) {
    var scope = link.closest('[data-atlas-portfolio], .atlas-gallery') || d;
    var group = qsa('[data-atlas-lightbox]', scope).filter(function (a) { return !a.closest('[hidden]'); });
    var i = Math.max(0, group.indexOf(link));
    var last = d.activeElement;
    var fig = d.createElement('figure'), img = d.createElement('img'), cap = d.createElement('figcaption');
    fig.appendChild(img); fig.appendChild(cap);
    lb = d.createElement('div');
    lb.className = 'atlas-lightbox'; lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-modal', 'true');
    function btn(attr, text, label) { var b = d.createElement('button'); b.type = 'button'; b.setAttribute(attr, ''); b.setAttribute('aria-label', label); b.textContent = text; return b; }
    var close = btn('data-close', '×', 'Close');
    lb.appendChild(fig); lb.appendChild(close);
    if (group.length > 1) { lb.appendChild(btn('data-prev', '‹', 'Previous')); lb.appendChild(btn('data-next', '›', 'Next')); }
    function show(n) {
      i = (n + group.length) % group.length;
      var a = group[i];
      img.src = a.getAttribute('href'); img.alt = a.getAttribute('data-caption') || '';
      cap.textContent = a.getAttribute('data-caption') || '';
    }
    function shut() { d.removeEventListener('keydown', key); if (lb) lb.remove(); lb = null; if (last && last.focus) last.focus(); }
    function key(e) {
      if (e.key === 'Escape') shut();
      else if (e.key === 'ArrowLeft') show(i - 1);
      else if (e.key === 'ArrowRight') show(i + 1);
    }
    lb.addEventListener('click', function (e) {
      if (e.target === lb || e.target === close) shut();
      else if (e.target.hasAttribute('data-prev')) show(i - 1);
      else if (e.target.hasAttribute('data-next')) show(i + 1);
    });
    d.addEventListener('keydown', key);
    d.body.appendChild(lb);
    show(i);
    close.focus();
  }
  d.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('[data-atlas-lightbox]');
    if (a && !e.metaKey && !e.ctrlKey) { e.preventDefault(); openLightbox(a); }
  });

  /* ---------------------------------------------------- parallax */
  var parallax = [];
  function parallaxInit(el) { if (once(el, 'px') && !reduce) parallax.push(el); }
  var ticking = false;
  function parallaxFrame() {
    ticking = false;
    parallax.forEach(function (el) {
      var host = el.parentElement, r = host.getBoundingClientRect(), vh = window.innerHeight;
      if (r.bottom < 0 || r.top > vh) return;
      var p = (r.top + r.height / 2 - vh / 2) / vh;
      el.style.transform = 'translate3d(0,' + (p * -90).toFixed(1) + 'px,0)';
    });
  }
  window.addEventListener('scroll', function () { if (!ticking && parallax.length) { ticking = true; requestAnimationFrame(parallaxFrame); } }, { passive: true });

  /* ------------------------------------------------- theme toggle */
  function effectiveTheme() {
    var m = root.getAttribute('data-atlas-theme');
    return m === 'dark' || (m !== 'light' && darkMq.matches) ? 'dark' : 'light';
  }
  d.addEventListener('click', function (e) {
    var t = e.target.closest && e.target.closest('[data-atlas-theme-toggle]');
    if (!t) return;
    var next = effectiveTheme() === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-atlas-theme', next);
    try { localStorage.setItem('atlas-theme', next); } catch (err) {}
    emit('theme', { theme: next });
  });

  /* -------------------------------------------------------- lottie */
  function lottie(el) {
    if (!once(el, 'lt')) return;
    var tries = 0;
    (function boot() {
      if (window.lottie) {
        window.lottie.loadAnimation({
          container: el, renderer: 'svg', path: el.getAttribute('data-atlas-lottie'),
          loop: el.getAttribute('data-loop') !== '0', autoplay: el.getAttribute('data-autoplay') !== '0' && !reduce
        });
      } else if (tries++ < 100) setTimeout(boot, 100);
    })();
  }

  /* ------------------------------------------------------- init */
  function init(scope) {
    scope = scope || d;
    qsa('[data-atlas-anim]:not(.is-in), [data-atlas-reveal]:not(.is-in)', scope).forEach(function (el) { if (once(el, 'watch')) watch(el); });
    qsa('[data-atlas-typewriter]', scope).forEach(typewriter);
    qsa('[data-atlas-carousel]', scope).forEach(carousel);
    qsa('[data-atlas-portfolio]', scope).forEach(portfolio);
    qsa('[data-atlas-parallax]', scope).forEach(parallaxInit);
    qsa('[data-atlas-lottie]', scope).forEach(lottie);
    parallaxFrame();
  }

  window.Atlas = { init: init, reveal: reveal, theme: function (t) { if (t) { root.setAttribute('data-atlas-theme', t); } return effectiveTheme(); }, version: '1.1' };
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', function () { init(); }); else init();
})();
