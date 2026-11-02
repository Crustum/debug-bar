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
  'data-dk-timeline-back',
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
  if (
    target &&
    typeof target.hasAttribute === 'function' &&
    target.hasAttribute('data-dk-timeline-entry') &&
    typeof target.getAttribute === 'function'
  ) {
    const timelineKey = target.getAttribute('data-dk-timeline-key') ?? '';
    if (timelineKey !== '') {
      lastTimelineRowKey = timelineKey;
    }
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
 * Derives the record key attribute from a row's entry attribute.
 *
 * Panels mark selectable rows with `data-dk-<name>-entry` (or `-item`)
 * and carry the stable record id in the matching `-key` attribute. The
 * timeline `ref` must equal that key, so any panel — core or a future
 * third-party one — is deep-linkable with no registry and no map.
 *
 * @param {object} row Row element.
 * @return {string|null} Key attribute name or null.
 */
function recordKeyAttr(row) {
  if (!row || typeof row.getAttributeNames !== 'function') {
    return null;
  }
  let names = [];
  try {
    names = row.getAttributeNames();
  } catch (error) {
    return null;
  }
  for (const name of names) {
    if (typeof name !== 'string') {
      continue;
    }
    if (name.endsWith('-entry')) {
      return name.slice(0, -'entry'.length) + 'key';
    }
    if (name.endsWith('-item')) {
      return name.slice(0, -'item'.length) + 'key';
    }
  }

  return null;
}

function findInspectorSection(root, inspectorKey) {
  if (!root || typeof root.querySelectorAll !== 'function') {
    return null;
  }
  const sections = root.querySelectorAll('[data-dk-inspector-panel]');
  for (const section of sections) {
    if (
      section &&
      typeof section.getAttribute === 'function' &&
      section.getAttribute('data-dk-inspector-panel') === inspectorKey
    ) {
      return section;
    }
  }

  return null;
}

function pressRecordRow(row) {
  try {
    if (row && typeof row.click === 'function') {
      row.click();
    }
  } catch (error) {}
  try {
    if (row && typeof row.scrollIntoView === 'function') {
      row.scrollIntoView({ block: 'nearest' });
    }
  } catch (error) {}
}

function revealQueryRecord(section, want) {
  if (
    !section ||
    typeof section.querySelectorAll !== 'function' ||
    want === ''
  ) {
    return false;
  }
  const rows = section.querySelectorAll('[data-dk-query-item]');
  for (const row of rows) {
    if (
      row &&
      typeof row.getAttribute === 'function' &&
      row.getAttribute('data-dk-execution') === want
    ) {
      pressRecordRow(row);

      return true;
    }
  }
  const blocks = section.querySelectorAll('[data-dk-query-execution]');
  for (const block of blocks) {
    if (
      !block ||
      typeof block.getAttribute !== 'function' ||
      block.getAttribute('data-dk-query-execution') !== want
    ) {
      continue;
    }
    const row =
      block && typeof block.closest === 'function'
        ? block.closest('[data-dk-query-item]')
        : null;
    if (!row) {
      continue;
    }
    pressRecordRow(row);
    selectQueryExecution(section, row, want);

    return true;
  }

  return false;
}

function selectQueryExecution(section, row, want) {
  try {
    const rowKey =
      row && typeof row.getAttribute === 'function'
        ? row.getAttribute('data-dk-query-key') ?? ''
        : '';
    if (rowKey === '') {
      return;
    }
    const details = section.querySelectorAll('[data-dk-query-detail]');
    for (const detail of details) {
      if (
        !detail ||
        typeof detail.getAttribute !== 'function' ||
        detail.getAttribute('data-dk-query-detail') !== rowKey
      ) {
        continue;
      }
      const select =
        typeof detail.querySelector === 'function'
          ? detail.querySelector('select')
          : null;
      if (!select || !('value' in select)) {
        return;
      }
      select.value = want;
      if (
        typeof Event === 'function' &&
        typeof select.dispatchEvent === 'function'
      ) {
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }

      return;
    }
  } catch (error) {}
}

/**
 * Reveals one stored record inside its inspector.
 *
 * Used by timeline "Open inspector" jumps: after the tab opens, the exact
 * row is pressed (opening its detail) instead of leaving the first row
 * selected. Unknown inspectors, missing rows and missing refs resolve to
 * false so the caller keeps the plain tab open.
 *
 * @param {object} root Shell root to search.
 * @param {string} inspectorKey Inspector key from the timeline row.
 * @param {string|number|null} ref Stored row reference.
 * @return {boolean} True when a row was pressed.
 */
export function revealInspectorRecord(root, inspectorKey, ref) {
  if (typeof inspectorKey !== 'string' || inspectorKey === '') {
    return false;
  }
  const want = ref === null || ref === undefined ? '' : String(ref);
  if (want === '') {
    return false;
  }
  const section = findInspectorSection(root, inspectorKey);
  if (!section) {
    return false;
  }
  if (inspectorKey === 'queries') {
    return revealQueryRecord(section, want);
  }
  const rows =
    typeof section.querySelectorAll === 'function'
      ? section.querySelectorAll('button')
      : [];
  for (const row of rows) {
    const attr = recordKeyAttr(row);
    if (
      attr !== null &&
      typeof row.getAttribute === 'function' &&
      row.getAttribute(attr) === want
    ) {
      pressRecordRow(row);

      return true;
    }
  }

  return false;
}

/**
 * Last pressed timeline row key, restored on the way back.
 *
 * Inspector switches always land on the list, so returning to the
 * timeline would drop the selected row. Remembering the pressed key
 * here lets the return path re-press it without a fresh click.
 *
 * @type {string|null}
 */
let lastTimelineRowKey = null;

/**
 * Re-presses the last selected timeline row.
 *
 * Runs after focus workspaces reset to the list: when the timeline tab
 * is visible again, its previous selection (and detail) comes back, so
 * an Open-inspector jump and back feels like a round trip instead of
 * a reset.
 *
 * @param {object} root Shell root to search.
 * @param {object} hooks Selection hooks (setRowPressed, paintCodeBlocks).
 * @return {boolean} True when a row was re-pressed.
 */
export function restoreTimelineSelection(root, hooks = {}) {
  if (
    !lastTimelineRowKey ||
    !root ||
    typeof root.querySelector !== 'function'
  ) {
    return false;
  }
  const key = lastTimelineRowKey;
  const escaped =
    typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
      ? CSS.escape(key)
      : key;
  const row = root.querySelector('[data-dk-timeline-key="' + escaped + '"]');
  if (!row) {
    return false;
  }
  const section =
    typeof row.closest === 'function'
      ? row.closest('[data-dk-inspector-panel]')
      : null;
  if (
    section &&
    (section.hidden === true ||
      (typeof section.hasAttribute === 'function' &&
        section.hasAttribute('hidden')))
  ) {
    return false;
  }

  return selectPanelRecord(row, root, hooks);
}

/**
 * Finds the viewport box of the row's actual scroll container.
 *
 * The nearest `[data-dk-inspector-content]`/`[data-dk-inspector-focus-list]`
 * ancestor is not always the scroller: the focus list wraps the whole
 * list height while the outer content scrolls. Comparing the row against
 * a full-height box reads every row as "visible" and the return trip
 * never scrolls. The scroller is the first ancestor that actually
 * overflows (`scrollHeight` beyond `clientHeight`).
 *
 * @param {object|null} row Pressed row element.
 * @return {object|null} Viewport box, or null when no scroller scrolls.
 */
function scrollerBoxOf(row) {
  let node =
    row == null
      ? null
      : (row.parentNode ?? row.parentElement ?? row.parent ?? null);
  while (node) {
    try {
      const height = node.scrollHeight;
      const visible = node.clientHeight;
      if (
        typeof height === 'number' &&
        typeof visible === 'number' &&
        height - visible > 1 &&
        typeof node.getBoundingClientRect === 'function'
      ) {
        return node.getBoundingClientRect();
      }
    } catch (error) {}
    node = node.parentNode ?? node.parentElement ?? node.parent ?? null;
  }

  return null;
}

/**
 * Scrolls a selected row into the middle of its scroll container.
 *
 * `scrollIntoView({block: 'nearest'})` parks the row at the bottom edge,
 * so returning to the timeline leaves the selection glued to the screen
 * edge. Centering lands on meaningful content instead, including a
 * timeline round trip. Rows already fully visible (e.g. at the top of
 * the list) are left alone — no scroll jump.
 *
 * @param {object|null} row Pressed row element.
 * @return {boolean} True when the row was already handled (visible or scrolled).
 */
export function scrollRowIntoView(row) {
  if (!row || typeof row.scrollIntoView !== 'function') {
    return false;
  }
  try {
    const rect =
      typeof row.getBoundingClientRect === 'function'
        ? row.getBoundingClientRect()
        : null;
    const box = rect ? scrollerBoxOf(row) : null;
    if (box && rect.top >= box.top && rect.bottom <= box.bottom) {
      return true;
    }
    row.scrollIntoView({ block: 'center' });
  } catch (error) {}

  return true;
}

/**
 * Remembers a timeline row as the return anchor without opening its preview.
 *
 * A direct jump (row icon) bypasses `selectPanelRecord`, so the shell would
 * otherwise anchor on the previously previewed row and the way back would
 * land in the wrong place. This records the jumped-from key and repaints
 * the pressed state only — the detail pane stays untouched.
 *
 * @param {object|null} row Timeline row element.
 * @return {boolean} True when a row key was remembered.
 */
export function rememberTimelineRow(row) {
  if (
    !row ||
    typeof row.getAttribute !== 'function' ||
    typeof row.hasAttribute !== 'function' ||
    !row.hasAttribute('data-dk-timeline-entry')
  ) {
    return false;
  }
  const key = row.getAttribute('data-dk-timeline-key') ?? '';

  if (key === '') {
    return false;
  }
  lastTimelineRowKey = key;

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
    try {
      const pressed =
        typeof workspace.querySelector === 'function'
          ? workspace.querySelector('[aria-pressed="true"]')
          : null;
      scrollRowIntoView(pressed);
    } catch (error) {}
  }

  return true;
}
