import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  buildRoot,
  installGlobals,
  cleanupGlobals,
  bootUrl,
} from './boot-shell-harness.mjs';

describe('boot shell frames', () => {
  beforeEach(() => {
    cleanupGlobals();
  });

  afterEach(() => {
    cleanupGlobals();
  });

  it('boots embedded frames without crashing', async () => {
    const built = buildRoot();
    installGlobals(built.root, { top: false });
    await import(bootUrl());
    assert.equal(built.root.getAttribute('data-dk-frame'), 'mini');
  });
});
