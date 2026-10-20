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
  waitForVisible,
} from './helpers.mjs';

const PORT = 8780;
const BASE = `http://127.0.0.1:${PORT}`;

describe('http client inspector', () => {
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

  it('lists the self-call request with timing', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.httpClient, 'http_client');

    const rows = await visibleRowCount(page, '[data-dk-http-entry]');
    t.assert.ok(rows > 0, 'expected http rows');
    const text = await page.textContent('[data-dk-inspector-panel="http_client"]');
    t.assert.match(text ?? '', /127\.0\.0\.1|ping/);
    assertNoErrors(t, handle);
  });

  it('searches and opens request detail with tabs', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.httpClient, 'http_client');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-http-entry]').length > 0,
    );

    await page.fill('[data-dk-search="http-client"]', 'ping');
    await page.waitForFunction(
      () =>
        Array.from(document.querySelectorAll('[data-dk-http-entry]')).filter((el) => {
          if (el.hasAttribute('hidden')) return false;
          const box = el.getBoundingClientRect();
          return box.width > 0 && box.height > 0;
        }).length >= 1,
    );

    await page.click('[data-dk-http-entry]');
    const key = await page.getAttribute('[data-dk-http-entry]', 'data-dk-http-key');
    await waitForVisible(page, `[data-dk-http-detail="${key}"]:not([hidden])`);
    assertNoErrors(t, handle);
  });
});
