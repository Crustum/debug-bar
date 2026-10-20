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

const PORT = 8787;
const BASE = `http://127.0.0.1:${PORT}`;

describe('findings inspector', () => {
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

  it('lists findings with severity and source links', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.timelineLong, 'findings');

    const rows = await visibleRowCount(page, '[data-dk-finding]');
    t.assert.ok(rows > 0, 'expected findings');
    assertNoErrors(t, handle);
  });

  it('opens the source inspector from a finding link', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.timelineLong, 'findings');

    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-finding] [data-dk-open-inspector]').length > 0,
    );
    const target = await page.evaluate(
      () =>
        document
          .querySelector('[data-dk-finding] [data-dk-open-inspector]')
          ?.getAttribute('data-dk-open-inspector') ?? null,
    );
    t.assert.ok(target);

    await page.click('[data-dk-finding] [data-dk-open-inspector]');
    await page.waitForFunction(
      (expected) =>
        document
          .querySelector('#debugbar [data-dk-inspector-nav-item][aria-current="page"]')
          ?.getAttribute('data-dk-inspector-nav-item') === expected,
      target,
    );
    assertNoErrors(t, handle);
  });
});
