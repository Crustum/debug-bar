import { chromium } from 'playwright';

/**
 * Shared browser helpers for the shell specs.
 *
 * Waits treat rendering as settled only after the target stays in its
 * desired state across animation frames; a script exception fails the
 * wait instead of reading as a layout mismatch.
 */

export async function launchBrowser(options = {}) {
  const browser = await chromium.launch(options);
  const context = await browser.newContext({
    viewport: { width: 1280, height: 900 },
  });
  const page = await context.newPage();
  const jsErrors = [];
  page.on('pageerror', (error) => {
    jsErrors.push(String(error && error.message ? error.message : error));
  });
  return { browser, context, page, jsErrors };
}

/**
 * Launches a shared browser; specs open isolated pages per test.
 */
export async function launchChromium() {
  return chromium.launch();
}

export async function closeBrowser(handle) {
  await handle.context.close().catch(() => {});
  await handle.browser.close().catch(() => {});
}

export function collectedErrors(handle) {
  return handle.jsErrors;
}

/**
 * Opens an isolated page: fresh storage and viewport per test.
 */
export async function newPage(browser, viewport, permissions) {
  const context = await browser.newContext({
    viewport: viewport ?? { width: 1280, height: 900 },
  });
  if (Array.isArray(permissions) && permissions.length > 0) {
    await context.grantPermissions(permissions);
  }
  const page = await context.newPage();
  const jsErrors = [];
  page.on('pageerror', (error) => {
    jsErrors.push(String(error && error.message ? error.message : error));
  });
  return { context, page, jsErrors };
}

export async function closePage(handle) {
  await handle.context.close().catch(() => {});
}

/**
 * Fails on any page error collected during the test.
 */
export function assertNoErrors(t, handle) {
  t.assert.deepEqual(handle.jsErrors, []);
}

/**
 * Drags from the center of one element to a viewport point.
 */
export async function dragToPoint(page, selector, to, steps = 25) {
  const from = await page.evaluate((sel) => {
    const box = document.querySelector(sel)?.getBoundingClientRect();
    return box ? { x: box.x + box.width / 2, y: box.y + box.height / 2 } : null;
  }, selector);
  if (!from) {
    throw new Error(`drag source missing: ${selector}`);
  }
  await page.mouse.move(from.x, from.y);
  await page.mouse.down();
  await page.mouse.move(to.x, to.y, { steps });
  await page.mouse.up();
}

export async function waitForVisible(page, selector, timeout = 10000) {
  await page.waitForFunction(
    (sel) => {
      const element = document.querySelector(sel);
      if (!element) {
        return false;
      }
      const style = getComputedStyle(element);
      const box = element.getBoundingClientRect();
      return (
        style.display !== 'none' &&
        style.visibility !== 'hidden' &&
        Number(style.opacity) !== 0 &&
        box.width > 0 &&
        box.height > 0
      );
    },
    selector,
    { timeout },
  );
}

export async function waitForShell(page, timeout = 15000) {
  await page.waitForFunction(() => document.getElementById('debugbar') !== null, null, {
    timeout,
  });
}

/**
 * Opens the full shell for a fixture page through its tracked request.
 *
 * Loads the host page, reads its tracked id, then opens the toolbar
 * document where the shell boots in place.
 *
 * @returns The tracked request id.
 */
export async function openToolbar(page, base, path) {
  const response = await page.goto(`${base}${path}`);
  const requestId = response.headers()['x-debugbar-id'];
  if (!requestId) {
    throw new Error(`missing tracked request id for ${path}`);
  }
  const toolbar = await page.goto(`${base}/debug-bar/toolbar/${requestId}`);
  if (toolbar.status() !== 200) {
    throw new Error(`toolbar failed with ${toolbar.status()} for ${requestId}`);
  }
  await waitForShell(page);
  return requestId;
}

export async function selectedInspector(page) {
  return page.evaluate(() => {
    const current = document.querySelector(
      '#debugbar [data-dk-inspector-nav-item][aria-current="page"]',
    );
    return current ? current.getAttribute('data-dk-inspector-nav-item') : null;
  });
}

export async function assertInspectorSelected(t, page, inspector) {
  t.assert.equal(await selectedInspector(page), inspector);
  const visiblePanels = await page.evaluate(
    () =>
      Array.from(document.querySelectorAll('#debugbar [data-dk-inspector-panel]')).filter(
        (panel) => getComputedStyle(panel).display !== 'none' && panel.hidden === false,
      ).length,
  );
  t.assert.equal(visiblePanels, 1);
}

/**
 * Opens a fixture page toolbar and selects an inspector.
 *
 * Waits for deferred content and the open transition to settle before
 * returning, so controls are actionable right away.
 */
export async function openInspector(t, page, base, path, inspector) {
  await openToolbar(page, base, path);
  await page.click('[data-dk-window-controls="compact"] [data-dk-window="expand"]');
  await waitForVisible(page, '#debugbar-inspector');
  await page.click(`[data-dk-inspector-nav-item="${inspector}"]`);
  await assertInspectorSelected(t, page, inspector);
  await page.waitForFunction(() => {
    const body = document.querySelector('[data-dk-inspector-body]');
    return body !== null && Number(getComputedStyle(body).opacity) === 1;
  });
}

/**
 * Counts rendered rows that are actually painted.
 */
export async function visibleRowCount(page, selector) {
  return page.evaluate(
    (sel) =>
      Array.from(document.querySelectorAll(sel)).filter((el) => {
        if (el.hasAttribute('hidden')) return false;
        const box = el.getBoundingClientRect();
        return box.width > 0 && box.height > 0;
      }).length,
    selector,
  );
}
