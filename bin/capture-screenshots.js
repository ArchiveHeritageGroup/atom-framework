/**
 * Capture screenshots of an AHG plugin for its distributable bundle.
 *
 * Points a headless browser at a reference AtoM instance with the plugin
 * installed, signs in, walks the routes named for that plugin and writes PNGs
 * into stuff/screenshots/<plugin>/, where build-plugin-bundle picks them up.
 *
 * Shot on the stock arDominionB5Plugin theme deliberately: that is what a
 * customer sees, and screenshots taken against the AHG theme would show
 * navigation and styling their install will not have.
 *
 * Usage:
 *   node capture-screenshots.js --base=http://host:port --user=x --pass=y \
 *        --out=/path/to/screenshots [--only=ahgBackupPlugin]
 */
const path = require('path');
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
const OUT = args.out || '/usr/share/nginx/archive/stuff/screenshots';

// Route per plugin. Kept here rather than derived from the routing table because
// a bundle wants the two or three screens that show what the plugin is for, not
// every endpoint it registers.
const SHOTS = {
  ahgProvenancePlugin: [
    ['provenance', '/index.php/provenance', 'Provenance management'],
    ['coverage', '/index.php/provenance/coverage', 'Provenance coverage'],
  ],
  ahgBackupPlugin: [
    ['backup', '/index.php/backup', 'Backups and schedules'],
    ['settings', '/index.php/backup/settings', 'Backup settings'],
  ],
  ahgFavoritesPlugin: [['favorites', '/index.php/favorites', 'Favourites and folders']],
  ahgFeedbackPlugin: [['feedback', '/index.php/feedback', 'Feedback management']],
  ahgPreservationPlugin: [
    ['packages', '/index.php/admin/preservation/packages/', 'Preservation packages'],
    ['tiffpdfmerge', '/index.php/tiffpdfmerge', 'TIFF and PDF merge'],
  ],
};

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();

  // Sign in. The login form is not the first form on the page, so the CSRF token
  // has to come from the form that carries the password field - taking the first
  // one silently fails with "CSRF Token Expired".
  await page.goto(`${BASE}/index.php/user/login`, { waitUntil: 'domcontentloaded' });
  const form = page.locator('form:has(input[name="password"])');
  // Two inputs are named email on this page; the real one is the typed, required
  // field in the login form. .last() rather than .first() for that reason.
  await form.locator('input[name="email"]').last().fill(args.user || '');
  await form.locator('input[name="password"]').last().fill(args.pass || '');
  // Submit from the password field rather than clicking a button: the page
  // carries more than one submit control and the first is not visible, which
  // Playwright waits on until it times out.
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
    form.locator('input[name="password"]').last().press('Enter'),
  ]);

  const signedIn = await page.locator('a[href*="user/logout"]').count();
  if (!signedIn) {
    console.error('  sign-in failed - no logout link; check credentials');
    await browser.close();
    process.exit(1);
  }
  console.log('  signed in');

  let taken = 0;
  for (const [plugin, shots] of Object.entries(SHOTS)) {
    if (args.only && args.only !== plugin) continue;
    const dir = path.join(OUT, plugin);
    fs.mkdirSync(dir, { recursive: true });

    for (const [name, route, caption] of shots) {
      const res = await page.goto(BASE + route, { waitUntil: 'networkidle' }).catch(() => null);
      const status = res ? res.status() : 0;
      const body = await page.locator('body').innerText().catch(() => '');

      // A themed error page still returns 200, so check the content too rather
      // than trusting the status code.
      if (status >= 400 || /Oops!|An Error Occurred/i.test(body)) {
        console.log(`  SKIP  ${plugin}/${name}  (status ${status})`);
        continue;
      }

      const file = path.join(dir, `${name}.png`);
      await page.screenshot({ path: file, fullPage: true });
      fs.writeFileSync(path.join(dir, `${name}.txt`), caption + '\n');
      const kb = Math.round(fs.statSync(file).size / 1024);
      console.log(`  shot  ${plugin}/${name}.png  ${kb}KB  "${caption}"`);
      taken++;
    }
  }

  console.log(`  ${taken} screenshots written to ${OUT}`);
  await browser.close();
})();
