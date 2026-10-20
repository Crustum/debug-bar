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

const PORT = 8781;
const BASE = `http://127.0.0.1:${PORT}`;

describe('mail inspector', () => {
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

  it('lists sent mail with subject', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.mail, 'mail');

    const rows = await visibleRowCount(page, '[data-dk-mail-key]');
    t.assert.ok(rows > 0, 'expected mail rows');
    const text = await page.textContent('[data-dk-inspector-panel="mail"]');
    t.assert.match(text ?? '', /Autumn options/);
    assertNoErrors(t, handle);
  });

  it('opens mail detail with preview', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.mail, 'mail');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-mail-key]').length > 0,
    );

    await page.click('[data-dk-mail-key]');
    const key = await page.getAttribute('[data-dk-mail-key]', 'data-dk-mail-key');
    await waitForVisible(page, `[data-dk-mail-detail="${key}"]:not([hidden])`);
    assertNoErrors(t, handle);
  });
});
