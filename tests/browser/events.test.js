import { describe, it, before, after, beforeEach, afterEach } from 'node:test';
import { startServer, stopServer } from './server.mjs';
import { pages } from './fixtures.mjs';
import {
  assertNoErrors,
  closePage,
  launchChromium,
  newPage,
  openInspector,
  visibleRowCount,
  waitForVisible,
} from './helpers.mjs';

const PORT = 8777;
const BASE = `http://127.0.0.1:${PORT}`;

describe('events inspector', () => {
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

  it('groups dispatched events with listener outcomes', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.events, 'events');

    const rows = await visibleRowCount(page, '[data-dk-event-key]');
    t.assert.ok(rows > 0, 'expected event rows');
    const text = await page.textContent('[data-dk-inspector-panel="events"]');
    t.assert.match(text ?? '', /dbb\.trip\.planned/);
    assertNoErrors(t, handle);
  });

  it('filters by source and searches events', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.events, 'events');
    await page.waitForFunction(
      () => document.querySelectorAll('[data-dk-event-key]').length > 0,
    );

    await page.selectOption('[data-dk-select="events-filter"]', 'application');
    const text = await page.textContent('[data-dk-inspector-panel="events"]');
    t.assert.match(text ?? '', /dbb\.trip/);

    await page.fill('[data-dk-search="events"]', 'archived');
    await page.waitForFunction(
      () =>
        (document.querySelector('[data-dk-inspector-panel="events"]')?.textContent ?? '').includes(
          'dbb.trip.archived',
        ),
    );
    assertNoErrors(t, handle);
  });

  it('opens event detail with tabs', async (t) => {
    const { page } = handle;
    await openInspector(t, page, BASE, pages.events, 'events');

    await page.click('[data-dk-event-key]');
    const key = await page.getAttribute('[data-dk-event-key]', 'data-dk-event-key');
    await waitForVisible(page, `[data-dk-event-detail="${key}"]:not([hidden])`);
    assertNoErrors(t, handle);
  });
});
