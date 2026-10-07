/* Atlas browser smoke test (Playwright + Chromium).
 *
 *   vendor/bin/testbench package:create-sqlite-db && vendor/bin/testbench migrate --force
 *   vendor/bin/testbench atlas:demo --force
 *   (vendor/bin/testbench serve --port=8765 &)
 *   node tests/e2e/editor-productivity.cjs            # BASE=http://localhost:8765 by default
 *
 * Uses a globally installed `playwright` (or ./node_modules). Exits non-zero on failure.
 */
const BASE = process.env.BASE || 'http://localhost:8765';
const pw = (() => { try { return require('playwright'); } catch (e) { return require(require('child_process').execSync('npm root -g').toString().trim() + '/playwright'); } })();
const { chromium } = pw;

(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1500, height: 950 } });
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(`${BASE}/atlas?q=zzzz`); console.log('empty search ok:', (await p.locator('.atlas-empty-row').count()) === 1);
  await p.goto(`${BASE}/atlas`); await p.fill('input[name=title]', 'Draft test ' + Date.now()); await p.click('button:has-text("Create page")');
  await p.waitForSelector('.atlas-frame'); await p.waitForTimeout(800);
  const url = p.url();
  const item = t => p.locator('.atlas-block-item', { hasText: t }).first();
  await item('Heading').click(); await p.waitForTimeout(500);
  await p.locator('.atlas-field:has(> label:has-text("Text")) input').nth(0).fill('Hello').catch(()=>{});
  await p.locator('.atlas-field input.atlas-input').nth(1).fill('Draft heading'); await p.waitForTimeout(1200);
  // --- autosave restore
  await p.evaluate(() => { window.onbeforeunload = null; });
  await p.goto(url.replace(/\/edit.*/, '/edit') + '?x=1', { waitUntil: 'load' }).catch(()=>{});
  await p.waitForSelector('.atlas-frame'); await p.waitForTimeout(1200);
  console.log('banner shown:', await p.locator('.atlas-banner').count());
  await p.click('.atlas-banner button:has-text("Restore")'); await p.waitForTimeout(900);
  console.log('restored heading:', await p.frameLocator('.atlas-frame').locator('[data-atlas-type=heading] h2').innerText());
  // --- copy / paste
  await p.frameLocator('.atlas-frame').locator('[data-atlas-type=heading]').click(); await p.keyboard.press('Control+c'); await p.keyboard.press('Control+v'); await p.waitForTimeout(800);
  console.log('headings after paste:', await p.frameLocator('.atlas-frame').locator('[data-atlas-type=heading]').count());
  // --- layers drag reorder
  await item('Text').click(); await p.waitForTimeout(600);
  const before = await p.evaluate(() => AtlasEditor.state.tree.map(n => n.type).join(','));
  await p.click('.atlas-tab:has-text("Layers")'); await p.waitForTimeout(300);
  const rows = p.locator('.atlas-layer');
  console.log('rows:', await rows.count());
  await rows.nth(await rows.count() - 1).dragTo(rows.first(), { targetPosition: { x: 40, y: 2 } }); await p.waitForTimeout(700);
  console.log('order before/after:', before, '->', await p.evaluate(() => AtlasEditor.state.tree.map(n => n.type).join(',')));
  // --- save clears draft; duplicate from list
  await p.keyboard.press('Control+s'); await p.waitForTimeout(900);
  console.log('draft cleared:', await p.evaluate(() => Object.keys(localStorage).filter(k => k.startsWith('atlas-draft-' + AtlasEditor.state.page.id)).length === 0));
  await p.goto(`${BASE}/atlas`); await p.locator('tr', { hasText: 'Draft test' }).first().locator('button:has-text("Duplicate")').click(); await p.waitForSelector('.atlas-frame');
  console.log('duplicate title:', await p.inputValue('.atlas-top__title'));
  console.log('errors', errs); await b.close();
})().catch(e => { console.error('FAIL', e.message.split('\n').slice(0,4).join('\n')); process.exit(1); });
