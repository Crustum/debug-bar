import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
  rememberTimelineRow,
  restoreTimelineSelection,
  revealInspectorRecord,
  selectPanelRecord,
} from '../../../webroot/js/shell/boot/panels.js';
import { createInspectorPart } from '../../../webroot/js/shell/inspector.js';
import { FakeElement } from '../boot-fake-dom.js';

function row(entryAttr, keyAttr, key, scrolled = null) {
  const element = new FakeElement('button', {
    [entryAttr]: '',
    [keyAttr]: key,
    'aria-pressed': 'false',
  });
  if (scrolled !== null) {
    element.scrollIntoView = () => scrolled.push(key);
  }

  return element;
}

function detail(detailAttr, key, hidden = true) {
  const attrs = { [detailAttr]: key };
  if (hidden) attrs.hidden = '';

  return new FakeElement('section', attrs);
}

function hooks(pressed) {
  return {
    setRowPressed: (element, active) => {
      pressed.push([element.getAttribute('data-dk-timeline-key') ?? '', active]);
      element.setAttribute('aria-pressed', active ? 'true' : 'false');
    },
    paintCodeBlocks: () => {},
  };
}

function timelineSection(keys) {
  const section = new FakeElement('section', {
    'data-dk-inspector-panel': 'timeline',
  });
  const rows = keys.map(
    (key) =>
      new FakeElement('button', {
        'data-dk-timeline-entry': '',
        'data-dk-timeline-key': key,
        'aria-pressed': 'false',
      }),
  );
  const details = keys.map((key) => detail('data-dk-timeline-detail', key));
  for (const entry of rows) section.appendChild(entry);
  for (const sectionDetail of details) section.appendChild(sectionDetail);

  return { section, rows, details };
}

describe('timeline reveal', () => {
  it('presses the exact row matching the timeline ref', () => {
    const scrolled = [];
    const section = new FakeElement('section', {
      'data-dk-inspector-panel': 'log',
    });
    const row1 = row('data-dk-log-entry', 'data-dk-log-key', 'log-0', scrolled);
    const row2 = row('data-dk-log-entry', 'data-dk-log-key', 'log-1', scrolled);
    const root = new FakeElement('div');
    root.appendChild(section);
    section.appendChild(row1);
    section.appendChild(row2);
    section.addEventListener('click', (event) => {
      if (event.target && event.target.hasAttribute('data-dk-log-entry')) {
        row1.setAttribute('aria-pressed', 'false');
        row2.setAttribute('aria-pressed', 'false');
        event.target.setAttribute('aria-pressed', 'true');
      }
    });

    assert.equal(revealInspectorRecord(root, 'log', 'log-1'), true);
    assert.equal(row2.getAttribute('aria-pressed'), 'true');
    assert.equal(row1.getAttribute('aria-pressed'), 'false');
    assert.deepEqual(scrolled, ['log-1']);
  });

  it('ignores unknown refs without touching rows', () => {
    const section = new FakeElement('section', {
      'data-dk-inspector-panel': 'cache',
    });
    const row1 = row('data-dk-cache-entry', 'data-dk-cache-key', 'cache-0');
    section.appendChild(row1);
    const root = new FakeElement('div');
    root.appendChild(section);

    assert.equal(revealInspectorRecord(root, 'cache', 'cache-9'), false);
    assert.equal(row1.getAttribute('aria-pressed'), 'false');
    assert.equal(revealInspectorRecord(root, 'cache', null), false);
    assert.equal(revealInspectorRecord(root, 'unknown', 'cache-0'), false);
  });

  it('matches query rows by execution id', () => {
    const section = new FakeElement('section', {
      'data-dk-inspector-panel': 'queries',
    });
    const row1 = new FakeElement('button', {
      'data-dk-query-item': '',
      'data-dk-query-key': 'query-1',
      'data-dk-execution': '1',
      'aria-pressed': 'false',
    });
    const row2 = new FakeElement('button', {
      'data-dk-query-item': '',
      'data-dk-query-key': 'query-2',
      'data-dk-execution': '2',
      'aria-pressed': 'false',
    });
    section.appendChild(row1);
    section.appendChild(row2);
    section.addEventListener('click', (event) => {
      if (event.target && event.target.hasAttribute('data-dk-query-item')) {
        event.target.setAttribute('aria-pressed', 'true');
      }
    });
    const root = new FakeElement('div');
    root.appendChild(section);

    assert.equal(revealInspectorRecord(root, 'queries', 2), true);
    assert.equal(row2.getAttribute('aria-pressed'), 'true');
    assert.equal(row1.getAttribute('aria-pressed'), 'false');
  });
});

describe('timeline selection round trip', () => {
  it('re-presses the last timeline row after a collapse', () => {
    const { section, rows, details } = timelineSection([
      'timeline-1',
      'timeline-2',
    ]);
    const root = new FakeElement('div');
    root.appendChild(section);
    const pressed = [];
    const calls = hooks(pressed);

    assert.equal(
      selectPanelRecord(rows[1], root, calls),
      true,
    );
    assert.equal(rows[1].getAttribute('aria-pressed'), 'true');
    assert.equal(details[1].hasAttribute('hidden'), false);

    details[1].setAttribute('hidden', '');
    rows[1].setAttribute('aria-pressed', 'false');

    assert.equal(restoreTimelineSelection(root, calls), true);
    assert.equal(rows[1].getAttribute('aria-pressed'), 'true');
    assert.equal(details[1].hasAttribute('hidden'), false);
  });

  it('does nothing without a matching row', () => {
    const { section, rows } = timelineSection(['timeline-9']);
    const root = new FakeElement('div');
    root.appendChild(section);
    const pressed = [];

    selectPanelRecord(rows[0], root, hooks(pressed));
    rows[0].remove();

    assert.equal(restoreTimelineSelection(root, hooks(pressed)), false);
  });

  it('remembers a jumped-from row without opening its preview', () => {
    const { section, rows, details } = timelineSection([
      'timeline-1',
      'timeline-2',
    ]);
    const root = new FakeElement('div');
    root.appendChild(section);
    const pressed = [];
    const calls = hooks(pressed);

    selectPanelRecord(rows[0], root, calls);
    assert.equal(details[0].hasAttribute('hidden'), false);

    assert.equal(rememberTimelineRow(rows[1]), true);
    details[1].setAttribute('hidden', '');

    assert.equal(restoreTimelineSelection(root, calls), true);
    assert.equal(rows[1].getAttribute('aria-pressed'), 'true');
    assert.equal(details[1].hasAttribute('hidden'), false);
  });

  it('ignores rows without a timeline key', () => {
    const plain = new FakeElement('button', { 'aria-pressed': 'false' });
    assert.equal(rememberTimelineRow(plain), false);
    assert.equal(rememberTimelineRow(null), false);
  });
});

describe('inspector row anchor', () => {
  it('scrolls the selected row into view on tab switch', () => {
    const browser = { afterPaint: (callback) => callback() };
    const part = createInspectorPart({ browser });
    const timeline = new FakeElement('section', {
      'data-dk-inspector-panel': 'timeline',
    });
    const pressed = new FakeElement('button', { 'aria-pressed': 'true' });
    const calls = [];
    pressed.scrollIntoView = (options) => calls.push(options);
    timeline.appendChild(pressed);
    const queries = new FakeElement('section', {
      'data-dk-inspector-panel': 'queries',
    });
    const panels = [timeline, queries];

    part.selected = 'timeline';
    part.syncInspectorPanels(panels);
    assert.deepEqual(calls, [{ block: 'center' }]);

    part.selected = 'queries';
    part.syncInspectorPanels(panels);
  });

  function scrolledTimeline() {
    const scroller = new FakeElement('div', {
      'data-dk-inspector-content': '',
    });
    scroller.scrollHeight = 800;
    scroller.clientHeight = 200;
    scroller.getBoundingClientRect = () => ({ top: 0, bottom: 200 });
    const timeline = new FakeElement('section', {
      'data-dk-inspector-panel': 'timeline',
    });
    scroller.appendChild(timeline);

    return { scroller, timeline };
  }

  it('leaves an already visible row alone', () => {
    const browser = { afterPaint: (callback) => callback() };
    const part = createInspectorPart({ browser });
    const { timeline } = scrolledTimeline();
    const pressed = new FakeElement('button', { 'aria-pressed': 'true' });
    const calls = [];
    pressed.scrollIntoView = (options) => calls.push(options);
    pressed.getBoundingClientRect = () => ({ top: 20, bottom: 40 });
    timeline.appendChild(pressed);
    const panels = [timeline];

    part.selected = 'timeline';
    part.syncInspectorPanels(panels);
    assert.deepEqual(calls, []);
  });

  it('centers a row below the fold', () => {
    const browser = { afterPaint: (callback) => callback() };
    const part = createInspectorPart({ browser });
    const { timeline } = scrolledTimeline();
    const pressed = new FakeElement('button', { 'aria-pressed': 'true' });
    const calls = [];
    pressed.scrollIntoView = (options) => calls.push(options);
    pressed.getBoundingClientRect = () => ({ top: 300, bottom: 320 });
    timeline.appendChild(pressed);
    const panels = [timeline];

    part.selected = 'timeline';
    part.syncInspectorPanels(panels);
    assert.deepEqual(calls, [{ block: 'center' }]);
  });
});
