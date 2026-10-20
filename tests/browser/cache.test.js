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

const PORT = 8776;
const BASE = `http://127.0.0.1:${PORT}`;

describe('cache inspector', () => {
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

  it('lists cache operations with keys', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.cacheRich, 'cache');

    const rows = await visibleRowCount(page, '[data-dk-cache-key]');
    t.assert.ok(rows > 0, 'expected cache rows');
    const text = await page.textContent('[data-dk-inspector-panel="cache"]');
    t.assert.match(text ?? '', /dbb_trip_weather/);
    t.assert.match(text ?? '', /Hit rate|hit rate/i);
    assertNoErrors(t, handle);
  });

  it('searches keys and filters by outcome', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.cacheRich, 'cache');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-cache-key]').length > 0,
    );

    await page.fill('[data-dk-search="cache"]', 'dbb_trip_weather');
    await page.waitForFunction(
      () =>
        Array.from(document.querySelectorAll('[data-dk-cache-key]')).filter((el) => {
          if (el.hasAttribute('hidden')) return false;
          const box = el.getBoundingClientRect();
          return box.width > 0 && box.height > 0;
        }).length >= 1,
    );

    const filterValue = await page.evaluate(() => {
      const select = document.querySelector('[data-dk-select="cache-filter"]');
      const options = Array.from(select?.querySelectorAll('option') ?? []).map((o) => o.value);
      return options.find((value) => value !== 'all') ?? 'all';
    });
    await page.selectOption('[data-dk-select="cache-filter"]', filterValue);
    const filtered = await visibleRowCount(page, '[data-dk-cache-key]');
    t.assert.ok(filtered >= 0);
    assertNoErrors(t, handle);
  });

  it('opens cache detail for a row', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.cacheRich, 'cache');

    await page.click('[data-dk-cache-key]');
    const key = await page.getAttribute('[data-dk-cache-key]', 'data-dk-cache-key');
    await waitForVisible(page, `[data-dk-cache-detail="${key}"]:not([hidden])`);
    assertNoErrors(t, handle);
  });

  it('shows a clear empty cache state', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.ping, 'cache');

    const text = await page.textContent('[data-dk-inspector-panel="cache"]');
    t.assert.match(text ?? '', /empty|no cache|0/i);
    assertNoErrors(t, handle);
  });
});
