/**
 * Load every page in a real browser and report what the JavaScript does.
 *
 *     node scripts/browser_check.mjs --url=http://127.0.0.1:8080 \
 *                                    --user=smoke --pass=... [--budget=N]
 *
 * WHY THIS EXISTS AS WELL AS smoke_test.php
 *
 * The PHP crawler proves a page renders. It does not run a single line
 * of the JavaScript on it, and this project has 44 inline <script>
 * blocks, 189 inline on* handlers and 1358 style attributes. A syntax
 * error in one inline block disables that whole block silently - phase
 * 13 found two files shipped that way, and the only reason they were
 * found is that somebody read them.
 *
 * This also makes the CSP work in RELEASE_READINESS 4.6 possible to
 * attempt. Converting 189 handlers blind was refused because nothing
 * could tell whether a converted button still worked. A browser can.
 *
 * WHAT IT FAILS ON
 *
 * Uncaught JavaScript errors, measured against a budget that can only
 * go down - the same ratchet as the inline handlers, for the same
 * reason: a long cleanup loses to new code unless something holds the
 * line.
 *
 * Requests to third-party CDNs are allowed through, because blocking
 * them would turn every page into a cascade of ReferenceErrors for
 * jQuery and Bootstrap and tell us nothing about this codebase.
 */

import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { chromium } from 'playwright';

const args = Object.fromEntries(
  process.argv.slice(2)
    .filter(a => a.startsWith('--'))
    .map(a => {
      const [k, ...v] = a.slice(2).split('=');
      return [k, v.length ? v.join('=') : true];
    })
);

const base = String(args.url || 'http://127.0.0.1:8080').replace(/\/$/, '');
const user = String(args.user || '');
const pass = String(args.pass || '');
const budgetFile = '.browser-error-budget';
const budget = args.budget !== undefined
  ? Number(args.budget)
  : (existsSync(budgetFile) ? Number(readFileSync(budgetFile, 'utf8').trim()) : Infinity);

const pages = JSON.parse(readFileSync(args.pages || '/tmp/pages.json', 'utf8'));

const inCI = process.env.GITHUB_ACTIONS === 'true';
const annotate = (file, msg) =>
  inCI && console.log(`::error${file ? ` file=${file}` : ''}::${msg.replace(/\s+/g, ' ').slice(0, 400)}`);

const browser = await chromium.launch();
const context = await browser.newContext({ ignoreHTTPSErrors: true });
const page = await context.newPage();

/* ---- log in ------------------------------------------------------ */

await page.goto(`${base}/index.php`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="username"]', user);
await page.fill('input[name="password"]', pass);
await Promise.all([
  page.waitForLoadState('domcontentloaded'),
  page.click('button[type="submit"], input[type="submit"]'),
]);

if (/name="password"/i.test(await page.content())) {
  annotate('', 'browser check could not log in');
  console.error('Login failed - still on the login form.');
  await browser.close();
  process.exit(1);
}

console.log(`Logged in. Visiting ${pages.length} pages in Chromium.\n`);

/* ---- visit ------------------------------------------------------- */

const findings = [];   // { page, kind, detail }
let visited = 0;

for (const rel of pages) {
  const errors = [];
  const missing = [];

  const onPageError = e => errors.push(String(e.message || e));
  const onConsole = m => {
    if (m.type() !== 'error') return;
    const t = m.text();
    /* A failed network request also logs a console error; the
       response handler below reports those with more detail. */
    if (/Failed to load resource/i.test(t)) return;
    errors.push(t);
  };
  const onResponse = r => {
    if (r.status() === 404 && r.url().startsWith(base)) {
      missing.push(r.url().slice(base.length));
    }
  };

  page.on('pageerror', onPageError);
  page.on('console', onConsole);
  page.on('response', onResponse);

  try {
    await page.goto(`${base}/${rel}`, { waitUntil: 'load', timeout: 20000 });
    /* Give deferred scripts and DOMContentLoaded handlers a moment to
       throw. Most of this codebase's JavaScript runs at parse time,
       but the DataTables and chart initialisers do not. */
    await page.waitForTimeout(250);
  } catch (e) {
    errors.push(`navigation: ${e.message}`);
  }

  page.off('pageerror', onPageError);
  page.off('console', onConsole);
  page.off('response', onResponse);

  visited++;
  for (const e of new Set(errors)) findings.push({ page: rel, kind: 'js', detail: e });
  for (const m of new Set(missing)) findings.push({ page: rel, kind: '404', detail: m });

  process.stdout.write(`\r[${visited}/${pages.length}] ${rel.slice(0, 60).padEnd(62)}`);
}

process.stdout.write('\r'.padEnd(80) + '\r');
await browser.close();

/* ---- report ------------------------------------------------------ */

const js = findings.filter(f => f.kind === 'js');
const notFound = findings.filter(f => f.kind === '404');

const group = list => {
  const by = new Map();
  for (const f of list) {
    if (!by.has(f.page)) by.set(f.page, []);
    by.get(f.page).push(f.detail);
  }
  return by;
};

console.log('='.repeat(72));
console.log(`Browser check: ${visited} pages`);
console.log('='.repeat(72));

if (js.length) {
  console.log(`\nUncaught JavaScript errors (${js.length}) on ${group(js).size} pages\n`);
  for (const [p, details] of group(js)) {
    console.log(`  ${p}`);
    for (const d of details) console.log(`      ${d.replace(/\s+/g, ' ').slice(0, 160)}`);
  }
}

if (notFound.length) {
  /* A missing asset is not a crash, so it never fails the build - but
     it is how a stylesheet quietly stops being applied. */
  console.log(`\nAssets returning 404 (${notFound.length}) - not fatal, but worth fixing\n`);
  for (const [p, details] of group(notFound)) {
    console.log(`  ${p}`);
    for (const d of [...new Set(details)].slice(0, 5)) console.log(`      ${d}`);
  }
}

console.log(`\n${visited} pages, ${js.length} JavaScript errors, ${notFound.length} missing assets.`);

writeFileSync('/tmp/browser-findings.json', JSON.stringify(findings, null, 2));

if (!Number.isFinite(budget)) {
  console.log(`\nNo budget recorded. Write ${js.length} to ${budgetFile} to start the ratchet.`);
  process.exit(0);
}

if (js.length > budget) {
  for (const [p, details] of group(js)) annotate(p, details[0]);
  console.error(`\nJavaScript errors: ${js.length}, budget ${budget}.`);
  console.error('New uncaught errors have been introduced.');
  process.exit(1);
}

if (js.length < budget) {
  console.log(`\n${budget - js.length} fewer than the budget of ${budget}. Lower it:`);
  console.log(`  echo ${js.length} > ${budgetFile}`);
  process.exit(1);
}

console.log(`\nJavaScript errors: ${js.length}, at budget.`);
process.exit(0);
