import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { createShell } from '../../../webroot/js/state.js';
import {
  createPreferencesPart,
  sanitizeTheme,
  sanitizePreferenceKeys,
  resolveTheme,
  themeOptionId,
  PREFERENCES_STORAGE_KEY,
  PREFERENCE_THEMES,
} from '../../../webroot/js/shell/preferences.js';

const INSPECTORS = [
  { key: 'request', label: 'Request', active: true },
  { key: 'queries', label: 'Queries', active: true },
  { key: 'cache', label: 'Cache', active: false },
];

function memoryStorage(initial = {}, throws = false) {
  const store = { ...initial };

  return {
    getItem: (key) => {
      if (throws) throw new Error('denied');

      return key in store ? store[key] : null;
    },
    setItem: (key, value) => {
      if (throws) throw new Error('denied');
      store[key] = String(value);
    },
    store,
  };
}

function fakeBrowser(overrides = {}) {
  return {
    storage: memoryStorage(),
    matchMedia: () => ({ matches: false }),
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

function shellWith(storage, summaryExtras = {}) {
  return createShell(
    { id: 'req-1', inspectors: INSPECTORS, ...summaryExtras },
    { runtime: fakeBrowser({ storage }), fetchClient: async () => null },
  );
}

describe('preferences helpers', () => {
  it('exposes the storage key and theme list', () => {
    assert.equal(PREFERENCES_STORAGE_KEY, 'debugbar.preferences.v1');
    assert.deepEqual(PREFERENCE_THEMES, ['system', 'light', 'dark']);
  });

  it('sanitizes themes with a fallback', () => {
    assert.equal(sanitizeTheme('dark'), 'dark');
    assert.equal(sanitizeTheme('nope'), 'system');
    assert.equal(sanitizeTheme('nope', 'light'), 'light');
    assert.equal(sanitizeTheme('nope', 'nope'), 'system');
    assert.equal(sanitizeTheme(null), 'system');
  });

  it('sanitizes key lists against known inspectors', () => {
    assert.deepEqual(
      sanitizePreferenceKeys(['a', 'b', 'a', '', 42, null], ['a', 'b']),
      ['a', 'b'],
    );
    assert.deepEqual(sanitizePreferenceKeys(['a', 'missing'], ['a']), ['a']);
    assert.deepEqual(sanitizePreferenceKeys(['b', 'a'], null), ['b', 'a']);
    assert.deepEqual(sanitizePreferenceKeys('nope', ['a']), []);
    assert.deepEqual(sanitizePreferenceKeys(null), []);
  });

  it('resolves the system theme through matchMedia', () => {
    assert.equal(resolveTheme('system', true), 'dark');
    assert.equal(resolveTheme('system', false), 'light');
    assert.equal(resolveTheme('dark', false), 'dark');
    assert.equal(resolveTheme('nope', false), 'light');
  });

  it('reads theme option ids', () => {
    assert.equal(themeOptionId({ dataset: { dkThemeOption: 'dark' } }), 'dark');
    assert.equal(
      themeOptionId({ dataset: {}, getAttribute: () => 'light' }),
      'light',
    );
    assert.equal(themeOptionId({ dataset: {}, getAttribute: () => '' }), null);
    assert.equal(themeOptionId({}), null);
    assert.equal(themeOptionId(null), null);
  });
});

describe('preferences persist round-trip', () => {
  it('restores theme, anchor, favorites and order', () => {
    const storage = memoryStorage();
    const shell = shellWith(storage);
    shell.setTheme('dark');
    shell.moveInspector('queries', 1);
    shell.toggleFavorite('queries');
    shell.toolbarPreferredPlacement = 'top-left';
    assert.equal(shell.persistPreferences(), true);
    const saved = JSON.parse(storage.store[PREFERENCES_STORAGE_KEY]);
    assert.equal(saved.theme, 'dark');
    assert.equal(saved.toolbarAnchor, 'top-left');
    assert.deepEqual(saved.favorites, ['queries']);
    assert.deepEqual(saved.inspectorOrder, ['cache', 'request', 'queries']);

    const next = shellWith(storage);
    assert.equal(next.restorePreferences(), true);
    assert.equal(next.theme, 'dark');
    assert.equal(next.resolvedTheme, 'dark');
    assert.deepEqual(next.favorites, ['queries']);
    assert.deepEqual(next.inspectorOrder, ['cache', 'request', 'queries']);
    assert.equal(next.toolbarPlacement, 'top-left');
    assert.equal(next.toolbarPreferredPlacement, 'top-left');
  });

  it('seeds the theme from the summary', () => {
    const shell = shellWith(memoryStorage(), { theme: 'light' });
    assert.equal(shell.theme, 'light');
    const fallback = shellWith(memoryStorage(), { theme: 'nope' });
    assert.equal(fallback.theme, 'system');
  });

  it('drops unknown keys and anchors on restore', () => {
    const storage = memoryStorage({
      [PREFERENCES_STORAGE_KEY]: JSON.stringify({
        theme: 'nope',
        toolbarAnchor: 'nowhere',
        favorites: ['queries', 'ghost'],
        inspectorOrder: ['cache', 'ghost'],
      }),
    });
    const shell = shellWith(storage);
    assert.equal(shell.restorePreferences(), true);
    assert.equal(shell.theme, 'system');
    assert.equal(shell.toolbarPlacement, 'bottom');
    assert.deepEqual(shell.favorites, ['queries']);
    assert.deepEqual(shell.inspectorOrder, ['cache']);
  });

  it('keeps defaults on broken payloads', () => {
    const shell = shellWith(
      memoryStorage({ [PREFERENCES_STORAGE_KEY]: 'nope{' }),
    );
    assert.equal(shell.restorePreferences(), false);
    assert.equal(shell.theme, 'system');

    const empty = shellWith(memoryStorage());
    assert.equal(empty.restorePreferences(), true);
  });

  it('stays silent when storage throws', () => {
    const shell = shellWith(memoryStorage({}, true));
    assert.equal(shell.persistPreferences(), false);
    assert.equal(shell.restorePreferences(), false);
  });
});

describe('preferences part behavior', () => {
  it('toggles favorites with validation and persistence', () => {
    const storage = memoryStorage();
    const shell = shellWith(storage);
    assert.deepEqual(shell.toggleFavorite('queries'), ['queries']);
    assert.deepEqual(shell.toggleFavorite('ghost'), ['queries']);
    assert.deepEqual(shell.toggleFavorite('queries'), []);
    const saved = JSON.parse(storage.store[PREFERENCES_STORAGE_KEY]);
    assert.deepEqual(saved.favorites, []);
  });

  it('sets and applies themes', () => {
    const shell = shellWith(memoryStorage());
    assert.equal(shell.setTheme('nope'), 'system');
    assert.equal(shell.setTheme('light'), 'light');
    assert.equal(shell.resolvedTheme, 'light');

    const root = { dataset: {} };
    shell.browser.matchMedia = () => ({ matches: true });
    shell.setTheme('system');
    assert.equal(shell.resolvedTheme, 'dark');
    assert.equal(shell.applyTheme(root), 'dark');
    assert.equal(root.dataset.dkTheme, 'dark');

    const legacy = {
      setAttribute: (name, value) => {
        legacy[name] = value;
      },
    };
    assert.equal(shell.applyTheme(legacy), 'dark');
    assert.equal(legacy['data-dk-theme'], 'dark');
    assert.equal(shell.applyTheme(null), 'dark');

    const failing = shellWith(memoryStorage());
    failing.browser.matchMedia = () => {
      throw new Error('denied');
    };
    failing.setTheme('system');
    assert.equal(failing.resolvedTheme, 'light');
  });

  it('reorders inspectors with Shift+Arrow semantics', () => {
    const shell = shellWith(memoryStorage());
    shell.favorites = ['request', 'queries'];
    assert.equal(shell.moveInspector('request', 1), true);
    assert.deepEqual(shell.favorites, ['queries', 'request']);
    assert.equal(shell.moveInspector('ghost', 1), false);
    assert.equal(shell.sortInspector('queries', 5), false);
    assert.equal(shell.sortInspector('queries', 1), true);
    assert.deepEqual(shell.favorites, ['request', 'queries']);

    shell.favorites = [];
    shell.inspectorOrder = ['cache'];
    assert.equal(shell.moveInspector('cache', -1), false);
    assert.equal(shell.moveInspector('queries', 1), true);
  });

  it('falls back without navigation helpers', () => {
    const part = createPreferencesPart({ browser: fakeBrowser() });
    part.orderedInspectors = [
      { key: 'a', label: 'A' },
      { key: 'b', label: 'B' },
    ];
    part.isFavorite = () => false;
    part.favorites = [];
    part.inspectorOrder = ['a', 'b'];
    assert.equal(part.moveInspector('a', 1), true);
    assert.deepEqual(part.inspectorOrder, ['b', 'a']);
  });
});

describe('preferences theme menu', () => {
  it('opens and closes per scope and inspector state', () => {
    const shell = shellWith(memoryStorage());
    assert.equal(shell.openThemeMenu('nowhere'), null);
    assert.equal(shell.openThemeMenu('toolbar'), 'toolbar');
    assert.equal(shell.toggleThemeMenu('toolbar'), null);
    assert.equal(shell.themeMenuScope, null);
    assert.equal(shell.closeThemeMenu(), false);

    shell.inspectorOpen = true;
    assert.equal(shell.openThemeMenu('toolbar'), null);
    assert.equal(shell.openThemeMenu('header'), 'header');
    shell.barVisible = false;
    assert.equal(shell.openThemeMenu('header'), null);
  });

  it('focuses the current theme and restores focus', () => {
    const shell = shellWith(memoryStorage());
    const focused = [];
    const menu = {
      querySelectorAll: () => [
        {
          dataset: { dkThemeOption: 'system' },
          focus: () => focused.push('system'),
        },
        {
          dataset: { dkThemeOption: 'dark' },
          focus: () => focused.push('dark'),
        },
      ],
    };
    shell.setTheme('dark');
    assert.equal(
      shell.openThemeMenu(
        'toolbar',
        { focus: () => focused.push('back') },
        menu,
      ),
      'toolbar',
    );
    assert.deepEqual(focused, ['dark']);
    assert.equal(shell.closeThemeMenu(), true);
    assert.deepEqual(focused, ['dark', 'back']);

    const empty = shellWith(memoryStorage());
    assert.equal(
      empty.openThemeMenu('toolbar', null, { querySelectorAll: () => [] }),
      'toolbar',
    );
  });

  it('moves across theme options with wraparound', () => {
    const shell = shellWith(memoryStorage());
    const focused = [];
    const menu = {
      querySelectorAll: () =>
        [0, 1, 2].map((index) => ({ focus: () => focused.push(index) })),
    };
    assert.equal(shell.moveThemeMenu(1, menu), 1);
    assert.equal(shell.moveThemeMenu(1, { querySelectorAll: () => [] }), -1);
    assert.deepEqual(focused, [1]);
  });
});

describe('preferences shell composition', () => {
  it('registers the preferences part in createShell', () => {
    const shell = createShell(
      { id: 'req-1', inspectors: INSPECTORS },
      { fetchClient: async () => null },
    );
    assert.equal(shell.theme, 'system');
    assert.equal(typeof shell.restorePreferences, 'function');
    assert.equal(typeof shell.persistPreferences, 'function');
    assert.equal(typeof shell.setTheme, 'function');
    assert.equal(typeof shell.moveInspector, 'function');
  });
});
