import { describe, it, before, after, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
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

const PORT = 8766;
const BASE = `http://127.0.0.1:${PORT}`;

describe('toolbar', () => {
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

  it('shows the quiet toolbar with actionable metrics and inert facts', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);
    await waitForVisible(page, '[data-dk-toolbar-shell]');

    t.assert.equal(
      await page.getAttribute('[data-dk-toolbar-shell]', 'data-dk-placement'),
      'bottom',
    );
    t.assert.equal(await page.getAttribute('#debugbar', 'data-dk-theme'), 'light');

    const kinds = await page.evaluate(() => ({
      duration: document.querySelector('[data-dk-toolbar="duration"]')?.tagName,
      memory: document.querySelector('[data-dk-toolbar="memory"]')?.tagName,
      queries: document.querySelector('[data-dk-toolbar="queries"]')?.tagName,
    }));
    t.assert.equal(kinds.duration, 'BUTTON');
    t.assert.equal(kinds.memory, 'DIV');
    t.assert.equal(kinds.queries, 'BUTTON');
    assertNoErrors(t, handle);
  });

  it('switches the theme from the menu and persists it', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await page.click('[data-dk-theme-trigger="toolbar"]');
    await waitForVisible(page, '[data-dk-theme-options="toolbar"]:not([hidden])');
    await page.click('[data-dk-theme-option="dark"]');
    t.assert.equal(await page.getAttribute('#debugbar', 'data-dk-theme'), 'dark');

    await page.reload();
    await waitForShell(page);
    t.assert.equal(await page.getAttribute('#debugbar', 'data-dk-theme'), 'dark');
    assertNoErrors(t, handle);
  });

  it('opens the inspector from metrics and window controls, closes until reload', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await page.click('[data-dk-toolbar="duration"]');
    await waitForVisible(page, '#debugbar-inspector');
    await assertInspectorSelected(t, page, 'request');

    await page.click('[data-dk-window-controls="expanded"] [data-dk-window="shrink"]');
    await waitForVisible(page, '[data-dk-toolbar-shell]');

    await page.click('[data-dk-window-controls="compact"] [data-dk-window="expand"]');
    await waitForVisible(page, '#debugbar-inspector');

    await page.click('[data-dk-window-controls="expanded"] [data-dk-window="close"]');
    await page.waitForFunction(
      () => getComputedStyle(document.querySelector('[role="toolbar"]')).display === 'none',
    );

    await page.reload();
    await waitForShell(page);
    await waitForVisible(page, '[data-dk-toolbar-shell]');
    assertNoErrors(t, handle);
  });

  it('opens the queries inspector from its metric', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await page.click('[data-dk-toolbar="queries"]');
    await waitForVisible(page, '#debugbar-inspector');
    await assertInspectorSelected(t, page, 'queries');
    assertNoErrors(t, handle);
  });
});
