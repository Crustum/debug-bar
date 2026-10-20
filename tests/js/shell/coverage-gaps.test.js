import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { flashButtonIcon } from '../../../webroot/js/shell/copy.js';
import { createShell } from '../../../webroot/js/state.js';
import { createToolbarPart } from '../../../webroot/js/shell/toolbar.js';
import { createRequestsPart } from '../../../webroot/js/shell/requests.js';
import {
  announceDiscoveredProfile,
  installRequestDiscovery,
} from '../../../webroot/js/shell/discovery.js';
import { createInspectorPart } from '../../../webroot/js/shell/inspector.js';
import { createActivityPart } from '../../../webroot/js/shell/activity-refresh.js';
import { createPreferencesPart } from '../../../webroot/js/shell/preferences.js';
import { createPalettePart } from '../../../webroot/js/shell/palette.js';
import {
  handleHostAjaxMessage,
  queriesInspectorFor,
  swapRequestPanels,
} from '../../../webroot/js/shell/boot/bridges.js';
import {
  closeAllPickers,
  refreshRequestOptions,
} from '../../../webroot/js/shell/boot/picker.js';
import { createQueriesInspector } from '../../../webroot/js/inspectors/queries.js';
import { selectPanelRecord } from '../../../webroot/js/shell/boot/panels.js';
import { FakeElement } from '../boot-fake-dom.js';

globalThis.Element ??= FakeElement;

function windowWith(location) {
  const realWindow = globalThis.window;
  globalThis.window = {
    location,
    addEventListener: () => {},
  };

  return function restore() {
    if (realWindow === undefined) {
      delete globalThis.window;
    } else {
      globalThis.window = realWindow;
    }
  };
}

describe('coverage gaps: copy and toolbar', () => {
  it('skips the flash timer without a scheduler', () => {
    const realWindow = globalThis.window;
    globalThis.window = {};
    const button = new FakeElement('button');
    const icon = new FakeElement('svg');
    flashButtonIcon(button, icon, 'dk-flash');
    if (realWindow === undefined) {
      delete globalThis.window;
    } else {
      globalThis.window = realWindow;
    }

    assert.equal(icon.classList.contains('dk-flash'), true);
  });

  it('resets the suppress flag through the scheduler', () => {
    const scheduled = [];
    const part = createToolbarPart({
      browser: {
        schedule: (callback) => {
          scheduled.push(callback);

          return 1;
        },
        cancelSchedule: () => {},
      },
    });
    part.suppressToolbarClick();

    assert.equal(part.toolbarSuppressClick, true);
    scheduled.forEach((callback) => callback());

    assert.equal(part.toolbarSuppressClick, false);
  });

  it('creates shells with a request limit', () => {
    const shell = createShell(
      { id: 'r1' },
      { requestLimit: 5, fetchClient: async () => null },
    );

    assert.ok(shell);
  });
});

describe('coverage gaps: requests part', () => {
  it('tolerates throwing recent lists in worker lookup', () => {
    const part = createRequestsPart({
      browser: {},
      fetchClient: async () => null,
    });
    Object.defineProperty(part, 'recentRequests', {
      get() {
        throw new Error('gone');
      },
    });

    assert.equal(part.workerInspector('r1'), null);
  });

  it('names queue rows by method', () => {
    const part = createRequestsPart({
      browser: {},
      fetchClient: async () => null,
      recentRequests: [{ id: 'w1', method: 'JOB' }],
    });

    assert.equal(part.workerInspector('w1'), 'queue');
    assert.equal(part.workerInspector('missing'), null);
    assert.equal(part.workerInspector(''), null);
  });
});

describe('coverage gaps: discovery', () => {
  const LOCATION = {
    href: 'https://app.test/page',
    origin: 'https://app.test',
  };

  function jsonResponse(url, profileId) {
    return {
      url,
      headers: {
        get: (name) => (name === 'X-DEBUGBAR-ID' ? profileId : null),
      },
    };
  }

  it('ignores shells that throw while resolving hooks', async () => {
    const runtime = {
      location: { ...LOCATION },
      fetch: async () => jsonResponse('/api/data', 'req-9'),
    };
    const seen = [];
    installRequestDiscovery(runtime, () => {
      throw new Error('gone');
    });
    await runtime.fetch('/api/data');

    assert.deepEqual(seen, []);
  });

  it('announces discovered profiles through hooks', async () => {
    const runtime = {
      location: { ...LOCATION },
      fetch: async () => jsonResponse('/api/data', 'req-9'),
    };
    const seen = [];
    installRequestDiscovery(runtime, {
      noticeRequest: (id) => seen.push(id),
    });
    await runtime.fetch('/api/data');
    await new Promise((resolve) => setImmediate(resolve));

    assert.deepEqual(seen, ['req-9']);
  });

  it('ignores responses with unparseable urls', async () => {
    const runtime = {
      location: { ...LOCATION },
      fetch: async () => jsonResponse('http://[invalid', 'req-9'),
    };
    const seen = [];
    installRequestDiscovery(runtime, {
      noticeRequest: (id) => seen.push(id),
    });
    await runtime.fetch('http://[invalid');
    await new Promise((resolve) => setImmediate(resolve));

    assert.deepEqual(seen, []);
  });

  it('notifies directly without a scheduler', async () => {
    const runtime = {
      location: { ...LOCATION },
      fetch: async () => jsonResponse('/api/data', 'req-9'),
    };
    delete runtime.setTimeout;
    const seen = [];
    installRequestDiscovery(runtime, {
      noticeRequest: (id) => seen.push(id),
    });
    await runtime.fetch('/api/data');

    assert.deepEqual(seen, ['req-9']);
  });

  it('drops invalid announcements', () => {
    assert.equal(announceDiscoveredProfile(null, 'req-1'), null);
    assert.equal(
      announceDiscoveredProfile(
        {
          noticeRequest: () => {
            throw new Error('gone');
          },
        },
        'req-1',
      ),
      null,
    );
  });
});

describe('coverage gaps: inspector focus trap', () => {
  it('leaves focus alone when no wrap is needed', () => {
    const part = createInspectorPart({ browser: {} });
    const container = new FakeElement('div');
    const first = new FakeElement('button');
    const second = new FakeElement('button');
    container.appendChild(first);
    container.appendChild(second);
    const event = {
      key: 'Tab',
      shiftKey: false,
      preventDefault: () => {},
    };
    Object.defineProperty(part, 'browser', { value: {} });
    const shell = { ...part, browser: { activeElement: () => first } };

    assert.equal(shell.keepFocusWithin(event, container), false);
  });

  it('wraps focus from the first element', () => {
    const part = createInspectorPart({ browser: {} });
    const container = new FakeElement('div');
    const first = new FakeElement('button');
    const second = new FakeElement('button');
    let focused = null;
    second.focus = () => {
      focused = 'second';
    };
    container.appendChild(first);
    container.appendChild(second);
    container.querySelectorAll = () => [first, second];
    const event = {
      key: 'Tab',
      shiftKey: true,
      preventDefault: () => {},
    };
    const shell = { ...part, browser: { activeElement: () => first } };

    assert.equal(shell.keepFocusWithin(event, container), true);
    assert.equal(focused, 'second');
  });

  it('rejects unusable containers', () => {
    const part = createInspectorPart({ browser: {} });
    const shell = { ...part, browser: { activeElement: () => null } };

    assert.equal(
      shell.keepFocusWithin({ key: 'Tab' }, new FakeElement('div')),
      false,
    );
    assert.equal(shell.keepFocusWithin(null, new FakeElement('div')), false);
  });
});

describe('coverage gaps: activity part', () => {
  function activityShell(
    summary = {},
    browserOverrides = {},
    fetchPayload = null,
  ) {
    const timers = new Map();
    let nextId = 1;
    const browser = {
      schedule: (callback, delay) => {
        const id = nextId++;
        timers.set(id, { callback, delay });

        return id;
      },
      cancelSchedule: (id) => {
        timers.delete(id);
      },
      timers,
      activeElement: () => null,
      afterPaint: (callback) => callback(),
      ...browserOverrides,
    };

    return createShell(summary, {
      runtime: browser,
      fetchClient: async () => ({
        json: async () => fetchPayload ?? { requests: [] },
      }),
    });
  }

  it('fires scheduled refresh callbacks', () => {
    const shell = activityShell({ id: 'a', background_pending: true });
    shell.inspectorOpen = true;
    shell.scheduleActivityRefresh();
    const timers = [...shell.browser.timers.values()];

    assert.equal(timers.length, 1);
    timers[0].callback();

    assert.equal(shell.browser.timers.size, 1);
  });

  it('receives related profiles without a remember hook', async () => {
    const shell = activityShell({ id: 'a' }, {}, { requests: [] });
    delete shell.rememberRequest;
    const seen = [];
    shell.receiveRequest = (profile) => seen.push(profile.id);
    shell.receiveActivityRefresh({ id: 'a', background_pending: true }, [
      { id: 'b', background_pending: true },
    ]);

    assert.deepEqual(seen, ['b']);
  });

  it('tolerates throwing reload inspectors', async () => {
    const shell = activityShell({ id: 'a' });
    shell.inspectorOpen = true;
    shell.selected = 'timeline';
    shell.loadedInspector = 'timeline';
    shell.inspectorLoading = false;
    shell.requestInspector = () => {
      throw new Error('gone');
    };
    shell.summary = { id: 'a' };
    shell.fetchClient = async () => ({
      json: async () => ({ requests: [{ id: 'b' }] }),
    });
    shell.rememberRequest = () => {};
    await shell.refreshBackgroundActivity(true);

    assert.equal(shell.activityRefreshError, null);
  });
});

describe('coverage gaps: preferences and palette parts', () => {
  it('toggles the theme menu shut', () => {
    const shell = createShell(
      { id: 'a' },
      { runtime: {}, fetchClient: async () => null },
    );
    assert.equal(shell.toggleThemeMenu('toolbar'), 'toolbar');
    assert.equal(shell.toggleThemeMenu('toolbar'), null);
  });

  it('paints theme menus without a painter', () => {
    const shell = createShell(
      { id: 'a' },
      { runtime: {}, fetchClient: async () => null },
    );
    assert.equal(shell.openThemeMenu('toolbar'), 'toolbar');
    shell.closeThemeMenu(false);
  });

  it('paints palettes without a painter', () => {
    const shell = createShell(
      { id: 'a' },
      { runtime: {}, fetchClient: async () => null },
    );
    assert.equal(shell.openPalette(null), true);
    shell.closePalette(false);
  });
});

describe('coverage gaps: bridges host ajax', () => {
  let restoreWindow;

  beforeEach(() => {
    restoreWindow = windowWith({
      origin: 'http://toolbar.test',
      assign: () => {},
      reload: () => {},
    });
  });

  afterEach(() => {
    restoreWindow();
  });

  function ajaxCtx(overrides = {}) {
    return {
      shell: {},
      summary: {},
      root: new FakeElement('div'),
      ...overrides,
    };
  }

  function ajaxData(url) {
    return 'ajax-completed$$' + JSON.stringify({ requestId: 'r1', url });
  }

  it('returns early without a window', () => {
    restoreWindow();
    delete globalThis.window;
    let noticed = 0;
    const ctx = ajaxCtx({
      shell: {
        noticeRequest: () => {
          noticed += 1;
          return null;
        },
      },
    });

    assert.doesNotThrow(() =>
      handleHostAjaxMessage(
        { origin: 'http://toolbar.test', data: ajaxData('/x') },
        ctx,
      ),
    );
    assert.equal(noticed, 0);
    restoreWindow = windowWith({
      origin: 'http://toolbar.test',
      assign: () => {},
      reload: () => {},
    });
  });

  it('returns early on throwing payloads', () => {
    const ctx = ajaxCtx();
    const event = {
      origin: 'http://toolbar.test',
    };
    Object.defineProperty(event, 'data', {
      get() {
        throw new Error('gone');
      },
    });

    assert.doesNotThrow(() => handleHostAjaxMessage(event, ctx));
  });

  it('ignores invalid origins and bad payloads', () => {
    const ctx = ajaxCtx();
    handleHostAjaxMessage(null, ctx);
    handleHostAjaxMessage(
      { origin: 'http://evil.test', data: ajaxData('/x') },
      ctx,
    );
    handleHostAjaxMessage(
      { origin: 'http://toolbar.test', data: 'garbage' },
      ctx,
    );
  });

  it('matches absolute and query urls against masks', () => {
    const noticed = [];
    const ctx = ajaxCtx({
      shell: {
        noticeRequest: (id) => {
          noticed.push(id);
          return null;
        },
      },
      summary: { ignored_url_masks: ['/skip/*'] },
    });
    handleHostAjaxMessage(
      {
        origin: 'http://toolbar.test',
        data: ajaxData('https://toolbar.test/skip/this?fast=1'),
      },
      ctx,
    );

    assert.deepEqual(noticed, []);
  });

  it('handles host-only absolute urls', () => {
    const noticed = [];
    const ctx = ajaxCtx({
      shell: {
        noticeRequest: (id) => {
          noticed.push(id);
          return null;
        },
      },
      summary: { ignored_url_masks: ['/exact'] },
    });
    handleHostAjaxMessage(
      { origin: 'http://toolbar.test', data: ajaxData('https://toolbar.test') },
      ctx,
    );

    assert.deepEqual(noticed, ['r1']);
  });

  it('matches wildcard, class, and escaped masks', () => {
    const seen = [];
    const run = (masks, url) => {
      const ctx = ajaxCtx({
        shell: {
          noticeRequest: () => {
            seen.push(url);
            return null;
          },
        },
        summary: { ignored_url_masks: masks },
      });
      handleHostAjaxMessage(
        { origin: 'http://toolbar.test', data: ajaxData(url) },
        ctx,
      );
    };
    run(['', '/other'], '/x?y=1');
    run(['/x*', '/nomatch'], '/xyz');
    run(['/pg?', '/nomatch'], '/pg1');
    run(['/pg[0-9]', '/nomatch'], '/pg7');
    run(['/pg[0-9', '/nomatch'], '/pg[0-9');
    run(['/x.html', '/nomatch'], '/x.html');
    run(['/feed/*', '/nomatch'], '/feed');

    assert.deepEqual(seen, ['/x?y=1']);
  });

  it('passes through urls that match nothing', () => {
    const noticed = [];
    const ctx = ajaxCtx({
      shell: {
        noticeRequest: (id) => {
          noticed.push(id);
          return null;
        },
      },
      summary: { ignored_url_masks: ['/other', '/x*'] },
    });
    handleHostAjaxMessage(
      { origin: 'http://toolbar.test', data: ajaxData('/unrelated') },
      ctx,
    );

    assert.deepEqual(noticed, ['r1']);
  });

  it('notifies null results without refreshing', () => {
    const ctx = ajaxCtx({
      shell: { noticeRequest: () => null },
    });
    handleHostAjaxMessage(
      { origin: 'http://toolbar.test', data: ajaxData('/x') },
      ctx,
    );
  });

  it('creates fresh query inspectors on demand', () => {
    const container = new FakeElement('div', { 'data-dk-queries': '' });
    const item = new FakeElement('button', {
      'data-dk-query-item': 'q1',
      'data-dk-query-key': 'query-1',
      'aria-pressed': 'false',
    });
    container.appendChild(item);
    const root = new FakeElement('div');
    root.appendChild(container);
    const ctx = { root, shell: {}, summary: {} };
    const inspector = queriesInspectorFor(ctx, item);

    assert.ok(inspector);
    assert.equal(item.getAttribute('aria-pressed'), 'false');
  });

  it('ignores unencodable request ids', async () => {
    const root = new FakeElement('div', { 'data-dk-asset-version': '' });
    const realFetch = globalThis.fetch;
    let fetched = 0;
    globalThis.fetch = async () => {
      fetched += 1;

      return { ok: true, json: async () => ({}) };
    };
    const ctx = {
      root,
      shell: {},
      summary: {},
      state: {},
      syncNavCounts() {},
      renderNavGroups() {},
      syncNavDom() {},
      syncPanels() {},
    };
    try {
      swapRequestPanels(ctx, '\uD800');
      await new Promise((resolve) => setImmediate(resolve));
    } finally {
      if (realFetch === undefined) {
        delete globalThis.fetch;
      } else {
        globalThis.fetch = realFetch;
      }
    }

    assert.equal(fetched, 0);
  });
});
