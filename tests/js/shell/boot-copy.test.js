import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  handleCopyClick,
  syncRequestIdCopy,
} from '../../../webroot/js/shell/boot/bridges.js';

function stubClassList() {
  const set = new Set();

  return {
    add: (name) => set.add(name),
    remove: (name) => set.delete(name),
    contains: (name) => set.has(name),
  };
}

function setup(copyResult) {
  const svg = { classList: stubClassList() };
  const label = { textContent: 'Copy request id' };
  const button = {
    attrs: {
      'data-dk-copy': '',
      'data-dk-copy-value': 'req-1',
    },
    hasAttribute: (name) => name in button.attrs,
    getAttribute: (name) => button.attrs[name] ?? null,
    querySelector: (selector) => {
      if (selector === '[data-dk-copy-label]') return label;
      if (selector === 'svg') return svg;

      return null;
    },
  };
  const timers = [];
  globalThis.window = {
    setTimeout: (fn) => {
      timers.push(fn);

      return timers.length;
    },
    clearTimeout: () => {},
  };
  const ctx = {
    root: null,
    shell: { copyWithFeedback: () => copyResult },
  };

  return { button, svg, label, timers, ctx };
}

describe('boot copy icon flash', () => {
  beforeEach(() => {
    delete globalThis.window;
  });

  afterEach(() => {
    delete globalThis.window;
  });

  it('bolds the icon on success and releases it after the delay', () => {
    const { button, svg, label, timers, ctx } = setup(true);
    const handled = handleCopyClick(button, ctx);

    assert.equal(handled, true);
    assert.equal(label.textContent, 'Copied');
    assert.equal(svg.classList.contains('dk-copy-flash'), true);
    assert.equal(timers.length, 2);
    for (const run of timers) run();
    assert.equal(svg.classList.contains('dk-copy-flash'), false);
    assert.equal(label.textContent, 'Copy request id');
  });

  it('leaves the icon alone on failure', () => {
    const { button, svg, label, timers, ctx } = setup(false);
    const handled = handleCopyClick(button, ctx);

    assert.equal(handled, true);
    assert.equal(label.textContent, 'Copy failed');
    assert.equal(svg.classList.contains('dk-copy-flash'), false);
    assert.equal(timers.length, 1);
  });

  it('ignores targets without copy hook or icon', () => {
    const { button, ctx } = setup(true);
    delete button.attrs['data-dk-copy'];
    assert.equal(handleCopyClick(button, ctx), false);
    button.attrs['data-dk-copy'] = '';
    button.querySelector = (selector) =>
      selector === '[data-dk-copy-label]' ? { textContent: 'x' } : null;
    assert.equal(handleCopyClick(button, ctx), true);
  });
});

describe('request id copy follows swaps', () => {
  function setupSwap() {
    const values = {};
    const control = {
      setAttribute: (name, value) => {
        values[name] = value;
      },
    };
    const ctx = {
      root: {
        querySelectorAll: (selector) =>
          selector === '[data-dk-copy-request-id]' ? [control] : [],
      },
    };

    return { control, ctx, values };
  }

  it('repoints the header control at the swapped request', () => {
    const { ctx, values } = setupSwap();
    assert.equal(syncRequestIdCopy(ctx, 'req-2'), true);
    assert.equal(values['data-dk-copy-value'], 'req-2');
  });

  it('leaves controls alone without a usable id', () => {
    const { ctx, values } = setupSwap();
    assert.equal(syncRequestIdCopy(ctx, ''), false);
    assert.equal(values['data-dk-copy-value'], undefined);
  });
});
