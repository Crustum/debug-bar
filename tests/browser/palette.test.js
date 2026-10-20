import { describe, it, before, after, beforeEach, afterEach } from 'node:test';
import { startServer, stopServer } from './server.mjs';
import { pages } from './fixtures.mjs';
import {
  assertNoErrors,
  closePage,
  launchChromium,
  newPage,
  openToolbar,
  waitForShell,
  waitForVisible,
} from './helpers.mjs';

const PORT = 8768;
const BASE = `http://127.0.0.1:${PORT}`;

async function openPalette(page) {
  await waitForVisible(page, '[data-dk-toolbar-shell]');
  await page.click('[data-dk-toolbar="palette"]');
  await waitForVisible(page, '[data-dk-palette]:not([hidden])');
}

describe('command palette', () => {
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

  it('opens with focused search and runs a filtered command', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await openPalette(page);
    t.assert.equal(
      await page.evaluate(() => document.activeElement === document.querySelector('[data-dk-palette-search]')),
      true,
    );

    await page.fill('[data-dk-palette-search]', 'dark theme');
    await page.click('[data-dk-palette-command="theme:dark"]');
    t.assert.equal(await page.getAttribute('#debugbar', 'data-dk-theme'), 'dark');
    assertNoErrors(t, handle);
  });

  it('runs the top filtered command with Enter', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await openPalette(page);
    await page.fill('[data-dk-palette-search]', 'pin to top right');
    await page.keyboard.press('Enter');
    await page.waitForFunction(
      () => document.querySelector('[data-dk-toolbar-shell]')?.dataset.dkPlacement === 'top-right',
    );
    t.assert.equal(
      await page.getAttribute('[data-dk-toolbar-shell]', 'data-dk-placement'),
      'top-right',
    );
    assertNoErrors(t, handle);
  });

  it('closes through Escape layers down to the toolbar', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await page.click('[data-dk-toolbar="duration"]');
    await waitForVisible(page, '#debugbar-inspector');
    await page.click('[data-dk-inspector-action="palette"]');
    await waitForVisible(page, '[data-dk-palette]:not([hidden])');

    await page.keyboard.press('Escape');
    await page.waitForFunction(
      () => document.querySelector('[data-dk-palette]')?.hasAttribute('hidden') === true,
    );
    const inspectorOpen = await page.evaluate(
      () => document.querySelector('#debugbar-inspector')?.hidden === false,
    );
    t.assert.equal(inspectorOpen, true);

    await page.keyboard.press('Escape');
    await page.waitForFunction(
      () => document.querySelector('[data-dk-inspector]')?.hasAttribute('hidden') === true,
    );
    await waitForVisible(page, '[data-dk-toolbar-shell]');
    assertNoErrors(t, handle);
  });

  it('toggles from the keyboard shortcut', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await page.click('[data-dk-toolbar-shell]');
    await page.keyboard.press('Control+Alt+P');
    await waitForVisible(page, '[data-dk-palette]:not([hidden])');

    await page.keyboard.press('Control+Alt+P');
    await page.waitForFunction(
      () => document.querySelector('[data-dk-palette]')?.hasAttribute('hidden') === true,
    );
    assertNoErrors(t, handle);
  });
});
