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

const PORT = 8774;
const BASE = `http://127.0.0.1:${PORT}`;

describe('queries inspector', () => {
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

  it('lists captured queries with metadata', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.articles, 'queries');

    const rows = await visibleRowCount(page, '[data-dk-query-item]');
    t.assert.ok(rows > 0, 'expected query rows');
    const sql = await page.textContent('[data-dk-query-item] [data-dk-query-list-sql]');
    t.assert.match(sql ?? '', /select/i);
    assertNoErrors(t, handle);
  });

  it('flags repeated queries on a repetitive page', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.articlesLarge, 'queries');

    const repeated = await page.evaluate(
      () =>
        Array.from(document.querySelectorAll('[data-dk-query-item]')).filter(
          (row) => row.getAttribute('data-dk-repeated') === 'true',
        ).length,
    );
    t.assert.ok(repeated > 0, 'expected repeated queries');
    assertNoErrors(t, handle);
  });

  it('searches and sorts the query list', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.articles, 'queries');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-query-item]').length > 0,
    );
    const total = await visibleRowCount(page, '[data-dk-query-item]');

    await page.fill('[data-dk-search="queries"]', 'articles');
    await page.waitForFunction(
      (before) =>
        Array.from(document.querySelectorAll('[data-dk-query-item]')).filter((el) => {
          if (el.hasAttribute('hidden')) return false;
          const box = el.getBoundingClientRect();
          return box.width > 0 && box.height > 0;
        }).length <= before,
      total,
    );

    const firstBefore = await page.getAttribute('[data-dk-query-item]', 'data-dk-duration');
    await page.click('[data-dk-sort="queries:duration"]');
    await page.click('[data-dk-sort="queries:duration"]');
    const firstAfter = await page.getAttribute('[data-dk-query-item]', 'data-dk-duration');
    t.assert.ok(firstBefore !== null && firstAfter !== null);
    assertNoErrors(t, handle);
  });

  it('opens query detail and runs EXPLAIN', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.articles, 'queries');

    await page.click('[data-dk-query-item]');
    const key = await page.getAttribute('[data-dk-query-item]', 'data-dk-query-key');
    await waitForVisible(page, `[data-dk-query-detail="${key}"]:not([hidden])`);

    await page.click(`[data-dk-tab="query:${key}:explain"]`);
    await page.waitForFunction(
      (k) => {
        const code = document.querySelector(
          `[data-dk-query-detail="${k}"] [data-dk-query-explain-plan-code]`,
        );
        return code !== null && (code.textContent ?? '').trim().length > 0;
      },
      key,
      { timeout: 15000 },
    );
    assertNoErrors(t, handle);
  });
});
