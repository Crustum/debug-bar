import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { createShell } from '../../../webroot/js/state.js';
import {
  createNavigationPart,
  sanitizeInspectorKey,
  sortInspectorsByOrder,
  favoritesFirst,
  DEFAULT_INSPECTOR,
} from '../../../webroot/js/shell/navigation.js';

const INSPECTORS = [
  { key: 'request', label: 'Request', active: true },
  { key: 'queries', label: 'Queries', active: true },
  { key: 'cache', label: 'Cache', active: false },
  { key: 'logs', label: 'Logs', active: true },
];

describe('navigation helpers', () => {
  it('sanitizes keys with a request fallback', () => {
    assert.equal(
      sanitizeInspectorKey('queries', ['request', 'queries']),
      'queries',
    );
    assert.equal(
      sanitizeInspectorKey('missing', ['request', 'queries']),
      'request',
    );
    assert.equal(sanitizeInspectorKey('', ['request']), 'request');
    assert.equal(sanitizeInspectorKey(null, ['request']), 'request');
    assert.equal(sanitizeInspectorKey('anything', []), 'anything');
    assert.equal(sanitizeInspectorKey('anything', null), 'anything');
    assert.equal(DEFAULT_INSPECTOR, 'request');
  });

  it('sorts custom order first, then alphabetically', () => {
    const ordered = sortInspectorsByOrder(INSPECTORS, ['logs', 'request']);
    assert.deepEqual(
      ordered.map((inspector) => inspector.key),
      ['logs', 'request', 'cache', 'queries'],
    );
    assert.deepEqual(sortInspectorsByOrder([], []), []);
    assert.deepEqual(
      sortInspectorsByOrder(INSPECTORS, ['missing']).map(
        (inspector) => inspector.key,
      ),
      ['cache', 'logs', 'queries', 'request'],
    );
  });

  it('orders favorites first in stored order', () => {
    const ordered = favoritesFirst(INSPECTORS, ['logs', 'request', 'missing']);
    assert.deepEqual(
      ordered.map((inspector) => inspector.key),
      ['logs', 'request', 'queries', 'cache'],
    );
    assert.deepEqual(favoritesFirst(INSPECTORS, []), INSPECTORS);
  });
});

describe('navigation part', () => {
  it('exposes keys and ordered inspectors', () => {
    const part = createNavigationPart({ summary: { inspectors: INSPECTORS } });
    part.inspectorOrder = ['logs'];
    part.favorites = ['queries'];

    assert.deepEqual(part.inspectorKeys, [
      'request',
      'queries',
      'cache',
      'logs',
    ]);
    assert.deepEqual(
      part.inspectorsInOrder.map((inspector) => inspector.key),
      ['logs', 'cache', 'queries', 'request'],
    );
    assert.deepEqual(
      part.orderedInspectors.map((inspector) => inspector.key),
      ['queries', 'logs', 'cache', 'request'],
    );
  });

  it('falls back to request for the selected inspector', () => {
    const part = createNavigationPart({ summary: { inspectors: INSPECTORS } });
    part.selected = 'queries';
    assert.equal(part.selectedInspector.key, 'queries');
    part.selected = 'missing';
    assert.deepEqual(part.selectedInspector, {
      key: 'request',
      label: 'Request',
      description: '',
      count: null,
    });
  });

  it('splits navigation by favorite with visibility rules', () => {
    const part = createNavigationPart({ summary: { inspectors: INSPECTORS } });
    part.favorites = ['cache'];
    part.selected = 'request';

    assert.deepEqual(
      part.navigationInspectors(true).map((inspector) => inspector.key),
      ['cache'],
    );
    assert.deepEqual(
      part.navigationInspectors(false).map((inspector) => inspector.key),
      ['logs', 'queries', 'request'],
    );
    assert.equal(
      part.isInspectorVisible({ key: 'cache', active: false }),
      true,
    );
    assert.equal(part.isInspectorVisible({ key: 'logs', active: true }), true);
    assert.equal(
      part.isInspectorVisible({ key: 'other', active: false }),
      false,
    );
  });

  it('toggles favorites', () => {
    const part = createNavigationPart({ summary: { inspectors: INSPECTORS } });
    assert.deepEqual(part.toggleFavorite('queries'), ['queries']);
    assert.equal(part.isFavorite('queries'), true);
    assert.deepEqual(part.toggleFavorite('queries'), []);
    assert.equal(part.isFavorite('queries'), false);
  });

  it('selects and navigates with fallback', () => {
    const part = createNavigationPart({ summary: { inspectors: INSPECTORS } });
    assert.equal(part.selectInspector('queries'), 'queries');
    assert.equal(part.selected, 'queries');
    assert.equal(part.selectInspector('missing'), 'request');
    assert.equal(part.navigateToInspector('logs'), 'logs');
    assert.equal(part.navigateToInspector(null), 'request');
  });

  it('requests the inspector when open and stale', () => {
    const requested = [];
    const part = createNavigationPart({ summary: { inspectors: INSPECTORS } });
    part.inspectorOpen = true;
    part.loadedInspector = 'request';
    part.requestInspector = (key, options) => requested.push([key, options]);

    part.selectInspector('queries', { panelId: 'panel-1' });
    assert.deepEqual(requested, [['queries', { panelId: 'panel-1' }]]);

    requested.length = 0;
    part.loadedInspector = 'queries';
    part.selectInspector('queries');
    assert.deepEqual(requested, []);

    const closed = createNavigationPart({
      summary: { inspectors: INSPECTORS },
    });
    closed.requestInspector = (key) => requested.push(key);
    closed.selectInspector('queries');
    assert.deepEqual(requested, []);
  });

  it('opens the request inspector through the shell hooks', () => {
    const picked = [];
    const part = createNavigationPart({ summary: { inspectors: INSPECTORS } });
    part.inspectorOpen = true;
    part.closeRequestPicker = (restore) => picked.push(['picker', restore]);
    part.selectInspector('queries');
    part.openRequestInspector();
    assert.equal(part.selected, 'request');
    assert.deepEqual(picked, [['picker', false]]);

    const opener = [];
    const closed = createNavigationPart({
      summary: { inspectors: INSPECTORS },
    });
    closed.openInspector = (key, focus) => opener.push([key, focus]);
    closed.openRequestInspector('focus');
    assert.deepEqual(opener, [['request', 'focus']]);

    const bare = createNavigationPart({ summary: { inspectors: INSPECTORS } });
    assert.equal(bare.openRequestInspector(), 'request');
  });
});

describe('navigation missing state', () => {
  it('tolerates empty summaries and ordering', () => {
    const part = createNavigationPart({});
    assert.deepEqual(part.inspectorKeys, []);
    assert.deepEqual(part.inspectorsInOrder, []);
    assert.deepEqual(part.orderedInspectors, []);
    assert.equal(part.selectedInspector.key, 'request');

    part.favorites = null;
    part.inspectorOrder = null;
    assert.equal(part.isFavorite('x'), false);
    assert.deepEqual(part.orderedInspectors, []);
    assert.equal(part.isInspectorActive(undefined), true);
    assert.equal(part.isInspectorVisible({ key: 'x', active: false }), false);
  });
});

describe('navigation shell composition', () => {
  it('registers the navigation part in createShell', () => {
    const shell = createShell(
      { id: 'req-1', inspectors: INSPECTORS },
      { fetchClient: async () => null },
    );
    assert.equal(shell.selected, 'request');
    assert.deepEqual(shell.inspectorKeys, [
      'request',
      'queries',
      'cache',
      'logs',
    ]);
    assert.equal(typeof shell.selectInspector, 'function');
    assert.equal(typeof shell.navigateToInspector, 'function');
  });
});
