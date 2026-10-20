import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { createShell } from '../../../webroot/js/state.js';
import {
  createPalettePart,
  buildPaletteCommands,
  countHiddenCommands,
  filterPaletteCommands,
  paletteNextIndex,
  parsePaletteCommand,
  inspectorCommandHint,
  inspectorCommandPriority,
  PALETTE_SHOW_OTHERS_ID,
} from '../../../webroot/js/shell/palette.js';

const INSPECTORS = [
  { key: 'request', label: 'Request', active: true },
  { key: 'queries', label: 'Queries', active: true, attention: true },
  { key: 'cache', label: 'Cache', active: false },
];

function fakeBrowser(overrides = {}) {
  return {
    activeElement: () => null,
    afterPaint: (callback) => callback(),
    schedule: (callback) => {
      callback();

      return 1;
    },
    cancelSchedule: () => {},
    ...overrides,
  };
}

function shellWith(summaryExtras = {}) {
  return createShell(
    { id: 'req-1', inspectors: INSPECTORS, ...summaryExtras },
    { runtime: fakeBrowser(), fetchClient: async () => null },
  );
}

describe('palette helpers', () => {
  it('hints and priorities inspectors by state', () => {
    assert.equal(
      inspectorCommandHint({ active: true, attention: true }),
      'Needs attention',
    );
    assert.equal(inspectorCommandHint({ active: false }), 'Other');
    assert.equal(inspectorCommandHint({ active: true }), 'Active');
    assert.equal(inspectorCommandPriority('Needs attention'), 0);
    assert.equal(inspectorCommandPriority('Other'), 2);
    assert.equal(inspectorCommandPriority('Active'), 1);
    assert.equal(inspectorCommandPriority('Theme'), 1);
  });

  it('builds priority-sorted commands with theme and toolbar pins', () => {
    const commands = buildPaletteCommands(INSPECTORS);
    assert.deepEqual(
      commands.slice(0, 3).map((command) => command.id),
      ['inspector:queries', 'inspector:request', 'inspector:cache'],
    );
    assert.deepEqual(
      commands.slice(3, 6).map((command) => command.id),
      ['theme:system', 'theme:light', 'theme:dark'],
    );
    assert.equal(
      commands.filter((command) => command.hint === 'Toolbar').length,
      6,
    );
    assert.ok(commands.some((command) => command.id === 'toolbar:bottom-left'));
    assert.deepEqual(buildPaletteCommands(null), buildPaletteCommands([]));
    assert.deepEqual(
      buildPaletteCommands([{ label: 'No key' }, { key: '' }]).filter(
        (command) => command.id.startsWith('inspector:'),
      ),
      [],
    );
  });

  it('counts hidden commands and filters with word-AND', () => {
    const commands = buildPaletteCommands(INSPECTORS);
    assert.equal(countHiddenCommands(commands), 1);
    assert.equal(countHiddenCommands([]), 0);
    assert.equal(countHiddenCommands(null), 0);

    const collapsed = filterPaletteCommands(commands, '');
    assert.ok(!collapsed.some((command) => command.id === 'inspector:cache'));
    const pseudo = collapsed.find(
      (command) => command.id === PALETTE_SHOW_OTHERS_ID,
    );
    assert.equal(pseudo.hint, '1 hidden');

    assert.deepEqual(
      filterPaletteCommands(commands, '', true).length,
      commands.length,
    );
    assert.deepEqual(
      filterPaletteCommands(commands, 'go queries').map(
        (command) => command.id,
      ),
      ['inspector:queries'],
    );
    assert.deepEqual(
      filterPaletteCommands(commands, 'GO  QUERIES').map(
        (command) => command.id,
      ),
      ['inspector:queries'],
    );
    assert.deepEqual(filterPaletteCommands(commands, 'queries theme'), []);

    const busy = buildPaletteCommands([
      { key: 'a', label: 'A', active: true },
      { key: 'b', label: 'B', active: true },
    ]);
    assert.ok(
      !filterPaletteCommands(busy, '').some(
        (command) => command.id === PALETTE_SHOW_OTHERS_ID,
      ),
    );
  });

  it('wraps the palette index', () => {
    assert.equal(paletteNextIndex(0, 1, 3), 1);
    assert.equal(paletteNextIndex(2, 1, 3), 0);
    assert.equal(paletteNextIndex(0, -1, 3), 2);
    assert.equal(paletteNextIndex(null, 1, 3), 1);
    assert.equal(paletteNextIndex(0, 1, 0), -1);
  });

  it('parses command ids', () => {
    assert.deepEqual(parsePaletteCommand('inspector:queries'), {
      kind: 'inspector',
      value: 'queries',
    });
    assert.deepEqual(parsePaletteCommand('theme:dark'), {
      kind: 'theme',
      value: 'dark',
    });
    assert.deepEqual(parsePaletteCommand('plain'), {
      kind: 'plain',
      value: null,
    });
    assert.deepEqual(parsePaletteCommand(''), { kind: null, value: null });
    assert.deepEqual(parsePaletteCommand(null), { kind: null, value: null });
  });
});

describe('palette open and close', () => {
  it('opens, focuses and closes with focus restore', () => {
    const shell = shellWith();
    const focused = [];
    const search = { focus: () => focused.push('search') };
    const back = { focus: () => focused.push('back') };
    shell.browser.activeElement = () => back;

    assert.equal(shell.openPalette(search), true);
    assert.equal(shell.paletteOpen, true);
    assert.deepEqual(focused, ['search']);
    assert.equal(shell.paletteSearchElement, search);

    assert.equal(shell.closePalette(), true);
    assert.equal(shell.paletteOpen, false);
    assert.deepEqual(focused, ['search', 'back']);

    assert.equal(shell.closePalette(), false);
  });

  it('toggles and captures picker focus first', () => {
    const shell = shellWith();
    shell.requestPickerScope = 'header';
    shell.requestPickerReturnFocus = { focus: () => {} };
    assert.equal(shell.togglePalette(), true);
    assert.equal(shell.requestPickerScope, null);
    assert.equal(shell.togglePalette(), false);
    assert.equal(shell.paletteOpen, false);
  });

  it('stays closed when the bar is hidden', () => {
    const shell = shellWith();
    shell.barVisible = false;
    assert.equal(shell.openPalette(), false);
    assert.equal(shell.paletteOpen, false);
  });

  it('moves the selection with wraparound', () => {
    const shell = shellWith();
    shell.openPalette();
    assert.equal(shell.movePalette(1), 1);
    assert.equal(
      shell.paletteActiveCommand?.id,
      shell.filteredPaletteCommands[1].id,
    );
    assert.equal(shell.paletteCommandIndex(shell.paletteActiveCommand.id), 1);
    assert.equal(shell.paletteCommandIndex('missing'), -1);

    shell.paletteSearch = 'nothing matches this';
    assert.equal(shell.movePalette(1), -1);
    assert.equal(shell.paletteActiveCommand, null);
    assert.equal(shell.runActiveCommand(), null);
  });
});

describe('palette commands', () => {
  it('runs the active command', () => {
    const shell = shellWith();
    shell.openPalette();
    shell.paletteSearch = 'queries';
    const ran = shell.runActiveCommand();
    assert.equal(ran.id, 'inspector:queries');
    assert.equal(shell.paletteOpen, false);
    assert.equal(shell.selected, 'queries');
  });

  it('opens inspectors through the shell', () => {
    const shell = shellWith();
    shell.openPalette();
    assert.equal(shell.runCommand('inspector:queries'), 'inspector:queries');
    assert.equal(shell.selected, 'queries');
    assert.equal(shell.inspectorOpen, true);
  });

  it('switches themes and closes the palette', () => {
    const shell = shellWith();
    shell.openPalette();
    assert.equal(shell.runCommand('theme:dark'), 'theme:dark');
    assert.equal(shell.theme, 'dark');
    assert.equal(shell.paletteOpen, false);
  });

  it('pins the toolbar and closes the inspector', () => {
    const shell = shellWith();
    shell.openInspector('request');
    shell.openPalette();
    assert.equal(shell.runCommand('toolbar:top'), 'toolbar:top');
    assert.equal(shell.toolbarPlacement, 'top');
    assert.equal(shell.inspectorOpen, false);
  });

  it('reveals quiet inspectors without closing', () => {
    const shell = shellWith();
    shell.openPalette();
    const pseudo = shell.filteredPaletteCommands.find(
      (command) => command.id === PALETTE_SHOW_OTHERS_ID,
    );
    assert.ok(pseudo);
    assert.equal(
      shell.runCommand(PALETTE_SHOW_OTHERS_ID),
      PALETTE_SHOW_OTHERS_ID,
    );
    assert.equal(shell.paletteOpen, true);
    assert.equal(shell.paletteShowQuiet, true);
    assert.ok(
      shell.filteredPaletteCommands.some(
        (command) => command.id === 'inspector:cache',
      ),
    );
  });

  it('closes on unknown commands', () => {
    const shell = shellWith();
    shell.openPalette();
    assert.equal(shell.runCommand('nope'), 'nope');
    assert.equal(shell.paletteOpen, false);
  });

  it('tolerates missing shell hooks', () => {
    const part = createPalettePart({ browser: fakeBrowser() });
    part.openPalette();
    assert.equal(part.runCommand('inspector:queries'), 'inspector:queries');
    assert.equal(part.runCommand('theme:dark'), 'theme:dark');
    assert.equal(part.runCommand('toolbar:top'), 'toolbar:top');
    assert.equal(part.paletteOpen, false);
  });
});

describe('palette shortcuts', () => {
  it('toggles on Ctrl+Alt+P', () => {
    const shell = shellWith();
    const event = {
      ctrlKey: true,
      altKey: true,
      shiftKey: false,
      code: 'KeyP',
      key: 'p',
      preventDefault: () => {},
    };
    assert.equal(shell.handleShortcut(event), true);
    assert.equal(shell.paletteOpen, true);
    assert.equal(
      shell.handleShortcut({ ...event, defaultPrevented: true }),
      false,
    );
  });

  it('ignores the Firefox private-window chord', () => {
    const shell = shellWith();
    assert.equal(
      shell.handleShortcut({
        ctrlKey: true,
        shiftKey: true,
        key: 'P',
        preventDefault: () => {},
      }),
      false,
    );
    assert.equal(
      shell.handleShortcut({
        ctrlKey: true,
        altKey: true,
        shiftKey: true,
        code: 'KeyP',
        key: 'p',
        preventDefault: () => {},
      }),
      false,
    );
    assert.equal(shell.paletteOpen, false);
  });

  it('walks the Esc chain in order', () => {
    const shell = shellWith();
    shell.openPalette();
    assert.equal(shell.handleShortcut({ key: 'Escape' }), true);
    assert.equal(shell.paletteOpen, false);

    shell.openThemeMenu('toolbar');
    assert.equal(shell.handleShortcut({ key: 'Escape' }), true);
    assert.equal(shell.themeMenuScope, null);

    shell.recentRequests = [{ id: 'req-1' }, { id: 'req-2' }];
    shell.currentRequestId = 'req-1';
    shell.openRequestPicker('toolbar');
    assert.equal(shell.handleShortcut({ key: 'Escape' }), true);
    assert.equal(shell.requestPickerScope, null);

    shell.openInspector('request');
    assert.equal(shell.handleShortcut({ key: 'Escape' }), true);
    assert.equal(shell.inspectorOpen, false);

    assert.equal(shell.handleShortcut({ key: 'Escape' }), false);
    assert.equal(shell.handleShortcut({ key: 'a' }), false);
    shell.barVisible = false;
    assert.equal(shell.handleShortcut({ key: 'Escape' }), false);
  });
});

describe('palette shell composition', () => {
  it('registers the palette part in createShell', () => {
    const shell = createShell(
      { id: 'req-1', inspectors: INSPECTORS },
      { fetchClient: async () => null },
    );
    assert.equal(shell.paletteOpen, false);
    assert.equal(shell.paletteHiddenCount, 1);
    assert.equal(typeof shell.runCommand, 'function');
    assert.equal(typeof shell.handleShortcut, 'function');
  });
});
