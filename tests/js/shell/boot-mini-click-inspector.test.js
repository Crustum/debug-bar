import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  buildRoot,
  installGlobals,
  cleanupGlobals,
  bootUrl,
  keydown,
} from './boot-shell-harness.mjs';

describe('boot mini click opens the inspector when configured', () => {
  beforeEach(() => {
    cleanupGlobals();
  });

  afterEach(() => {
    cleanupGlobals();
  });

  it('opens the inspector and returns to the bar on Escape', async () => {
    const built = buildRoot();
    built.root.setAttribute('data-dk-mini-action', 'inspector');
    const { listeners, posted } = installGlobals(built.root);
    await import(bootUrl());

    assert.equal(built.root.__dkShell.miniAction, 'inspector');

    built.mini.click();
    assert.equal(built.root.getAttribute('data-dk-frame'), 'inspector');
    assert.ok(posted.includes('expand'));

    keydown(listeners, built.root, { key: 'Escape' });
    assert.equal(built.root.getAttribute('data-dk-frame'), 'bar');
  });
});
