import { notifyParent, setHidden } from './core.js';
import { syncAnchorPreview } from './drag.js';
import { closeAllPickers } from './picker.js';
import { sanitizeMiniAction, sanitizeMiniPosition } from '../mini.js';
import { parsePaletteCommand } from '../palette.js';

export function syncMiniToggle(ctx) {
  const mini = ctx.mini ?? ctx.root.querySelector('[data-dk-mini-toggle]');
  if (!mini || typeof mini.setAttribute !== 'function') {
    return;
  }
  let action = 'bar';
  try {
    action = sanitizeMiniAction(ctx.shell.miniAction);
  } catch (error) {
    action = sanitizeMiniAction(ctx.root.getAttribute('data-dk-mini-action'));
  }
  const opensInspector = action === 'inspector';
  mini.setAttribute(
    'aria-label',
    opensInspector ? 'Open debug inspector' : 'Open debug toolbar',
  );
  mini.setAttribute(
    'title',
    opensInspector
      ? 'Open debug inspector (Control + Alt + P for the command palette)'
      : 'Open debug toolbar (Control + Alt + P for the command palette)',
  );
}

export function openPalette(ctx) {
  const palette = ctx.root.querySelector('[data-dk-palette]');
  const search = ctx.root.querySelector('[data-dk-palette-search]');
  closeAllPickers(ctx, false);
  try {
    ctx.shell.openPalette(search);
  } catch (error) {}
  setHidden(palette, false);
  if (search) {
    search.value = '';
  }
  try {
    ctx.shell.paletteSearch = '';
    ctx.shell.paletteIndex = 0;
    ctx.shell.paletteShowQuiet = false;
  } catch (error) {}
  renderPalette(ctx);
  const frame = ctx.root.getAttribute('data-dk-frame');
  if (frame === 'bar' || frame === 'mini') {
    ctx.setFrame('palette');
    notifyParent('expand');
  }
  if (search && typeof search.focus === 'function') {
    search.focus();
  }
}

export function closePalette(ctx) {
  try {
    ctx.shell.closePalette(false);
  } catch (error) {}
  setHidden(ctx.root.querySelector('[data-dk-palette]'), true);
  if (ctx.root.getAttribute('data-dk-frame') === 'palette') {
    ctx.setFrame('bar');
    notifyParent('toolbar');
  }
}

export function renderPalette(ctx) {
  const list = ctx.root.querySelector('[data-dk-palette-list]');
  if (!list) {
    return;
  }
  let commands = [];
  try {
    commands = ctx.shell.filteredPaletteCommands || [];
  } catch (error) {
    commands = [];
  }
  let index = 0;
  try {
    index = ctx.shell.paletteIndex || 0;
  } catch (error) {
    index = 0;
  }
  while (list.firstChild) {
    list.removeChild(list.firstChild);
  }
  commands.forEach(function (command, position) {
    const item = document.createElement('button');
    item.type = 'button';
    item.setAttribute('role', 'option');
    item.setAttribute('data-dk-palette-command', command.id);
    item.setAttribute('aria-selected', position === index ? 'true' : 'false');
    item.className =
      'dk-menu-item dk-palette-command' +
      (position === index ? ' dk-inspector-active' : '');
    const label = document.createElement('span');
    label.className = 'dk-palette-command-label';
    label.textContent = command.label || command.id;
    const hint = document.createElement('span');
    hint.className = 'dk-palette-command-hint';
    hint.textContent = command.hint || '';
    item.appendChild(label);
    item.appendChild(hint);
    list.appendChild(item);
  });
  const empty = ctx.root.querySelector('[data-dk-palette-empty]');
  if (empty) {
    setHidden(empty, commands.length !== 0);
  }
}

export function runPaletteCommand(ctx, id) {
  const parsed = parsePaletteCommand(id);
  const kind = parsed.kind || '';
  const value = parsed.value ?? '';
  if (kind === 'inspector' && value !== '') {
    closePalette(ctx);
    ctx.openInspector(value);
  } else if (kind === 'theme' && value !== '') {
    try {
      ctx.shell.setTheme(value);
      ctx.shell.applyTheme(ctx.root);
    } catch (error) {}
    closePalette(ctx);
  } else if (kind === 'toolbar' && value !== '') {
    try {
      ctx.shell.pinToolbar(value);
    } catch (error) {}
    syncAnchorPreview(ctx);
    if (typeof ctx.syncToolbarForm === 'function') {
      ctx.syncToolbarForm();
    }
    closePalette(ctx);
    ctx.closeInspector(false);
  } else if (kind === 'inspectors' && value === 'show') {
    try {
      ctx.shell.paletteShowQuiet = true;
      ctx.shell.paletteIndex = 0;
    } catch (error) {}
    renderPalette(ctx);
  } else if (kind === 'mini' && value !== '') {
    if (value === 'open-bar' || value === 'open-inspector') {
      try {
        ctx.shell.setMiniAction(
          value === 'open-inspector' ? 'inspector' : 'bar',
        );
      } catch (error) {}
      syncMiniToggle(ctx);
      closePalette(ctx);
    } else if (value === 'open-server-default') {
      let serverDefault = null;
      try {
        serverDefault = ctx.root.getAttribute('data-dk-mini-action');
      } catch (error) {}
      try {
        if (typeof ctx.shell.resetMiniAction === 'function') {
          ctx.shell.resetMiniAction(serverDefault);
        } else {
          ctx.shell.setMiniAction(serverDefault);
        }
      } catch (error) {}
      syncMiniToggle(ctx);
      closePalette(ctx);
    } else if (value === 'server-default') {
      notifyParent('mini-position$$server-default');
      closePalette(ctx);
    } else {
      const position = sanitizeMiniPosition(value);
      notifyParent(`mini-position$$${position}`);
      closePalette(ctx);
    }
  } else {
    closePalette(ctx);
  }
}

export function handlePaletteMessage(event, ctx) {
  let sameOrigin = false;
  try {
    sameOrigin = !!event && event.origin === window.location.origin;
  } catch (error) {
    sameOrigin = false;
  }
  if (sameOrigin !== true) {
    return false;
  }
  let fromParent = false;
  try {
    fromParent = !!event && event.source === window.parent;
  } catch (error) {
    fromParent = false;
  }
  if (fromParent !== true) {
    return false;
  }
  if (!event || event.data !== 'open-palette') {
    return false;
  }
  const palette = ctx.root.querySelector('[data-dk-palette]');
  if (palette && !palette.hasAttribute('hidden')) {
    closePalette(ctx);
  } else {
    openPalette(ctx);
  }

  return true;
}

export function handlePaletteClick(target, ctx) {
  if (target.hasAttribute('data-dk-open-palette')) {
    openPalette(ctx);

    return true;
  }
  if (target.hasAttribute('data-dk-palette-command')) {
    runPaletteCommand(ctx, target.getAttribute('data-dk-palette-command'));

    return true;
  }

  return false;
}

export function handlePaletteKey(event, ctx) {
  const paletteDialog = ctx.root.querySelector('[data-dk-palette]');
  if (!paletteDialog || paletteDialog.hasAttribute('hidden')) {
    return false;
  }
  const inPalette =
    event.target instanceof Element && paletteDialog.contains(event.target);
  if (inPalette && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
    event.preventDefault();
    try {
      ctx.shell.movePalette(event.key === 'ArrowDown' ? 1 : -1);
    } catch (error) {}
    renderPalette(ctx);

    return true;
  }
  if (
    event.key === 'Enter' &&
    event.target instanceof Element &&
    event.target.closest('[data-dk-palette-search]')
  ) {
    event.preventDefault();
    let active = null;
    try {
      active = ctx.shell.paletteActiveCommand || null;
    } catch (error) {
      active = null;
    }
    if (active && active.id) {
      runPaletteCommand(ctx, active.id);
    }

    return true;
  }

  return false;
}

export function initPaletteSearch(ctx) {
  const paletteSearchInput = ctx.root.querySelector('[data-dk-palette-search]');
  if (paletteSearchInput) {
    paletteSearchInput.addEventListener('input', function () {
      try {
        ctx.shell.paletteSearch = paletteSearchInput.value || '';
        ctx.shell.paletteIndex = 0;
      } catch (error) {}
      renderPalette(ctx);
    });
  }
}
