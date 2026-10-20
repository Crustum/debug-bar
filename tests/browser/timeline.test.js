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
} from './helpers.mjs';

const PORT = 8785;
const BASE = `http://127.0.0.1:${PORT}`;

describe('timeline inspector', () => {
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

  it('merges framework and query events on one axis', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.timelineLong, 'timeline');

    const rows = await visibleRowCount(page, '[data-dk-timeline-entry]');
    t.assert.ok(rows > 10, 'expected a dense timeline');
    assertNoErrors(t, handle);
  });

  it('filters by key and searches timeline rows', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.timelineLong, 'timeline');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-timeline-entry]').length > 10,
    );

    await page.fill('[data-dk-search="timeline"]', 'no-such-timeline-row-xyz');
    await page.waitForFunction(
      () =>
        Array.from(document.querySelectorAll('[data-dk-timeline-entry]')).filter((el) => {
          if (el.hasAttribute('hidden')) return false;
          const box = el.getBoundingClientRect();
          return box.width > 0 && box.height > 0;
        }).length === 0,
    );
    assertNoErrors(t, handle);
  });
});
