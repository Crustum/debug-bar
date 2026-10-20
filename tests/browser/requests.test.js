import { describe, it, before, after, beforeEach, afterEach } from 'node:test';
import { startServer, stopServer } from './server.mjs';
import { pages } from './fixtures.mjs';
import {
  assertNoErrors,
  closePage,
  launchChromium,
  newPage,
  openToolbar,
  waitForShell,
  waitForVisible,
} from './helpers.mjs';

const PORT = 8771;
const BASE = `http://127.0.0.1:${PORT}`;

describe('request switcher and discovery', () => {
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

  it('switches to a recent request without reloading', async (t) => {
    const { page } = handle;
    const firstId = await openToolbar(page, BASE, pages.articles);
    const secondId = await openToolbar(page, BASE, pages.logs);
    t.assert.notEqual(firstId, secondId);

    await page.click('[data-dk-request-trigger="toolbar"]');
    await waitForVisible(page, '[data-dk-request-picker="toolbar"]:not([hidden])');
    await page.click(`[data-dk-request-option="${firstId}"]`);

    await page.waitForFunction(
      (expected) => document.getElementById('debugbar')?.dataset.dkRequestId === expected,
      firstId,
    );
    const heading = await page.textContent('[data-dk-inspector-heading]');
    t.assert.match(heading ?? '', /Request/);
    const copyValue = await page.getAttribute(
      '[data-dk-copy-request-id]',
      'data-dk-copy-value',
    );
    t.assert.equal(copyValue, firstId);
    assertNoErrors(t, handle);
  });

  it('notices background host requests in the toolbar frame', async (t) => {
    const { page } = handle;
    await page.goto(`${BASE}${pages.articles}`);
    const frame = page.frameLocator('iframe[src*="/debug-bar/toolbar/"]');
    await frame.locator('#debugbar').waitFor({ timeout: 15000 });

    await page.evaluate(() => fetch('/browser-fixtures/ping').then((response) => response.text()));
    // The badge lives in the 40px mini frame, so it has no painted box:
    // assert on DOM state instead of visibility.
    await page.waitForFunction(() => {
      const frameElement = document.querySelector('iframe[src*="/debug-bar/toolbar/"]');
      const badge = frameElement?.contentDocument?.querySelector(
        '[data-dk-request-badge]:not([hidden])',
      );
      return badge !== null && badge !== undefined && (badge.textContent ?? '').trim() !== '';
    });
    assertNoErrors(t, handle);
  });
});
