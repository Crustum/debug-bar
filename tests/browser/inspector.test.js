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
  waitForVisible,
} from './helpers.mjs';

const PORT = 8769;
const BASE = `http://127.0.0.1:${PORT}`;

describe('inspector shell', () => {
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

  it('opens the inspector with a single selected panel', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await page.click('[data-dk-window-controls="compact"] [data-dk-window="expand"]');
    await waitForVisible(page, '#debugbar-inspector');

    const selected = await page.evaluate(
      () =>
        document.querySelector('#debugbar [data-dk-inspector-nav-item][aria-current="page"]')
          ?.dataset.dkInspectorNavItem ?? null,
    );
    t.assert.ok(selected);
    await assertInspectorSelected(t, page, selected);
    assertNoErrors(t, handle);
  });

  it('switches inspectors from the navigation with one visible panel', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await page.click('[data-dk-window-controls="compact"] [data-dk-window="expand"]');
    await waitForVisible(page, '#debugbar-inspector');

    await page.click('[data-dk-inspector-nav-item="queries"]');
    await page.waitForFunction(
      () =>
        document
          .querySelector('#debugbar [data-dk-inspector-nav-item="queries"]')
          ?.getAttribute('aria-current') === 'page',
    );
    await assertInspectorSelected(t, page, 'queries');

    await page.click('[data-dk-inspector-nav-item="request"]');
    await page.waitForFunction(
      () =>
        document
          .querySelector('#debugbar [data-dk-inspector-nav-item="request"]')
          ?.getAttribute('aria-current') === 'page',
    );
    await assertInspectorSelected(t, page, 'request');
    assertNoErrors(t, handle);
  });

  it('closes the inspector back to the toolbar', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await page.click('[data-dk-toolbar="duration"]');
    await waitForVisible(page, '#debugbar-inspector');

    await page.keyboard.press('Escape');
    await page.waitForFunction(
      () => document.querySelector('[data-dk-inspector]')?.hasAttribute('hidden') === true,
    );
    await waitForVisible(page, '[data-dk-toolbar-shell]');
    assertNoErrors(t, handle);
  });
});
