import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  buildRoot,
  installGlobals,
  cleanupGlobals,
  bootUrl,
  keydown,
} from './boot-shell-harness.mjs';

describe('boot second Escape closes the bar to the mini icon', () => {
  beforeEach(() => {
    cleanupGlobals();
  });

  afterEach(() => {
    cleanupGlobals();
  });

  it('walks mini to bar to mini on repeated Escape', async () => {
    const built = buildRoot();
    const { listeners, posted } = installGlobals(built.root, { top: false });
    await import(bootUrl());

    assert.equal(built.root.getAttribute('data-dk-frame'), 'mini');

    built.mini.click();
    assert.equal(built.root.getAttribute('data-dk-frame'), 'bar');
    assert.ok(posted.includes('toolbar'));

    keydown(listeners, built.root, { key: 'Escape' });
    assert.equal(built.root.getAttribute('data-dk-frame'), 'mini');
    assert.ok(posted.includes('collapse'));
    assert.equal(built.mini.hasAttribute('hidden'), false);
  });
});
