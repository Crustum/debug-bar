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

const PORT = 8778;
const BASE = `http://127.0.0.1:${PORT}`;

describe('models inspector', () => {
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

  it('groups model activity with retrieval counts', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.articles, 'models');

    const rows = await visibleRowCount(page, '[data-dk-model-entry]');
    t.assert.ok(rows > 0, 'expected model groups');
    const text = await page.textContent('[data-dk-inspector-panel="models"]');
    t.assert.match(text ?? '', /articles/i);
    assertNoErrors(t, handle);
  });

  it('sorts groups and opens group detail', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.articlesLarge, 'models');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-model-entry]').length > 0,
    );

    await page.click('[data-dk-sort="models:retrieved"]');
    const first = await page.evaluate(
      () =>
        document
          .querySelector('[data-dk-model-entry]')
          ?.getAttribute('data-dk-model-sort-retrieved') ?? null,
    );
    t.assert.ok(first !== null);

    await page.click('[data-dk-model-entry]');
    const key = await page.getAttribute('[data-dk-model-entry]', 'data-dk-model-key');
    await waitForVisible(page, `[data-dk-model-detail="${key}"]:not([hidden])`);
    assertNoErrors(t, handle);
  });

  it('searches model groups', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.articles, 'models');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-model-entry]').length > 0,
    );

    await page.fill('[data-dk-search="models"]', 'no-such-table-xyz');
    await page.waitForFunction(
      () =>
        Array.from(document.querySelectorAll('[data-dk-model-entry]')).filter((el) => {
          if (el.hasAttribute('hidden')) return false;
          const box = el.getBoundingClientRect();
          return box.width > 0 && box.height > 0;
        }).length === 0,
    );
    assertNoErrors(t, handle);
  });
});
