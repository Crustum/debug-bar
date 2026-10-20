import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { handlePickerKey } from '../../../webroot/js/shell/boot/picker.js';
import { FakeElement } from '../boot-fake-dom.js';

function option(id) {
  const row = new FakeElement('li', {
    role: 'option',
    'data-dk-request-option': id,
    tabindex: '-1',
  });
  row.focused = false;
  row.focus = function () {
    row.focused = true;
  };

  return row;
}

function setup() {
  const focused = [];
  const root = new FakeElement('div');
  const trigger = new FakeElement('button', {
    'data-dk-request-trigger': 'toolbar',
  });
  root.appendChild(trigger);
  const picker = new FakeElement('ul', { 'data-dk-request-picker': 'toolbar' });
  const first = option('req-1');
  const second = option('req-2');
  const third = option('req-3');
  for (const row of [first, second, third]) {
    const original = row.focus.bind(row);
    row.focus = function () {
      original();
      focused.push(row.getAttribute('data-dk-request-option'));
    };
    picker.appendChild(row);
  }
  root.appendChild(picker);
  const selected = [];
  const shell = {
    requestPickerIndex: 0,
    selectRequest: (id) => {
      selected.push(id);

      return { id };
    },
    closeRequestPicker: () => {},
  };
  const ctx = { root, shell, state: {} };

  return { ctx, trigger, picker, first, second, third, focused, selected };
}

function key(target, name) {
  return {
    key: name,
    shiftKey: false,
    ctrlKey: false,
    metaKey: false,
    altKey: false,
    target,
    preventDefault: () => {},
  };
}

describe('boot picker keyboard', () => {
  it('walks options with arrows and wraps around', () => {
    const { ctx, second, third, focused } = setup();

    assert.equal(handlePickerKey(key(second, 'ArrowDown'), ctx), true);
    assert.deepEqual(focused, ['req-3']);
    assert.equal(ctx.shell.requestPickerIndex, 2);

    assert.equal(handlePickerKey(key(third, 'ArrowUp'), ctx), true);
    assert.deepEqual(focused, ['req-3', 'req-2']);
  });

  it('jumps to edges with Home and End', () => {
    const { ctx, second, focused } = setup();

    assert.equal(handlePickerKey(key(second, 'Home'), ctx), true);
    assert.equal(handlePickerKey(key(second, 'End'), ctx), true);
    assert.deepEqual(focused, ['req-1', 'req-3']);
  });

  it('activates the focused row with Enter', () => {
    const { ctx, second, selected } = setup();

    assert.equal(handlePickerKey(key(second, 'Enter'), ctx), true);
    assert.deepEqual(selected, ['req-2']);
  });

  it('jumps into the open list from the trigger', () => {
    const { ctx, trigger, focused } = setup();

    assert.equal(handlePickerKey(key(trigger, 'ArrowDown'), ctx), true);
    assert.equal(handlePickerKey(key(trigger, 'ArrowUp'), ctx), true);
    assert.deepEqual(focused, ['req-1', 'req-3']);
  });

  it('ignores keys with modifiers and closed pickers', () => {
    const { ctx, second, picker } = setup();
    const shifted = key(second, 'ArrowDown');
    shifted.shiftKey = true;

    assert.equal(handlePickerKey(shifted, ctx), false);

    picker.setAttribute('hidden', '');

    assert.equal(handlePickerKey(key(second, 'ArrowDown'), ctx), false);
    assert.equal(handlePickerKey({ key: 'a', target: second }, ctx), false);
  });
});
