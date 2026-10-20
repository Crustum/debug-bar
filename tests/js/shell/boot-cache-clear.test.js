import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { handleCacheClearClick } from '../../../webroot/js/shell/panels/cache.js';

function stubClassList() {
  const set = new Set();

  return {
    add: (name) => set.add(name),
    remove: (name) => set.delete(name),
    contains: (name) => set.has(name),
  };
}

function setup({
  ok = true,
  message = 'Cache cleared.',
  jsonThrows = false,
  all = false,
} = {}) {
  const svg = { classList: stubClassList() };
  const result = {
    textContent: '',
    hidden: true,
    setAttribute: (name) => {
      if (name === 'hidden') result.hidden = true;
    },
    removeAttribute: (name) => {
      if (name === 'hidden') result.hidden = false;
    },
  };
  const row = {
    querySelector: (selector) =>
      selector === '[data-dk-cache-clear-result]' ? result : null,
  };
  const button = {
    attrs: all
      ? {
          'data-dk-cache-clear-all': '',
          'data-dk-cache-clear-all-url': '/debug-bar/toolbar/clear-all-caches',
        }
      : {
          'data-dk-cache-clear': '',
          'data-dk-cache-clear-name': 'default',
          'data-dk-cache-clear-url': '/debug-bar/toolbar/clear-cache',
        },
    hasAttribute: (name) => name in button.attrs,
    getAttribute: (name) => button.attrs[name] ?? null,
    setAttribute: (name, value) => {
      button.attrs[name] = value;
    },
    removeAttribute: (name) => {
      delete button.attrs[name];
    },
    closest: (selector) => (selector === 'li' ? row : null),
    querySelector: (selector) => (selector === 'svg' ? svg : null),
  };
  const timers = [];
  globalThis.window = {
    setTimeout: (fn) => {
      timers.push(fn);

      return timers.length;
    },
    clearTimeout: () => {},
  };
  const fetchCalls = [];
  globalThis.fetch = async (url, init) => {
    fetchCalls.push({ url, init });

    return ok
      ? {
          ok: true,
          json: async () => {
            if (jsonThrows) throw new Error('no json');

            return { message };
          },
        }
      : { ok: false };
  };

  return { button, svg, result, timers, fetchCalls };
}

describe('boot cache clear feedback', () => {
  beforeEach(() => {
    delete globalThis.window;
    delete globalThis.fetch;
  });

  afterEach(() => {
    delete globalThis.window;
    delete globalThis.fetch;
  });

  it('writes the result into the row and flashes the icon on success', async () => {
    const { button, svg, result, timers } = setup();
    assert.equal(handleCacheClearClick(button), true);
    assert.equal(result.textContent, 'Clearing default…');
    assert.equal(result.hidden, false);

    await Promise.resolve();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(result.textContent, 'Cache cleared.');
    assert.equal(svg.classList.contains('dk-cache-flash'), true);

    for (const run of timers) run();
    assert.equal(svg.classList.contains('dk-cache-flash'), false);
    assert.equal(result.textContent, '');
    assert.equal(result.hidden, true);
  });

  it('writes the failure into the row without flashing', async () => {
    const { button, svg, result, timers } = setup({ ok: false });
    assert.equal(handleCacheClearClick(button), true);

    await Promise.resolve();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(result.textContent, 'Could not clear default.');
    assert.equal(result.hidden, false);
    assert.equal(svg.classList.contains('dk-cache-flash'), false);
    assert.ok(timers.length >= 1);

    for (const run of timers) run();
    assert.equal(result.textContent, '');
  });

  it('ignores targets without the clear hook', () => {
    const { button } = setup();
    delete button.attrs['data-dk-cache-clear'];
    assert.equal(handleCacheClearClick(button), false);
  });

  it('clears every cache through the All row on clear-all', async () => {
    const { button, svg, result, timers, fetchCalls } = setup({
      all: true,
      message: 'All 2 caches cleared.',
    });
    assert.equal(handleCacheClearClick(button), true);
    assert.equal(result.textContent, 'Clearing all caches…');
    assert.equal(result.hidden, false);

    await Promise.resolve();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(fetchCalls.length, 1);
    assert.equal(fetchCalls[0].url, '/debug-bar/toolbar/clear-all-caches');
    assert.equal(fetchCalls[0].init.body, '');
    assert.equal(result.textContent, 'All 2 caches cleared.');
    assert.equal(svg.classList.contains('dk-cache-flash'), true);

    for (const run of timers) run();
    assert.equal(result.textContent, '');
    assert.equal(result.hidden, true);
  });
});
