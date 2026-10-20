import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  handleVariablesClick,
  initVariablesPanels,
} from '../../../webroot/js/shell/panels/variables.js';
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
  const node = section.querySelector(`[data-dk-variables-${name}]`);

  return node ? !node.hasAttribute('hidden') : null;
}

function detailSection(
  panel,
  name,
  { visible = true, url = '/debug-bar/panels/view-variable/p1' } = {},
) {
  const section = new FakeElement('section', {
    'data-dk-variable-detail': name,
  });
  if (!visible) {
    section.setAttribute('hidden', '');
  }
  const states = ['loading', 'data', 'empty', 'error'];
  for (const state of states) {
    const node = new FakeElement('div', {
      [`data-dk-variables-${state}`]: '',
    });
    node.setAttribute('hidden', '');
    section.appendChild(node);
  }
  const holder = new FakeElement('div', {
    'data-dk-variable-data-panel': '',
    'data-dk-variables-url': url,
    'data-dk-variable-name': name,
  });
  section.appendChild(holder);
  panel.appendChild(section);

  return { section, holder };
}

function variablesPanel() {
  const panel = new FakeElement('div', {
    'data-dk-variables-panel': '',
    'data-dk-variables-url': '/debug-bar/panels/view-variable/p1',
  });

  return panel;
}

function variableEntry(panel, name) {
  const entry = new FakeElement('button', {
    'data-dk-variable-entry': '',
    'data-dk-variable-key': name,
    class: 'dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60',
  });
  panel.appendChild(entry);

  return entry;
}

describe('variables lazy inspector', () => {
  let fetchStub;

  beforeEach(() => {
    fetchStub = null;
  });

  afterEach(() => {
    fetchStub?.restore();
  });

  it('loads the visible detail on boot', async () => {
    const panel = variablesPanel();
    detailSection(panel, 'user');
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(jsonPayload({ html: '<ul></ul>' }));
    initVariablesPanels({ root });
    await flush();

    const section = panel.querySelector('[data-dk-variable-detail]');
    assert.equal(stateOf(section, 'data'), true);
    assert.equal(fetchStub.calls[0].url.includes('.json?var=user'), true);
  });

  it('shows the empty state when no html arrives', async () => {
    const panel = variablesPanel();
    detailSection(panel, 'user');
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(jsonPayload({ html: '' }));
    initVariablesPanels({ root });
    await flush();

    assert.equal(
      stateOf(panel.querySelector('[data-dk-variable-detail]'), 'empty'),
      true,
    );
  });

  it('shows the error state on failed responses', async () => {
    const panel = variablesPanel();
    detailSection(panel, 'user');
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(() => Promise.resolve({ ok: false }));
    initVariablesPanels({ root });
    await flush();

    assert.equal(
      stateOf(panel.querySelector('[data-dk-variable-detail]'), 'error'),
      true,
    );
  });

  it('shows the error state without a base url', async () => {
    const panel = new FakeElement('div', { 'data-dk-variables-panel': '' });
    const { section } = detailSection(panel, 'user', { url: '' });
    const root = new FakeElement('div');
    root.appendChild(panel);
    initVariablesPanels({ root });
    await flush();

    assert.equal(stateOf(section, 'error'), true);
  });

  it('skips panels without details', () => {
    const root = new FakeElement('div');
    root.appendChild(variablesPanel());

    assert.doesNotThrow(() => initVariablesPanels({ root }));
  });

  it('boots the first detail when none is visible', async () => {
    const panel = variablesPanel();
    detailSection(panel, 'b', { visible: false });
    detailSection(panel, 'a', { visible: false });
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(jsonPayload({ html: '<ul></ul>' }));
    initVariablesPanels({ root });
    await flush();

    assert.equal(fetchStub.calls.length, 1);
  });

  it('selects entries and loads their data', async () => {
    const panel = variablesPanel();
    const first = detailSection(panel, 'one');
    const second = detailSection(panel, 'two');
    const entryOne = variableEntry(panel, 'one');
    const entryTwo = variableEntry(panel, 'two');
    const root = new FakeElement('div');
    root.appendChild(panel);
    const ctx = { root };
    fetchStub = stubFetch(jsonPayload({ html: '<ul></ul>' }));

    assert.equal(handleVariablesClick(entryTwo, ctx), true);
    await flush();

    assert.equal(entryTwo.getAttribute('aria-pressed'), 'true');
    assert.equal(entryOne.getAttribute('aria-pressed'), 'false');
    assert.equal(second.section.hasAttribute('hidden'), false);
    assert.equal(first.section.hasAttribute('hidden'), true);
    assert.equal(stateOf(second.section, 'data'), true);
  });

  it('retries failed variables', async () => {
    const panel = variablesPanel();
    const { section } = detailSection(panel, 'user');
    const retry = new FakeElement('button', {
      'data-dk-variables-retry': '',
    });
    section.appendChild(retry);
    const root = new FakeElement('div');
    root.appendChild(panel);
    const ctx = { root };
    fetchStub = stubFetch(jsonPayload({ html: '<ul></ul>' }));

    assert.equal(handleVariablesClick(retry, ctx), true);
    await flush();

    assert.equal(stateOf(section, 'data'), true);
  });

  it('retries and selects outside the panel scope', async () => {
    const root = new FakeElement('div');
    const retry = new FakeElement('button', {
      'data-dk-variables-retry': '',
    });
    root.appendChild(retry);
    const entry = new FakeElement('button', {
      'data-dk-variable-entry': '',
      'data-dk-variable-key': 'ghost',
    });
    root.appendChild(entry);
    const ctx = { root };
    fetchStub = stubFetch(jsonPayload({ html: '<ul></ul>' }));

    assert.equal(handleVariablesClick(retry, ctx), true);
    assert.equal(handleVariablesClick(entry, ctx), true);
    await flush();
  });

  it('loads into sections without state markers', async () => {
    const panel = new FakeElement('div', {
      'data-dk-variables-panel': '',
      'data-dk-variables-url': '/debug-bar/panels/view-variable/p1',
    });
    const section = new FakeElement('section', {
      'data-dk-variable-detail': 'bare',
    });
    const holder = new FakeElement('div', {
      'data-dk-variable-data-panel': '',
      'data-dk-variables-url': '/debug-bar/panels/view-variable/p1',
      'data-dk-variable-name': 'bare',
    });
    section.appendChild(holder);
    panel.appendChild(section);
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(jsonPayload({ html: '<ul></ul>' }));
    initVariablesPanels({ root });
    await flush();

    assert.equal(fetchStub.calls.length, 1);
  });

  it('ignores unrelated clicks', () => {
    const panel = variablesPanel();
    const root = new FakeElement('div');
    root.appendChild(panel);

    assert.equal(
      handleVariablesClick(new FakeElement('button'), { root }),
      false,
    );
  });
});
