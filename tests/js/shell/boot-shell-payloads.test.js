import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  buildRoot,
  installGlobals,
  cleanupGlobals,
  bootUrl,
} from './boot-shell-harness.mjs';

describe('boot shell payloads', () => {
  beforeEach(() => {
    cleanupGlobals();
  });

  afterEach(() => {
    cleanupGlobals();
  });

  it('boots broken payloads without crashing', async () => {
    const broken = buildRoot();
    broken.root.querySelector('[data-dk-summary]').textContent = '{oops';
    installGlobals(broken.root);
    await import(bootUrl());
    assert.equal(broken.root.getAttribute('data-dk-frame'), 'full');
    assert.ok(broken.root.__dkShell);
  });
});
