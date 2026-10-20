import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  buildRoot,
  installGlobals,
  cleanupGlobals,
  bootUrl,
} from './boot-shell-harness.mjs';

describe('boot host palette hotkey', () => {
  beforeEach(() => {
    cleanupGlobals();
  });

  afterEach(() => {
    cleanupGlobals();
  });

  it('toggles the palette on open-palette messages from the host', async () => {
    const built = buildRoot();
    const { listeners, posted, fakeWindow } = installGlobals(built.root, {
      top: false,
    });
    await import(bootUrl());

    assert.equal(built.root.getAttribute('data-dk-frame'), 'mini');

    const message = (
      data,
      origin = 'http://localhost',
      source = fakeWindow.parent,
    ) => {
      for (const fn of listeners.window.message ?? []) {
        fn({ data, origin, source });
      }
    };

    assert.equal(built.palette.hasAttribute('hidden'), true);

    message('open-palette');
    assert.equal(built.palette.hasAttribute('hidden'), false);
    assert.equal(built.root.getAttribute('data-dk-frame'), 'palette');
    assert.ok(posted.includes('expand'));

    message('open-palette');
    assert.equal(built.palette.hasAttribute('hidden'), true);
    assert.equal(built.root.getAttribute('data-dk-frame'), 'bar');
    assert.ok(posted.includes('toolbar'));
  });

  it('ignores foreign origins and unknown messages', async () => {
    const built = buildRoot();
    const { listeners, posted } = installGlobals(built.root);
    await import(bootUrl());

    const foreign = listeners.window.message ?? [];
    for (const fn of foreign) {
      fn({ data: 'open-palette', origin: 'http://evil/', source: null });
      fn({ data: 'nope', origin: 'http://localhost', source: null });
    }

    assert.equal(built.palette.hasAttribute('hidden'), true);
    assert.deepEqual(posted, []);
  });
});
