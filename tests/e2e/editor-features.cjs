/* Atlas browser smoke test (Playwright + Chromium).
 *
 *   vendor/bin/testbench package:create-sqlite-db && vendor/bin/testbench migrate --force
 *   vendor/bin/testbench atlas:demo --force
 *   (vendor/bin/testbench serve --port=8765 &)
 *   node tests/e2e/editor-features.cjs            # BASE=http://localhost:8765 by default
 *
 * Uses a globally installed `playwright` (or ./node_modules). Exits non-zero on failure.
 */
const BASE = process.env.BASE || 'http://localhost:8765';
const pw = (() => { try { return require('playwright'); } catch (e) { return require(require('child_process').execSync('npm root -g').toString().trim() + '/playwright'); } })();
const { chromium } = pw;

(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1500, height: 950 }, colorScheme: 'light' });
  const errs = []; p.on('pageerror', e => errs.push('PAGEERR ' + e.message)); p.on('console', m => m.type()==='error' && !/404/.test(m.text()) && errs.push('CONSOLE ' + m.text()));
  await p.goto(`${BASE}/atlas`);
  await p.fill('input[name=title]', 'E2E ' + Date.now()); await p.click('button:has-text("Create page")');
  await p.waitForSelector('.atlas-frame'); await p.waitForTimeout(700);
  const item = t => p.locator('.atlas-block-item', { hasText: t }).first();
  // click-add heading and portfolio grid
  await item('Heading').click(); await p.waitForTimeout(500);
  console.log('palette groups:', await p.locator('.atlas-cat').allInnerTexts());
  // locale: type default text, then switch to nl and translate
  await p.locator('.atlas-field input.atlas-input').nth(1).fill('Hello world');
  await p.waitForTimeout(500);
  await p.selectOption('.atlas-top select >> nth=1', 'nl');
  await p.waitForTimeout(300);
  console.log('hint:', await p.locator('.atlas-hint').first().innerText());
  await p.locator('.atlas-field:has(> label:has-text("Text🌐")) input').fill('Hallo wereld');
  await p.waitForTimeout(700);
  console.log('canvas nl heading:', await p.frameLocator('.atlas-frame').locator('[data-atlas-type=heading] h2').innerText());
  await p.selectOption('.atlas-top select >> nth=1', 'en'); await p.waitForTimeout(700);
  console.log('canvas en heading:', await p.frameLocator('.atlas-frame').locator('[data-atlas-type=heading] h2').innerText());
  // portfolio grid + repeater
  await item('Portfolio Grid').click(); await p.waitForTimeout(800);
  const reps = await p.locator('.atlas-rep').count();
  await p.locator('button:has-text("+ Add item")').first().click();
  console.log('repeater items before/after:', reps, await p.locator('.atlas-rep').count());
  await p.locator('.atlas-rep').last().locator('input.atlas-input').first().fill('https://example.com/img.jpg').catch(()=>{});
  await p.waitForTimeout(700);
  console.log('grid items in canvas:', await p.frameLocator('.atlas-frame').locator('.atlas-pf__item').count());
  // animation group
  await p.locator('summary:has-text("Animation")').click();
  await p.locator('.atlas-field', { hasText: 'Entrance animation' }).locator('select').selectOption('fade-up');
  // canvas dark preview
  await p.waitForTimeout(600); await p.click('button[title^="Preview the page"]'); await p.waitForTimeout(900);
  console.log('canvas theme attr:', await p.frameLocator('.atlas-frame').locator('html').getAttribute('data-atlas-theme'));
  // page tab: set theme dark + toggle
  await p.click('.atlas-tab:has-text("Page")');
  await p.locator('.atlas-field', { hasText: 'Colour mode' }).locator('select').selectOption('dark');
  await p.locator('label:has-text("Show light/dark switch") input').check();
  await p.waitForTimeout(700);
  // block builder
  await p.click('.atlas-tab:has-text("Blocks")');
  await p.click('button:has-text("New custom block")'); await p.waitForSelector('.atlas-builder');
  await p.locator('.atlas-builder input.atlas-input').first().fill('Price Card ' + Date.now());
  await p.locator('.atlas-builder textarea').first().fill('<div class="pc"><h3>{{ title }}</h3></div>');
  await p.click('.atlas-builder button:has-text("Save")'); await p.waitForTimeout(800);
  console.log('custom block in palette:', await p.locator('.atlas-custom-item').count());
  await p.locator('.atlas-custom-item .atlas-block-item').first().click(); await p.waitForTimeout(800);
  console.log('custom block rendered:', await p.frameLocator('.atlas-frame').locator('.pc h3').innerText());
  await p.keyboard.press('Control+s'); await p.waitForTimeout(900);
  console.log('status:', await p.locator('.atlas-status').innerText());
  await p.screenshot({ path: require('os').tmpdir() + '/editor2.png' });
  const url = await p.locator('a:has-text("View")').getAttribute('href');
  console.log('errors:', errs);
  await b.close();
})().catch(e => { console.error('FAIL', e.message.split('\n').slice(0,6).join('\n')); process.exit(1); });
