import { describe, it, before, after, beforeEach, afterEach } from 'node:test';
import { startServer, stopServer } from './server.mjs';
import { pages } from './fixtures.mjs';
import {
  assertNoErrors,
  closePage,
  launchChromium,
  newPage,
  openInspector,
  visibleRowCount,
  waitForShell,
  waitForVisible,
} from './helpers.mjs';

const PORT = 8784;
const BASE = `http://127.0.0.1:${PORT}`;

async function openValidationToolbar(page, t) {
  await page.goto(`${BASE}${pages.articleAdd}`);
  await waitForVisible(page, '[data-testid="article-form"]');
  const [response] = await Promise.all([
    page.waitForResponse(
      (r) => r.url().includes('/browser-fixtures/article-add') && r.request().method() === 'POST',
    ),
    page.click('[data-testid="article-form"] [type="submit"]'),
  ]);
  t.assert.equal(response.status(), 422);
  const requestId = response.headers()['x-debugbar-id'];
  t.assert.ok(requestId);
  const toolbar = await page.goto(`${BASE}/debug-bar/toolbar/${requestId}`);
  t.assert.equal(toolbar.status(), 200);
  await waitForShell(page);
  await page.click('[data-dk-window-controls="compact"] [data-dk-window="expand"]');
  await waitForVisible(page, '#debugbar-inspector');
  await page.click('[data-dk-inspector-nav-item="validation"]');
  await page.waitForFunction(
    () =>
      document
        .querySelector('#debugbar [data-dk-inspector-nav-item="validation"]')
        ?.getAttribute('aria-current') === 'page',
  );
}

describe('validation inspector', () => {
  let server;
  let browser;
  let handle;

  before(async () => {
    server = await startServer(PORT);
    browser = await launchChromium();
  });

  after(async () => {
    await browser.close().catch(() => {});
    await stopServer(server);
  });

  beforeEach(async () => {
    handle = await newPage(browser);
  });

  afterEach(async () => {
    await closePage(handle);
  });

  it('shows explicit field errors from a failed submit', async (t) => {
    const { page } = handle;
    await openValidationToolbar(page, t);

    const rows = await visibleRowCount(page, '[data-dk-validation-entry]');
    t.assert.ok(rows > 0, 'expected validation rows');
    const text = await page.textContent('[data-dk-inspector-panel="validation"]');
    t.assert.match(text ?? '', /title/i);
    assertNoErrors(t, handle);
  });
});
