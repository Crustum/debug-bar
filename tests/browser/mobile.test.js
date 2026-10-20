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

const PORT = 8772;
const BASE = `http://127.0.0.1:${PORT}`;
const MOBILE = { width: 390, height: 844 };

describe('mobile chrome', () => {
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
    handle = await newPage(browser, MOBILE);
  });

  afterEach(async () => {
    await closePage(handle);
  });

  it('swaps desktop facts for mobile menus', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);
    await waitForVisible(page, '[data-dk-toolbar-shell]');

    const visibility = await page.evaluate(() => ({
      desktopFacts: getComputedStyle(document.querySelector('[data-dk-toolbar-facts]')).display,
      desktopActions: getComputedStyle(document.querySelector('[data-dk-toolbar-actions]'))
        .display,
      mobileMetrics: getComputedStyle(
        document.querySelector('[data-dk-mobile-toolbar-control="metrics"]'),
      ).display,
      mobileActions: getComputedStyle(
        document.querySelector('[data-dk-mobile-toolbar-control="actions"]'),
      ).display,
    }));
    t.assert.equal(visibility.desktopFacts, 'none');
    t.assert.equal(visibility.desktopActions, 'none');
    t.assert.notEqual(visibility.mobileMetrics, 'none');
    t.assert.notEqual(visibility.mobileActions, 'none');
    assertNoErrors(t, handle);
  });

  it('opens the inspector from the mobile metric and actions menu', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await page.click('[data-dk-mobile-toolbar-control="metrics"] [data-dk-open-inspector]');
    await waitForVisible(page, '#debugbar-inspector');
    await assertInspectorSelected(t, page, 'request');

    await page.click('[data-dk-mobile-trigger="header-actions"]');
    await waitForVisible(page, '[data-dk-mobile-popover="header-actions"]:not([hidden])');
    await page.click('[data-dk-mobile-popover="header-actions"] [data-dk-window="shrink"]');
    await waitForVisible(page, '[data-dk-toolbar-shell]');
    await page.waitForTimeout(800);

    await page.click('[data-dk-mobile-trigger="actions"]');
    await waitForVisible(page, '[data-dk-mobile-popover="actions"]:not([hidden])');
    // Full-page popovers open downward by design (the host frame flips
    // them upward); drive the menu item directly to verify the flow.
    await page.evaluate(() =>
      document
        .querySelector('[data-dk-mobile-popover="actions"] [data-dk-open-inspector="request"]')
        ?.dispatchEvent(new MouseEvent('click', { bubbles: true })),
    );
    await waitForVisible(page, '#debugbar-inspector');
    assertNoErrors(t, handle);
  });

  it('reaches the palette from the mobile actions menu', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await page.click('[data-dk-mobile-trigger="actions"]');
    await waitForVisible(page, '[data-dk-mobile-popover="actions"]:not([hidden])');
    await page.evaluate(() =>
      document
        .querySelector('[data-dk-mobile-popover="actions"] [data-dk-open-palette]')
        ?.dispatchEvent(new MouseEvent('click', { bubbles: true })),
    );
    await waitForVisible(page, '[data-dk-palette]:not([hidden])');
    assertNoErrors(t, handle);
  });
});
