import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { createShell } from '../../../webroot/js/state.js';
import {
  runPaletteCommand,
  syncMiniToggle,
} from '../../../webroot/js/shell/boot/palette.js';

const INSPECTORS = [{ key: 'request', label: 'Request', active: true }];

function fakeBrowser() {
  return {
    storage: { getItem: () => null, setItem: () => {} },
    matchMedia: () => ({ matches: false }),
    activeElement: () => null,
    afterPaint: (callback) => callback(),
    schedule: (callback) => {
      callback();

      return 1;
    },
    cancelSchedule: () => {},
  };
}

function shellWith(summaryExtras = {}) {
  return createShell(
    { id: 'req-1', inspectors: INSPECTORS, ...summaryExtras },
    { runtime: fakeBrowser(), fetchClient: async () => null },
  );
}

function fakeCtx(shell, attrs = {}) {
  return {
    root: {
      getAttribute: (name) => attrs[name] ?? null,
      setAttribute: () => {},
      querySelector: () => null,
    },
    shell,
    mini: {
      attrs: {},
      setAttribute(name, value) {
        this.attrs[name] = value;
      },
    },
    setFrame: () => {},
    syncToolbarForm: () => {},
    openInspector: () => {},
    closeInspector: () => {},
  };
}

describe('boot mini launcher', () => {
  it('syncs the mini toggle label with the action', () => {
    const shell = shellWith();
    const ctx = fakeCtx(shell);
    shell.setMiniAction('inspector');
    syncMiniToggle(ctx);
    assert.equal(ctx.mini.attrs['aria-label'], 'Open debug inspector');

    shell.setMiniAction('bar');
    syncMiniToggle(ctx);
    assert.equal(ctx.mini.attrs['aria-label'], 'Open debug toolbar');
    assert.ok(ctx.mini.attrs.title.includes('Control + Alt + P'));
  });

  it('tolerates a missing mini toggle', () => {
    syncMiniToggle({
      shell: shellWith(),
      mini: null,
      root: { querySelector: () => null },
    });
  });

  it('runs mini open commands through the boot layer', () => {
    const shell = shellWith();
    const ctx = fakeCtx(shell);
    runPaletteCommand(ctx, 'mini:open-inspector');
    assert.equal(shell.miniAction, 'inspector');

    runPaletteCommand(ctx, 'mini:open-bar');
    assert.equal(shell.miniAction, 'bar');
  });

  it('resets the mini action to the root server default', () => {
    const shell = shellWith({ mini_action: 'bar' });
    const ctx = fakeCtx(shell, { 'data-dk-mini-action': 'inspector' });
    runPaletteCommand(ctx, 'mini:open-bar');
    assert.equal(shell.miniAction, 'bar');
    runPaletteCommand(ctx, 'mini:open-server-default');
    assert.equal(shell.miniAction, 'inspector');
    assert.equal(ctx.mini.attrs['aria-label'], 'Open debug inspector');
  });

  it('forwards mini positions to the host frame', () => {
    const posted = [];
    globalThis.window = {
      parent: { postMessage: (message) => posted.push(message) },
      location: { origin: 'http://localhost' },
    };
    try {
      const shell = shellWith();
      const ctx = fakeCtx(shell);
      runPaletteCommand(ctx, 'mini:middle-right');
      runPaletteCommand(ctx, 'mini:server-default');
    } finally {
      delete globalThis.window;
    }
    assert.deepEqual(posted, [
      'mini-position$$middle-right',
      'mini-position$$server-default',
    ]);
  });
});
