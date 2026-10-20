import { DK_TOOLBAR_PLACEMENTS } from '../runtime.js';
import { sanitizeMiniAction } from './mini.js';

export const PREFERENCES_STORAGE_KEY = 'debugbar.preferences.v1';
export const PREFERENCE_THEMES = ['system', 'light', 'dark'];
export const PREFERENCE_DARK_QUERY = '(prefers-color-scheme: dark)';
export const THEME_MENU_SCOPES = ['toolbar', 'header'];
export const THEME_OPTION_ATTRIBUTE = 'data-dk-theme-option';

export function sanitizeTheme(theme, fallback = 'system') {
  if (PREFERENCE_THEMES.includes(theme)) return theme;
  if (PREFERENCE_THEMES.includes(fallback)) return fallback;

  return 'system';
}

export function sanitizePreferenceKeys(list, knownKeys = null) {
  if (!Array.isArray(list)) return [];
  const known = Array.isArray(knownKeys) ? new Set(knownKeys) : null;
  const seen = new Set();
  const cleaned = [];
  for (const key of list) {
    if (typeof key !== 'string' || key === '' || seen.has(key)) continue;
    if (known !== null && !known.has(key)) continue;
    seen.add(key);
    cleaned.push(key);
  }

  return cleaned;
}

export function resolveTheme(theme, matchesDark = false) {
  if (theme === 'system') return matchesDark === true ? 'dark' : 'light';

  return sanitizeTheme(theme, 'light');
}

export function themeOptionId(option) {
  if (!option) return null;
  const datasetId = option.dataset?.dkThemeOption;
  if (typeof datasetId === 'string' && datasetId !== '') return datasetId;
  const attribute =
    typeof option.getAttribute === 'function'
      ? (option.getAttribute(THEME_OPTION_ATTRIBUTE) ?? null)
      : null;

  return typeof attribute === 'string' && attribute !== '' ? attribute : null;
}

export function createPreferencesPart(context = {}) {
  const browser = context.browser ?? {};
  const summary = context.summary ?? {};

  const readBrowser = (shell) =>
    shell?.browser && typeof shell.browser === 'object'
      ? shell.browser
      : browser;
  const readKeys = (shell) =>
    Array.isArray(shell?.inspectorKeys) ? shell.inspectorKeys : [];
  const readStorage = (shell) => readBrowser(shell).storage ?? null;

  return {
    themeMenuScope: null,
    themeMenuReturnFocus: null,
    theme: sanitizeTheme(summary?.theme),
    resolvedTheme: 'light',
    miniAction: sanitizeMiniAction(summary?.mini_action ?? summary?.miniAction),
    miniActionServer: sanitizeMiniAction(
      summary?.mini_action ?? summary?.miniAction,
    ),

    restorePreferences() {
      const storage = readStorage(this);
      let saved = null;
      try {
        saved = JSON.parse(storage?.getItem?.(PREFERENCES_STORAGE_KEY) ?? '{}');
      } catch {
        return false;
      }
      if (!saved || typeof saved !== 'object') return false;

      if (PREFERENCE_THEMES.includes(saved.theme)) this.theme = saved.theme;
      const serverAction = sanitizeMiniAction(
        this.miniActionServer ??
          sanitizeMiniAction(summary?.mini_action ?? summary?.miniAction),
      );
      if (
        typeof saved.miniAction === 'string' &&
        sanitizeMiniAction(saved.miniActionServer) === serverAction
      ) {
        this.miniAction = sanitizeMiniAction(saved.miniAction);
      } else {
        this.miniAction = serverAction;
      }
      this.miniActionServer = serverAction;
      if (DK_TOOLBAR_PLACEMENTS.includes(saved.toolbarAnchor)) {
        this.toolbarPreferredPlacement = saved.toolbarAnchor;
        this.toolbarPlacement = saved.toolbarAnchor;
        this.toolbarDragTarget = saved.toolbarAnchor;
        this.toolbarDragOriginPlacement = saved.toolbarAnchor;
      }
      const keys = readKeys(this);
      for (const preference of ['favorites', 'inspectorOrder']) {
        if (Array.isArray(saved[preference])) {
          this[preference] = sanitizePreferenceKeys(saved[preference], keys);
        }
      }
      this.applyTheme();

      return true;
    },

    persistPreferences() {
      try {
        readStorage(this)?.setItem?.(
          PREFERENCES_STORAGE_KEY,
          JSON.stringify({
            theme: this.theme,
            toolbarAnchor: this.toolbarPreferredPlacement,
            favorites: this.favorites,
            inspectorOrder: this.inspectorOrder,
            miniAction: sanitizeMiniAction(this.miniAction),
            miniActionServer: sanitizeMiniAction(this.miniActionServer),
          }),
        );
      } catch {
        return false;
      }

      return true;
    },

    setMiniAction(action) {
      const next = sanitizeMiniAction(action);
      this.miniAction = next;
      this.persistPreferences();

      return next;
    },

    resetMiniAction(serverDefault = null) {
      const next = sanitizeMiniAction(serverDefault);
      this.miniAction = next;
      this.miniActionServer = next;
      this.persistPreferences();

      return next;
    },

    toggleFavorite(key) {
      const keys = readKeys(this);
      if (keys.length > 0 && !keys.includes(key))
        return [...(this.favorites ?? [])];
      const favorites = [...(this.favorites ?? [])];
      const index = favorites.indexOf(key);
      if (index === -1) favorites.push(key);
      else favorites.splice(index, 1);
      this.favorites = favorites;
      this.persistPreferences();

      return favorites;
    },

    moveInspector(key, direction) {
      const peers = this.preferencePeers(key);
      const index = peers.findIndex((inspector) => inspector?.key === key);
      if (index === -1) return false;

      return this.sortInspector(key, index + direction);
    },

    sortInspector(key, position) {
      const peers = this.preferencePeers(key);
      const index = peers.findIndex((inspector) => inspector?.key === key);
      const target = peers[position]?.key;
      if (index === -1 || typeof target !== 'string' || target === key)
        return false;

      const preference =
        this.isFavorite?.(key) === true ? 'favorites' : 'inspectorOrder';
      const scope = this.inspectorsInOrder ?? this.orderedInspectors ?? [];
      const order =
        preference === 'favorites'
          ? [...(this.favorites ?? [])]
          : scope.map((inspector) => inspector?.key);
      const reordered = order.filter((candidate) => candidate !== key);
      reordered.splice(
        reordered.indexOf(target) + (position > index ? 1 : 0),
        0,
        key,
      );
      this[preference] = reordered;
      this.persistPreferences();

      return true;
    },

    preferencePeers(key) {
      if (typeof this.navigationInspectors === 'function') {
        return this.navigationInspectors(this.isFavorite?.(key) === true);
      }

      return (this.orderedInspectors ?? []).filter(
        (inspector) =>
          (this.isFavorite?.(inspector?.key) ?? false) ===
          (this.isFavorite?.(key) ?? false),
      );
    },

    setTheme(theme) {
      if (!PREFERENCE_THEMES.includes(theme)) return this.theme;
      this.theme = theme;
      this.applyTheme();
      this.persistPreferences();

      return theme;
    },

    applyTheme(root = null) {
      let matchesDark = false;
      try {
        matchesDark =
          readBrowser(this).matchMedia?.(PREFERENCE_DARK_QUERY)?.matches ===
          true;
      } catch {
        matchesDark = false;
      }
      this.resolvedTheme = resolveTheme(this.theme, matchesDark);
      if (root && typeof root === 'object') {
        if (root.dataset && typeof root.dataset === 'object') {
          root.dataset.dkTheme = this.resolvedTheme;
        } else if (typeof root.setAttribute === 'function') {
          root.setAttribute('data-dk-theme', this.resolvedTheme);
        }
      }

      return this.resolvedTheme;
    },

    toggleThemeMenu(scope, returnFocus = null, menu = null) {
      if (this.themeMenuScope === scope) {
        this.closeThemeMenu();

        return null;
      }

      return this.openThemeMenu(scope, returnFocus, menu);
    },

    openThemeMenu(scope, returnFocus = null, menu = null) {
      if (!THEME_MENU_SCOPES.includes(scope)) return null;
      if ((this.barVisible ?? true) === false) return null;
      if (scope === 'toolbar' && this.inspectorOpen === true) return null;
      if (scope === 'header' && this.inspectorOpen !== true) return null;

      if (typeof this.closeRequestPicker === 'function')
        this.closeRequestPicker(false);
      this.themeMenuReturnFocus =
        returnFocus ?? readBrowser(this).activeElement?.() ?? null;
      this.themeMenuScope = scope;
      const shell = this;
      const focus = () => {
        const options = [
          ...(menu?.querySelectorAll?.('[data-dk-theme-option]') ?? []),
        ];
        if (options.length === 0) return;
        const current = options.find(
          (option) => themeOptionId(option) === shell.theme,
        );
        (current ?? options[0])?.focus?.();
      };
      const shellBrowser = readBrowser(this);
      if (typeof shellBrowser.afterPaint === 'function')
        shellBrowser.afterPaint(focus);
      else focus();

      return scope;
    },

    closeThemeMenu(restoreFocus = true) {
      if (this.themeMenuScope === null || this.themeMenuScope === undefined)
        return false;
      const returnFocus = this.themeMenuReturnFocus;
      this.themeMenuScope = null;
      this.themeMenuReturnFocus = null;
      if (restoreFocus === true) {
        const shellBrowser = readBrowser(this);
        const focus = () => returnFocus?.focus?.();
        if (typeof shellBrowser.afterPaint === 'function')
          shellBrowser.afterPaint(focus);
        else focus();
      }

      return true;
    },

    moveThemeMenu(direction, menu) {
      const options = [
        ...(menu?.querySelectorAll?.('[data-dk-theme-option]') ?? []),
      ];
      if (options.length === 0) return -1;
      const active = options.indexOf(readBrowser(this).activeElement?.());
      const selected = options.findIndex(
        (option) => themeOptionId(option) === this.theme,
      );
      const current = active >= 0 ? active : Math.max(0, selected);
      const next =
        (((current + direction) % options.length) + options.length) %
        options.length;
      options[next]?.focus?.();

      return next;
    },
  };
}
