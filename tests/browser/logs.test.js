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

const PORT = 8775;
const BASE = `http://127.0.0.1:${PORT}`;

describe('logs inspector', () => {
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

  it('groups log entries with counts and severities', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.logs, 'log');

    const groups = await visibleRowCount(page, '[data-dk-log-key]');
    t.assert.ok(groups > 0, 'expected log groups');
    const text = await page.textContent('[data-dk-inspector-panel="log"]');
    t.assert.match(text ?? '', /Rail reservation refresh failed/);
    assertNoErrors(t, handle);
  });

  it('filters by level and searches messages', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.logs, 'log');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-log-key]').length > 0,
    );
    const total = await visibleRowCount(page, '[data-dk-log-key]');

    await page.selectOption('[data-dk-select="logs-level"]', 'error');
    await page.waitForFunction(
      (before) =>
        Array.from(document.querySelectorAll('[data-dk-log-key]')).filter((el) => {
          if (el.hasAttribute('hidden')) return false;
          const box = el.getBoundingClientRect();
          return box.width > 0 && box.height > 0;
        }).length < before,
      total,
    );

    await page.selectOption('[data-dk-select="logs-level"]', 'all');
    await page.fill('[data-dk-search="logs"]', 'workspace');
    await page.waitForFunction(
      () =>
        Array.from(document.querySelectorAll('[data-dk-log-key]')).filter((el) => {
          if (el.hasAttribute('hidden')) return false;
          const box = el.getBoundingClientRect();
          return box.width > 0 && box.height > 0;
        }).length >= 1,
    );
    assertNoErrors(t, handle);
  });

  it('opens log detail with context', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.logs, 'log');

    await page.click('[data-dk-log-key]');
    await waitForVisible(page, '[data-dk-log-detail]:not([hidden])');
    assertNoErrors(t, handle);
  });
});
