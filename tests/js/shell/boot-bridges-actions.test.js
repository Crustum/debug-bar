import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  handleCopyClick,
  handleExceptionClick,
  handleQueryClick,
  handleSortClick,
  handleTabsClick,
  handleListChange,
  handleListInput,
  initQueriesInspectors,
  syncRequestIdCopy,
} from '../../../webroot/js/shell/boot/bridges.js';
import { FakeElement } from '../boot-fake-dom.js';

globalThis.Element ??= FakeElement;

function stubWindow() {
  const realWindow = globalThis.window;
  const labels = [];
  globalThis.window = {
    setTimeout: (fn) => {
      labels.push(fn);

      return labels.length;
    },
    clearTimeout: () => {},
  };

  return {
    labels,
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

function exceptionPanel() {
  const panel = new FakeElement('div', {
    'data-dk-exceptions-panel': '',
  });
  const first = new FakeElement('button', {
    'data-dk-exception-entry': '',
    'data-dk-exception-key': 'e1',
    'aria-pressed': 'false',
  });
  const second = new FakeElement('button', {
    'data-dk-exception-entry': '',
    'data-dk-exception-key': 'e2',
    'aria-pressed': 'false',
  });
  const detailOne = new FakeElement('section', {
    'data-dk-exception-detail': 'e1',
  });
  detailOne.setAttribute('hidden', '');
  const detailTwo = new FakeElement('section', {
    'data-dk-exception-detail': 'e2',
  });
  detailTwo.setAttribute('hidden', '');
  panel.appendChild(first);
  panel.appendChild(second);
  panel.appendChild(detailOne);
  panel.appendChild(detailTwo);

  return { panel, first, second, detailOne, detailTwo };
}

describe('bridges exception selection', () => {
  it('toggles entries and details', () => {
    const { panel, first, second, detailOne, detailTwo } = exceptionPanel();
    const ctx = { root: panel };

    assert.equal(handleExceptionClick(first, ctx), true);
    assert.equal(first.getAttribute('aria-pressed'), 'true');
    assert.equal(second.getAttribute('aria-pressed'), 'false');
    assert.equal(detailOne.hasAttribute('hidden'), false);
    assert.equal(detailTwo.hasAttribute('hidden'), true);
  });

  it('ignores unrelated clicks', () => {
    assert.equal(
      handleExceptionClick(new FakeElement('button'), { root: null }),
      false,
    );
  });
});

function queriesCtx() {
  const container = new FakeElement('div', { 'data-dk-queries': '' });
  const item = new FakeElement('button', {
    'data-dk-query-item': 'q1',
    'data-dk-query-key': 'query-1',
    'data-dk-query-execution': '1',
    'data-dk-duration': '5',
    'data-dk-query-type': 'select',
    'data-dk-search-text': 'select 1',
    'aria-pressed': 'false',
  });
  container.appendChild(item);
  const root = new FakeElement('div');
  root.appendChild(container);
  const ctx = { root, shell: {}, summary: {} };
  initQueriesInspectors(ctx);

  return { ctx, container, item };
}

describe('bridges query actions', () => {
  it('selects query records through the inspector', () => {
    const { ctx, item } = queriesCtx();

    assert.equal(handleQueryClick(item, ctx), true);
    assert.equal(item.getAttribute('aria-pressed'), 'true');
  });

  it('selects without an inspector', () => {
    const item = new FakeElement('button', {
      'data-dk-query-item': 'q9',
      'data-dk-query-key': 'query-9',
    });
    const ctx = { root: new FakeElement('div'), shell: {}, summary: {} };

    assert.equal(handleQueryClick(item, ctx), true);
  });

  it('toggles the sort without an inspector', () => {
    const sort = new FakeElement('button', {
      'data-dk-sort': 'queries:toggle',
    });
    const ctx = { root: new FakeElement('div'), shell: {}, summary: {} };

    assert.equal(handleQueryClick(sort, ctx), true);
  });

  it('toggles the sort through the inspector', () => {
    const { ctx, container } = queriesCtx();
    const sort = new FakeElement('button', {
      'data-dk-sort': 'queries:toggle',
    });
    container.appendChild(sort);

    assert.equal(handleQueryClick(sort, ctx), true);
  });

  it('ignores unrelated clicks', () => {
    assert.equal(
      handleQueryClick(new FakeElement('button'), { root: null }),
      false,
    );
  });
});

function modelsPanel() {
  const panel = new FakeElement('div', {
    'data-dk-models-panel': '',
    'data-dk-sort-state': 'capture',
    'data-dk-sort-direction': 'asc',
  });
  const list = new FakeElement('div', { 'data-dk-models-list': '' });
  const heads = {};
  for (const key of ['model', 'retrieved']) {
    const head = new FakeElement('button', { 'data-dk-sort': 'models:' + key });
    const label = new FakeElement('span', { 'data-dk-sort-label': '' });
    const icon = new FakeElement('span', { 'data-dk-sort-icon': '' });
    icon.setAttribute('hidden', '');
    head.appendChild(label);
    head.appendChild(icon);
    panel.appendChild(head);
    heads[key] = { head, label, icon };
  }
  const alpha = new FakeElement('div', {
    'data-dk-model-entry': '',
    'data-dk-model-index': '1',
    'data-dk-model-sort-name': 'Beta',
    'data-dk-model-sort-retrieved': '2',
  });
  const beta = new FakeElement('div', {
    'data-dk-model-entry': '',
    'data-dk-model-index': '0',
    'data-dk-model-sort-name': 'Alpha',
    'data-dk-model-sort-retrieved': '9',
  });
  list.appendChild(alpha);
  list.appendChild(beta);
  panel.appendChild(list);

  return { panel, list, heads };
}

describe('bridges models sort', () => {
  it('cycles model name sort directions', () => {
    const { panel, heads } = modelsPanel();
    const target = heads.model.head;

    assert.equal(handleSortClick(target), true);
    assert.equal(panel.getAttribute('data-dk-sort-state'), 'model');
    assert.equal(heads.model.head.getAttribute('aria-pressed'), 'true');
    assert.match(heads.model.label.textContent, /ascending/);

    handleSortClick(target);
    assert.equal(panel.getAttribute('data-dk-sort-direction'), 'desc');
    assert.match(heads.model.label.textContent, /descending/);

    handleSortClick(target);
    assert.equal(panel.getAttribute('data-dk-sort-state'), 'capture');
    assert.match(heads.model.label.textContent, /not sorted/);
  });

  it('sorts rows by retrieved count', () => {
    const { panel, list } = modelsPanel();
    const target = panel.querySelector('[data-dk-sort="models:retrieved"]');
    handleSortClick(target);

    assert.equal(list.children[0].getAttribute('data-dk-model-index'), '0');
  });

  it('ignores clicks outside a models list', () => {
    const lonely = new FakeElement('button', {
      'data-dk-sort': 'models:model',
    });

    assert.equal(handleSortClick(lonely), true);
  });

  it('ignores unknown sort scopes', () => {
    assert.equal(
      handleSortClick(new FakeElement('button', { 'data-dk-sort': 'nope' })),
      false,
    );
  });
});

function httpPanel() {
  const panel = new FakeElement('div', { 'data-dk-http-panel': '' });
  const target = new FakeElement('button', {
    'data-dk-sort': 'http-client:duration',
    'data-dk-sort-state': 'execution',
    'aria-pressed': 'false',
  });
  const label = new FakeElement('span', { 'data-dk-sort-label': '' });
  const icon = new FakeElement('span', { 'data-dk-sort-icon': '' });
  icon.setAttribute('hidden', '');
  target.appendChild(label);
  target.appendChild(icon);
  const list = new FakeElement('div', { 'data-dk-http-list': '' });
  const slow = new FakeElement('div', {
    'data-dk-http-entry': '',
    'data-dk-execution': '1',
    'data-dk-duration': '30',
  });
  const fast = new FakeElement('div', {
    'data-dk-http-entry': '',
    'data-dk-execution': '0',
    'data-dk-duration': '5',
  });
  const unknown = new FakeElement('div', {
    'data-dk-http-entry': '',
    'data-dk-execution': '2',
    'data-dk-duration': '-1',
  });
  list.appendChild(slow);
  list.appendChild(fast);
  list.appendChild(unknown);
  panel.appendChild(target);
  panel.appendChild(list);

  return { panel, target, label, list };
}

describe('bridges http duration sort', () => {
  it('cycles execution and duration orders', () => {
    const { target, label, list } = httpPanel();

    handleSortClick(target);
    assert.equal(target.getAttribute('data-dk-sort-state'), 'duration-desc');
    assert.match(label.textContent, /descending/);
    assert.equal(list.children[0].getAttribute('data-dk-duration'), '30');

    handleSortClick(target);
    assert.equal(target.getAttribute('data-dk-sort-state'), 'duration-asc');
    assert.equal(list.children[0].getAttribute('data-dk-duration'), '5');

    handleSortClick(target);
    assert.equal(target.getAttribute('data-dk-sort-state'), 'execution');
    assert.match(label.textContent, /not sorted/);
    assert.equal(list.children[0].getAttribute('data-dk-execution'), '0');
  });

  it('sorts without a label or icon', () => {
    const panel = new FakeElement('div', { 'data-dk-http-panel': '' });
    const target = new FakeElement('button', {
      'data-dk-sort': 'http-client:duration',
      'data-dk-sort-state': 'execution',
    });
    const list = new FakeElement('div', { 'data-dk-http-list': '' });
    panel.appendChild(target);
    panel.appendChild(list);

    assert.equal(handleSortClick(target), true);
  });

  it('ignores clicks outside an http list', () => {
    const lonely = new FakeElement('button', {
      'data-dk-sort': 'http-client:duration',
    });

    assert.equal(handleSortClick(lonely), true);
  });
});

describe('bridges tab switching', () => {
  function tabsRoot() {
    const root = new FakeElement('div');
    const tabA = new FakeElement('button', {
      'data-dk-tab': 'first',
      'aria-selected': 'true',
    });
    const tabB = new FakeElement('button', {
      'data-dk-tab': 'second',
      'aria-selected': 'false',
    });
    const panelA = new FakeElement('section', { 'data-dk-tab-panel': 'first' });
    const panelB = new FakeElement('section', {
      'data-dk-tab-panel': 'second',
    });
    panelB.setAttribute('hidden', '');
    root.appendChild(tabA);
    root.appendChild(tabB);
    root.appendChild(panelA);
    root.appendChild(panelB);

    return { root, tabA, tabB, panelA, panelB };
  }

  it('switches tabs and panels', () => {
    const { root, tabA, tabB, panelA, panelB } = tabsRoot();

    assert.equal(handleTabsClick(tabB, { root }), true);
    assert.equal(tabB.getAttribute('aria-selected'), 'true');
    assert.equal(tabA.getAttribute('aria-selected'), 'false');
    assert.equal(panelB.hasAttribute('hidden'), false);
    assert.equal(panelA.hasAttribute('hidden'), true);
  });

  it('scopes tabs to the closest detail tabs', () => {
    const { root, tabB, panelB } = tabsRoot();
    const holder = new FakeElement('div', { 'data-dk-detail-tabs': '' });
    root.appendChild(holder);
    const stray = new FakeElement('button', {
      'data-dk-tab': 'second',
      'aria-selected': 'false',
    });
    holder.appendChild(stray);

    assert.equal(handleTabsClick(stray, { root }), true);
    assert.equal(panelB.hasAttribute('hidden'), false);
  });

  it('runs explains for query explain tabs', () => {
    const { container } = (() => {
      const c = new FakeElement('div', { 'data-dk-queries': '' });
      const r = new FakeElement('div');
      r.appendChild(c);

      return { container: c, root: r };
    })();
    const root = container.parent;
    const ctx = { root, shell: {}, summary: {} };
    initQueriesInspectors(ctx);
    const detail = new FakeElement('section', {
      'data-dk-query-detail': 'query-1',
    });
    const tab = new FakeElement('button', {
      'data-dk-tab': 'query:1:explain',
    });
    detail.appendChild(tab);
    container.appendChild(detail);
    globalThis.fetch = () =>
      Promise.resolve({ ok: true, json: () => Promise.resolve({}) });

    try {
      assert.equal(handleTabsClick(tab, ctx), true);
    } finally {
      delete globalThis.fetch;
    }
  });

  it('ignores unrelated clicks', () => {
    assert.equal(
      handleTabsClick(new FakeElement('button'), { root: null }),
      false,
    );
  });
});

describe('bridges copy control', () => {
  let windowStub;
  let fetchStub;

  beforeEach(() => {
    windowStub = stubWindow();
    fetchStub = null;
  });

  afterEach(() => {
    fetchStub?.restore();
    windowStub.restore();
  });

  function copyButton() {
    const button = new FakeElement('button', {
      'data-dk-copy': '',
      'data-dk-copy-value': 'r1',
    });
    const label = new FakeElement('span', { 'data-dk-copy-label': '' });
    label.textContent = 'Copy request id';
    button.appendChild(label);

    return { button, label };
  }

  it('copies synchronously with feedback', () => {
    const { button, label } = copyButton();
    const ctx = {
      root: new FakeElement('div'),
      shell: { copyWithFeedback: () => true },
    };

    assert.equal(handleCopyClick(button, ctx), true);
    assert.equal(label.textContent, 'Copied');
    windowStub.labels.forEach((fn) => fn());
    assert.equal(label.textContent, 'Copy request id');
  });

  it('copies through a promise client', async () => {
    const { button, label } = copyButton();
    const ctx = {
      root: new FakeElement('div'),
      shell: { copyWithFeedback: () => Promise.resolve(false) },
    };
    handleCopyClick(button, ctx);
    await flush(2);

    assert.equal(label.textContent, 'Copy failed');
  });

  it('reports copy failures without a client', () => {
    const { button, label } = copyButton();
    const ctx = {
      root: new FakeElement('div'),
      shell: {
        copyWithFeedback: () => {
          throw new Error('denied');
        },
      },
    };

    assert.equal(handleCopyClick(button, ctx), true);
    assert.equal(label.textContent, 'Copy failed');
  });

  it('copies without a label or icon', () => {
    const button = new FakeElement('button', {
      'data-dk-copy': '',
      'data-dk-copy-value': 'r1',
    });
    const ctx = {
      root: new FakeElement('div'),
      shell: { copyWithFeedback: () => true },
    };

    assert.equal(handleCopyClick(button, ctx), true);
  });

  it('ignores unrelated clicks', () => {
    assert.equal(
      handleCopyClick(new FakeElement('button'), { root: null }),
      false,
    );
  });
});

describe('bridges list input routing', () => {
  function filterable() {
    const root = new FakeElement('div', { 'data-dk-active-filter': 'all' });
    const input = new FakeElement('input', { 'data-dk-search': 'x' });
    input.value = 'beta';
    const keep = new FakeElement('button', {
      'data-dk-search-text': 'beta row',
    });
    const drop = new FakeElement('button', {
      'data-dk-search-text': 'alpha row',
    });
    root.appendChild(input);
    root.appendChild(keep);
    root.appendChild(drop);

    return { root, input, keep, drop };
  }

  it('filters plain lists', () => {
    const { root, input, keep, drop } = filterable();
    handleListInput({ target: input }, { root });

    assert.equal(keep.hasAttribute('hidden'), false);
    assert.equal(drop.hasAttribute('hidden'), true);
  });

  it('routes queries search to the inspector', () => {
    const { container } = (() => {
      const c = new FakeElement('div', { 'data-dk-queries': '' });
      const item = new FakeElement('button', {
        'data-dk-query-item': 'q1',
        'data-dk-query-key': 'query-1',
        'data-dk-search-text': 'select users',
        'aria-pressed': 'false',
      });
      c.appendChild(item);
      const r = new FakeElement('div');
      r.appendChild(c);

      return { container: c };
    })();
    const root = container.parent;
    const ctx = { root, shell: {}, summary: {} };
    initQueriesInspectors(ctx);
    const input = new FakeElement('input', { 'data-dk-search': 'queries' });
    input.value = 'users';
    container.appendChild(input);
    handleListInput({ target: input }, ctx);

    assert.ok(true);
  });

  it('ignores input outside the root', () => {
    const { input } = filterable();

    assert.doesNotThrow(() =>
      handleListInput({ target: input }, { root: new FakeElement('div') }),
    );
    assert.doesNotThrow(() =>
      handleListInput({ target: null }, { root: new FakeElement('div') }),
    );
  });
});

describe('bridges list change routing', () => {
  let fetchStub;

  beforeEach(() => {
    fetchStub = null;
  });

  afterEach(() => {
    fetchStub?.restore();
  });

  function changeTarget(attrs, value) {
    const select = new FakeElement('select', attrs);
    select.value = value;

    return select;
  }

  it('reloads the render on render select', async () => {
    const panel = new FakeElement('div', {
      'data-dk-views-panel': '',
      'data-dk-views-url': '/debug-bar/panels/view-data/p1',
    });
    const detail = new FakeElement('section', {
      'data-dk-view-detail': 'view-1',
    });
    const states = ['loading', 'data', 'empty', 'error'];
    for (const state of states) {
      const node = new FakeElement('div', {
        [`data-dk-views-${state}`]: '',
      });
      node.setAttribute('hidden', '');
      detail.appendChild(node);
    }
    const holder = new FakeElement('div', {
      'data-dk-view-data-panel': '',
      'data-dk-views-render': '1',
    });
    detail.appendChild(holder);
    const select = changeTarget({ 'data-dk-select': 'views-render' }, '2');
    detail.appendChild(select);
    panel.appendChild(detail);
    const root = new FakeElement('div');
    root.appendChild(panel);
    fetchStub = stubFetch(() =>
      Promise.resolve({ ok: true, json: () => Promise.resolve({ html: '' }) }),
    );
    handleListChange({ target: select }, { root });
    await flush(4);

    assert.equal(fetchStub.calls[0].url.includes('render=2'), true);
  });

  it('ignores render selects outside a views panel', () => {
    const select = changeTarget({ 'data-dk-select': 'views-render' }, '2');
    const root = new FakeElement('div');
    root.appendChild(select);

    assert.doesNotThrow(() => handleListChange({ target: select }, { root }));
  });

  it('routes the queries filter to the inspector', () => {
    const container = new FakeElement('div', { 'data-dk-queries': '' });
    const root = new FakeElement('div');
    root.appendChild(container);
    const ctx = { root, shell: {}, summary: {} };
    initQueriesInspectors(ctx);
    const select = changeTarget({ 'data-dk-select': 'queries-filter' }, 'all');
    container.appendChild(select);
    handleListChange({ target: select }, ctx);

    assert.ok(true);
  });

  it('routes mail preview format switches', () => {
    const detail = new FakeElement('section', { 'data-dk-mail-detail': 'm1' });
    const frame = new FakeElement('iframe', {
      'data-dk-mail-preview-frame': '',
      'data-dk-mail-text-url': '/mail/1.txt',
      'data-dk-mail-html-url': '/mail/1.html',
    });
    detail.appendChild(frame);
    const root = new FakeElement('div');
    root.appendChild(detail);
    const select = changeTarget(
      { 'data-dk-select': 'mail-preview-format' },
      'text',
    );
    detail.appendChild(select);
    handleListChange({ target: select }, { root });

    assert.equal(frame.getAttribute('src'), '/mail/1.txt');

    select.value = 'html';
    handleListChange({ target: select }, { root });

    assert.equal(frame.getAttribute('src'), '/mail/1.html');
  });

  it('ignores mail format switches without frames', () => {
    const detail = new FakeElement('section', { 'data-dk-mail-detail': 'm1' });
    const root = new FakeElement('div');
    root.appendChild(detail);
    const select = changeTarget(
      { 'data-dk-select': 'mail-preview-format' },
      'text',
    );
    detail.appendChild(select);

    assert.doesNotThrow(() => handleListChange({ target: select }, { root }));
  });

  it('routes query execution changes to the inspector', () => {
    const container = new FakeElement('div', { 'data-dk-queries': '' });
    const detail = new FakeElement('section', {
      'data-dk-query-detail': 'query-1',
    });
    container.appendChild(detail);
    const root = new FakeElement('div');
    root.appendChild(container);
    const ctx = { root, shell: {}, summary: {} };
    initQueriesInspectors(ctx);
    const select = changeTarget({ 'data-dk-select': 'query-execution:1' }, '1');
    detail.appendChild(select);
    handleListChange({ target: select }, ctx);

    assert.ok(true);
  });

  it('applies generic selects to the filter panel', () => {
    const panel = new FakeElement('div', { 'data-dk-active-filter': 'all' });
    const keep = new FakeElement('button', {
      'data-dk-search-text': 'x',
      'data-dk-filter-group': 'application',
    });
    panel.appendChild(keep);
    const root = new FakeElement('div');
    root.appendChild(panel);
    const select = changeTarget(
      { 'data-dk-select': 'views-filter' },
      'application',
    );
    panel.appendChild(select);
    handleListChange({ target: select }, { root });

    assert.equal(panel.getAttribute('data-dk-active-filter'), 'application');
  });

  it('ignores generic selects outside a filter panel', () => {
    const select = changeTarget({ 'data-dk-select': 'views-filter' }, 'all');
    const root = new FakeElement('div');
    root.appendChild(select);

    assert.doesNotThrow(() => handleListChange({ target: select }, { root }));
  });

  it('ignores change events outside the root', () => {
    assert.doesNotThrow(() =>
      handleListChange(
        { target: changeTarget({}, 'x') },
        { root: new FakeElement('div') },
      ),
    );
    assert.doesNotThrow(() =>
      handleListChange({ target: null }, { root: new FakeElement('div') }),
    );
  });
});

describe('bridges request id copy', () => {
  it('rejects empty ids', () => {
    assert.equal(syncRequestIdCopy({ root: null }, ''), false);
    assert.equal(syncRequestIdCopy({ root: null }, null), false);
  });

  it('repoints copy controls', () => {
    const root = new FakeElement('div');
    const control = new FakeElement('button', {
      'data-dk-copy-request-id': '',
    });
    root.appendChild(control);

    assert.equal(syncRequestIdCopy({ root }, 'r9'), true);
    assert.equal(control.getAttribute('data-dk-copy-value'), 'r9');
  });

  it('reports when nothing was repointed', () => {
    assert.equal(
      syncRequestIdCopy({ root: new FakeElement('div') }, 'r9'),
      false,
    );
  });
});
