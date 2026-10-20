import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { handleCopyAllClick } from '../../../webroot/js/shell/boot/bridges.js';
import { FakeElement } from '../boot-fake-dom.js';

function setup({ scopeValues = ['one', 'two'], rootValues = [] } = {}) {
  const root = new FakeElement('div');
  for (const value of rootValues) {
    root.appendChild(new FakeElement('span', { 'data-dk-copy-value': value }));
  }
  const scope = new FakeElement('div', { 'data-dk-copy-scope': '' });
  for (const value of scopeValues) {
    scope.appendChild(new FakeElement('span', { 'data-dk-copy-value': value }));
  }
  const label = new FakeElement('span', { 'data-dk-copy-label': '' });
  label.textContent = 'Copy all';
  const button = new FakeElement('button', { 'data-dk-copy-all': '' });
  button.appendChild(label);
  scope.appendChild(button);
  root.appendChild(scope);
  const copied = [];
  const ctx = {
    root,
    shell: {
      copyWithFeedback: (value) => {
        copied.push(value);

        return true;
      },
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

  return { root, scope, button, label, copied, timers, ctx };
}

describe('boot copy all', () => {
  beforeEach(() => {
    delete globalThis.window;
  });

  afterEach(() => {
    delete globalThis.window;
  });

  it('copies every value in the copy scope joined by newline', () => {
    const { button, label, copied, ctx } = setup();
    const handled = handleCopyAllClick(button, ctx);

    assert.equal(handled, true);
    assert.deepEqual(copied, ['one\ntwo']);
    assert.equal(label.textContent, 'Copied');
  });

  it('falls back to the root scope without a copy scope', () => {
    const root = new FakeElement('div');
    root.appendChild(new FakeElement('span', { 'data-dk-copy-value': 'solo' }));
    const label = new FakeElement('span', { 'data-dk-copy-label': '' });
    const button = new FakeElement('button', { 'data-dk-copy-all': '' });
    button.appendChild(label);
    root.appendChild(button);
    const copied = [];
    globalThis.window = { setTimeout: () => 0, clearTimeout: () => {} };
    const ctx = {
      root,
      shell: {
        copyWithFeedback: (value) => {
          copied.push(value);

          return true;
        },
      },
    };

    assert.equal(handleCopyAllClick(button, ctx), true);
    assert.deepEqual(copied, ['solo']);
  });

  it('reports failure when the scope has no values', () => {
    const { button, label, copied, ctx } = setup({ scopeValues: [] });
    const handled = handleCopyAllClick(button, ctx);

    assert.equal(handled, true);
    assert.deepEqual(copied, []);
    assert.equal(label.textContent, 'Copy failed');
  });

  it('supports async clipboard results', async () => {
    const { button, label, copied, ctx } = setup();
    ctx.shell.copyWithFeedback = (value) => {
      copied.push(value);

      return Promise.resolve(true);
    };
    const handled = handleCopyAllClick(button, ctx);

    assert.equal(handled, true);
    await Promise.resolve();
    await Promise.resolve();
    assert.equal(label.textContent, 'Copied');
  });

  it('reports failure when copying throws', () => {
    const { button, label, ctx } = setup();
    ctx.shell.copyWithFeedback = () => {
      throw new Error('denied');
    };

    assert.equal(handleCopyAllClick(button, ctx), true);
    assert.equal(label.textContent, 'Copy failed');
  });

  it('ignores targets without the copy-all hook', () => {
    const { button, ctx } = setup();
    button.removeAttribute('data-dk-copy-all');

    assert.equal(handleCopyAllClick(button, ctx), false);
  });
});
