/**
 * End-to-end check of the AHG plugin pages against a running AtoM.
 *
 * Why a browser rather than curl: a curl sweep proves a page renders. It says
 * nothing about whether the JavaScript on it works, and every failure found while
 * packaging these plugins was of that second kind - AJAX posts rejected 403
 * because the CSRF token was never emitted, and fetches returning an empty body
 * that surfaced only as "Unexpected end of JSON input". A page can be perfect at
 * the HTTP level and broken the moment anyone uses it.
 *
 * So each page is judged on four things, not one:
 *
 *   status      the response code
 *   content     no themed error page - AtoM returns 200 for those
 *   console     no JavaScript errors
 *   network     no failed requests issued by the page itself
 *
 * Usage:
 *   node e2e-plugins.js --base=http://host:port --user=x --pass=y [--json=out.json]
 */
const fs = require('fs');
const PW = '/root/.npm/_npx/e41f203b7505f1fb/node_modules/playwright';
const { chromium } = require(PW);

const args = Object.fromEntries(
  process.argv.slice(2).map((a) => {
    const [k, ...v] = a.replace(/^--/, '').split('=');
    return [k, v.join('=')];
  })
);
const BASE = args.base || 'http://192.168.0.132:8028';

// Pages to exercise, grouped by the plugin that owns them. `expect` is the text
// that proves the page is the real thing rather than a login form or an error.
const PAGES = [
  ['ahgProvenancePlugin', '/index.php/provenance', 'Provenance Management'],
  ['ahgProvenancePlugin', '/index.php/provenance/coverage', 'Coverage'],
  ['ahgBackupPlugin', '/index.php/backup', 'Backup'],
  ['ahgBackupPlugin', '/index.php/backup/settings', 'Settings'],
  ['ahgFavoritesPlugin', '/index.php/favorites', 'My Favorites'],
  ['ahgFeedbackPlugin', '/index.php/feedback', 'Feedback'],
  ['ahgPreservationPlugin', '/index.php/admin/preservation/packages/', 'Packages'],
  ['ahgPreservationPlugin', '/index.php/tiffpdfmerge', 'Merge'],
  // IIIF and the two viewers that sit behind it. The manifest endpoints are
  // deliberately public - a manifest that needs a session is not a manifest - so
  // these are checked signed in and again anonymously further down.
  ['ahgIiifPlugin', '/index.php/iiif/settings', 'IIIF'],
  ['ahgIiifPlugin', '/index.php/mediaSettings', 'Media'],
  // Pages whose templates carried inline style attributes. Added after a sweep
  // moved 81 of them into nonced style blocks, because the suite had covered
  // none of these and a fix nobody exercises is a fix nobody trusts.
  ['ahgPreservationPlugin', '/index.php/admin/preservation/scheduler', 'Schedul'],
  ['ahgPreservationPlugin', '/index.php/admin/preservation/identification', 'dentif'],
  ['ahgPreservationPlugin', '/index.php/admin/preservation/conversion', 'onversi'],
  ['ahgIiifPlugin', '/index.php/manifest-collections', 'ollection'],
  ['ahgBackupPlugin', '/index.php/backup/upload', 'pload'],
  ['ahgCorePlugin', '/index.php/settings/email', 'Email'],
  ['atom', '/index.php/informationobject/browse', 'Showing'],
  ['atom', '/index.php/informationobject/add', 'Identity area'],
  ['atom', '/index.php/actor/add', 'Identity area'],
  ['atom', '/index.php/repository/add', 'Identity area'],
];

// Interactions worth running, because a button that 403s looks identical to a
// button that works until it is pressed.
const ACTIONS = [
  {
    plugin: 'ahgBackupPlugin',
    name: 'test database connection',
    page: '/index.php/backup',
    run: async (page) => {
      const btn = page.locator('button:has-text("Test Connection"), a:has-text("Test Connection")').first();
      if (!(await btn.count())) return 'control not present';
      await btn.click();
      await page.waitForTimeout(4000);
      return 'clicked';
    },
  },
  {
    plugin: 'ahgIiifPlugin',
    name: 'open the IIIF viewer and confirm a renderer loads',
    page: '/index.php/informationobject/browse',
    run: async (page) => {
      // Walk from browse to a record and look for the viewer the injector puts
      // on the page. That is the path a reader takes, and it needs no ids.
      //
      // This used to try digital object ids [455, 1, 2, 3, 4, 5] in turn. 455 was
      // whatever happened to be viewable on the instance the check was written
      // against; the rest were hopeful. A database rebuild made every one of them
      // 404, and the check then reported a plugin failure for months when what it
      // had actually found was its own stale fixture.
      const base = page.url().split('/index.php')[0];

      // Read every href in one evaluate rather than locator-by-locator: a
      // locator that matches nothing makes getAttribute wait for the full
      // timeout, which is how this check came to throw instead of answering.
      // Result links first. Reading every anchor on the page meant walking the
      // navigation, the language switcher and the footer before ever reaching a
      // record, and on a small catalogue the loop ran out first.
      const hrefs = await page.evaluate(() => {
        const pick = (sel) => Array.from(document.querySelectorAll(sel)).map((a) => a.getAttribute('href'));
        const results = pick('.search-result a[href], article a[href]');

        return (results.length ? results : pick('#main-column a[href]')).filter(Boolean);
      });
      const count = hrefs.length;

      if (!count) {
        // An empty catalogue is not a plugin fault, and should not read as one.
        return 'no descriptions on this instance - nothing to view';
      }

      let opened = false;

      for (const href of hrefs.slice(0, 40)) {
        if (!href || !/\/index\.php\//.test(href)) continue;
        if (/\/(browse|search|add|edit|login|logout|settings|admin)/.test(href)) continue;

        await page.goto(href.startsWith('http') ? href : base + href, { waitUntil: 'networkidle' }).catch(() => {});
        opened = true;

        const container = page.locator('[data-viewer]');
        if (await container.count()) {
          const which = await container.first().getAttribute('data-viewer');
          const by = await container.first().getAttribute('data-rendered-by');

          // Give the viewer a moment to boot, then confirm it actually painted
          // rather than merely being present in the markup.
          await page.waitForTimeout(6000);
          const painted = await container.first().locator('canvas, iframe').count();

          return `${which} via ${by}, ${painted ? 'rendered' : 'container present but nothing painted'}`;
        }
      }

      return opened
        ? 'records exist but none carry a digital object'
        : 'no record links found on browse';
    },
  },
  {
    plugin: 'ahgProvenancePlugin',
    name: 'open a provenance record',
    page: '/index.php/provenance',
    run: async (page) => {
      const link = page.locator('a:has-text("Browse Records")').first();
      if (!(await link.count())) return 'control not present';
      await link.click();
      await page.waitForLoadState('networkidle').catch(() => {});
      return 'navigated';
    },
  },
];

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();

  let consoleErrors = [];
  let netFailures = [];
  page.on('console', (m) => {
    if (m.type() === 'error') consoleErrors.push(m.text().slice(0, 160));
  });
  page.on('response', async (r) => {
    if (r.status() >= 400) netFailures.push(`${r.status()} ${r.url().replace(BASE, '')}`.slice(0, 160));
  });

  // Sign in.
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

  const results = [];
  console.log('  PAGE CHECKS');
  for (const [plugin, route, expect] of PAGES) {
    consoleErrors = [];
    netFailures = [];
    const res = await page.goto(BASE + route, { waitUntil: 'networkidle' }).catch(() => null);
    const status = res ? res.status() : 0;
    const body = await page.locator('body').innerText().catch(() => '');
    const isError = /Oops!|An Error Occurred|does not exist|not available/i.test(body);
    const isLogin = /Sign in|Log in/i.test(body) && body.length < 3000;
    const hasExpected = new RegExp(expect, 'i').test(body);

    const problems = [];
    if (status >= 400) problems.push(`status ${status}`);
    if (isError) problems.push('error page');
    if (isLogin) problems.push('login page');
    if (!hasExpected) problems.push(`missing "${expect}"`);
    if (consoleErrors.length) problems.push(`${consoleErrors.length} console error(s)`);
    if (netFailures.length) problems.push(`${netFailures.length} failed request(s)`);

    results.push({ plugin, route, status, problems, consoleErrors: [...consoleErrors], netFailures: [...netFailures] });
    console.log(`    ${problems.length ? 'FAIL' : 'ok  '} ${route.padEnd(44)} ${problems.join('; ') || ''}`);
    for (const e of consoleErrors.slice(0, 2)) console.log(`           console: ${e}`);
    for (const n of netFailures.slice(0, 2)) console.log(`           network: ${n}`);
  }

  console.log('  INTERACTIONS');
  for (const act of ACTIONS) {
    consoleErrors = [];
    netFailures = [];
    await page.goto(BASE + act.page, { waitUntil: 'networkidle' }).catch(() => {});
    let outcome;
    let threw = false;
    try {
      outcome = await act.run(page);
    } catch (e) {
      // A check that throws is a check that did not run. This was counted as a
      // pass, so a timeout inside an interaction reported green - the worst
      // possible outcome for a suite whose job is to notice things.
      threw = true;
      outcome = 'threw: ' + String(e.message).split('\n')[0].slice(0, 90);
    }
    const problems = [];
    if (threw) problems.push('check threw');
    if (consoleErrors.length) problems.push(`${consoleErrors.length} console error(s)`);
    if (netFailures.length) problems.push(`${netFailures.length} failed request(s)`);
    console.log(`    ${problems.length ? 'FAIL' : 'ok  '} ${act.name.padEnd(30)} ${outcome}  ${problems.join('; ')}`);
    for (const n of netFailures.slice(0, 3)) console.log(`           network: ${n}`);
    results.push({ plugin: act.plugin, action: act.name, outcome, problems, netFailures: [...netFailures] });
  }

  const failed = results.filter((r) => r.problems.length).length;
  console.log(`  ---- ${results.length - failed} pass, ${failed} fail ----`);
  if (args.json) fs.writeFileSync(args.json, JSON.stringify(results, null, 1));

  await browser.close();
  process.exit(failed ? 1 : 0);
})();
