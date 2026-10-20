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

const PORT = 8779;
const BASE = `http://127.0.0.1:${PORT}`;

describe('views inspector', () => {
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

  it('lists rendered views', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.views, 'views');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-view-entry]').length > 0,
    );

    const text = await page.textContent('[data-dk-inspector-panel="views"]');
    t.assert.match(text ?? '', /BrowserFixtures|context|views/i);
    assertNoErrors(t, handle);
  });

  it('searches views and lazily loads view data', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.views, 'views');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-view-entry]').length > 0,
    );

    await page.fill('[data-dk-search="views"]', 'views.php');
    const rows = await visibleRowCount(page, '[data-dk-view-entry]');
    t.assert.ok(rows >= 1);

    await page.click('[data-dk-view-entry]');
    await page.waitForFunction(
      () => {
        const panel = document.querySelector(
          '[data-dk-view-detail-content], [data-dk-view-data-panel]',
        );
        return panel !== null && (panel.textContent ?? '').trim().length > 0;
      },
      null,
      { timeout: 15000 },
    );
    assertNoErrors(t, handle);
  });
});
