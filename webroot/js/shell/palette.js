import { DK_TOOLBAR_PLACEMENTS } from '../runtime.js';
import { buildMiniCommands } from './mini.js';

export const PALETTE_SHOW_OTHERS_ID = 'inspectors:show';

export function inspectorCommandHint(inspector) {
  if (inspector?.attention === true) return 'Needs attention';
  if (inspector?.active === false) return 'Other';

  return 'Active';
}

export function inspectorCommandPriority(hint) {
  if (hint === 'Needs attention') return 0;
  if (hint === 'Other') return 2;

  return 1;
}

export function buildPaletteCommands(inspectors = [], favorites = []) {
  const list = Array.isArray(inspectors) ? inspectors : [];
  const favoriteOrder = new Map();
  (Array.isArray(favorites) ? favorites : []).forEach((key, index) => {
    if (typeof key === 'string' && key !== '' && !favoriteOrder.has(key)) {
      favoriteOrder.set(key, index);
    }
  });
  const jumps = list
    .filter(
      (inspector) => typeof inspector?.key === 'string' && inspector.key !== '',
    )
    .map((inspector) => {
      const hint = inspectorCommandHint(inspector);

      return {
        id: `inspector:${inspector.key}`,
        key: inspector.key,
        label: `Go to ${inspector.label ?? inspector.key}`,
        hint,
        priority: inspectorCommandPriority(hint),
        starred: favoriteOrder.has(inspector.key),
      };
    })
    .sort((left, right) => {
      const leftStarred = left.starred === true ? 0 : 1;
      const rightStarred = right.starred === true ? 0 : 1;
      if (leftStarred !== rightStarred) return leftStarred - rightStarred;
      if (leftStarred === 0) {
        return (
          (favoriteOrder.get(left.key) ?? 0) -
          (favoriteOrder.get(right.key) ?? 0)
        );
      }

      return (
        left.priority - right.priority || left.label.localeCompare(right.label)
      );
    });

  return [
    ...jumps,
    { id: 'theme:system', label: 'Use system theme', hint: 'Theme' },
    { id: 'theme:light', label: 'Use light theme', hint: 'Theme' },
    { id: 'theme:dark', label: 'Use dark theme', hint: 'Theme' },
    ...DK_TOOLBAR_PLACEMENTS.map((placement) => ({
      id: `toolbar:${placement}`,
      label: `Pin to ${placement.replaceAll('-', ' ')}`,
      hint: 'Toolbar',
    })),
    ...buildMiniCommands(),
  ];
}

export function countHiddenCommands(commands = []) {
  return (Array.isArray(commands) ? commands : []).filter(
    (command) => command?.hint === 'Other' && command?.starred !== true,
  ).length;
}

export function filterPaletteCommands(
  commands = [],
  search = '',
  showQuiet = false,
) {
  const all = Array.isArray(commands) ? commands : [];
  const words = String(search ?? '')
    .toLowerCase()
    .trim()
    .split(/\s+/)
    .filter(Boolean);

  if (words.length === 0 && showQuiet !== true) {
    const active = all.filter(
      (command) => command?.hint !== 'Other' || command?.starred === true,
    );
    const hidden = countHiddenCommands(all);
    if (hidden === 0) return active;

    return [
      ...active,
      {
        id: PALETTE_SHOW_OTHERS_ID,
        label: 'Show other inspectors',
        hint: `${hidden} hidden`,
      },
    ];
  }

  if (words.length === 0) return [...all];

  return all.filter((command) => {
    const value =
      `${command?.label ?? ''} ${command?.hint ?? ''}`.toLowerCase();

    return words.every((word) => value.includes(word));
  });
}

export function paletteNextIndex(index, direction, length) {
  if (!Number.isInteger(length) || length <= 0) return -1;
  const current = Number.isInteger(index) && index >= 0 ? index : 0;

  return (((current + direction) % length) + length) % length;
}

export function parsePaletteCommand(id) {
  if (typeof id !== 'string' || id === '') return { kind: null, value: null };
  const separator = id.indexOf(':');
  if (separator === -1) return { kind: id, value: null };

  return { kind: id.slice(0, separator), value: id.slice(separator + 1) };
}

export function createPalettePart(context = {}) {
  const browser = context.browser ?? {};
  const summary = context.summary ?? {};

  const readBrowser = (shell) =>
    shell?.browser && typeof shell.browser === 'object'
      ? shell.browser
      : browser;
  const readSummary = (shell) => shell?.summary ?? summary;

  return {
    paletteOpen: false,
    paletteSearch: '',
    paletteIndex: 0,
    paletteShowQuiet: false,
    paletteReturnFocus: null,
    paletteSearchElement: null,

    get paletteCommands() {
      return buildPaletteCommands(
        readSummary(this)?.inspectors ?? [],
        this.favorites ?? [],
      );
    },

    get paletteHiddenCount() {
      return countHiddenCommands(this.paletteCommands);
    },

    get filteredPaletteCommands() {
      return filterPaletteCommands(
        this.paletteCommands,
        this.paletteSearch,
        this.paletteShowQuiet,
      );
    },

    get paletteActiveCommand() {
      return this.filteredPaletteCommands[this.paletteIndex] ?? null;
    },

    openPalette(searchElement = null) {
      if ((this.barVisible ?? true) === false) return false;
      this.paletteReturnFocus = this.requestPickerScope
        ? this.requestPickerReturnFocus
        : this.themeMenuScope
          ? this.themeMenuReturnFocus
          : (readBrowser(this).activeElement?.() ?? null);
      if (typeof this.closeRequestPicker === 'function')
        this.closeRequestPicker(false);
      if (typeof this.closeThemeMenu === 'function') this.closeThemeMenu(false);
      this.paletteOpen = true;
      this.paletteSearch = '';
      this.paletteIndex = 0;
      this.paletteShowQuiet = false;
      if (searchElement !== null && searchElement !== undefined) {
        this.paletteSearchElement = searchElement;
      }
      const shell = this;
      const focus = () => shell.paletteSearchElement?.focus?.();
      const shellBrowser = readBrowser(this);
      if (typeof shellBrowser.afterPaint === 'function')
        shellBrowser.afterPaint(focus);
      else focus();

      return true;
    },

    closePalette(restoreFocus = true) {
      const returnFocus = this.paletteReturnFocus;
      const wasOpen = this.paletteOpen === true;
      this.paletteOpen = false;
      this.paletteSearch = '';
      this.paletteIndex = 0;
      this.paletteShowQuiet = false;
      this.paletteReturnFocus = null;
      if (restoreFocus === true) {
        const shellBrowser = readBrowser(this);
        const focus = () => returnFocus?.focus?.();
        if (typeof shellBrowser.afterPaint === 'function')
          shellBrowser.afterPaint(focus);
        else focus();
      }

      return wasOpen;
    },

    togglePalette(searchElement = null) {
      if (this.paletteOpen === true) {
        this.closePalette();

        return false;
      }

      return this.openPalette(searchElement);
    },

    movePalette(direction) {
      const count = this.filteredPaletteCommands.length;
      if (count === 0) return -1;
      this.paletteIndex = paletteNextIndex(
        this.paletteIndex ?? 0,
        direction,
        count,
      );

      return this.paletteIndex;
    },

    paletteCommandIndex(id) {
      return this.filteredPaletteCommands.findIndex(
        (command) => command?.id === id,
      );
    },

    runActiveCommand() {
      const command = this.paletteActiveCommand;
      if (!command) return null;
      this.runCommand(command.id);

      return command;
    },

    runCommand(id) {
      const { kind, value } = parsePaletteCommand(id);

      if (kind === 'inspector') {
        const returnFocus = this.paletteReturnFocus;
        this.closePalette(false);
        if (typeof this.openInspector === 'function')
          this.openInspector(value, returnFocus);

        return id;
      }

      if (kind === 'theme') {
        if (typeof this.setTheme === 'function') this.setTheme(value);
        this.closePalette();

        return id;
      }

      if (kind === 'toolbar') {
        if (typeof this.pinToolbar === 'function') this.pinToolbar(value);
        this.closePalette(this.inspectorOpen !== true);
        if (typeof this.closeInspector === 'function') this.closeInspector();

        return id;
      }

      if (kind === 'mini') {
        if (
          value === 'open-bar' ||
          value === 'open-inspector' ||
          value === 'open-server-default'
        ) {
          if (value === 'open-server-default') {
            const serverDefault =
              readSummary(this)?.mini_action ??
              readSummary(this)?.miniAction ??
              null;
            if (typeof this.resetMiniAction === 'function') {
              this.resetMiniAction(serverDefault);
            } else if (typeof this.setMiniAction === 'function') {
              this.setMiniAction(serverDefault);
            }
          } else {
            const action = value === 'open-inspector' ? 'inspector' : 'bar';
            if (typeof this.setMiniAction === 'function')
              this.setMiniAction(action);
          }
          if (typeof this.syncMiniToggle === 'function') this.syncMiniToggle();
          this.closePalette();

          return id;
        }
        if (typeof this.requestMiniPosition === 'function') {
          this.requestMiniPosition(value);
        }
        this.closePalette();

        return id;
      }

      if (kind === 'inspectors' && value === 'show') {
        this.paletteShowQuiet = true;
        this.paletteIndex = 0;

        return id;
      }

      this.closePalette();

      return id;
    },

    handleShortcut(event = {}) {
      if ((this.barVisible ?? true) === false) return false;
      if (event.defaultPrevented === true) return false;

      const key = typeof event.key === 'string' ? event.key.toLowerCase() : '';
      if (
        (event.metaKey === true || event.ctrlKey === true) &&
        event.altKey === true &&
        event.shiftKey !== true &&
        (event.code === 'KeyP' || key === 'p')
      ) {
        event.preventDefault?.();
        this.togglePalette();

        return true;
      }

      if (event.key === 'Escape') {
        if (this.paletteOpen === true) this.closePalette();
        else if (this.themeMenuScope) {
          if (typeof this.closeThemeMenu === 'function') this.closeThemeMenu();
        } else if (this.requestPickerScope) {
          if (typeof this.closeRequestPicker === 'function')
            this.closeRequestPicker();
        } else if (this.inspectorOpen === true) {
          if (typeof this.closeInspector === 'function') this.closeInspector();
        } else {
          return false;
        }

        return true;
      }

      return false;
    },
  };
}
