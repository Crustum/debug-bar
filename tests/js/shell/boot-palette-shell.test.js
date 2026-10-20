import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  closePalette,
  handlePaletteClick,
  handlePaletteKey,
  initPaletteSearch,
  openPalette,
  renderPalette,
  runPaletteCommand,
} from '../../../webroot/js/shell/boot/palette.js';
import { FakeElement } from '../boot-fake-dom.js';

globalThis.Element ??= FakeElement;

function stubDocument() {
  const realDocument = globalThis.document;
  globalThis.document = {
    createElement: (tag) => new FakeElement(tag),
  };

  return function restore() {
    if (realDocument === undefined) {
      delete globalThis.document;
    } else {
      globalThis.document = realDocument;
    }
  };
}

function paletteCtx(commands = [], frame = 'bar') {
  const root = new FakeElement('div', { 'data-dk-frame': frame });
  const palette = new FakeElement('div', { 'data-dk-palette': '' });
  palette.setAttribute('hidden', '');
  const search = new FakeElement('input', { 'data-dk-palette-search': '' });
  search.focus = () => {};
  const list = new FakeElement('div', { 'data-dk-palette-list': '' });
  const empty = new FakeElement('div', { 'data-dk-palette-empty': '' });
  root.appendChild(palette);
  root.appendChild(search);
  root.appendChild(list);
  root.appendChild(empty);
  const shell = {
    openPalette: () => {},
    closePalette: () => {},
    filteredPaletteCommands: commands,
    paletteIndex: 0,
    paletteSearch: 'x',
    paletteShowQuiet: true,
  };
  const calls = [];
  const ctx = {
    root,
    shell,
    setFrame: (mode) => calls.push(['frame', mode]),
    openInspector: (key) => calls.push(['inspector', key]),
    closeInspector: (restore) => calls.push(['close', restore]),
    syncToolbarForm: () => calls.push(['form']),
  };

  return { ctx, root, palette, search, list, empty, shell, calls };
}

describe('palette open and close', () => {
  let restoreDocument;

  beforeEach(() => {
    restoreDocument = stubDocument();
  });

  afterEach(() => {
    restoreDocument();
  });

  it('opens the palette and resets the search', () => {
    const { ctx, palette, search, shell, calls } = paletteCtx([
      { id: 'inspector:request', label: 'Request', hint: 'r' },
    ]);

    openPalette(ctx);

    assert.equal(palette.hasAttribute('hidden'), false);
    assert.equal(search.value, '');
    assert.equal(shell.paletteSearch, '');
    assert.equal(shell.paletteIndex, 0);
    assert.deepEqual(calls[0], ['frame', 'palette']);
  });

  it('opens without list, search, or frame changes', () => {
    const root = new FakeElement('div', { 'data-dk-frame': 'full' });
    const palette = new FakeElement('div', { 'data-dk-palette': '' });
    root.appendChild(palette);
    const calls = [];
    const ctx = {
      root,
      shell: {
        openPalette: () => {
          throw new Error('gone');
        },
      },
      setFrame: (mode) => calls.push(mode),
    };
    openPalette(ctx);

    assert.equal(palette.hasAttribute('hidden'), false);
    assert.deepEqual(calls, []);
  });

  it('closes the palette and returns to the bar', () => {
    const { ctx, palette, calls } = paletteCtx();
    palette.removeAttribute('hidden');
    ctx.root.setAttribute('data-dk-frame', 'palette');
    closePalette(ctx);

    assert.equal(palette.hasAttribute('hidden'), true);
    assert.deepEqual(calls, [['frame', 'bar']]);
  });

  it('closes an already hidden palette', () => {
    const { ctx, palette } = paletteCtx();
    closePalette(ctx);

    assert.equal(palette.hasAttribute('hidden'), true);
  });

  it('tolerates shells that throw on close', () => {
    const { ctx } = paletteCtx();
    ctx.shell.closePalette = () => {
      throw new Error('gone');
    };

    assert.doesNotThrow(() => closePalette(ctx));
  });
});

describe('palette list rendering', () => {
  let restoreDocument;

  beforeEach(() => {
    restoreDocument = stubDocument();
  });

  afterEach(() => {
    restoreDocument();
  });

  it('renders commands with the active one marked', () => {
    const { ctx, list, empty } = paletteCtx([
      { id: 'inspector:request', label: 'Request', hint: 'R' },
      { id: 'theme:dark' },
    ]);
    ctx.shell.paletteIndex = 1;
    renderPalette(ctx);

    assert.equal(list.children.length, 2);
    assert.equal(list.children[1].getAttribute('aria-selected'), 'true');
    assert.equal(list.children[0].getAttribute('aria-selected'), 'false');
    assert.equal(empty.hasAttribute('hidden'), true);
  });

  it('shows the empty note without commands', () => {
    const { ctx, list, empty } = paletteCtx();
    renderPalette(ctx);

    assert.equal(list.children.length, 0);
    assert.equal(empty.hasAttribute('hidden'), false);
  });

  it('renders without an empty note', () => {
    const { ctx, list } = paletteCtx([{ id: 'theme:dark' }]);
    ctx.root.removeChild(ctx.root.querySelector('[data-dk-palette-empty]'));
    renderPalette(ctx);

    assert.equal(list.children.length, 1);
  });

  it('skips rendering without a list', () => {
    const { ctx } = paletteCtx();
    ctx.root.removeChild(ctx.root.querySelector('[data-dk-palette-list]'));

    assert.doesNotThrow(() => renderPalette(ctx));
  });

  it('tolerates shells that throw', () => {
    const { ctx } = paletteCtx();
    Object.defineProperty(ctx.shell, 'filteredPaletteCommands', {
      get() {
        throw new Error('gone');
      },
    });

    assert.doesNotThrow(() => renderPalette(ctx));
  });
});

describe('palette commands', () => {
  let restoreDocument;

  beforeEach(() => {
    restoreDocument = stubDocument();
  });

  afterEach(() => {
    restoreDocument();
  });

  it('opens inspectors from commands', () => {
    const { ctx, calls } = paletteCtx();

    runPaletteCommand(ctx, 'inspector:sql');

    assert.deepEqual(calls[0], ['inspector', 'sql']);
  });

  it('applies theme commands', () => {
    const { ctx, shell, calls } = paletteCtx();
    shell.setTheme = (value) => calls.push(['theme', value]);
    shell.applyTheme = () => calls.push(['applied']);

    runPaletteCommand(ctx, 'theme:dark');

    assert.deepEqual(calls[0], ['theme', 'dark']);
  });

  it('tolerates theme shells that throw', () => {
    const { ctx } = paletteCtx();
    ctx.shell.setTheme = () => {
      throw new Error('gone');
    };

    assert.doesNotThrow(() => runPaletteCommand(ctx, 'theme:dark'));
  });

  it('pins toolbars from commands', () => {
    const { ctx, calls } = paletteCtx();
    ctx.shell.pinToolbar = (value) => calls.push(['pin', value]);

    runPaletteCommand(ctx, 'toolbar:top');

    assert.deepEqual(calls[0], ['pin', 'top']);
    assert.ok(calls.some((call) => call[0] === 'form'));
  });

  it('tolerates pin shells that throw', () => {
    const { ctx } = paletteCtx();
    ctx.shell.pinToolbar = () => {
      throw new Error('gone');
    };

    assert.doesNotThrow(() => runPaletteCommand(ctx, 'toolbar:top'));
  });

  it('pins without a form sync hook', () => {
    const { ctx } = paletteCtx();
    delete ctx.syncToolbarForm;
    ctx.shell.pinToolbar = () => {};

    assert.doesNotThrow(() => runPaletteCommand(ctx, 'toolbar:top'));
  });

  it('expands quiet inspectors from commands', () => {
    const { ctx, shell } = paletteCtx();

    runPaletteCommand(ctx, 'inspectors:show');

    assert.equal(shell.paletteShowQuiet, true);
    assert.equal(shell.paletteIndex, 0);
  });

  it('tolerates quiet shells that throw', () => {
    const { ctx } = paletteCtx();
    Object.defineProperty(ctx.shell, 'paletteShowQuiet', {
      set() {
        throw new Error('gone');
      },
    });

    assert.doesNotThrow(() => runPaletteCommand(ctx, 'inspectors:show'));
  });

  it('closes the palette for unknown commands', () => {
    const { ctx, palette } = paletteCtx();
    palette.removeAttribute('hidden');

    runPaletteCommand(ctx, 'nope');

    assert.equal(palette.hasAttribute('hidden'), true);
  });

  it('routes palette button clicks', () => {
    const { ctx, palette } = paletteCtx();
    const opener = new FakeElement('button', {
      'data-dk-open-palette': '',
    });

    assert.equal(handlePaletteClick(opener, ctx), true);
    assert.equal(palette.hasAttribute('hidden'), false);

    const command = new FakeElement('button', {
      'data-dk-palette-command': 'nope',
    });

    assert.equal(handlePaletteClick(command, ctx), true);
    assert.equal(handlePaletteClick(new FakeElement('button'), ctx), false);
  });
});

describe('palette keyboard handling', () => {
  let restoreDocument;

  beforeEach(() => {
    restoreDocument = stubDocument();
  });

  afterEach(() => {
    restoreDocument();
  });

  it('moves the selection with arrows', () => {
    const { ctx, palette } = paletteCtx();
    palette.removeAttribute('hidden');
    const moves = [];
    ctx.shell.movePalette = (direction) => moves.push(direction);
    const event = {
      key: 'ArrowDown',
      target: palette,
      preventDefault: () => {},
    };

    assert.equal(handlePaletteKey(event, ctx), true);
    assert.deepEqual(moves, [1]);

    event.key = 'ArrowUp';
    assert.equal(handlePaletteKey(event, ctx), true);
    assert.deepEqual(moves, [1, -1]);
  });

  it('tolerates shells that throw on move', () => {
    const { ctx, palette } = paletteCtx();
    palette.removeAttribute('hidden');
    ctx.shell.movePalette = () => {
      throw new Error('gone');
    };

    assert.equal(
      handlePaletteKey(
        { key: 'ArrowDown', target: palette, preventDefault: () => {} },
        ctx,
      ),
      true,
    );
  });

  it('runs the active command on enter in search', () => {
    const { ctx, palette, search, calls } = paletteCtx();
    palette.removeAttribute('hidden');
    ctx.shell.paletteActiveCommand = { id: 'inspector:request' };

    assert.equal(
      handlePaletteKey(
        { key: 'Enter', target: search, preventDefault: () => {} },
        ctx,
      ),
      true,
    );
    assert.deepEqual(calls[0], ['inspector', 'request']);
  });

  it('ignores enter without an active command', () => {
    const { ctx, palette, search } = paletteCtx();
    palette.removeAttribute('hidden');

    assert.equal(
      handlePaletteKey(
        { key: 'Enter', target: search, preventDefault: () => {} },
        ctx,
      ),
      true,
    );
  });

  it('tolerates shells that throw on active command', () => {
    const { ctx, palette, search } = paletteCtx();
    palette.removeAttribute('hidden');
    Object.defineProperty(ctx.shell, 'paletteActiveCommand', {
      get() {
        throw new Error('gone');
      },
    });

    assert.equal(
      handlePaletteKey(
        { key: 'Enter', target: search, preventDefault: () => {} },
        ctx,
      ),
      true,
    );
  });

  it('ignores keys outside the palette', () => {
    const { ctx } = paletteCtx();

    assert.equal(
      handlePaletteKey(
        {
          key: 'ArrowDown',
          target: new FakeElement('div'),
          preventDefault: () => {},
        },
        ctx,
      ),
      false,
    );
    assert.equal(
      handlePaletteKey(
        { key: 'x', target: null, preventDefault: () => {} },
        ctx,
      ),
      false,
    );
  });
});

describe('palette search wiring', () => {
  let restoreDocument;

  beforeEach(() => {
    restoreDocument = stubDocument();
  });

  afterEach(() => {
    restoreDocument();
  });

  it('filters on search input', () => {
    const { ctx, search, list } = paletteCtx([
      { id: 'inspector:request', label: 'Request', hint: '' },
    ]);
    initPaletteSearch(ctx);
    search.value = 'req';
    search.listeners.input[0]();

    assert.equal(ctx.shell.paletteSearch, 'req');
    assert.equal(list.children.length, 1);
  });

  it('tolerates shells that throw on search', () => {
    const { ctx, search } = paletteCtx();
    Object.defineProperty(ctx.shell, 'paletteSearch', {
      set() {
        throw new Error('gone');
      },
    });
    initPaletteSearch(ctx);
    search.value = 'req';

    assert.doesNotThrow(() => search.listeners.input[0]());
  });

  it('skips wiring without a search field', () => {
    const { ctx } = paletteCtx();
    ctx.root.removeChild(ctx.root.querySelector('[data-dk-palette-search]'));

    assert.doesNotThrow(() => initPaletteSearch(ctx));
  });
});
