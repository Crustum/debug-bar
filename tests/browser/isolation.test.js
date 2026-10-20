import { describe, it, before, after, beforeEach, afterEach } from 'node:test';
import { startServer, stopServer } from './server.mjs';
import { pages } from './fixtures.mjs';
import {
  assertInspectorSelected,
  assertNoErrors,
  closePage,
  launchChromium,
  newPage,
  openToolbar,
  waitForShell,
  waitForVisible,
} from './helpers.mjs';

const PORT = 8773;
const BASE = `http://127.0.0.1:${PORT}`;

describe('hostile host isolation', () => {
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

  it('boots the toolbar frame on a hostile host page', async (t) => {
    const { page } = handle;
    await page.goto(`${BASE}${pages.hostile}`);
    const frame = page.frameLocator('iframe[src*="/debug-bar/toolbar/"]');
    await frame.locator('#debugbar').waitFor({ timeout: 15000 });
    const hostHeading = await page.textContent('[data-testid="host-page"]');
    t.assert.match(hostHeading ?? '', /Hostile styles/);
    assertNoErrors(t, handle);
  });

  it('keeps shell controls readable and functional in the isolated frame', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.hostile);
    await waitForVisible(page, '[data-dk-toolbar-shell]');

    const hostileLeak = await page.evaluate(() => {
      const expand = document.querySelector(
        '[data-dk-window-controls="compact"] [data-dk-window="expand"]',
      );
      const background = getComputedStyle(expand).backgroundColor;
      const height = expand.getBoundingClientRect().height;
      return { background, height };
    });
    t.assert.notEqual(hostileLeak.background, 'rgb(255, 0, 0)');
    t.assert.ok(hostileLeak.height < 91, `expected compact control, got ${hostileLeak.height}px`);

    await page.click('[data-dk-window-controls="compact"] [data-dk-window="expand"]');
    await waitForVisible(page, '#debugbar-inspector');
    await assertInspectorSelected(t, page, 'request');
    assertNoErrors(t, handle);
  });

  it('ignores host theme attributes and follows only package state', async (t) => {
    const { page } = handle;
    await page.goto(`${BASE}${pages.hostile}`);
    t.assert.equal(
      await page.evaluate(() => document.documentElement.getAttribute('data-theme')),
      'dark',
    );

    const frame = page.frameLocator('iframe[src*="/debug-bar/toolbar/"]');
    await frame.locator('#debugbar').waitFor({ timeout: 15000 });
    const shellTheme = await frame
      .locator('#debugbar')
      .evaluate((root) => root.getAttribute('data-dk-theme'));
    t.assert.equal(shellTheme, 'light');
    assertNoErrors(t, handle);
  });
});
