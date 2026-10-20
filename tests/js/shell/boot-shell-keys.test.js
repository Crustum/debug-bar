import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  buildRoot,
  installGlobals,
  cleanupGlobals,
  bootUrl,
  keydown,
} from './boot-shell-harness.mjs';

describe('boot shell keys', () => {
  beforeEach(() => {
    cleanupGlobals();
  });

  afterEach(() => {
    cleanupGlobals();
  });
  it('routes keyboard, input, change and message events', async () => {
    const built = buildRoot();
    const { listeners } = installGlobals(built.root);
    await import(bootUrl('keys'));

    keydown(listeners, built.httpRow, { key: 'Enter' });
    assert.equal(built.httpRow.getAttribute('aria-pressed'), 'true');

    keydown(listeners, built.httpRow, { key: ' ' });
    assert.equal(built.httpDetail.hasAttribute('hidden'), false);

    keydown(listeners, built.root, { key: 'Escape' });
    assert.equal(built.root.getAttribute('data-dk-frame'), 'full');

    for (const fn of listeners.window.message ?? []) {
      fn({ origin: 'http://evil.test', data: 'x' });
      fn({ origin: 'http://localhost', data: 'not-json' });
    }

    built.searchInput.value = 'hello';
    for (const fn of built.root.listeners.input ?? []) {
      fn({ target: built.searchInput, preventDefault: () => {} });
    }
    assert.equal(built.filterRow.hasAttribute('hidden'), false);

    built.select.value = 'sent';
    for (const fn of built.root.listeners.change ?? []) {
      fn({ target: built.select, preventDefault: () => {} });
    }
    assert.equal(
      built.root
        .querySelector('[data-dk-active-filter]')
        .getAttribute('data-dk-active-filter'),
      'sent',
    );
  });
});
