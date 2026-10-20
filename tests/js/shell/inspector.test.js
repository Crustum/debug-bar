import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { createShell } from '../../../webroot/js/state.js';
import {
  createInspectorPart,
  inspectorPanelUrl,
  panelKeyOf,
  resolveVisibleInspector,
  INSPECTOR_SKELETON_DELAY,
} from '../../../webroot/js/shell/inspector.js';
import { DEFAULT_INSPECTOR } from '../../../webroot/js/shell/navigation.js';

function fakeBrowser(overrides = {}) {
  const scheduled = [];
  let nextId = 0;
  return {
    scheduled,
    schedule: (callback, delay) => {
      nextId += 1;
      scheduled.push({ id: nextId, callback, delay });

      return nextId;
    },
    cancelSchedule: (timer) => {
      const index = scheduled.findIndex((entry) => entry.id === timer);
      if (index !== -1) scheduled.splice(index, 1);
    },
    afterPaint: (callback) => callback(),
    activeElement: () => null,
    ...overrides,
  };
}

function jsonResponse(payload) {
  return { json: async () => payload };
}

function fakePanel(key) {
  return { dataset: { dkInspectorPanel: key }, hidden: false };
}

describe('inspector helpers', () => {
  it('builds the M1 panel JSON url', () => {
    assert.equal(
      inspectorPanelUrl('abc 123'),
      '/debug-bar/panels/view/abc%20123.json',
    );
  });

  it('resolves single-panel visibility', () => {
    assert.equal(
      resolveVisibleInspector({
        selected: 'queries',
        loaded: 'request',
        loading: false,
      }),
      'queries',
    );
    assert.equal(
      resolveVisibleInspector({
        selected: 'queries',
        loaded: 'request',
        loading: true,
      }),
      'request',
    );
    assert.equal(
      resolveVisibleInspector({ selected: null, loaded: 'cache' }),
      'cache',
    );
  });

  it('reads panel keys from dataset or attribute fakes', () => {
    assert.equal(panelKeyOf(fakePanel('queries')), 'queries');
    assert.equal(panelKeyOf({ getAttribute: () => 'cache' }), 'cache');
    assert.equal(panelKeyOf({ dataset: {}, getAttribute: () => '' }), null);
    assert.equal(panelKeyOf(null), null);
  });

  it('exposes the 200ms skeleton delay', () => {
    assert.equal(INSPECTOR_SKELETON_DELAY, 200);
  });

  it('defaults to the request inspector', () => {
    assert.equal(DEFAULT_INSPECTOR, 'request');
  });
});

describe('inspector open/close', () => {
  it('opens with an explicit key and stores the return focus', () => {
    const focused = [];
    const part = createInspectorPart({ browser: fakeBrowser() });
    const returnFocus = { focus: () => focused.push('back') };
    const heading = { focus: () => focused.push('heading') };
    const dialog = { querySelector: () => heading };

    assert.equal(part.openInspector('queries', returnFocus, dialog), 'queries');
    assert.equal(part.inspectorOpen, true);
    assert.equal(part.selected, 'queries');
    assert.equal(part.inspectorReturnFocus, returnFocus);
    assert.deepEqual(focused, ['heading']);
  });

  it('falls back to the active element and accepts keys without a registry', () => {
    const active = { id: 'toolbar-button' };
    const part = createInspectorPart({
      browser: fakeBrowser({ activeElement: () => active }),
    });

    assert.equal(part.openInspector('nope'), 'nope');
    assert.equal(part.inspectorReturnFocus, active);
    assert.equal(part.openInspector(''), 'request');
  });

  it('closes and restores focus, ignoring a closed dialog', () => {
    const focused = [];
    const part = createInspectorPart({ browser: fakeBrowser() });
    assert.equal(part.closeInspector(), false);

    part.openInspector('request', { focus: () => focused.push('back') });
    assert.equal(part.closeInspector(), true);
    assert.equal(part.inspectorOpen, false);
    assert.deepEqual(focused, ['back']);
    assert.equal(part.closeInspector(), false);
  });
});

describe('inspector fetch', () => {
  it('loads panel JSON through the injected client', async () => {
    const calls = [];
    const part = createInspectorPart({
      browser: fakeBrowser(),
      fetchClient: async (url) => {
        calls.push(url);

        return jsonResponse({ title: 'Queries' });
      },
    });
    const loaded = [];
    part.markInspectorLoaded = (key) => loaded.push(key);

    const result = await part.requestInspector('queries', {
      panelId: 'panel-1',
    });
    assert.deepEqual(calls, ['/debug-bar/panels/view/panel-1.json']);
    assert.deepEqual(result, { key: 'queries', data: { title: 'Queries' } });
    assert.equal(part.loadedInspector, 'queries');
    assert.equal(part.inspectorLoading, false);
    assert.equal(part.inspectorError, false);
    assert.deepEqual(loaded, ['queries']);
  });

  it('resolves local-only when no round-trip is available', async () => {
    const part = createInspectorPart({ browser: fakeBrowser() });

    const result = await part.requestInspector('cache');
    assert.deepEqual(result, { key: 'cache', data: null });
    assert.equal(part.loadedInspector, 'cache');
  });

  it('dedupes cached and in-flight requests', async () => {
    let calls = 0;
    const part = createInspectorPart({
      browser: fakeBrowser(),
      fetchClient: async () => {
        calls += 1;

        return jsonResponse({});
      },
    });

    await part.requestInspector('queries', { url: '/q.json' });
    assert.deepEqual(
      await part.requestInspector('queries', { url: '/q.json' }),
      {
        key: 'queries',
        cached: true,
      },
    );
    assert.equal(calls, 1);

    const slow = [];
    const pendingPart = createInspectorPart({
      browser: fakeBrowser(),
      fetchClient: (url) =>
        new Promise((resolve) => {
          slow.push(() => resolve(jsonResponse({ url })));
        }),
    });
    const first = pendingPart.requestInspector('queries', { url: '/q.json' });
    assert.equal(
      await pendingPart.requestInspector('queries', { url: '/q.json' }),
      null,
    );
    slow[0]();
    assert.deepEqual(await first, { key: 'queries', data: { url: '/q.json' } });
  });

  it('drops stale responses via the version counter', async () => {
    const resolvers = [];
    const part = createInspectorPart({
      browser: fakeBrowser(),
      fetchClient: (url) =>
        new Promise((resolve) => {
          resolvers.push(() => resolve(jsonResponse({ url })));
        }),
    });

    const first = part.requestInspector('queries', { url: '/q.json' });
    const second = part.requestInspector('cache', { url: '/c.json' });
    assert.equal(part.inspectorRequestVersion, 2);
    resolvers[0]();
    resolvers[1]();
    assert.equal(await first, null);
    assert.deepEqual(await second, { key: 'cache', data: { url: '/c.json' } });
    assert.equal(part.loadedInspector, 'cache');
  });

  it('marks errors and retries with force', async () => {
    let calls = 0;
    const part = createInspectorPart({
      browser: fakeBrowser(),
      fetchClient: async () => {
        calls += 1;
        if (calls === 1) throw new Error('offline');

        return jsonResponse({ ok: true });
      },
    });

    assert.equal(
      await part.requestInspector('queries', { url: '/q.json' }),
      null,
    );
    assert.equal(part.inspectorError, true);
    assert.equal(part.inspectorLoading, false);

    const retried = await part.retryInspector();
    assert.deepEqual(retried, { key: 'queries', data: { ok: true } });
    assert.equal(calls, 2);
    assert.equal(part.inspectorError, false);
  });

  it('shows the skeleton after 200ms and skips it when stale', async () => {
    const browser = fakeBrowser();
    const part = createInspectorPart({
      browser,
      fetchClient: async () => jsonResponse({}),
    });

    const pending = part.requestInspector('queries', { url: '/q.json' });
    assert.equal(browser.scheduled.length, 1);
    assert.equal(browser.scheduled[0].delay, 200);
    browser.scheduled[0].callback();
    assert.equal(part.inspectorLoadingIndicator, true);
    await pending;
    assert.equal(part.inspectorLoadingIndicator, false);

    const staleBrowser = fakeBrowser();
    const stalePart = createInspectorPart({
      browser: staleBrowser,
      fetchClient: async () => jsonResponse({}),
    });
    const stale = stalePart.requestInspector('cache', { url: '/c.json' });
    stalePart.inspectorRequestVersion += 1;
    staleBrowser.scheduled[0].callback();
    assert.equal(stalePart.inspectorLoadingIndicator, false);
    assert.equal(await stale, null);
  });
});

describe('inspector panels and focus trap', () => {
  it('keeps a single panel visible', () => {
    const part = createInspectorPart({ browser: fakeBrowser() });
    part.selected = 'queries';
    const panels = [fakePanel('request'), fakePanel('queries')];

    assert.equal(part.syncInspectorPanels(panels), 'queries');
    assert.deepEqual(
      panels.map((panel) => panel.hidden),
      [true, false],
    );

    part.inspectorLoading = true;
    part.loadedInspector = 'request';
    assert.equal(part.syncInspectorPanels(panels), 'request');
    assert.deepEqual(
      panels.map((panel) => panel.hidden),
      [false, true],
    );
  });

  it('traps Tab inside the dialog', () => {
    const makeElement = () => ({
      hidden: false,
      getClientRects: () => [{}],
      focused: 0,
      focus() {
        this.focused += 1;
      },
    });
    const first = makeElement();
    const last = makeElement();
    let active = last;
    const part = createInspectorPart({
      browser: fakeBrowser({ activeElement: () => active }),
    });
    const container = {
      querySelectorAll: () => [first, last],
      contains: (element) => element === first || element === last,
    };

    let prevented = 0;
    assert.equal(
      part.keepFocusWithin(
        {
          key: 'Tab',
          preventDefault: () => {
            prevented += 1;
          },
        },
        container,
      ),
      true,
    );
    assert.equal(first.focused, 1);
    assert.equal(prevented, 1);

    active = first;
    assert.equal(
      part.keepFocusWithin(
        {
          key: 'Tab',
          shiftKey: true,
          preventDefault: () => {
            prevented += 1;
          },
        },
        container,
      ),
      true,
    );
    assert.equal(last.focused, 1);

    assert.equal(part.keepFocusWithin({ key: 'Enter' }, container), false);
    assert.equal(part.keepFocusWithin({ key: 'Tab' }, null), false);
    assert.equal(
      part.keepFocusWithin({ key: 'Tab' }, { querySelectorAll: () => [] }),
      false,
    );
  });
});

describe('inspector summary mapping and guards', () => {
  it('resolves panel urls from the summary registry', async () => {
    const calls = [];
    const part = createInspectorPart({
      browser: fakeBrowser(),
      fetchClient: async (url) => {
        calls.push(url);

        return jsonResponse({});
      },
      summary: {
        panels: [
          { key: 'queries', id: 'panel-1' },
          { id: 'panel-2' },
          { title: 'Cache', id: 'panel-3' },
        ],
      },
    });

    await part.requestInspector('queries');
    assert.deepEqual(calls, ['/debug-bar/panels/view/panel-1.json']);
    await part.requestInspector('panel-2', { force: true });
    assert.equal(calls.at(-1), '/debug-bar/panels/view/panel-2.json');
    await part.requestInspector('cache', { force: true });
    assert.equal(calls.at(-1), '/debug-bar/panels/view/panel-3.json');
  });

  it('drops fetch responses for stale profiles', async () => {
    let release = null;
    const part = createInspectorPart({
      browser: fakeBrowser(),
      fetchClient: () =>
        new Promise((resolve) => {
          release = () => resolve(jsonResponse({}));
        }),
    });
    part.currentProfileId = 'a';
    const pending = part.requestInspector('queries', { url: '/q.json' });
    part.currentProfileId = 'b';
    release();
    assert.equal(await pending, null);
  });

  it('guards local receives by version and profile', () => {
    const part = createInspectorPart({ browser: fakeBrowser() });
    assert.equal(part.receiveInspectorLocal('x', 999), null);

    part.currentProfileId = 'b';
    assert.equal(
      part.receiveInspectorLocal('x', part.inspectorRequestVersion, 'a'),
      null,
    );
    assert.deepEqual(
      part.receiveInspectorLocal('x', part.inspectorRequestVersion, 'b'),
      {
        key: 'x',
        data: null,
      },
    );
  });

  it('works without paint scheduling and bare events', () => {
    const part = createInspectorPart({
      browser: { activeElement: () => null },
    });
    assert.equal(part.openInspector('request'), 'request');
    assert.equal(part.closeInspector(), true);

    const first = {
      hidden: false,
      getClientRects: () => [{}],
      focused: 0,
      focus() {
        this.focused += 1;
      },
    };
    const container = { querySelectorAll: () => [first] };
    assert.equal(part.keepFocusWithin({ key: 'Tab' }, container), true);
    assert.equal(first.focused, 1);
  });
});

describe('inspector shell composition', () => {
  it('registers the inspector part in createShell', () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    assert.equal(shell.inspectorOpen, false);
    assert.equal(shell.inspectorRequestVersion, 0);
    assert.equal(typeof shell.openInspector, 'function');
    assert.equal(typeof shell.requestInspector, 'function');
    assert.equal(typeof shell.keepFocusWithin, 'function');
  });
});
