import { describe, it, before, after, beforeEach, afterEach } from 'node:test';
import { startServer, stopServer } from './server.mjs';
import { pages } from './fixtures.mjs';
import {
  assertInspectorSelected,
  assertNoErrors,
  closePage,
  launchChromium,
  newPage,
  openInspector,
  visibleRowCount,
} from './helpers.mjs';

const PORT = 8785;
const BASE = `http://127.0.0.1:${PORT}`;

describe('timeline inspector', () => {
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

  it('merges framework and query events on one axis', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.timelineLong, 'timeline');

    const rows = await visibleRowCount(page, '[data-dk-timeline-entry]');
    t.assert.ok(rows > 10, 'expected a dense timeline');
    assertNoErrors(t, handle);
  });

  it('filters by key and searches timeline rows', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.timelineLong, 'timeline');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-timeline-entry]').length > 10,
    );

    await page.fill('[data-dk-search="timeline"]', 'no-such-timeline-row-xyz');
    await page.waitForFunction(
      () =>
        Array.from(document.querySelectorAll('[data-dk-timeline-entry]')).filter((el) => {
          if (el.hasAttribute('hidden')) return false;
          const box = el.getBoundingClientRect();
          return box.width > 0 && box.height > 0;
        }).length === 0,
    );
    assertNoErrors(t, handle);
  });

  it('opens the preview when a row is clicked', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.timelineLong, 'timeline');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-timeline-entry]').length > 10,
    );

    await page.click('[data-dk-timeline-entry]');
    const state = await page.evaluate(() => {
      const row = document.querySelector(
        '#debugbar [data-dk-timeline-entry][aria-pressed="true"]',
      );
      const detail = document.querySelector(
        '#debugbar [data-dk-timeline-detail]:not([hidden])',
      );
      return {
        key: row ? row.getAttribute('data-dk-timeline-key') : null,
        detail: detail ? detail.getAttribute('data-dk-timeline-detail') : null,
      };
    });
    t.assert.ok(state.key, 'expected a pressed timeline row');
    t.assert.equal(state.detail, state.key);
    assertNoErrors(t, handle);
  });

  it('jumps from a row icon to the exact inspector record', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.timelineLong, 'timeline');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-timeline-entry]').length > 10,
    );

    const jump = await page.evaluate(() => {
      const rows = Array.from(
        document.querySelectorAll('#debugbar [data-dk-timeline-entry]'),
      );
      const row = rows.find((entry) => entry.querySelector('[data-dk-timeline-jump]'));
      if (!row) {
        return null;
      }
      const button = row.querySelector('[data-dk-timeline-jump]');
      return {
        key: row.getAttribute('data-dk-timeline-key'),
        inspector: button.getAttribute('data-dk-open-inspector'),
        ref: button.getAttribute('data-dk-open-inspector-ref'),
      };
    });
    t.assert.ok(jump, 'expected a timeline row with a jump icon');

    await page.click(`[data-dk-timeline-key="${jump.key}"] [data-dk-timeline-jump]`);
    await assertInspectorSelected(t, page, jump.inspector);
    await page.waitForFunction(() => {
      const panel = document.querySelector(
        '#debugbar [data-dk-inspector-panel]:not([hidden])',
      );
      return panel !== null && panel.querySelector('[aria-pressed="true"]') !== null;
    });
    const pressed = await page.evaluate(() => {
      const panel = document.querySelector(
        '#debugbar [data-dk-inspector-panel]:not([hidden])',
      );
      const active = panel ? panel.querySelector('[aria-pressed="true"]') : null;
      if (!active) {
        return null;
      }
      const keys = Array.from(active.attributes)
        .filter(
          (attr) =>
            attr.name === 'data-dk-execution' || attr.name.endsWith('-key'),
        )
        .map((attr) => attr.value);
      return keys;
    });
    t.assert.ok(
      pressed !== null && pressed.includes(jump.ref),
      `expected the pressed record to carry ref ${jump.ref}`,
    );
    assertNoErrors(t, handle);
  });

  it('returns from a jump to the timeline list without the preview', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.timelineLong, 'timeline');
    await page.waitForFunction(
      () => document.querySelector('[data-dk-timeline-entry] [data-dk-timeline-jump]') !== null,
    );

    const key = await page.evaluate(
      () =>
        document
          .querySelector('[data-dk-timeline-entry] [data-dk-timeline-jump]')
          ?.closest('[data-dk-timeline-entry]')
          ?.getAttribute('data-dk-timeline-key'),
    );
    await page.click(`[data-dk-timeline-key="${key}"] [data-dk-timeline-jump]`);
    await page.waitForFunction(
      () =>
        document.querySelector('[data-dk-timeline-back]') !== null &&
        !document.querySelector('[data-dk-timeline-back]').hasAttribute('hidden'),
    );

    await page.click('[data-dk-timeline-back]');
    await assertInspectorSelected(t, page, 'timeline');
    const state = await page.evaluate(() => {
      const row = document.querySelector(
        '#debugbar [data-dk-timeline-entry][aria-pressed="true"]',
      );
      const focusDetail = document.querySelector(
        '#debugbar [data-dk-timeline-panel] [data-dk-inspector-focus-detail]',
      );
      const content = document.querySelector('#debugbar [data-dk-inspector-content]');
      const rowBox = row ? row.getBoundingClientRect() : null;
      const viewBox = content ? content.getBoundingClientRect() : null;
      return {
        key: row ? row.getAttribute('data-dk-timeline-key') : null,
        detailHidden: focusDetail ? focusDetail.hasAttribute('hidden') : null,
        rowTop: rowBox ? rowBox.top : null,
        rowBottom: rowBox ? rowBox.bottom : null,
        viewTop: viewBox ? viewBox.top : null,
        viewBottom: viewBox ? viewBox.bottom : null,
      };
    });
    t.assert.equal(state.key, key);
    t.assert.equal(state.detailHidden, true);
    t.assert.ok(
      state.rowTop !== null &&
        state.rowTop >= state.viewTop &&
        state.rowBottom <= state.viewBottom,
      'expected the anchored row inside the timeline viewport',
    );
    const viewCenter = (state.viewTop + state.viewBottom) / 2;
    const rowCenter = (state.rowTop + state.rowBottom) / 2;
    t.assert.ok(
      Math.abs(rowCenter - viewCenter) < (state.viewBottom - state.viewTop) / 4,
      'expected the anchored row near the viewport middle',
    );
    assertNoErrors(t, handle);
  });

  it('hides the back button on direct tab navigation', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.timelineLong, 'timeline');

    await page.click('[data-dk-inspector-nav-item="queries"]');
    await assertInspectorSelected(t, page, 'queries');
    const hidden = await page.evaluate(
      () =>
        document.querySelector('[data-dk-timeline-back]')?.hasAttribute('hidden') ?? true,
    );
    t.assert.equal(hidden, true);
    assertNoErrors(t, handle);
  });
});
