import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  buildRoot,
  installGlobals,
  cleanupGlobals,
  bootUrl,
  keydown,
} from './boot-shell-harness.mjs';

describe('boot mini click opens the bar by default', () => {
  beforeEach(() => {
    cleanupGlobals();
  });

  afterEach(() => {
    cleanupGlobals();
  });

  it('clicks through mini to bar and keeps Escape inert on a full page', async () => {
    const built = buildRoot();
    const { listeners, posted } = installGlobals(built.root);
    await import(bootUrl());

    built.mini.click();
    assert.equal(built.root.getAttribute('data-dk-frame'), 'bar');
    assert.ok(posted.includes('toolbar'));

    keydown(listeners, built.root, { key: 'Escape' });
    assert.equal(built.root.getAttribute('data-dk-frame'), 'bar');
    assert.ok(!posted.includes('collapse'));
  });
});
