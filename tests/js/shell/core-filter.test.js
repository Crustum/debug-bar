import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
  applyListFilter,
  filterContainerOf,
} from '../../../webroot/js/shell/boot/core.js';
import { FakeElement } from '../boot-fake-dom.js';

function filterRow(group, search, attention) {
  return new FakeElement('button', {
    'data-dk-search-text': search,
    'data-dk-filter-group': group,
    'data-dk-filter-attention': attention ? 'true' : 'false',
  });
}

function filterPanel(filter, rows) {
  const root = new FakeElement('div', {
    'data-dk-notifications-panel': '',
    'data-dk-active-filter': filter,
  });
  for (const row of rows) root.appendChild(row);
  root.appendChild(
    new FakeElement('div', { 'data-dk-list-empty': '', hidden: '' }),
  );
  root.appendChild(
    new FakeElement('span', { 'data-dk-visible-count': '', hidden: '' }),
  );

  return root;
}

describe('boot generic list filter', () => {
  it('finds the closest filterable panel root', () => {
    const root = filterPanel('all', []);
    const row = filterRow('sent', 'mail', false);
    root.appendChild(row);

    assert.equal(filterContainerOf(row), root);
    assert.equal(filterContainerOf(new FakeElement('div')), null);
    assert.equal(filterContainerOf(null), null);
  });

  it('matches multi-token filter groups', () => {
    const ok = filterRow(
      'sent to:admiral class:postpublished',
      'PostPublished admiral',
      false,
    );
    const bad = filterRow('attention to:ops class:alert', 'Alert ops', true);
    const root = filterPanel('to:admiral', [ok, bad]);
    applyListFilter(root);

    assert.equal(ok.hasAttribute('hidden'), false);
    assert.equal(bad.hasAttribute('hidden'), true);
  });

  it('keeps single-token groups and attention working', () => {
    const sent = filterRow('sent', 'mail sent', false);
    const failed = filterRow('attention', 'mail failed', true);
    const root = filterPanel('attention', [sent, failed]);
    applyListFilter(root);

    assert.equal(sent.hasAttribute('hidden'), true);
    assert.equal(failed.hasAttribute('hidden'), false);
  });

  it('combines filter facets with search text', () => {
    const first = filterRow('sent to:ann', 'Ann welcome', false);
    const second = filterRow('sent to:ops', 'Ops alert', false);
    const root = filterPanel('all', [first, second]);
    const input = new FakeElement('input', {
      'data-dk-search': 'notifications',
    });
    input.value = 'ops';
    root.appendChild(input);
    applyListFilter(root);

    assert.equal(first.hasAttribute('hidden'), true);
    assert.equal(second.hasAttribute('hidden'), false);
  });
});
