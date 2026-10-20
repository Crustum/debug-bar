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

const PORT = 8782;
const BASE = `http://127.0.0.1:${PORT}`;

describe('exceptions inspector', () => {
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

  it('lists reported throwables with causes', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.exceptions, 'exceptions');

    const rows = await visibleRowCount(page, '[data-dk-exception-entry]');
    t.assert.equal(rows, 2);
    const text = await page.textContent('[data-dk-inspector-panel="exceptions"]');
    t.assert.match(text ?? '', /Reported failure/);
    t.assert.match(text ?? '', /Second reported failure/);
    assertNoErrors(t, handle);
  });

  it('switches exception detail panes', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.exceptions, 'exceptions');

    await page.click('[data-dk-exception-key="exception-2"]');
    await waitForVisible(page, '[data-dk-exception-detail="exception-2"]:not([hidden])');
    const hidden = await page.evaluate(
      () =>
        document
          .querySelector('[data-dk-exception-detail="exception-1"]')
          ?.hasAttribute('hidden') ?? null,
    );
    t.assert.equal(hidden, true);
    assertNoErrors(t, handle);
  });
});
