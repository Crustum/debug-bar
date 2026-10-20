import { describe, it, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { baseUrl, startServer, stopServer } from './server.mjs';
import { pages } from './fixtures.mjs';
import { assertInspectorSelected, collectedErrors, closeBrowser, launchBrowser, waitForShell } from './helpers.mjs';

describe('browser smoke', () => {
  let server;
  let handle;
  let base;

  before(async () => {
    server = await startServer();
    base = baseUrl();
    handle = await launchBrowser();
  });

  after(async () => {
    if (handle) {
      await closeBrowser(handle);
    }
    await stopServer(server);
  });

  it('injects the toolbar tag and tracks the request', async () => {
    const response = await handle.page.goto(`${base}${pages.dashboard}`);
    assert.equal(response.status(), 200);
    const requestId = response.headers()['x-debugbar-id'];
    assert.ok(requestId, 'expected an X-DEBUGBAR-ID response header');
    const html = await response.text();
    assert.match(html, /__debug_bar_script/);
  });

  it('renders the shell root with the first inspector selected', async () => {
    const dashboard = await handle.page.goto(`${base}${pages.dashboard}`);
    const requestId = dashboard.headers()['x-debugbar-id'];
    assert.ok(requestId, 'expected an X-DEBUGBAR-ID response header');

    const response = await handle.page.goto(`${base}${pages.toolbar(requestId)}`);
    assert.equal(response.status(), 200);
    await waitForShell(handle.page);
    const firstKey = await handle.page.evaluate(() =>
      document
        .querySelector('#debugbar [data-dk-inspector-nav-item]')
        ?.getAttribute('data-dk-inspector-nav-item'),
    );
    assert.ok(firstKey, 'expected at least one inspector nav entry');
    await assertInspectorSelected({ assert }, handle.page, firstKey);
    assert.deepEqual(collectedErrors(handle), []);
  });

  it('renders the fixture flow page with the host heading', async () => {
    const response = await handle.page.goto(`${base}${pages.articles}`);
    assert.equal(response.status(), 200);
    await handle.page.getByTestId('host-page').waitFor({ timeout: 10000 });
    assert.match(await handle.page.content(), /__debug_bar_script/);
    assert.deepEqual(collectedErrors(handle), []);
  });
});
