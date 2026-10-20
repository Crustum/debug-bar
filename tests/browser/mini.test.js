import { describe, it, before, after, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { startServer, stopServer } from './server.mjs';
import { pages } from './fixtures.mjs';
import {
  assertNoErrors,
  closePage,
  launchChromium,
  newPage,
} from './helpers.mjs';

const PORT = 8788;
const BASE = `http://127.0.0.1:${PORT}`;
const VIEWPORT = { width: 1280, height: 900 };

const CORNERS = {
  'bottom-right': { x: 1240, y: 860 },
  'bottom-left': { x: 0, y: 860 },
  'top-right': { x: 1240, y: 0 },
  'top-left': { x: 0, y: 0 },
  'middle-right': { x: 1240, y: 430 },
  'middle-left': { x: 0, y: 430 },
};

function hostFrame(page) {
  const frame = page
    .frames()
    .find((candidate) => candidate.url().includes('/debug-bar/toolbar/'));
  if (!frame) {
    throw new Error('shell frame missing on the host page');
  }

  return frame;
}

async function serverDefaults(page) {
  return page.evaluate(() => {
    const tag = document.getElementById('__debug_bar_script');
    const frame = document.querySelector('#debugbar');
    void frame;

    return {
      position: tag?.getAttribute('data-mini-position') ?? 'bottom-right',
      action: 'bar',
    };
  });
}

async function shellAction(frame) {
  return frame.evaluate(
    () => document.getElementById('debugbar')?.getAttribute('data-dk-mini-action') ?? 'bar',
  );
}

async function hostRect(page) {
  const box = await page.evaluate(() => {
    const element = document.querySelector('iframe');
    if (!element) {
      return null;
    }
    const rect = element.getBoundingClientRect();

    return { x: rect.x, y: rect.y, width: rect.width, height: rect.height };
  });
  assert.ok(box, 'expected the host mini frame');

  return box;
}

async function waitForHostRect(page, expected, timeout = 10000) {
  await page.waitForFunction(
    (want) => {
      const element = document.querySelector('iframe');
      if (!element) {
        return false;
      }
      const rect = element.getBoundingClientRect();

      return (
        Math.abs(rect.width - want.width) < 2 &&
        Math.abs(rect.height - want.height) < 2 &&
        Math.abs(rect.x - want.x) < 2 &&
        Math.abs(rect.y - want.y) < 2
      );
    },
    expected,
    { timeout },
  );

  return hostRect(page);
}

async function dispatchShellEscape(frame) {
  await frame.evaluate(() => {
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
  });
}

describe('mini launcher', () => {
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

  it('docks the mini icon at the server position and opens on click', async () => {
    const { page } = handle;
    await page.goto(`${BASE}${pages.articles}`);
    const frame = hostFrame(page);
    const { position } = await serverDefaults(page);
    const action = await shellAction(frame);
    const corner = CORNERS[position] ?? CORNERS['bottom-right'];

    const mini = await waitForHostRect(page, { ...corner, width: 40, height: 40 });
    assert.equal(mini.width, 40);
    assert.equal(mini.height, 40);

    await page.frameLocator('iframe').locator('[data-dk-mini-toggle]').click();
    if (action === 'inspector') {
      const full = await waitForHostRect(page, { x: 0, y: 0, ...VIEWPORT });
      assert.equal(full.height, VIEWPORT.height);
    } else {
      const bar = await waitForHostRect(page, {
        x: 0,
        y: VIEWPORT.height - 40,
        width: VIEWPORT.width,
        height: 40,
      });
      assert.equal(bar.height, 40);
    }

    assertNoErrors({ assert }, handle);
  });

  it('walks inspector to bar to mini on repeated Escape', async () => {
    const { page } = handle;
    await page.goto(`${BASE}${pages.articles}`);
    const frame = hostFrame(page);
    const action = await shellAction(frame);

    await page.frameLocator('iframe').locator('[data-dk-mini-toggle]').click();
    if (action === 'inspector') {
      await waitForHostRect(page, { x: 0, y: 0, ...VIEWPORT });
      await dispatchShellEscape(frame);
    }

    const bar = await waitForHostRect(page, {
      x: 0,
      y: VIEWPORT.height - 40,
      width: VIEWPORT.width,
      height: 40,
    });
    assert.equal(bar.height, 40);

    await dispatchShellEscape(frame);
    const { position } = await serverDefaults(page);
    const corner = CORNERS[position] ?? CORNERS['bottom-right'];
    const docked = await waitForHostRect(page, { ...corner, width: 40, height: 40 });
    assert.equal(docked.width, 40);
    assert.equal(docked.height, 40);

    assertNoErrors({ assert }, handle);
  });

  it('opens the palette from the mini icon with the host hotkey', async () => {
    const { page } = handle;
    await page.goto(`${BASE}${pages.articles}`);
    hostFrame(page);

    await page.keyboard.press('Control+Alt+p');
    const palette = page.frameLocator('iframe').locator('[data-dk-palette]');
    await palette.waitFor({ state: 'visible', timeout: 10000 });
    const full = await hostRect(page);
    assert.equal(full.height, VIEWPORT.height);

    assertNoErrors({ assert }, handle);
  });

  it('pins the mini icon from the shell and follows the server reset', async () => {
    const { page } = handle;
    await page.goto(`${BASE}${pages.articles}`);
    const { position } = await serverDefaults(page);
    const other = position === 'top-left' ? 'bottom-right' : 'top-left';
    const corner = CORNERS[other];

    await hostFrame(page).evaluate((pos) => {
      window.parent.postMessage(`mini-position$$${pos}`, window.location.origin);
    }, other);
    const pinned = await waitForHostRect(page, { ...corner, width: 40, height: 40 });
    assert.ok(Math.abs(pinned.x - corner.x) < 2);

    await page.reload();
    const kept = await waitForHostRect(page, { ...corner, width: 40, height: 40 });
    assert.equal(kept.width, 40);

    await hostFrame(page).evaluate(() => {
      window.parent.postMessage('mini-position$$server-default', window.location.origin);
    });
    const home = CORNERS[position] ?? CORNERS['bottom-right'];
    const reset = await waitForHostRect(page, { ...home, width: 40, height: 40 });
    assert.ok(Math.abs(reset.x - home.x) < 2);

    assertNoErrors({ assert }, handle);
  });
});
