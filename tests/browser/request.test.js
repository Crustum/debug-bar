import { describe, it, before, after, beforeEach, afterEach } from 'node:test';
import { startServer, stopServer } from './server.mjs';
import { pages } from './fixtures.mjs';
import {
  assertInspectorSelected,
  assertNoErrors,
  closePage,
  launchChromium,
  newPage,
  openInspector,
  waitForVisible,
} from './helpers.mjs';

const PORT = 8786;
const BASE = `http://127.0.0.1:${PORT}`;

describe('request inspector', () => {
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

  it('walks the request steps', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.articles, 'request');

    for (const step of ['received', 'matched', 'responded']) {
      const visible = await page.evaluate(
        (name) => {
          const el = document.querySelector(`[data-dk-request-step="${name}"]`);
          if (!el) return false;
          const box = el.getBoundingClientRect();
          return box.width > 0 && box.height > 0;
        },
        step,
      );
      t.assert.equal(visible, true, `expected step ${step}`);
    }
    assertNoErrors(t, handle);
  });

  it('switches request detail tabs', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.articles, 'request');

    await page.click('[data-dk-request-details] > summary');
    await page.waitForSelector('[data-dk-tab="request:query"]', { state: 'visible' });
    await page.click('[data-dk-tab="request:query"]');
    await waitForVisible(page, '[data-dk-tab-panel="request:query"]:not([hidden])');
    await assertInspectorSelected(t, page, 'request');
    assertNoErrors(t, handle);
  });
});
