/* Atlas browser smoke test (Playwright + Chromium).
 *
 *   vendor/bin/testbench package:create-sqlite-db && vendor/bin/testbench migrate --force
 *   vendor/bin/testbench atlas:demo --force
 *   (vendor/bin/testbench serve --port=8765 &)
 *   node tests/e2e/public-page.cjs            # BASE=http://localhost:8765 by default
 *
 * Uses a globally installed `playwright` (or ./node_modules). Exits non-zero on failure.
 */
const BASE = process.env.BASE || 'http://localhost:8765';
const pw = (() => { try { return require('playwright'); } catch (e) { return require(require('child_process').execSync('npm root -g').toString().trim() + '/playwright'); } })();
const { chromium } = pw;

(async () => {
  const b = await chromium.launch();
  const errs = [];
  async function shot(name, opts, fn) {
    const ctx = await b.newContext(opts); const p = await ctx.newPage();
    p.on('pageerror', e => errs.push(name + ' PAGEERR ' + e.message));
    p.on('console', m => m.type() === 'error' && !/favicon|404/.test(m.text()) && errs.push(name + ' CONSOLE ' + m.text()));
    await p.goto(`${BASE}/demo`); await p.waitForTimeout(500);
    if (fn) await fn(p);
    // scroll through to trigger reveals
    await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); });
    await p.waitForTimeout(1800);
    await p.screenshot({ path: require('os').tmpdir() + `/${name}.png`, fullPage: true });
    return { p, ctx };
  }
  await shot('light', { viewport: { width: 1280, height: 800 }, colorScheme: 'light' });
  const d = await shot('dark', { viewport: { width: 1280, height: 800 }, colorScheme: 'dark' });
  const info = await d.p.evaluate(() => ({ theme: document.documentElement.dataset.atlasTheme, bg: getComputedStyle(document.body).backgroundColor, counters: [...document.querySelectorAll('[data-atlas-count]')].map(e => e.textContent), typed: document.querySelector('.atlas-typewriter__word').textContent, anims: document.querySelectorAll('[data-atlas-anim].is-in').length + '/' + document.querySelectorAll('[data-atlas-anim]').length }));
  console.log('dark info', JSON.stringify(info));
  // toggle theme
  await d.p.click('.atlas-theme-fab'); await d.p.waitForTimeout(200);
  console.log('after toggle', await d.p.evaluate(() => [document.documentElement.dataset.atlasTheme, getComputedStyle(document.body).backgroundColor]));
  // portfolio filter
  await d.p.click('.atlas-pf__filters button:has-text("Web")');
  console.log('visible after Web filter:', await d.p.locator('.atlas-pf__item:visible').count());
  // lightbox: items have no image in demo -> check carousel
  await d.p.click('.atlas-carousel__dots button >> nth=1'); await d.p.waitForTimeout(700);
  console.log('carousel dot active idx:', await d.p.evaluate(() => [...document.querySelectorAll('.atlas-carousel__dots button')].findIndex(b => b.classList.contains('is-active'))));
  // language switch
  await d.p.goto(`${BASE}/demo?lang=nl`); 
  console.log('nl title/hero:', await d.p.title(), '|', await d.p.locator('.atlas-hero__title').innerText());
  const m = await shot('mobile', { viewport: { width: 390, height: 800 }, colorScheme: 'light', isMobile: true });
  console.log('mobile overflow-x:', await m.p.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1));
  console.log('errors:', errs);
  await b.close();
})().catch(e => { console.error('FAIL', e); process.exit(1); });
