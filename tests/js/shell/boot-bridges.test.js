import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  handleCopyClick,
  handleExceptionClick,
  handleFilterClick,
  handleHostAjaxMessage,
  handleListChange,
  handleListInput,
  handleQueryClick,
  handleSortClick,
  handleTabsClick,
  initQueriesInspectors,
  queriesInspectorFor,
  swapRequestPanels,
  syncRequestIdCopy,
} from '../../../webroot/js/shell/boot/bridges.js';
import { FakeElement } from '../boot-fake-dom.js';

globalThis.Element ??= FakeElement;

function stubWindow() {
  const realWindow = globalThis.window;
  const assigned = [];
  const reloaded = [];
  globalThis.window = {
    location: {
      origin: 'http://toolbar.test',
      assign: (url) => assigned.push(url),
      reload: () => reloaded.push(true),
    },
    setTimeout: (fn) => {
      fn();

      return 1;
    },
    clearTimeout: () => {},
  };

  return {
    assigned,
    reloaded,
    restore() {
      if (realWindow === undefined) {
        delete globalThis.window;
      } else {
        globalThis.window = realWindow;
      }
    },
  };
}

function stubFetch(handler) {
  const calls = [];
  const realFetch = globalThis.fetch;
  globalThis.fetch = (url, init) => {
    calls.push({ url, init });

    return handler(url, init);
  };

  return {
    calls,
    restore() {
      if (realFetch === undefined) {
        delete globalThis.fetch;
      } else {
        globalThis.fetch = realFetch;
      }
    },
  };
}

async function flush(times = 8) {
  for (let i = 0; i < times; i += 1) {
    await new Promise((resolve) => setImmediate(resolve));
  }
}

function okPayload(payload) {
  return () =>
    Promise.resolve({ ok: true, json: () => Promise.resolve(payload) });
}

function swapCtx() {
  const root = new FakeElement('div', { 'data-dk-asset-version': '' });

  return {
    root,
    shell: {},
    summary: {},
    state: {},
    syncNavCounts() {},
    renderNavGroups() {},
    syncNavDom() {},
    syncPanels() {},
  };
}

function ajaxEvent(data, origin = 'http://toolbar.test') {
  return { origin, data };
}

function ajaxData(id, extra = {}) {
  return (
    'ajax-completed$$' + JSON.stringify({ requestId: id, url: '/x', ...extra })
  );
}

describe('bridges host ajax message', () => {
  let windowStub;

  beforeEach(() => {
    windowStub = stubWindow();
  });

  afterEach(() => {
    windowStub.restore();
  });

  it('ignores foreign origins', () => {
    let noticed = 0;
    const ctx = {
      shell: {
        noticeRequest: () => {
          noticed += 1;
        },
      },
      summary: {},
    };
    handleHostAjaxMessage(ajaxEvent(ajaxData('r1'), 'http://evil.test'), ctx);

    assert.equal(noticed, 0);
  });

  it('ignores payloads without an id', () => {
    let noticed = 0;
    const ctx = {
      shell: {
        noticeRequest: () => {
          noticed += 1;
        },
      },
      summary: {},
    };
    handleHostAjaxMessage(ajaxEvent('hello'), ctx);
    handleHostAjaxMessage(ajaxEvent('ajax-completed$${bad'), ctx);

    assert.equal(noticed, 0);
  });

  it('notifies synchronously and refreshes pickers', () => {
    const root = new FakeElement('div');
    const option = new FakeElement('button', {
      'data-dk-request-option': 'r1',
    });
    root.appendChild(option);
    const ctx = {
      shell: {
        noticeRequest: () => ({ id: 'r1' }),
        currentRequestId: null,
        recentRequests: [],
      },
      summary: {},
      root,
    };
    handleHostAjaxMessage(ajaxEvent(ajaxData('r1')), ctx);

    assert.equal(option.getAttribute('aria-selected'), 'false');
  });

  it('supports promise notices', async () => {
    const ctx = {
      shell: {
        noticeRequest: () => Promise.resolve({ id: 'r1' }),
        currentRequestId: null,
        recentRequests: [],
      },
      summary: {},
      root: new FakeElement('div'),
    };
    handleHostAjaxMessage(ajaxEvent(ajaxData('r1')), ctx);
    await flush(2);

    assert.ok(true);
  });

  it('tolerates notice failures', async () => {
    const ctx = {
      shell: {
        noticeRequest: () => Promise.reject(new Error('gone')),
      },
      summary: {},
      root: new FakeElement('div'),
    };
    handleHostAjaxMessage(ajaxEvent(ajaxData('r1')), ctx);
    await flush(2);

    assert.ok(true);
  });

  it('skips ignored urls', () => {
    let noticed = 0;
    const ctx = {
      shell: {
        noticeRequest: () => {
          noticed += 1;
          return null;
        },
      },
      summary: { ignored_url_masks: ['/x'] },
      root: new FakeElement('div'),
    };
    handleHostAjaxMessage(ajaxEvent(ajaxData('r1')), ctx);

    assert.equal(noticed, 0);
  });
});

function queriesContainer() {
  const container = new FakeElement('div', { 'data-dk-queries': '' });
  const item = new FakeElement('button', {
    'data-dk-query-item': 'q1',
    'data-dk-query-key': 'query-1',
    'aria-pressed': 'false',
  });
  container.appendChild(item);

  return { container, item };
}

describe('bridges queries inspectors', () => {
  it('returns null without a queries container', () => {
    assert.equal(queriesInspectorFor({}, new FakeElement('button')), null);
    assert.equal(queriesInspectorFor({}, null), null);
  });

  it('boots inspectors and selects the first record', () => {
    const { container, item } = queriesContainer();
    const root = new FakeElement('div');
    root.appendChild(container);
    const ctx = { root, shell: {}, summary: {} };
    initQueriesInspectors(ctx);

    assert.equal(item.getAttribute('aria-pressed'), 'true');
    assert.equal(queriesInspectorFor(ctx, item) !== null, true);
  });

  it('skips already booted containers', () => {
    const { container } = queriesContainer();
    const root = new FakeElement('div');
    root.appendChild(container);
    const ctx = { root, shell: {}, summary: {} };
    initQueriesInspectors(ctx);
    const first = queriesInspectorFor(ctx, container);
    initQueriesInspectors(ctx);
    const second = queriesInspectorFor(ctx, container);

    assert.equal(first, second);
  });

  it('drops disconnected containers and boots fresh ones', () => {
    const { container } = queriesContainer();
    const root = new FakeElement('div');
    root.appendChild(container);
    const ctx = { root, shell: {}, summary: {} };
    initQueriesInspectors(ctx);
    container.isConnected = false;
    const fresh = new FakeElement('div', { 'data-dk-queries': '' });
    root.appendChild(fresh);
    initQueriesInspectors(ctx);

    assert.equal(queriesInspectorFor(ctx, fresh) !== null, true);
  });
});

describe('bridges request swap', () => {
  let fetchStub;
  let windowStub;

  beforeEach(() => {
    windowStub = stubWindow();
    fetchStub = null;
  });

  afterEach(() => {
    fetchStub?.restore();
    windowStub.restore();
  });

  function swappedPayload() {
    return {
      request: {
        id: 'r2',
        method: 'POST',
        url: '/articles',
        status_code: 200,
        duration_label: '12ms',
        peak_memory_label: '8MB',
        query_count: 3,
      },
      asset_version: '',
      panels: [{ key: 'request', html: '<p>swapped</p>', title: 'Request' }],
    };
  }

  it('swaps panel html and header facts', async () => {
    const ctx = swapCtx();
    const section = new FakeElement('section', {
      'data-dk-inspector-panel': 'request',
    });
    ctx.root.appendChild(section);
    const status = new FakeElement('span', {
      'data-dk-header-status': '',
    });
    ctx.root.appendChild(status);
    const memory = new FakeElement('span', {
      'data-dk-header-memory': '',
    });
    ctx.root.appendChild(memory);
    const queries = new FakeElement('span', {
      'data-dk-header-query-count': '',
    });
    ctx.root.appendChild(queries);
    const dot = new FakeElement('span', { 'data-dk-warning-dot': '' });
    ctx.root.appendChild(dot);
    const copy = new FakeElement('button', {
      'data-dk-copy-request-id': '',
    });
    ctx.root.appendChild(copy);
    fetchStub = stubFetch(okPayload(swappedPayload()));
    swapRequestPanels(ctx, 'r2');
    await flush(4);

    assert.equal(section.innerHTML, '<p>swapped</p>');
    assert.equal(section.getAttribute('aria-label'), 'Request');
    assert.equal(ctx.root.getAttribute('data-dk-request-id'), 'r2');
    assert.equal(status.textContent, '12ms');
    assert.equal(memory.textContent, '8MB');
    assert.equal(dot.getAttribute('data-dk-status-tone'), 'good');
    assert.equal(copy.getAttribute('data-dk-copy-value'), 'r2');
    assert.equal(ctx.summary.id, 'r2');
  });

  it('falls back to the default panels url for empty ids', async () => {
    const ctx = swapCtx();
    fetchStub = stubFetch(okPayload(swappedPayload()));
    swapRequestPanels(ctx, '');
    await flush(4);

    assert.equal(fetchStub.calls[0].url, '/debug-bar/requests/panels/.json');
  });

  it('reloads on asset version drift', async () => {
    const ctx = swapCtx();
    ctx.root.setAttribute('data-dk-asset-version', 'old');
    const payload = swappedPayload();
    payload.asset_version = 'new';
    fetchStub = stubFetch(okPayload(payload));
    swapRequestPanels(ctx, 'r2');
    await flush(4);

    assert.equal(windowStub.reloaded.length, 1);
    assert.equal(ctx.root.getAttribute('data-dk-asset-version'), 'new');
  });

  it('falls back to the toolbar on empty swaps', async () => {
    const ctx = swapCtx();
    const payload = swappedPayload();
    payload.panels = [{ key: 'missing', html: '<p>x</p>' }];
    fetchStub = stubFetch(okPayload(payload));
    swapRequestPanels(ctx, 'r2');
    await flush(4);

    assert.equal(windowStub.assigned.length, 1);
    assert.match(windowStub.assigned[0], /toolbar\/r2/);
  });

  it('falls back to the toolbar on invalid payloads', async () => {
    const ctx = swapCtx();
    fetchStub = stubFetch(okPayload({ nope: true }));
    swapRequestPanels(ctx, 'r2');
    await flush(4);

    assert.equal(windowStub.assigned.length, 1);
  });

  it('falls back to the toolbar on failed responses', async () => {
    const ctx = swapCtx();
    fetchStub = stubFetch(() => Promise.resolve({ ok: false }));
    swapRequestPanels(ctx, 'r2');
    await flush(4);

    assert.equal(windowStub.assigned.length, 1);
  });

  it('falls back to the toolbar on request errors', async () => {
    const ctx = swapCtx();
    fetchStub = stubFetch(() => Promise.reject(new Error('offline')));
    swapRequestPanels(ctx, 'r2');
    await flush(4);

    assert.equal(windowStub.assigned.length, 1);
  });
});
