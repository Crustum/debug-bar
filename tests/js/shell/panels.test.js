import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
  buildClickSelector,
  deriveAutoSelection,
  findAutoEntryTarget,
  handleWorkspaceBackClick,
  matchPanelEntry,
  registerPanelSelection,
  registerSelectionConfigs,
  resetFocusWorkspaces,
  selectPanelRecord,
} from '../../../webroot/js/shell/boot/panels.js';
import { setRowPressed } from '../../../webroot/js/shell/boot/core.js';
import { FakeElement } from '../boot-fake-dom.js';

function row(entryAttr, keyAttr, key) {
  return new FakeElement('button', {
    [entryAttr]: '',
    [keyAttr]: key,
    'aria-pressed': 'false',
  });
}

function detail(detailAttr, key) {
  return new FakeElement('section', { [detailAttr]: key, hidden: '' });
}

function panel(root, entries, details) {
  for (const entry of entries) root.appendChild(entry);
  for (const section of details) root.appendChild(section);

  return root;
}

describe('boot panels selection', () => {
  it('selects a cache row and shows only its detail without paint', () => {
    const row1 = row('data-dk-cache-entry', 'data-dk-cache-key', 'op-1');
    const row2 = row('data-dk-cache-entry', 'data-dk-cache-key', 'op-2');
    const detail1 = detail('data-dk-cache-detail', 'op-1');
    const detail2 = detail('data-dk-cache-detail', 'op-2');
    const root = panel(
      new FakeElement('div'),
      [row1, row2],
      [detail1, detail2],
    );
    const painted = [];
    const pressed = [];
    const handled = selectPanelRecord(row2, root, {
      setRowPressed: (row, active) => pressed.push([row, active]),
      paintCodeBlocks: (node) => painted.push(node),
    });

    assert.equal(handled, true);
    assert.deepEqual(
      pressed.map(([node, active]) => [
        node.getAttribute('data-dk-cache-key'),
        active,
      ]),
      [
        ['op-1', false],
        ['op-2', true],
      ],
    );
    assert.equal(detail1.hasAttribute('hidden'), true);
    assert.equal(detail2.hasAttribute('hidden'), false);
    assert.deepEqual(painted, []);
  });

  it('paints http details on show', () => {
    const row1 = row('data-dk-http-entry', 'data-dk-http-key', 'req-1');
    const detail1 = detail('data-dk-http-detail', 'req-1');
    const root = panel(new FakeElement('div'), [row1], [detail1]);
    const painted = [];
    const handled = selectPanelRecord(row1, root, {
      setRowPressed: () => {},
      paintCodeBlocks: (node) => painted.push(node),
    });

    assert.equal(handled, true);
    assert.deepEqual(painted, [detail1]);
  });

  it('paints notification details registered from element config', () => {
    const config = new FakeElement('script', {
      'data-dk-selection-config': '',
    });
    config.textContent = JSON.stringify({
      entry: 'data-dk-notification-entry',
      key: 'data-dk-notification-key',
      detail: 'data-dk-notification-detail',
      panel: '[data-dk-notifications-panel]',
      paint: true,
    });
    const broken = new FakeElement('script', {
      'data-dk-selection-config': '',
    });
    broken.textContent = '{oops';
    const root = panel(
      new FakeElement('div'),
      [
        row(
          'data-dk-notification-entry',
          'data-dk-notification-key',
          'notification-1',
        ),
      ],
      [detail('data-dk-notification-detail', 'notification-1')],
    );
    root.appendChild(config);
    root.appendChild(broken);
    root.appendChild(
      new FakeElement('div', { 'data-dk-selection-config': '' }),
    );

    assert.equal(registerSelectionConfigs(root), 1);
    assert.equal(registerSelectionConfigs(null), 0);
    const row1 = root.children[0];
    const detail1 = root.children[1];
    const painted = [];
    const handled = selectPanelRecord(row1, root, {
      setRowPressed: () => {},
      paintCodeBlocks: (node) => painted.push(node),
    });

    assert.equal(handled, true);
    assert.deepEqual(painted, [detail1]);
  });

  it('builds the click selector from hooks plus registered entries', () => {
    const selector = buildClickSelector();

    assert.match(selector, /\[data-dk-tab\]/);
    assert.match(selector, /\[data-dk-copy\]/);
    assert.match(selector, /\[data-dk-cache-entry\]/);
    assert.match(selector, /\[data-dk-exception-entry\]/);
  });

  it('registers third-party panel entries at runtime', () => {
    assert.equal(registerPanelSelection(null), false);
    assert.equal(
      registerPanelSelection({ entry: 'data-dk-acme-entry' }),
      false,
    );
    assert.equal(
      registerPanelSelection({
        entry: 'data-dk-acme-entry',
        key: 'data-dk-acme-key',
        detail: 'data-dk-acme-detail',
        panel: '[data-dk-acme-panel]',
      }),
      true,
    );

    const found = matchPanelEntry(
      new FakeElement('button', { 'data-dk-acme-entry': '' }),
    );
    assert.equal(found && found.detail, 'data-dk-acme-detail');
    assert.equal(buildClickSelector().includes('[data-dk-acme-entry]'), true);

    assert.equal(
      registerPanelSelection({
        entry: 'data-dk-acme-entry',
        key: 'data-dk-acme-key',
        detail: 'data-dk-acme-detail-v2',
        panel: '[data-dk-acme-panel]',
        paint: true,
      }),
      true,
    );
    const replaced = matchPanelEntry(
      new FakeElement('button', { 'data-dk-acme-entry': '' }),
    );
    assert.equal(replaced.detail, 'data-dk-acme-detail-v2');
    assert.equal(replaced.paint, true);
  });

  it('toggles a focused workspace between list and detail', () => {
    const workspace = new FakeElement('div', {
      'data-dk-workspace-mode': 'focus',
    });
    const list = new FakeElement('div', { 'data-dk-inspector-focus-list': '' });
    const focusDetail = new FakeElement('div', {
      'data-dk-inspector-focus-detail': '',
      hidden: '',
    });
    const timeline = new FakeElement('div', { 'data-dk-timeline-panel': '' });
    const entry = row(
      'data-dk-timeline-entry',
      'data-dk-timeline-key',
      'timeline-1',
    );
    const section = detail('data-dk-timeline-detail', 'timeline-1');
    const back = new FakeElement('button', { 'data-dk-workspace-back': '' });
    const scroller = new FakeElement('div', { 'data-dk-list-body': '' });
    scroller.scrollTop = 240;
    list.scrollTop = 120;
    timeline.appendChild(workspace);
    workspace.appendChild(list);
    workspace.appendChild(focusDetail);
    list.appendChild(scroller);
    scroller.appendChild(entry);
    focusDetail.appendChild(back);
    focusDetail.appendChild(section);

    assert.equal(
      selectPanelRecord(entry, timeline, { setRowPressed: () => {} }),
      true,
    );
    assert.equal(list.hasAttribute('hidden'), true);
    assert.equal(focusDetail.hasAttribute('hidden'), false);
    assert.equal(section.hasAttribute('hidden'), false);

    scroller.scrollTop = 0;
    list.scrollTop = 0;
    assert.equal(handleWorkspaceBackClick(back), true);
    assert.equal(list.hasAttribute('hidden'), false);
    assert.equal(focusDetail.hasAttribute('hidden'), true);
    assert.equal(scroller.scrollTop, 240);
    assert.equal(list.scrollTop, 120);
    assert.equal(handleWorkspaceBackClick(new FakeElement('button')), false);
  });

  it('resets focused workspaces to the list on inspector switch', () => {
    const workspace = new FakeElement('div', {
      'data-dk-workspace-mode': 'focus',
    });
    const list = new FakeElement('div', {
      'data-dk-inspector-focus-list': '',
      hidden: '',
    });
    const focusDetail = new FakeElement('div', {
      'data-dk-inspector-focus-detail': '',
    });
    workspace.appendChild(list);
    workspace.appendChild(focusDetail);
    const root = new FakeElement('div');
    root.appendChild(workspace);

    assert.equal(resetFocusWorkspaces(root), 1);
    assert.equal(list.hasAttribute('hidden'), false);
    assert.equal(focusDetail.hasAttribute('hidden'), true);
    assert.equal(resetFocusWorkspaces(null), 0);
  });

  it('ignores rows owned by local handlers', () => {
    const root = new FakeElement('div');
    const calls = [];
    assert.equal(
      selectPanelRecord(
        new FakeElement('button', { 'data-dk-exception-entry': '' }),
        root,
        { setRowPressed: () => calls.push(1) },
      ),
      false,
    );
    assert.equal(
      selectPanelRecord(
        new FakeElement('button', { 'data-dk-view-entry': '' }),
        root,
        { setRowPressed: () => calls.push(1) },
      ),
      false,
    );
    assert.equal(
      selectPanelRecord(
        new FakeElement('button', { 'data-dk-query-item': '' }),
        root,
        { setRowPressed: () => calls.push(1) },
      ),
      false,
    );
    assert.deepEqual(calls, []);
    assert.equal(matchPanelEntry(null), null);
  });
});

describe('boot panels zero-registration selection', () => {
  it('derives selection for unknown namespaces without registration', () => {
    const target = new FakeElement('button', {
      'data-dk-widget-entry': '',
      'data-dk-widget-key': 'w-1',
    });
    const config = deriveAutoSelection(target);

    assert.equal(config && config.entry, 'data-dk-widget-entry');
    assert.equal(config && config.key, 'data-dk-widget-key');
    assert.equal(config && config.detail, 'data-dk-widget-detail');
    assert.equal(config && config.panel, '[data-dk-widget-panel]');
    assert.deepEqual(matchPanelEntry(target), config);
  });

  it('supports the bare entry/key/detail trio', () => {
    const config = deriveAutoSelection(
      new FakeElement('button', { 'data-dk-entry': '' }),
    );

    assert.equal(config && config.key, 'data-dk-key');
    assert.equal(config && config.detail, 'data-dk-detail');
    assert.equal(config && config.panel, '[data-dk-panel]');
  });

  it('never auto-matches dedicated namespaces', () => {
    for (const namespace of ['exception', 'view', 'variable']) {
      const target = new FakeElement('button', {
        [`data-dk-${namespace}-entry`]: '',
      });

      assert.equal(deriveAutoSelection(target), null);
      assert.equal(matchPanelEntry(target), null);
    }
  });

  it('returns null for non-selectable targets', () => {
    assert.equal(deriveAutoSelection(null), null);
    assert.equal(deriveAutoSelection({}), null);
    assert.equal(
      deriveAutoSelection(new FakeElement('button', { 'data-dk-tab': '' })),
      null,
    );
    assert.equal(
      deriveAutoSelection({
        hasAttribute: (name) => name === 'data-dk-fallback-entry',
        getAttributeNames: () => {
          throw new Error('nope');
        },
        attrs: { 'data-dk-fallback-entry': '' },
      }).entry,
      'data-dk-fallback-entry',
    );
    assert.equal(
      deriveAutoSelection({
        hasAttribute: (name) => name === 'data-dk-second-entry',
        getAttributeNames: () => 'nope',
        attrs: { 'data-dk-second-entry': '' },
      }).entry,
      'data-dk-second-entry',
    );
    assert.equal(
      deriveAutoSelection({ hasAttribute: () => false, attrs: null }),
      null,
    );
  });

  it('prefers registered configs over auto derivation', () => {
    assert.equal(
      registerPanelSelection({
        entry: 'data-dk-gadget-entry',
        key: 'data-dk-gadget-key',
        detail: 'data-dk-gadget-detail',
        panel: '[data-dk-gadget-panel]',
      }),
      true,
    );
    const found = matchPanelEntry(
      new FakeElement('button', { 'data-dk-gadget-entry': '' }),
    );

    assert.equal(found && found.panel, '[data-dk-gadget-panel]');
    assert.equal(found && found.auto, undefined);
  });

  it('selects auto rows scoped to their own panel', () => {
    const panelEl = new FakeElement('div', { 'data-dk-gizmo-panel': '' });
    const row1 = row('data-dk-gizmo-entry', 'data-dk-gizmo-key', 'g-1');
    const row2 = row('data-dk-gizmo-entry', 'data-dk-gizmo-key', 'g-2');
    const detail1 = detail('data-dk-gizmo-detail', 'g-1');
    const detail2 = detail('data-dk-gizmo-detail', 'g-2');
    const root = new FakeElement('div');
    panelEl.appendChild(row1);
    panelEl.appendChild(row2);
    panelEl.appendChild(detail1);
    panelEl.appendChild(detail2);
    root.appendChild(panelEl);
    const painted = [];
    const handled = selectPanelRecord(row2, root, {
      setRowPressed: () => {},
      paintCodeBlocks: (node) => painted.push(node),
    });

    assert.equal(handled, true);
    assert.equal(detail1.hasAttribute('hidden'), true);
    assert.equal(detail2.hasAttribute('hidden'), false);
    assert.deepEqual(painted, [detail2]);
  });

  it('falls back to the inspector panel scope for auto rows', () => {
    const inspectorPanel = new FakeElement('div', {
      'data-dk-inspector-panel': 'gadget',
    });
    const entry = row('data-dk-gadget2-entry', 'data-dk-gadget2-key', 'k-1');
    const section = detail('data-dk-gadget2-detail', 'k-1');
    const root = new FakeElement('div');
    inspectorPanel.appendChild(entry);
    inspectorPanel.appendChild(section);
    root.appendChild(inspectorPanel);

    assert.equal(
      selectPanelRecord(entry, root, { setRowPressed: () => {} }),
      true,
    );
    assert.equal(section.hasAttribute('hidden'), false);
  });

  it('selects without a closest scope on minimal targets', () => {
    const entry = {
      hasAttribute: (name) => name === 'data-dk-minimal-entry',
      getAttributeNames: () => ['data-dk-minimal-entry'],
      getAttribute: () => 'm-1',
    };
    const section = detail('data-dk-minimal-detail', 'm-1');
    const root = new FakeElement('div');
    root.appendChild(section);

    assert.equal(
      selectPanelRecord(entry, root, { setRowPressed: () => {} }),
      true,
    );
    assert.equal(section.hasAttribute('hidden'), false);
  });

  it('walks up from children to the nearest auto row', () => {
    const root = new FakeElement('div');
    const panelEl = new FakeElement('div', { 'data-dk-hike-panel': '' });
    const entry = row('data-dk-hike-entry', 'data-dk-hike-key', 'h-1');
    const child = new FakeElement('span');
    entry.appendChild(child);
    panelEl.appendChild(entry);
    root.appendChild(panelEl);
    const plain = new FakeElement('span');
    root.appendChild(plain);
    const orphan = row('data-dk-hike-entry', 'data-dk-hike-key', 'h-9');

    assert.equal(findAutoEntryTarget(child, root), entry);
    assert.equal(findAutoEntryTarget(entry, root), entry);
    assert.equal(findAutoEntryTarget(plain, root), null);
    assert.equal(findAutoEntryTarget(null, root), null);
    assert.equal(findAutoEntryTarget(root, root), null);
    assert.equal(findAutoEntryTarget(orphan, root), null);
    assert.equal(findAutoEntryTarget(entry, null), entry);
  });
});

describe('boot core row tones', () => {
  it('keeps indigo toggle for plain rows', () => {
    const row = new FakeElement('button', { 'aria-pressed': 'false' });
    row.classList.add('dk:hover:bg-zinc-50/80');
    setRowPressed(row, true);

    assert.equal(row.getAttribute('aria-pressed'), 'true');
    assert.equal(row.classList.contains('dk:bg-indigo-50/65'), true);
  });

  it('delegates tone-marked rows to the query tone table', () => {
    const row = new FakeElement('button', {
      'data-dk-query-key': 'query-1',
      'data-dk-slow': 'true',
      'data-dk-repeated': 'false',
      'data-dk-execution': '1',
      'data-dk-duration': '10',
      'data-dk-query-type': 'read',
      'data-dk-attention': 'false',
      'data-dk-query-execution-count': '1',
      'data-dk-search-text': 'select 1',
      'aria-pressed': 'false',
    });
    setRowPressed(row, true);

    assert.equal(row.getAttribute('aria-pressed'), 'true');
    assert.equal(row.classList.contains('dk:bg-red-50/90'), true);
    setRowPressed(row, false);

    assert.equal(row.classList.contains('dk:bg-red-50/70'), true);
  });
});
