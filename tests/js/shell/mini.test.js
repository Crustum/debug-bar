import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
  MINI_POSITIONS,
  MINI_ACTIONS,
  MINI_POSITION_DEFAULT,
  MINI_ACTION_DEFAULT,
  MINI_STORAGE_KEY,
  sanitizeMiniPosition,
  sanitizeMiniAction,
  miniPositionLabel,
  buildMiniCommands,
} from '../../../webroot/js/shell/mini.js';
import { buildPaletteCommands } from '../../../webroot/js/shell/palette.js';
import { createShell } from '../../../webroot/js/state.js';

describe('mini launcher contract', () => {
  it('exposes six positions and two actions with safe defaults', () => {
    assert.deepEqual(MINI_POSITIONS, [
      'bottom-right',
      'bottom-left',
      'top-right',
      'top-left',
      'middle-right',
      'middle-left',
    ]);
    assert.deepEqual(MINI_ACTIONS, ['bar', 'inspector']);
    assert.equal(MINI_POSITION_DEFAULT, 'bottom-right');
    assert.equal(MINI_ACTION_DEFAULT, 'bar');
    assert.equal(MINI_STORAGE_KEY, 'debugbar.mini.position.v1');
  });

  it('sanitizes positions with fallback', () => {
    assert.equal(sanitizeMiniPosition('top-left'), 'top-left');
    assert.equal(sanitizeMiniPosition('middle-right'), 'middle-right');
    assert.equal(sanitizeMiniPosition('nowhere'), 'bottom-right');
    assert.equal(sanitizeMiniPosition(null), 'bottom-right');
    assert.equal(sanitizeMiniPosition('nowhere', 'top-left'), 'top-left');
    assert.equal(sanitizeMiniPosition('nowhere', 'nowhere'), 'bottom-right');
  });

  it('sanitizes actions with fallback', () => {
    assert.equal(sanitizeMiniAction('inspector'), 'inspector');
    assert.equal(sanitizeMiniAction('bar'), 'bar');
    assert.equal(sanitizeMiniAction('nowhere'), 'bar');
    assert.equal(sanitizeMiniAction(null), 'bar');
    assert.equal(sanitizeMiniAction('nowhere', 'inspector'), 'inspector');
    assert.equal(sanitizeMiniAction('nowhere', 'nowhere'), 'bar');
  });

  it('labels positions for humans', () => {
    assert.equal(miniPositionLabel('middle-right'), 'middle right');
    assert.equal(miniPositionLabel('top-left'), 'top left');
  });

  it('builds six position commands plus reset and open commands', () => {
    const commands = buildMiniCommands();
    assert.equal(commands.length, 10);
    for (const position of MINI_POSITIONS) {
      assert.ok(commands.some((command) => command.id === `mini:${position}`));
    }
    assert.ok(commands.some((command) => command.id === 'mini:server-default'));
    assert.ok(commands.some((command) => command.id === 'mini:open-bar'));
    assert.ok(commands.some((command) => command.id === 'mini:open-inspector'));
    assert.ok(
      commands.some((command) => command.id === 'mini:open-server-default'),
    );
    assert.ok(commands.every((command) => command.hint === 'Mini'));
  });

  it('exposes mini commands through the palette', () => {
    const commands = buildPaletteCommands([
      { key: 'request', label: 'Request', active: true },
    ]);
    assert.ok(commands.some((command) => command.id === 'mini:middle-right'));
    assert.ok(commands.some((command) => command.id === 'mini:open-inspector'));
    assert.ok(
      commands
        .filter((command) => command.id.startsWith('mini:'))
        .every((command) => command.hint === 'Mini'),
    );
  });

  it('seeds miniAction from the summary and persists it', () => {
    const store = {};
    const storage = {
      getItem: (key) => (key in store ? store[key] : null),
      setItem: (key, value) => {
        store[key] = String(value);
      },
    };
    const browser = {
      storage,
      matchMedia: () => ({ matches: false }),
      activeElement: () => null,
      afterPaint: (callback) => callback(),
      schedule: (callback) => {
        callback();

        return 1;
      },
      cancelSchedule: () => {},
    };
    const shell = createShell(
      {
        id: 'req-1',
        mini_action: 'inspector',
        inspectors: [{ key: 'request', label: 'Request', active: true }],
      },
      { runtime: browser, fetchClient: async () => null },
    );
    assert.equal(shell.miniAction, 'inspector');
    assert.equal(shell.setMiniAction('bar'), 'bar');
    const saved = JSON.parse(store['debugbar.preferences.v1']);
    assert.equal(saved.miniAction, 'bar');
    assert.equal(shell.setMiniAction('nowhere'), 'bar');

    const next = createShell(
      {
        id: 'req-1',
        inspectors: [{ key: 'request', label: 'Request', active: true }],
      },
      { runtime: browser, fetchClient: async () => null },
    );
    assert.equal(next.restorePreferences(), true);
    assert.equal(next.miniAction, 'bar');
  });

  it('runs mini open commands through the shell', () => {
    const shell = createShell(
      {
        id: 'req-1',
        inspectors: [{ key: 'request', label: 'Request', active: true }],
      },
      {
        runtime: {
          activeElement: () => null,
          afterPaint: (callback) => callback(),
          schedule: (callback) => {
            callback();

            return 1;
          },
          cancelSchedule: () => {},
        },
        fetchClient: async () => null,
      },
    );
    shell.openPalette();
    assert.equal(
      shell.runCommand('mini:open-inspector'),
      'mini:open-inspector',
    );
    assert.equal(shell.miniAction, 'inspector');
    shell.openPalette();
    assert.equal(shell.runCommand('mini:open-bar'), 'mini:open-bar');
    assert.equal(shell.miniAction, 'bar');
    shell.openPalette();
    assert.equal(shell.runCommand('mini:middle-left'), 'mini:middle-left');
    assert.equal(shell.paletteOpen, false);
  });

  it('resets the mini action to the server default', () => {
    const store = {};
    const storage = {
      getItem: (key) => (key in store ? store[key] : null),
      setItem: (key, value) => {
        store[key] = String(value);
      },
    };
    const browser = {
      storage,
      matchMedia: () => ({ matches: false }),
      activeElement: () => null,
      afterPaint: (callback) => callback(),
      schedule: (callback) => {
        callback();

        return 1;
      },
      cancelSchedule: () => {},
    };
    const shell = createShell(
      {
        id: 'req-1',
        mini_action: 'bar',
        inspectors: [{ key: 'request', label: 'Request', active: true }],
      },
      { runtime: browser, fetchClient: async () => null },
    );
    shell.setMiniAction('inspector');
    assert.equal(shell.miniAction, 'inspector');
    shell.openPalette();
    assert.equal(
      shell.runCommand('mini:open-server-default'),
      'mini:open-server-default',
    );
    assert.equal(shell.miniAction, 'bar');
    assert.equal(shell.miniActionServer, 'bar');
    const saved = JSON.parse(store['debugbar.preferences.v1']);
    assert.equal(saved.miniAction, 'bar');
    assert.equal(saved.miniActionServer, 'bar');
  });

  it('keeps the pinned action while the server value is unchanged', () => {
    const store = {
      'debugbar.preferences.v1': JSON.stringify({
        miniAction: 'inspector',
        miniActionServer: 'bar',
      }),
    };
    const storage = {
      getItem: (key) => (key in store ? store[key] : null),
      setItem: (key, value) => {
        store[key] = String(value);
      },
    };
    const browser = {
      storage,
      matchMedia: () => ({ matches: false }),
      activeElement: () => null,
      afterPaint: (callback) => callback(),
      schedule: (callback) => {
        callback();

        return 1;
      },
      cancelSchedule: () => {},
    };
    const shell = createShell(
      {
        id: 'req-1',
        mini_action: 'bar',
        inspectors: [{ key: 'request', label: 'Request', active: true }],
      },
      { runtime: browser, fetchClient: async () => null },
    );
    assert.equal(shell.restorePreferences(), true);
    assert.equal(shell.miniAction, 'inspector');
    assert.equal(shell.miniActionServer, 'bar');
  });

  it('drops the pinned action when the server value changes', () => {
    const store = {
      'debugbar.preferences.v1': JSON.stringify({
        miniAction: 'bar',
        miniActionServer: 'bar',
      }),
    };
    const storage = {
      getItem: (key) => (key in store ? store[key] : null),
      setItem: (key, value) => {
        store[key] = String(value);
      },
    };
    const browser = {
      storage,
      matchMedia: () => ({ matches: false }),
      activeElement: () => null,
      afterPaint: (callback) => callback(),
      schedule: (callback) => {
        callback();

        return 1;
      },
      cancelSchedule: () => {},
    };
    const shell = createShell(
      {
        id: 'req-1',
        mini_action: 'inspector',
        inspectors: [{ key: 'request', label: 'Request', active: true }],
      },
      { runtime: browser, fetchClient: async () => null },
    );
    assert.equal(shell.restorePreferences(), true);
    assert.equal(shell.miniAction, 'inspector');
    assert.equal(shell.miniActionServer, 'inspector');
  });
});
