import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { createQueriesInspector } from '../../../webroot/js/inspectors/queries.js';
import { FakeElement } from '../boot-fake-dom.js';

function queryRow(key, search, execution = '1') {
  return new FakeElement('button', {
    'data-dk-query-item': key,
    'data-dk-query-key': key,
    'data-dk-query-execution': execution,
    'data-dk-duration': '5',
    'data-dk-query-type': 'select',
    'data-dk-search-text': search,
    'aria-pressed': 'false',
  });
}

function queriesRoot() {
  const root = new FakeElement('div', { 'data-dk-queries': '' });
  const first = queryRow('query-1', 'select users', '1');
  const second = queryRow('query-2', 'update orders', '2');
  root.appendChild(first);
  root.appendChild(second);

  return { root, first, second };
}

describe('queries inspector empty states', () => {
  it('shows the empty note when nothing matches', () => {
    const { root, first, second } = queriesRoot();
    const empty = new FakeElement('div', { 'data-dk-list-empty': '' });
    const counter = new FakeElement('span', {
      'data-dk-visible-count': '',
    });
    root.appendChild(empty);
    root.appendChild(counter);
    const inspector = createQueriesInspector(root, {
      fetchClient: async () => null,
    });
    inspector.onSearch('nothing-matches-this');

    assert.equal(first.hasAttribute('hidden'), true);
    assert.equal(second.hasAttribute('hidden'), true);
    assert.equal(empty.hasAttribute('hidden'), false);
    assert.equal(counter.textContent, '0 shown');
  });

  it('hides the counter when everything matches', () => {
    const { root } = queriesRoot();
    const counter = new FakeElement('span', {
      'data-dk-visible-count': '',
    });
    root.appendChild(counter);
    const inspector = createQueriesInspector(root, {
      fetchClient: async () => null,
    });
    inspector.onSearch('');

    assert.equal(counter.hasAttribute('hidden'), true);
  });

  it('shows the detail empty note on deselect', () => {
    const { root } = queriesRoot();
    const detail = new FakeElement('section', {
      'data-dk-query-detail': 'query-1',
    });
    detail.setAttribute('hidden', '');
    const empty = new FakeElement('div', { 'data-dk-detail-empty': '' });
    empty.setAttribute('hidden', '');
    root.appendChild(detail);
    root.appendChild(empty);
    const inspector = createQueriesInspector(root, {
      fetchClient: async () => null,
    });
    inspector.selectRecord('query-1');

    assert.equal(detail.hasAttribute('hidden'), false);
    assert.equal(empty.hasAttribute('hidden'), true);

    inspector.selectRecord(null);

    assert.equal(detail.hasAttribute('hidden'), true);
    assert.equal(empty.hasAttribute('hidden'), false);
  });
});

describe('queries inspector sort heading', () => {
  function sortableRoot() {
    const { root } = queriesRoot();
    const heading = new FakeElement('button', { 'data-dk-sort': 'queries' });
    const label = new FakeElement('span', { 'data-dk-sort-label': '' });
    const icon = new FakeElement('span', { 'data-dk-sort-icon': '' });
    icon.setAttribute('hidden', '');
    heading.appendChild(label);
    heading.appendChild(icon);
    root.appendChild(heading);

    return { root, heading, label, icon };
  }

  it('toggles duration sorting with label and icon', () => {
    const { root, heading, label, icon } = sortableRoot();
    const inspector = createQueriesInspector(root, {
      fetchClient: async () => null,
    });
    inspector.onSortToggle();

    assert.equal(heading.getAttribute('aria-pressed'), 'true');
    assert.match(label.textContent, /descending/);
    assert.equal(icon.hasAttribute('hidden'), false);

    inspector.onSortToggle();

    assert.match(label.textContent, /ascending/);

    inspector.onSortToggle();

    assert.equal(heading.getAttribute('aria-pressed'), 'false');
    assert.match(label.textContent, /not sorted/);
    assert.equal(icon.hasAttribute('hidden'), true);
  });
});

describe('queries inspector explain', () => {
  function explainDetail() {
    const detail = new FakeElement('section', {
      'data-dk-query-detail': 'query-1',
    });
    const loading = new FakeElement('div', {
      'data-dk-query-explain-loading': '',
    });
    loading.setAttribute('hidden', '');
    const result = new FakeElement('div', {
      'data-dk-query-explain-result': '',
    });
    result.setAttribute('hidden', '');
    const errorBox = new FakeElement('div', {
      'data-dk-query-explain-error': '',
    });
    errorBox.setAttribute('hidden', '');
    const errorText = new FakeElement('p', {
      'data-dk-query-explain-error-text': '',
    });
    errorBox.appendChild(errorText);
    detail.appendChild(loading);
    detail.appendChild(result);
    detail.appendChild(errorBox);

    return { detail, loading, result, errorBox, errorText };
  }

  it('renders json plans without pretty output', async () => {
    const { root } = queriesRoot();
    const { detail, result } = explainDetail();
    root.appendChild(detail);
    const inspector = createQueriesInspector(root, {
      fetchClient: async () => ({
        status: 200,
        json: async () => ({ plan: [{ table: 'users' }] }),
      }),
      requestId: 'r1',
      getRequestId: () => 'r1',
    });
    const payload = await inspector.runExplain(detail);

    assert.deepEqual(payload.plan, [{ table: 'users' }]);
    assert.match(result.textContent, /users/);
    assert.equal(result.hasAttribute('hidden'), false);
  });

  it('reports unavailable explains when the client throws', async () => {
    const { root } = queriesRoot();
    const { detail, errorBox, errorText } = explainDetail();
    root.appendChild(detail);
    const inspector = createQueriesInspector(root, {
      fetchClient: async () => null,
      getRequestId: () => {
        throw new Error('gone');
      },
    });
    const payload = await inspector.runExplain(detail);

    assert.equal(payload, null);
    assert.equal(errorBox.hasAttribute('hidden'), false);
    assert.equal(errorText.textContent, 'EXPLAIN is unavailable here.');
  });
});
