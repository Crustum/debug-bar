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

const PORT = 8783;
const BASE = `http://127.0.0.1:${PORT}`;

describe('authorization inspector', () => {
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

  it('lists allowed and denied decisions', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.authorization, 'authorization');

    const text = await page.textContent('[data-dk-inspector-panel="authorization"]');
    t.assert.match(text ?? '', /view/);
    t.assert.match(text ?? '', /refund/);
    assertNoErrors(t, handle);
  });

  it('opens authorization detail', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.authorization, 'authorization');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-authorization-key]').length > 0,
    );

    await page.click('[data-dk-authorization-key]');
    const key = await page.getAttribute(
      '[data-dk-authorization-key]',
      'data-dk-authorization-key',
    );
    await waitForVisible(page, `[data-dk-authorization-detail="${key}"]:not([hidden])`);
    assertNoErrors(t, handle);
  });
});
