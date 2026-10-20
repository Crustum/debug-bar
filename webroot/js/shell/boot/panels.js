/**
 * Shell chrome hooks owned by the delegated click listener.
 *
 * Panel row entries are NOT listed here: registered ones append from
 * `PANEL_SELECTION` via `buildClickSelector()`, and any other
 * `data-dk-<namespace>-entry` row is picked up with zero registration
 * through `deriveAutoSelection()` plus the `findAutoEntryTarget()`
 * fallback in the click handler. Entries with local owners
 * (`exception`, `view`, `query-item`) stay explicit — the shell never
 * selects those rows itself.
 *
 * @type {Array<string>}
 */
const GENERIC_CLICK_HOOKS = [
  'data-dk-mini-toggle',
  'data-dk-open-inspector',
  'data-dk-open-palette',
  'data-dk-window',
  'data-dk-request-trigger',
  'data-dk-request-option',
  'data-dk-theme-trigger',
  'data-dk-theme-option',
  'data-dk-mobile-trigger',
  'data-dk-request-link',
  'data-dk-requests-refresh',
  'data-dk-copy',
  'data-dk-copy-all',
  'data-dk-tab',
  'data-dk-filter',
  'data-dk-query-item',
  'data-dk-sort',
  'data-dk-inspector-nav-item',
  'data-dk-inspector-favorite',
  'data-dk-inspector-retry',
  'data-dk-inspector-reload',
  'data-dk-activity-retry',
  'data-dk-backdrop',
  'data-dk-palette-command',
  'data-dk-cache-clear',
  'data-dk-cache-clear-all',
  'data-dk-packages-check',
  'data-dk-packages-audit',
  'data-dk-attributes-warm',
  'data-dk-attributes-inspect',
  'data-dk-mail-viewport',
  'data-dk-exception-entry',
  'data-dk-view-entry',
  'data-dk-views-retry',
  'data-dk-variable-entry',
  'data-dk-variables-retry',
  'data-dk-tree-expand',
  'data-dk-workspace-back',
];

const PANEL_SELECTION = [
  {
    entry: 'data-dk-model-entry',
    key: 'data-dk-model-key',
    detail: 'data-dk-model-detail',
    panel: '[data-dk-models-panel]',
    paint: true,
  },
  {
    entry: 'data-dk-event-entry',
    key: 'data-dk-event-key',
    detail: 'data-dk-event-detail',
    panel: '[data-dk-events-panel]',
    paint: true,
  },
  {
    entry: 'data-dk-authorization-entry',
    key: 'data-dk-authorization-key',
    detail: 'data-dk-authorization-detail',
    panel: '[data-dk-authorization-panel]',
    paint: true,
  },
  {
    entry: 'data-dk-http-entry',
    key: 'data-dk-http-key',
    detail: 'data-dk-http-detail',
    panel: '[data-dk-http-panel]',
    paint: true,
  },
  {
    entry: 'data-dk-log-entry',
    key: 'data-dk-log-key',
    detail: 'data-dk-log-detail',
    panel: '[data-dk-logs-panel]',
    paint: false,
  },
  {
    entry: 'data-dk-cache-entry',
    key: 'data-dk-cache-key',
    detail: 'data-dk-cache-detail',
    panel: '[data-dk-cache-panel]',
    paint: false,
  },
  {
    entry: 'data-dk-plugin-entry',
    key: 'data-dk-plugin-key',
    detail: 'data-dk-plugin-detail',
    panel: '[data-dk-plugins-panel]',
    paint: false,
  },
  {
    entry: 'data-dk-route-entry',
    key: 'data-dk-route-key',
    detail: 'data-dk-route-detail',
    panel: '[data-dk-routes-panel]',
    paint: false,
  },
  {
    entry: 'data-dk-package-entry',
    key: 'data-dk-package-key',
    detail: 'data-dk-package-detail',
    panel: '[data-dk-packages-panel]',
    paint: false,
  },
  {
    entry: 'data-dk-attribute-entry',
    key: 'data-dk-attribute-key',
    detail: 'data-dk-attribute-detail',
    panel: '[data-dk-attributes-panel]',
    paint: false,
  },
  {
    entry: 'data-dk-mail-entry',
    key: 'data-dk-mail-key',
    detail: 'data-dk-mail-detail',
    panel: '[data-dk-mail-panel]',
    paint: false,
  },
  {
    entry: 'data-dk-timeline-entry',
    key: 'data-dk-timeline-key',
    detail: 'data-dk-timeline-detail',
    panel: '[data-dk-timeline-panel],[data-dk-timeline]',
    paint: true,
  },
];

/**
 * Tracks panel scripts already appended, so rescans never double-load.
 *
 * @type {Set<string>}
 */
const loadedPanelScripts = new Set();

/**
 * Resolves whether a panel script URL is safe to load.
 *
 * Same origin only: stored panel rows must never pull foreign hosts.
 *
 * @param {string} url Script URL as declared by the panel.
 * @return {boolean} True when the URL may be loaded.
 */
function panelScriptAllowed(url) {
  if (typeof url !== 'string' || url === '') {
    return false;
  }
  try {
    const host =
      typeof window !== 'undefined' && window !== null ? window : null;
    const location = host && host.location ? host.location : null;
    const base =
      location && typeof location.href === 'string' && location.href !== ''
        ? location.href
        : 'http://localhost/';
    const parsed = new URL(url, base);
    if (
      location &&
      typeof location.origin === 'string' &&
      location.origin !== ''
    ) {
      return parsed.origin === location.origin;
    }

    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
  } catch (error) {
    return false;
  }
}

/**
 * Appends one panel script to the document head.
 *
 * @param {string} url Same-origin script URL.
 * @return {boolean} False when there is nowhere to append.
 */
function appendPanelScript(url) {
  try {
    const doc = typeof document !== 'undefined' ? document : null;
    if (!doc || typeof doc.createElement !== 'function') {
      return false;
    }
    const node = doc.createElement('script');
    if (!node || typeof node.setAttribute !== 'function') {
      return false;
    }
    node.setAttribute('src', url);
    node.setAttribute('async', '');
    const head =
      doc.head ||
      (typeof doc.querySelector === 'function'
        ? doc.querySelector('head')
        : null);
    const target =
      head && typeof head.appendChild === 'function'
        ? head
        : doc.body && typeof doc.body.appendChild === 'function'
          ? doc.body
          : null;
    if (!target) {
      return false;
    }
    target.appendChild(node);

    return true;
  } catch (error) {
    return false;
  }
}

/**
 * Loads third-party panel scripts declared in server HTML.
 *
 * Panels opt in with an embedded JSON block in their own element — the
 * same shape as selection configs, but for code instead of row mapping:
 * `<script type="application/json" data-dk-panel-scripts>` carrying an
 * array of same-origin script URLs. Runs on boot and after every panels
 * swap; already-loaded URLs never append twice.
 *
 * @param {object} root Shell root to scan.
 * @param {Function} loader Appender used for one URL. Defaults to head.
 * @return {number} Appended scripts.
 */
export function registerPanelScripts(root, loader = appendPanelScript) {
  if (!root || typeof root.querySelectorAll !== 'function') {
    return 0;
  }

  let loaded = 0;
  root.querySelectorAll('[data-dk-panel-scripts]').forEach((node) => {
    const tag =
      node && node.tagName ? String(node.tagName).toUpperCase() : 'SCRIPT';
    if (tag !== 'SCRIPT') {
      return;
    }

    let urls = null;
    try {
      urls = JSON.parse((node.textContent || '').trim());
    } catch (error) {
      urls = null;
    }

    if (!Array.isArray(urls)) {
      return;
    }

    for (const url of urls) {
      if (loadedPanelScripts.has(url) || !panelScriptAllowed(url)) {
        continue;
      }
      loadedPanelScripts.add(url);
      try {
        loader(url);
      } catch (error) {}
      loaded += 1;
    }
  });

  return loaded;
}

export function matchPanelEntry(target) {
  if (!target || typeof target.hasAttribute !== 'function') {
    return null;
  }
  for (const config of PANEL_SELECTION) {
    if (target.hasAttribute(config.entry)) {
      return config;
    }
  }

  return deriveAutoSelection(target);
}

/**
 * Namespaces with dedicated click owners.
 *
 * These rows look like selectable entries but are handled by their own
 * modules (lazy loading, inspector wiring) — generic auto-selection
 * must never claim them.
 *
 * @type {Array<string>}
 */
const DEDICATED_ENTRY_NAMESPACES = ['exception', 'view', 'variable'];

function selectionAttributeNames(target) {
  if (!target) {
    return [];
  }
  if (typeof target.getAttributeNames === 'function') {
    try {
      const names = target.getAttributeNames();
      if (Array.isArray(names)) {
        return names;
      }
    } catch (error) {}
  }
  if (target.attrs && typeof target.attrs === 'object') {
    return Object.keys(target.attrs);
  }

  return [];
}

/**
 * Derives a selection config from markup alone, no registration.
 *
 * Any `data-dk-<namespace>-entry` row pairs with
 * `data-dk-<namespace>-key` and `data-dk-<namespace>-detail` panes
 * scoped to the closest `[data-dk-<namespace>-panel]` (falling back to
 * the closest inspector panel, then the root). The bare `data-dk-entry`
 * / `data-dk-key` / `data-dk-detail` trio works the same for panels
 * that do not need their own namespace. Dedicated namespaces keep
 * their local owners and never auto-match.
 *
 * @param {object} target Clicked element.
 * @return {object|null} Selection config or null.
 */
export function deriveAutoSelection(target) {
  if (!target || typeof target.hasAttribute !== 'function') {
    return null;
  }
  if (target.hasAttribute('data-dk-entry')) {
    return {
      entry: 'data-dk-entry',
      key: 'data-dk-key',
      detail: 'data-dk-detail',
      panel: '[data-dk-panel]',
      paint: true,
      auto: true,
    };
  }
  for (const name of selectionAttributeNames(target)) {
    const match = /^data-dk-(.+)-entry$/.exec(name);
    if (!match) {
      continue;
    }
    const namespace = match[1];
    if (DEDICATED_ENTRY_NAMESPACES.indexOf(namespace) !== -1) {
      continue;
    }

    return {
      entry: name,
      key: `data-dk-${namespace}-key`,
      detail: `data-dk-${namespace}-detail`,
      panel: `[data-dk-${namespace}-panel]`,
      paint: true,
      auto: true,
    };
  }

  return null;
}

/**
 * Walks up from a click that missed the static click selector.
 *
 * `buildClickSelector()` cannot enumerate third-party namespaces, so
 * clicks on unregistered `data-dk-*-entry` rows (or their children)
 * land here and resolve to the nearest auto-selectable row.
 *
 * @param {object} from Click event target.
 * @param {object} root Shell root bounding the walk.
 * @return {object|null} Nearest selectable row or null.
 */
export function findAutoEntryTarget(from, root) {
  let node = from;
  while (node && node !== root) {
    if (
      typeof node.hasAttribute === 'function' &&
      deriveAutoSelection(node) !== null
    ) {
      if (!root || typeof root.contains !== 'function' || root.contains(node)) {
        return node;
      }

      return null;
    }
    node = node.parentNode || node.parentElement || node.parent || null;
  }

  return null;
}

/**
 * Builds the delegated click selector from shell hooks plus every
 * registered panel row entry.
 *
 * Third-party panels register through `registerPanelSelection()` and
 * become clickable without editing the shell.
 *
 * @return {string}
 */
export function buildClickSelector() {
  const hooks = GENERIC_CLICK_HOOKS.map((hook) => '[' + hook + ']');
  for (const config of PANEL_SELECTION) {
    hooks.push('[' + config.entry + ']');
  }

  return hooks.join(', ');
}

/**
 * Registers panel selection configs embedded in server HTML.
 *
 * Panels declare list+detail selection with a JSON script tag inside
 * their own element, so third-party panels never touch shell code:
 * `<script type="application/json" data-dk-selection-config>` carrying
 * `{entry, key, detail, panel, paint?}`. Runs on boot and after every
 * panels swap; re-registration replaces, so rescans are idempotent.
 *
 * @param {object} root Shell root to scan.
 * @return {number} Registered configs.
 */
export function registerSelectionConfigs(root) {
  if (!root || typeof root.querySelectorAll !== 'function') {
    return 0;
  }

  let registered = 0;
  root.querySelectorAll('[data-dk-selection-config]').forEach((node) => {
    const tag =
      node && node.tagName ? String(node.tagName).toUpperCase() : 'SCRIPT';
    if (tag !== 'SCRIPT') {
      return;
    }

    let config = null;
    try {
      config = JSON.parse((node.textContent || '').trim());
    } catch (error) {
      config = null;
    }

    if (registerPanelSelection(config)) {
      registered += 1;
    }
  });

  return registered;
}

/**
 * Registers one list+detail panel for row selection.
 *
 * @param {object} config Panel selection config.
 * @param {string} config.entry Row attribute, e.g. `data-dk-notification-entry`.
 * @param {string} config.key Row key attribute.
 * @param {string} config.detail Detail pane attribute.
 * @param {string} config.panel Panel root selector scoping the toggle.
 * @param {boolean} [config.paint] Highlight code blocks on show.
 * @return {boolean} False when the config is incomplete.
 */
export function registerPanelSelection(config) {
  if (
    !config ||
    typeof config.entry !== 'string' ||
    config.entry === '' ||
    typeof config.key !== 'string' ||
    config.key === '' ||
    typeof config.detail !== 'string' ||
    config.detail === '' ||
    typeof config.panel !== 'string' ||
    config.panel === ''
  ) {
    return false;
  }

  const existing = PANEL_SELECTION.findIndex(
    (item) => item.entry === config.entry,
  );
  const normalized = {
    entry: config.entry,
    key: config.key,
    detail: config.detail,
    panel: config.panel,
    paint: config.paint === true,
  };
  if (existing === -1) {
    PANEL_SELECTION.push(normalized);
  } else {
    PANEL_SELECTION[existing] = normalized;
  }

  return true;
}

export function selectPanelRecord(target, root, hooks = {}) {
  const config = matchPanelEntry(target);
  if (!config) {
    return false;
  }
  const setRowPressed = hooks.setRowPressed;
  const paintCodeBlocks = hooks.paintCodeBlocks;
  const panel = target.closest ? target.closest(config.panel) : null;
  const inspectorPanel =
    !panel && target.closest
      ? target.closest('[data-dk-inspector-panel]')
      : null;
  const scope = panel || inspectorPanel || root;
  const key = target.getAttribute(config.key) || '';
  scope.querySelectorAll(`[${config.entry}]`).forEach((row) => {
    setRowPressed(row, key !== '' && row.getAttribute(config.key) === key);
  });
  scope.querySelectorAll(`[${config.detail}]`).forEach((detail) => {
    const show = key !== '' && detail.getAttribute(config.detail) === key;
    if (show) {
      detail.removeAttribute('hidden');
      if (config.paint && typeof paintCodeBlocks === 'function') {
        paintCodeBlocks(detail);
      }
    } else {
      detail.setAttribute('hidden', '');
    }
  });
  const workspace =
    target.closest && typeof target.closest === 'function'
      ? target.closest('[data-dk-workspace-mode="focus"]')
      : null;
  if (workspace) {
    rememberFocusScroll(workspace, target);
    const list = workspace.querySelector('[data-dk-inspector-focus-list]');
    const focusDetail = workspace.querySelector(
      '[data-dk-inspector-focus-detail]',
    );
    if (list) {
      list.setAttribute('hidden', '');
    }
    if (focusDetail) {
      focusDetail.removeAttribute('hidden');
      if (typeof focusDetail.focus === 'function') {
        focusDetail.focus({ preventScroll: true });
      }
    }
  }

  return true;
}

/**
 * Saved scroll offsets per focused workspace, restored on Back.
 *
 * Hiding the list collapses the inspector height, so an outer scroller
 * (dialog, panel section) can clamp to top too — one list-body offset
 * is not enough. Offsets record on drill-in, replay on drill-out.
 *
 * @type {WeakMap<object, Array<{node: object, top: number}>>}
 */
const focusScrollMemory = new WeakMap();

function rememberFocusScroll(workspace, from) {
  const saved = [];
  let node = from;
  while (node) {
    if (typeof node.scrollTop === 'number' && node.scrollTop > 0) {
      saved.push({ node, top: node.scrollTop });
    }
    if (node === workspace) {
      break;
    }
    node = node.parentNode || node.parentElement || node.parent || null;
    if (!node || typeof node.querySelector !== 'function') {
      break;
    }
  }
  focusScrollMemory.set(workspace, saved);
}

function restoreFocusScroll(workspace) {
  const saved = focusScrollMemory.get(workspace) || [];
  saved.forEach(function (entry) {
    try {
      entry.node.scrollTop = entry.top;
    } catch (error) {}
  });
  focusScrollMemory.delete(workspace);
}

/**
 * Returns every focused workspace under scope to its list view.
 *
 * Inspector switches (left nav, Open inspector jumps) must never land
 * on a stale drill-in: the return path is the list, Back is only for
 * stepping out without leaving the inspector.
 *
 * @param {object} scope Root to scan.
 * @return {number} Reset workspaces.
 */
export function resetFocusWorkspaces(scope) {
  if (!scope || typeof scope.querySelectorAll !== 'function') {
    return 0;
  }
  let reset = 0;
  scope
    .querySelectorAll('[data-dk-workspace-mode="focus"]')
    .forEach(function (workspace) {
      const list = workspace.querySelector('[data-dk-inspector-focus-list]');
      const focusDetail = workspace.querySelector(
        '[data-dk-inspector-focus-detail]',
      );
      if (list) {
        list.removeAttribute('hidden');
      }
      if (focusDetail) {
        focusDetail.setAttribute('hidden', '');
      }
      reset += 1;
    });

  return reset;
}

/**
 * Returns from a focused workspace detail to its list.
 *
 * @param {object} target Clicked element.
 * @return {boolean} True when a back button was handled.
 */
export function handleWorkspaceBackClick(target) {
  if (!target.hasAttribute('data-dk-workspace-back')) {
    return false;
  }
  const workspace =
    target.closest && typeof target.closest === 'function'
      ? target.closest('[data-dk-workspace-mode="focus"]')
      : null;
  if (workspace) {
    const list = workspace.querySelector('[data-dk-inspector-focus-list]');
    const focusDetail = workspace.querySelector(
      '[data-dk-inspector-focus-detail]',
    );
    if (list) {
      list.removeAttribute('hidden');
    }
    if (focusDetail) {
      focusDetail.setAttribute('hidden', '');
    }
    restoreFocusScroll(workspace);
  }

  return true;
}
