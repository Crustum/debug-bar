export const QUERIES_EXPLAIN_URL = '/debug-bar/panels/explain.json';

import { readCsrfToken } from '../csrf.js';

export { readCsrfToken };

export function matchQuery(record = {}, controls = {}) {
  const needle =
    typeof controls.needle === 'string'
      ? controls.needle.toLowerCase().trim()
      : '';
  const filter = typeof controls.filter === 'string' ? controls.filter : 'all';
  const type = typeof record.type === 'string' ? record.type : '';
  const attention = record.attention === true;

  if (filter === 'attention' && attention !== true) return false;
  if ((filter === 'read' || filter === 'write') && type !== filter)
    return false;

  if (needle === '') return true;
  const hay =
    typeof record.search === 'string' ? record.search.toLowerCase() : '';

  return hay.indexOf(needle) !== -1;
}

export function compareQueryDuration(left = {}, right = {}, direction = 1) {
  const delta = (Number(left.duration) || 0) - (Number(right.duration) || 0);
  if (delta !== 0) return delta * (direction < 0 ? -1 : 1);

  return (Number(left.execution) || 0) - (Number(right.execution) || 0);
}

export function compareQueries(
  left = {},
  right = {},
  sort = 'execution',
  direction = 'asc',
) {
  if (sort === 'duration') {
    return compareQueryDuration(left, right, direction === 'asc' ? 1 : -1);
  }

  return (Number(left.execution) || 0) - (Number(right.execution) || 0);
}

export function formatQueryType(type) {
  const value = typeof type === 'string' && type !== '' ? type : 'query';

  return value.charAt(0).toUpperCase() + value.slice(1);
}

export function readQueryRow(row) {
  if (!row || typeof row.getAttribute !== 'function') return null;
  const slow = row.getAttribute('data-dk-slow') === 'true';
  const repeated = row.getAttribute('data-dk-repeated') === 'true';

  return {
    key: row.getAttribute('data-dk-query-key') ?? '',
    execution: Number(row.getAttribute('data-dk-execution')) || 0,
    duration: Number(row.getAttribute('data-dk-duration')) || 0,
    type: row.getAttribute('data-dk-query-type') ?? '',
    attention: row.getAttribute('data-dk-attention') === 'true',
    slow,
    repeated,
    count: Number(row.getAttribute('data-dk-query-execution-count')) || 1,
    search: row.getAttribute('data-dk-search-text') ?? '',
  };
}

const QUERY_ROW_TONES = {
  slow: {
    selected: [
      'dk:bg-red-50/90',
      'dk:ring-1',
      'dk:ring-inset',
      'dk:ring-red-200',
      'dk:dark:bg-red-950/35',
      'dk:dark:ring-red-900/80',
    ],
    idle: [
      'dk:bg-red-50/70',
      'dk:hover:bg-red-100/75',
      'dk:dark:bg-red-950/25',
      'dk:dark:hover:bg-red-950/40',
    ],
  },
  repeated: {
    selected: [
      'dk:bg-amber-50/90',
      'dk:ring-1',
      'dk:ring-inset',
      'dk:ring-amber-200',
      'dk:dark:bg-amber-950/35',
      'dk:dark:ring-amber-900/80',
    ],
    idle: [
      'dk:bg-amber-50/70',
      'dk:hover:bg-amber-100/75',
      'dk:dark:bg-amber-950/25',
      'dk:dark:hover:bg-amber-950/40',
    ],
  },
  default: {
    selected: [
      'dk:bg-indigo-50/90',
      'dk:ring-1',
      'dk:ring-inset',
      'dk:ring-indigo-200',
      'dk:dark:bg-indigo-950/35',
      'dk:dark:ring-indigo-800/80',
    ],
    idle: ['dk:hover:bg-zinc-50/80', 'dk:dark:hover:bg-zinc-900/60'],
  },
};

const QUERY_ROW_TONE_CLASSES = [
  ...QUERY_ROW_TONES.slow.selected,
  ...QUERY_ROW_TONES.slow.idle,
  ...QUERY_ROW_TONES.repeated.selected,
  ...QUERY_ROW_TONES.repeated.idle,
  ...QUERY_ROW_TONES.default.selected,
  ...QUERY_ROW_TONES.default.idle,
];

export function queryRowTone(record = {}) {
  if (record.slow === true) return 'slow';
  if (record.repeated === true) return 'repeated';

  return 'default';
}

export function syncQueryRowTone(row, pressed) {
  const list = row?.classList ?? null;
  if (
    !row ||
    !list ||
    typeof list.add !== 'function' ||
    typeof list.remove !== 'function'
  )
    return;
  const tone =
    QUERY_ROW_TONES[queryRowTone(readQueryRow(row) ?? {})] ??
    QUERY_ROW_TONES.default;
  for (const name of QUERY_ROW_TONE_CLASSES) {
    if (name === undefined) continue;
    try {
      list.remove(name);
    } catch {}
  }
  const active = pressed === true ? tone.selected : tone.idle;
  for (const name of active) {
    try {
      list.add(name);
    } catch {}
  }
}

function rowKey(row) {
  return typeof row?.getAttribute === 'function'
    ? (row.getAttribute('data-dk-query-key') ?? '')
    : '';
}

function defaultHighlight() {
  if (typeof globalThis === 'undefined') return null;
  const direct = globalThis.DebugBarHighlight;
  if (typeof direct === 'function') return direct.bind(globalThis);

  return null;
}

export function createQueriesInspector(root = null, options = {}) {
  const fetchClient =
    options.fetchClient ??
    (typeof fetch === 'function' ? fetch.bind(globalThis) : null);
  const getRequestId =
    typeof options.getRequestId === 'function'
      ? options.getRequestId
      : () => options.requestId ?? null;
  const highlight = options.highlight ?? defaultHighlight();
  const state = {
    selected: null,
    filter: 'all',
    needle: '',
    sort: 'execution',
    sortDirection: 'asc',
    explainVersion: 0,
  };

  const query = (selector) => root?.querySelector?.(selector) ?? null;
  const queryAll = (selector) => [
    ...(root?.querySelectorAll?.(selector) ?? []),
  ];

  function rows() {
    return queryAll('[data-dk-query-item]');
  }

  function details() {
    return queryAll('[data-dk-query-detail]');
  }

  function visibleCount() {
    return rows()
      .filter((row) => row.hidden !== true)
      .reduce((total, row) => total + (readQueryRow(row)?.count ?? 1), 0);
  }

  function syncEmpty() {
    const empty = query('[data-dk-list-empty]');
    if (empty) {
      if (visibleCount() === 0 && rows().length > 0)
        empty.removeAttribute('hidden');
      else empty.setAttribute('hidden', '');
    }
    const counter = query('[data-dk-visible-count]');
    if (counter) {
      const total = rows().reduce(
        (sum, row) => sum + (readQueryRow(row)?.count ?? 1),
        0,
      );
      if (visibleCount() !== total) {
        counter.textContent = `${visibleCount()} shown`;
        counter.removeAttribute('hidden');
      } else {
        counter.setAttribute('hidden', '');
      }
    }
  }

  function syncSortHeading() {
    const heading = query('[data-dk-sort]');
    if (!heading) return;
    const active = state.sort === 'duration';
    heading.setAttribute('aria-pressed', active ? 'true' : 'false');
    const label = heading.querySelector?.('[data-dk-sort-label]') ?? null;
    if (label && 'textContent' in label) {
      label.textContent = active
        ? `Time, sorted ${state.sortDirection === 'asc' ? 'ascending' : 'descending'}`
        : 'Time, not sorted';
    }
    const icon = heading.querySelector?.('[data-dk-sort-icon]') ?? null;
    if (icon) {
      if (active) icon.removeAttribute?.('hidden');
      else icon.setAttribute?.('hidden', '');
      if (icon.style && typeof icon.style.setProperty === 'function') {
        if (active && state.sortDirection === 'asc')
          icon.style.setProperty('transform', 'rotate(180deg)');
        else icon.style.removeProperty?.('transform');
      }
    }
    try {
      heading.classList?.toggle?.('dk:text-indigo-600', active);
      heading.classList?.toggle?.('dk:dark:text-indigo-300', active);
      heading.classList?.toggle?.('dk:text-zinc-400', !active);
    } catch {}
  }

  function applyView() {
    const list = rows();
    const ordered = [...list].sort((a, b) =>
      compareQueries(
        readQueryRow(a) ?? {},
        readQueryRow(b) ?? {},
        state.sort,
        state.sortDirection,
      ),
    );
    if (state.sort !== 'execution') {
      const parent = list[0]?.parentElement ?? null;
      for (const row of ordered) parent?.append?.(row);
    } else {
      const parent = list[0]?.parentElement ?? null;
      const natural = [...list].sort(
        (a, b) =>
          (readQueryRow(a)?.execution ?? 0) - (readQueryRow(b)?.execution ?? 0),
      );
      for (const row of natural) parent?.append?.(row);
    }
    for (const row of list) {
      const record = readQueryRow(row) ?? {};
      const show = matchQuery(record, {
        needle: state.needle,
        filter: state.filter,
      });
      if (show) {
        if (typeof row.removeAttribute === 'function')
          row.removeAttribute('hidden');
        if ('hidden' in row) row.hidden = false;
        if (row.style && typeof row.style.removeProperty === 'function') {
          row.style.removeProperty('display');
        }
      } else {
        if (typeof row.setAttribute === 'function')
          row.setAttribute('hidden', '');
        if ('hidden' in row) row.hidden = true;
        if (row.style && typeof row.style.setProperty === 'function') {
          row.style.setProperty('display', 'none', 'important');
        }
        if (state.selected === record.key) selectRecord(null);
      }
    }
    syncSortHeading();
    syncEmpty();
  }

  function paint(node = root) {
    try {
      if (typeof highlight === 'function') highlight(node ?? undefined);
    } catch {}
  }

  function selectRecord(key) {
    state.selected = key;
    for (const row of rows()) {
      const pressed = rowKey(row) === key;
      row.setAttribute('aria-pressed', pressed ? 'true' : 'false');
      syncQueryRowTone(row, pressed);
    }
    for (const detail of details()) {
      const show =
        key !== null &&
        (detail.getAttribute?.('data-dk-query-detail') ?? '') === key;
      if (show) detail.removeAttribute('hidden');
      else detail.setAttribute('hidden', '');
    }
    const empty = query('[data-dk-detail-empty]');
    if (empty) {
      if (key === null) empty.removeAttribute('hidden');
      else empty.setAttribute('hidden', '');
    }
    paint();
  }

  function currentExecution(detail) {
    const select = detail?.querySelector?.('[data-dk-select]') ?? null;
    const fallback =
      detail?.querySelector?.('[data-dk-query-execution]') ?? null;
    const value = select && 'value' in select ? select.value : null;
    if (value !== null && value !== '') return Number(value) || 0;
    const attr = fallback?.getAttribute?.('data-dk-query-execution') ?? '';

    return Number(attr) || 0;
  }

  function showExecution(detail, execution) {
    detail?.querySelectorAll?.('[data-dk-query-execution]').forEach((block) => {
      const show =
        Number(block.getAttribute?.('data-dk-query-execution')) === execution;
      if (show) block.removeAttribute('hidden');
      else block.setAttribute('hidden', '');
    });
    paint(detail);
  }

  async function runExplain(detail) {
    const version = (state.explainVersion ?? 0) + 1;
    state.explainVersion = version;
    const loading =
      detail?.querySelector?.('[data-dk-query-explain-loading]') ?? null;
    const result =
      detail?.querySelector?.('[data-dk-query-explain-result]') ?? null;
    const errorBox =
      detail?.querySelector?.('[data-dk-query-explain-error]') ?? null;
    const errorText =
      detail?.querySelector?.('[data-dk-query-explain-error-text]') ?? null;
    if (loading) loading.removeAttribute('hidden');
    if (result) result.setAttribute('hidden', '');
    if (errorBox) errorBox.setAttribute('hidden', '');

    let requestId = null;
    try {
      requestId = getRequestId();
    } catch {
      requestId = null;
    }
    if (
      typeof fetchClient !== 'function' ||
      requestId === null ||
      requestId === ''
    ) {
      if (loading) loading.setAttribute('hidden', '');
      if (errorBox) errorBox.removeAttribute('hidden');
      if (errorText) errorText.textContent = 'EXPLAIN is unavailable here.';

      return null;
    }

    let payload = null;
    let httpStatus = null;
    try {
      const token = readCsrfToken(detail);
      const response = await fetchClient(QUERIES_EXPLAIN_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          ...(token !== null ? { 'X-CSRF-Token': token } : {}),
        },
        body: JSON.stringify({
          request_id: requestId,
          execution: currentExecution(detail),
        }),
      });
      httpStatus =
        response && typeof response.status === 'number'
          ? response.status
          : null;
      payload =
        response && typeof response.json === 'function'
          ? await response.json()
          : response;
    } catch {
      payload = null;
    }
    if (version !== state.explainVersion) return null;
    if (loading) loading.setAttribute('hidden', '');

    if (payload && Array.isArray(payload.plan)) {
      if (result) {
        const mode =
          result.querySelector?.('[data-dk-query-explain-mode]') ?? null;
        const driver =
          result.querySelector?.('[data-dk-query-explain-driver]') ?? null;
        const meta =
          result.querySelector?.('[data-dk-query-explain-meta]') ?? null;
        if (mode && 'textContent' in mode)
          mode.textContent = payload.mode ?? '';
        if (driver && 'textContent' in driver)
          driver.textContent = payload.driver ?? '';
        if (meta && (payload.mode || payload.driver))
          meta.removeAttribute('hidden');
        const code =
          result.querySelector?.('[data-dk-query-explain-plan-code]') ?? null;
        if (code && 'textContent' in code) {
          if (typeof payload.pretty === 'string' && payload.pretty !== '') {
            code.textContent = payload.pretty;
            code.removeAttribute?.('data-highlighted');
            code.removeAttribute?.('data-dk-language');
          } else {
            code.textContent = JSON.stringify(payload.plan, null, 2);
            code.removeAttribute?.('data-highlighted');
            if (typeof code.setAttribute === 'function')
              code.setAttribute('data-dk-language', 'json');
          }
        } else if ('textContent' in result) {
          result.textContent =
            typeof payload.pretty === 'string' && payload.pretty !== ''
              ? payload.pretty
              : JSON.stringify(payload.plan, null, 2);
        }
        result.removeAttribute('hidden');
        paint(detail);
      }

      return payload;
    }
    if (errorBox) errorBox.removeAttribute('hidden');
    if (errorText) {
      errorText.textContent =
        (payload && (payload.unavailable_reason || payload.error)) ||
        (httpStatus !== null && httpStatus !== 200
          ? `EXPLAIN request failed (HTTP ${httpStatus}).`
          : 'EXPLAIN could not run');
    }

    return payload;
  }

  function onSearch(value) {
    state.needle = typeof value === 'string' ? value : '';
    applyView();
  }

  function onFilter(value) {
    state.filter = typeof value === 'string' && value !== '' ? value : 'all';
    applyView();
  }

  function onSortToggle() {
    if (state.sort !== 'duration') {
      state.sort = 'duration';
      state.sortDirection = 'desc';
    } else if (state.sortDirection === 'desc') {
      state.sortDirection = 'asc';
    } else {
      state.sort = 'execution';
      state.sortDirection = 'asc';
    }
    applyView();
  }

  function onExecutionChange(detail, value) {
    showExecution(detail, Number(value) || 0);
  }

  return {
    state,
    rows,
    applyView,
    selectRecord,
    onSearch,
    onFilter,
    onSortToggle,
    onExecutionChange,
    showExecution,
    runExplain,
    currentExecution,
  };
}
