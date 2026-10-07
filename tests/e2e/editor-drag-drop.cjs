/* Atlas browser smoke test (Playwright + Chromium).
 *
 *   vendor/bin/testbench package:create-sqlite-db && vendor/bin/testbench migrate --force
 *   vendor/bin/testbench atlas:demo --force
 *   (vendor/bin/testbench serve --port=8765 &)
 *   node tests/e2e/editor-drag-drop.cjs            # BASE=http://localhost:8765 by default
 *
 * Uses a globally installed `playwright` (or ./node_modules). Exits non-zero on failure.
 */
const BASE = process.env.BASE || 'http://localhost:8765';
const pw = (() => { try { return require('playwright'); } catch (e) { return require(require('child_process').execSync('npm root -g').toString().trim() + '/playwright'); } })();
const { chromium } = pw;

(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' }).catch(async () => chromium.launch());
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  const errs = []; p.on('pageerror', e => errs.push('PAGEERR ' + e.message)); p.on('console', m => m.type()==='error' && errs.push('CONSOLE ' + m.text()));
  await p.goto(`${BASE}/atlas`);
  await p.fill('input[name=title]', 'Home'); await p.click('text=Create page');
  await p.waitForSelector('.atlas-frame');
  await p.waitForTimeout(800);
  const frame = () => p.frameLocator('.atlas-frame');
  const drag = async (srcSel, tx, ty) => {
    const s = await p.locator(srcSel).first().boundingBox();
    await p.mouse.move(s.x + s.width/2, s.y + s.height/2); await p.mouse.down();
    await p.mouse.move(s.x + s.width/2 + 20, s.y + 20, { steps: 3 });
    await p.mouse.move(tx, ty, { steps: 8 }); await p.mouse.up();
    await p.waitForTimeout(700);
  };
  const item = t => `.atlas-block-item:has-text("${t}")`;
  const fr = await p.locator('.atlas-frame').boundingBox();
  await drag(item('Section'), fr.x + 300, fr.y + 100);
  console.log('after section:', await p.evaluate(() => JSON.stringify(AtlasEditor.state.tree.map(n => n.type))));
  // drop heading inside the section (center of section area)
  const sec = await frame().locator('[data-atlas-type=section]').boundingBox();
  await drag(item('Heading'), sec.x + sec.width/2, sec.y + sec.height/2);
  await drag(item('Columns'), sec.x + sec.width/2, sec.y + sec.height + 6);
  console.log('tree:', await p.evaluate(() => JSON.stringify(AtlasEditor.state.tree, (k,v)=>k==='props'?undefined:v)));
  // drop custom code into first column
  const col = await frame().locator('[data-atlas-type=columns] > .atlas-section__inner > .atlas-block, [data-atlas-type=columns] [data-atlas-type=section]').first().boundingBox();
  await drag(item('Custom Code'), col.x + col.width/2, col.y + col.height/2);
  console.log('tree2:', await p.evaluate(() => JSON.stringify(AtlasEditor.state.tree, (k,v)=>k==='props'?undefined:v)));
  // select heading + edit text
  await frame().locator('[data-atlas-type=heading]').click();
  await p.waitForSelector('.atlas-field input.atlas-input');
  await p.locator('.atlas-field input.atlas-input').first().fill('Hello Atlas');
  await p.waitForTimeout(600);
  console.log('canvas heading:', await frame().locator('[data-atlas-type=heading]').innerText());
  // set page to published, save
  await p.selectOption('select.atlas-select >> nth=0', 'published');
  await p.keyboard.press('Control+s'); await p.waitForTimeout(800);
  console.log('status:', await p.locator('.atlas-status').innerText());
  await p.screenshot({ path: require('os').tmpdir() + '/editor.png' });
  const r = await (await p.request.get(`${BASE}/home`)).text();
  console.log('public has heading:', r.includes('Hello Atlas'), '| custom code:', r.includes('Hello from custom code'), '| script:', r.includes('document.getElementById'));
  // undo
  await p.keyboard.press('Control+z'); await p.waitForTimeout(300);
  console.log('errors:', errs);
  await b.close();
})().catch(e => { console.error('FAIL', e); process.exit(1); });
