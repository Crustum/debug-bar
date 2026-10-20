import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  buildRoot,
  installGlobals,
  cleanupGlobals,
  bootUrl,
} from './boot-shell-harness.mjs';

describe('boot shell string payloads', () => {
  beforeEach(() => {
    cleanupGlobals();
  });

  afterEach(() => {
    cleanupGlobals();
  });

  it('boots string-encoded payloads without crashing', async () => {
    const built = buildRoot();
    built.root.querySelector('[data-dk-summary]').textContent = JSON.stringify(
      JSON.stringify({ id: String(9) }),
    );
    built.root.querySelector('[data-dk-recent]').textContent = '"[]"';
    installGlobals(built.root);
    await import(bootUrl());
    assert.ok(built.root.__dkShell);
  });
});
