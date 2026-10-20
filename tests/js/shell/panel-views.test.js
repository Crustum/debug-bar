import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  handleViewsClick,
  initViewsPanels,
} from '../../../webroot/js/shell/panels/views.js';
import { FakeElement } from '../boot-fake-dom.js';

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

function jsonPayload(payload) {
  return () =>
    Promise.resolve({ ok: true, json: () => Promise.resolve(payload) });
}

function stateOf(section, name) {
  const node = section.querySelector(`[data-dk-views-${name}]`);

  return node ? !node.hasAttribute('hidden') : null;
}

function detailSection(panel, id, { visible = true, render = '3' } = {}) {
  const section = new FakeElement('section', {
    'data-dk-view-detail': id,
  });
  if (!visible) {
    section.setAttribute('hidden', '');
  }
  for (const state of ['loading', 'data', 'empty', 'error']) {
    const node = new FakeElement('div', {
      [`data-dk-views-${state}`]: '',
    });
    node.setAttribute('hidden', '');
    section.appendChild(node);
  }
  const holder = new FakeElement('div', {
    'data-dk-view-data-panel': '',
    'data-dk-views-url': '/debug-bar/panels/view-data/p1',
    'data-dk-views-render': render,
  });
  section.appendChild(holder);
  panel.appendChild(section);

  return { section, holder };
}

function viewsPanel() {
  return new FakeElement('div', {
    'data-dk-views-panel': '',
    'data-dk-views-url': '/debug-bar/panels/view-data/p1',
  });
}

function viewEntry(panel, id) {
  const entry = new FakeElement('button', {
    'data-dk-view-entry': '',
    'data-dk-view-key': id,
    class: 'dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60',
  });
  panel.appendChild(entry);

  return entry;
}

describe('views lazy inspector', () => {
  let fetchStub;

  beforeEach(() => {
    fetchStub = null;
  });

  afterEach(() => {
    fetchStub?.restore();
  });

  it('loads the visible render on boot', async () => {
    const panel = viewsPanel();
    detailSection(panel, 'view-1');
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(jsonPayload({ html: '<ul></ul>' }));
    initViewsPanels({ root });
    await flush();

    const section = panel.querySelector('[data-dk-view-detail]');
    assert.equal(stateOf(section, 'data'), true);
    assert.equal(fetchStub.calls[0].url.includes('render=3'), true);
  });

  it('boots the first detail when none is visible', async () => {
    const panel = viewsPanel();
    detailSection(panel, 'view-2', { visible: false });
    detailSection(panel, 'view-1', { visible: false });
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(jsonPayload({ html: '<ul></ul>' }));
    initViewsPanels({ root });
    await flush();

    assert.equal(fetchStub.calls.length, 1);
  });

  it('prefers the render select over the panel attribute', async () => {
    const panel = viewsPanel();
    const { section } = detailSection(panel, 'view-1');
    const select = new FakeElement('select', {
      'data-dk-select': 'views-render',
    });
    select.value = '2';
    section.appendChild(select);
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(jsonPayload({ html: '<ul></ul>' }));
    initViewsPanels({ root });
    await flush();

    assert.equal(fetchStub.calls[0].url.includes('render=2'), true);
  });

  it('shows the empty state when no html arrives', async () => {
    const panel = viewsPanel();
    detailSection(panel, 'view-1');
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(jsonPayload({ html: '' }));
    initViewsPanels({ root });
    await flush();

    assert.equal(
      stateOf(panel.querySelector('[data-dk-view-detail]'), 'empty'),
      true,
    );
  });

  it('shows the error state on failed responses', async () => {
    const panel = viewsPanel();
    detailSection(panel, 'view-1');
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(() => Promise.resolve({ ok: false }));
    initViewsPanels({ root });
    await flush();

    assert.equal(
      stateOf(panel.querySelector('[data-dk-view-detail]'), 'error'),
      true,
    );
  });

  it('shows the error state without a base url', async () => {
    const panel = new FakeElement('div', { 'data-dk-views-panel': '' });
    const { section } = detailSection(panel, 'view-1');
    section
      .querySelector('[data-dk-view-data-panel]')
      .setAttribute('data-dk-views-url', '');
    panel.setAttribute('data-dk-views-url', '');
    const root = new FakeElement('div');
    root.appendChild(panel);
    initViewsPanels({ root });
    await flush();

    assert.equal(stateOf(section, 'error'), true);
  });

  it('skips panels without details', () => {
    const root = new FakeElement('div');
    root.appendChild(viewsPanel());

    assert.doesNotThrow(() => initViewsPanels({ root }));
  });

  it('selects entries and loads their renders', async () => {
    const panel = viewsPanel();
    const first = detailSection(panel, 'view-1');
    const second = detailSection(panel, 'view-2');
    const entryOne = viewEntry(panel, 'view-1');
    const entryTwo = viewEntry(panel, 'view-2');
    const root = new FakeElement('div');
    root.appendChild(panel);
    const ctx = { root };
    fetchStub = stubFetch(jsonPayload({ html: '<ul></ul>' }));

    assert.equal(handleViewsClick(entryTwo, ctx), true);
    await flush();

    assert.equal(entryTwo.getAttribute('aria-pressed'), 'true');
    assert.equal(entryOne.getAttribute('aria-pressed'), 'false');
    assert.equal(second.section.hasAttribute('hidden'), false);
    assert.equal(first.section.hasAttribute('hidden'), true);
    assert.equal(stateOf(second.section, 'data'), true);
  });

  it('selects entries using the render select', async () => {
    const panel = viewsPanel();
    const { section } = detailSection(panel, 'view-2');
    const select = new FakeElement('select', {
      'data-dk-select': 'views-render',
    });
    select.value = '5';
    section.appendChild(select);
    const entry = viewEntry(panel, 'view-2');
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(jsonPayload({ html: '<ul></ul>' }));
    handleViewsClick(entry, { root });
    await flush();

    assert.equal(fetchStub.calls[0].url.includes('render=5'), true);
  });

  it('retries failed renders', async () => {
    const panel = viewsPanel();
    const { section } = detailSection(panel, 'view-1');
    const retry = new FakeElement('button', { 'data-dk-views-retry': '' });
    section.appendChild(retry);
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(jsonPayload({ html: '<ul></ul>' }));

    assert.equal(handleViewsClick(retry, { root }), true);
    await flush();

    assert.equal(stateOf(section, 'data'), true);
  });

  it('retries outside a detail section', async () => {
    const panel = viewsPanel();
    detailSection(panel, 'view-1');
    const retry = new FakeElement('button', { 'data-dk-views-retry': '' });
    panel.appendChild(retry);
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(jsonPayload({ html: '<ul></ul>' }));

    assert.equal(handleViewsClick(retry, { root }), true);
    await flush();
  });

  it('ignores unrelated clicks', () => {
    const panel = viewsPanel();
    const root = new FakeElement('div');
    root.appendChild(panel);

    assert.equal(handleViewsClick(new FakeElement('button'), { root }), false);
  });

  it('loads into sections without state markers', async () => {
    const panel = viewsPanel();
    const section = new FakeElement('section', {
      'data-dk-view-detail': 'bare',
    });
    const holder = new FakeElement('div', {
      'data-dk-view-data-panel': '',
      'data-dk-views-url': '/debug-bar/panels/view-data/p1',
      'data-dk-views-render': '1',
    });
    section.appendChild(holder);
    panel.appendChild(section);
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(jsonPayload({ html: '<ul></ul>' }));
    initViewsPanels({ root });
    await flush();

    assert.equal(fetchStub.calls.length, 1);
  });
});
