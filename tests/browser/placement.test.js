import { describe, it, before, after, beforeEach, afterEach } from 'node:test';
import { startServer, stopServer } from './server.mjs';
import { pages } from './fixtures.mjs';
import {
  assertInspectorSelected,
  assertNoErrors,
  closePage,
  dragToPoint,
  launchChromium,
  newPage,
  openToolbar,
  waitForShell,
  waitForVisible,
} from './helpers.mjs';

const PORT = 8767;
const BASE = `http://127.0.0.1:${PORT}`;
const PLACEMENTS = ['top-left', 'top', 'top-right', 'bottom-left', 'bottom', 'bottom-right'];

async function openPalette(page) {
  const centerVisible = await page.evaluate(() => {
    const center = document.querySelector('[data-dk-center-toolbar]');
    return (
      center !== null &&
      !center.hasAttribute('hidden') &&
      getComputedStyle(center).display !== 'none'
    );
  });
  if (centerVisible) {
    await page.click('[data-dk-toolbar="palette"]');
  } else {
    await page.click('[data-dk-corner-toolbar] [data-dk-window="expand"]');
    await waitForVisible(page, '#debugbar-inspector');
    await page.click('[data-dk-inspector-action="palette"]');
  }
  await waitForVisible(page, '[data-dk-palette]:not([hidden])');
}

async function pinPlacement(page, placement) {
  await openPalette(page);
  await page.fill('[data-dk-palette-search]', `pin to ${placement.replaceAll('-', ' ')}`);
  await page.click(`[data-dk-palette-command="toolbar:${placement}"]`);
  await page.waitForFunction(
    (expected) =>
      document.querySelector('[data-dk-toolbar-shell]')?.dataset.dkPlacement === expected,
    placement,
  );
}

describe('toolbar placement', () => {
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

  it('pins every placement from the palette and persists it', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    for (const placement of PLACEMENTS) {
      await pinPlacement(page, placement);
      t.assert.equal(
        await page.getAttribute('[data-dk-toolbar-shell]', 'data-dk-placement'),
        placement,
      );
      const corner = placement.includes('-');
      const forms = await page.evaluate(() => ({
        center: document.querySelector('[data-dk-center-toolbar]').hasAttribute('hidden'),
        corner: document.querySelector('[data-dk-corner-toolbar]').hasAttribute('hidden'),
      }));
      t.assert.equal(forms.center, corner);
      t.assert.equal(forms.corner, !corner);

      await page.reload();
      await waitForShell(page);
      t.assert.equal(
        await page.getAttribute('[data-dk-toolbar-shell]', 'data-dk-placement'),
        placement,
      );
    }
    assertNoErrors(t, handle);
  });

  it('drags the toolbar to the opposite edge and persists the anchor', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);
    await waitForVisible(page, '[data-dk-toolbar-shell]');

    await dragToPoint(page, '[data-dk-toolbar-shell]', { x: 720, y: 60 });
    await page.waitForFunction(() => {
      const shell = document.querySelector('[data-dk-toolbar-shell]');
      const stored = localStorage.getItem('dk.toolbar.preferredPlacement') ?? '';
      return (shell?.dataset.dkPlacement ?? '').startsWith('top') && stored.startsWith('top');
    });
    const placement = await page.getAttribute('[data-dk-toolbar-shell]', 'data-dk-placement');
    t.assert.match(placement, /^top/);

    const stored = await page.evaluate(
      () => localStorage.getItem('dk.toolbar.preferredPlacement'),
    );
    t.assert.match(stored, /^top/);

    await page.reload();
    await waitForShell(page);
    t.assert.match(
      await page.getAttribute('[data-dk-toolbar-shell]', 'data-dk-placement'),
      /^top/,
    );
    assertNoErrors(t, handle);
  });

  it('keeps the corner form compact with a working request switcher', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await pinPlacement(page, 'bottom-right');

    const forms = await page.evaluate(() => ({
      center: document.querySelector('[data-dk-center-toolbar]').hasAttribute('hidden'),
      corner: document.querySelector('[data-dk-corner-toolbar]').hasAttribute('hidden'),
    }));
    t.assert.equal(forms.center, true);
    t.assert.equal(forms.corner, false);

    await page.click('[data-dk-corner-toolbar] [data-dk-window="expand"]');
    await waitForVisible(page, '#debugbar-inspector');
    await assertInspectorSelected(t, page, 'request');
    assertNoErrors(t, handle);
  });
});
