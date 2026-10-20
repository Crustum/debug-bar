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

const PORT = 8770;
const BASE = `http://127.0.0.1:${PORT}`;

async function favoriteKeys(page, group) {
  return page.evaluate(
    (scope) =>
      Array.from(
        document.querySelectorAll(
          `#debugbar [data-dk-nav-group="${scope}"] [data-dk-inspector-nav-item]`,
        ),
      ).map((item) => item.getAttribute('data-dk-inspector-nav-item')),
    group,
  );
}

describe('favorites and copy feedback', () => {
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

  it('stars an inspector and persists the favorites group', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await page.click('[data-dk-window-controls="compact"] [data-dk-window="expand"]');
    await waitForVisible(page, '#debugbar-inspector');
    await page.click('[data-dk-inspector-favorite="queries"]');

    await page.waitForFunction(
      () =>
        document.querySelector('[data-dk-nav-group="favorites"]')?.hasAttribute('hidden') ===
          false,
    );
    t.assert.deepEqual(await favoriteKeys(page, 'favorites'), ['queries']);

    await page.reload();
    await waitForShell(page);
    await page.click('[data-dk-window-controls="compact"] [data-dk-window="expand"]');
    await waitForVisible(page, '#debugbar-inspector');
    t.assert.deepEqual(await favoriteKeys(page, 'favorites'), ['queries']);
    assertNoErrors(t, handle);
  });

  it('reorders favorites with the keyboard and persists the order', async (t) => {
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);

    await page.click('[data-dk-window-controls="compact"] [data-dk-window="expand"]');
    await waitForVisible(page, '#debugbar-inspector');
    await page.click('[data-dk-inspector-favorite="queries"]');
    await page.click('[data-dk-inspector-favorite="request"]');
    await page.waitForFunction(
      () =>
        Array.from(
          document.querySelectorAll(
            '#debugbar [data-dk-nav-group="favorites"] [data-dk-inspector-nav-item]',
          ),
        ).length === 2,
    );
    const beforeOrder = await favoriteKeys(page, 'favorites');

    const second = await page.evaluate(() => {
      const items = Array.from(
        document.querySelectorAll(
          '#debugbar [data-dk-nav-group="favorites"] [data-dk-inspector-nav-item]',
        ),
      );
      items[1]?.focus();
      return items[1]?.getAttribute('data-dk-inspector-nav-item') ?? null;
    });
    await page.keyboard.press('Shift+ArrowUp');
    await page.waitForFunction(
      (moved) =>
        document
          .querySelector('#debugbar [data-dk-nav-group="favorites"] [data-dk-inspector-nav-item]')
          ?.getAttribute('data-dk-inspector-nav-item') === moved,
      second,
    );
    const afterOrder = await favoriteKeys(page, 'favorites');
    t.assert.notDeepEqual(afterOrder, beforeOrder);

    await page.reload();
    await waitForShell(page);
    await page.click('[data-dk-window-controls="compact"] [data-dk-window="expand"]');
    await waitForVisible(page, '#debugbar-inspector');
    t.assert.deepEqual(await favoriteKeys(page, 'favorites'), afterOrder);
    assertNoErrors(t, handle);
  });

  it('copies the request id with feedback', async (t) => {
    await closePage(handle);
    handle = await newPage(browser, undefined, ['clipboard-write', 'clipboard-read']);
    const { page } = handle;
    await openToolbar(page, BASE, pages.articles);
    await waitForVisible(page, '[data-dk-toolbar-shell]');

    await page.click('[data-dk-copy]');
    await page.waitForFunction(
      () =>
        document.querySelector('[data-dk-copy-label]')?.textContent?.trim() === 'Copied',
    );

    const clipboard = await page.evaluate(() => navigator.clipboard.readText());
    const expected = await page.getAttribute('[data-dk-copy]', 'data-dk-copy-value');
    t.assert.equal(clipboard, expected);
    assertNoErrors(t, handle);
  });
});
