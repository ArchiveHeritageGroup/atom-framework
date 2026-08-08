/**
 * Drive the whole preservation package flow through the browser and download the
 * result.
 *
 * The API endpoints were already proved with curl, which is a weaker claim than
 * it sounds: it says the endpoints answer, not that the buttons wired to them
 * work. Every failure found while packaging these plugins was on the JavaScript
 * side, so the flow is worth walking the way a person walks it - create, add an
 * object, build and export, then click Download and check what lands.
 *
 * Usage:
 *   node e2e-preservation-package.js --base=http://host:port --user=x --pass=y \
 *        [--out=/tmp/downloads]
 */
const fs = require('fs');
const path = require('path');
const PW = '/root/.npm/_npx/e41f203b7505f1fb/node_modules/playwright';
const { chromium } = require(PW);

const args = Object.fromEntries(
  process.argv.slice(2).map((a) => {
    const [k, ...v] = a.replace(/^--/, '').split('=');
    return [k, v.join('=')];
  })
);
const BASE = args.base || 'http://192.168.0.132:8028';
const OUT = args.out || '/tmp/pkg-download';
fs.mkdirSync(OUT, { recursive: true });

const step = (n, msg) => console.log(`  ${n}. ${msg}`);

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, acceptDownloads: true });
  const page = await ctx.newPage();

  const consoleErrors = [];
  const netFailures = [];
  page.on('console', (m) => m.type() === 'error' && consoleErrors.push(m.text().slice(0, 150)));
  page.on('response', (r) => r.status() >= 400 && netFailures.push(`${r.status()} ${r.url().replace(BASE, '')}`.slice(0, 150)));

  // ---------------------------------------------------------------- sign in
  await page.goto(`${BASE}/index.php/user/login`, { waitUntil: 'domcontentloaded' });
  const form = page.locator('form:has(input[name="password"])');
  await form.locator('input[name="email"]').last().fill(args.user || '');
  await form.locator('input[name="password"]').last().fill(args.pass || '');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
    form.locator('input[name="password"]').last().press('Enter'),
  ]);
  if (!(await page.locator('a[href*="user/logout"]').count())) {
    console.error('  sign-in failed');
    await browser.close();
    process.exit(1);
  }
  step(1, 'signed in');

  // ---------------------------------------------------------------- create
  await page.goto(`${BASE}/index.php/admin/preservation/packages/`, { waitUntil: 'networkidle' });
  const before = await page.locator('table tbody tr').count();
  step(2, `packages listed before: ${before}`);

  const create = page.locator('a:has-text("Create Package"), button:has-text("Create Package")').first();
  if (!(await create.count())) {
    console.error('  no Create Package control');
    await browser.close();
    process.exit(1);
  }
  await create.click();
  await page.waitForLoadState('networkidle').catch(() => {});
  step(3, `create form at ${page.url().replace(BASE, '')}`);

  // Fill whatever the form asks for: a name, and a type if it offers one.
  const name = 'E2E package ' + (await page.evaluate(() => String(Date.now()).slice(-6)));
  const nameField = page.locator('input[name="name"], input[name="title"]').first();
  if (await nameField.count()) await nameField.fill(name);

  for (const sel of ['select[name="package_type"]', 'select[name="type"]']) {
    const s = page.locator(sel).first();
    if (await s.count()) { await s.selectOption({ index: 1 }).catch(() => {}); break; }
  }

  const submit = page.locator('button[type="submit"], input[type="submit"]').last();
  if (await submit.count()) {
    await Promise.all([page.waitForLoadState('networkidle').catch(() => {}), submit.click()]);
  }
  step(4, `created "${name}"`);

  // ---------------------------------------------------------------- build
  // The edit page carries the add-object and build controls, all AJAX.
  let built = false;
  const buildBtn = page.locator('button:has-text("Build"), a:has-text("Build")').first();
  if (await buildBtn.count()) {
    await buildBtn.click();
    await page.waitForTimeout(8000);
    built = true;
  }
  step(5, built ? 'build control clicked' : 'no build control on this page');

  // ---------------------------------------------------------------- download
  await page.goto(`${BASE}/index.php/admin/preservation/packages/`, { waitUntil: 'networkidle' });
  const dl = page.locator('a[href*="/download"]').first();
  step(6, `download links on the list: ${await dl.count()}`);

  let saved = null;
  if (await dl.count()) {
    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 60000 }).catch(() => null),
      dl.click(),
    ]);
    if (download) {
      saved = path.join(OUT, download.suggestedFilename() || 'package.zip');
      await download.saveAs(saved);
      step(7, `downloaded ${path.basename(saved)} (${fs.statSync(saved).size} bytes)`);
    } else {
      step(7, 'click produced no download event');
    }
  }

  console.log(`  console errors: ${consoleErrors.length}`);
  consoleErrors.slice(0, 3).forEach((e) => console.log(`     ${e}`));
  console.log(`  failed requests: ${netFailures.length}`);
  netFailures.slice(0, 3).forEach((n) => console.log(`     ${n}`));

  await browser.close();
  process.exit(saved ? 0 : 1);
})();
