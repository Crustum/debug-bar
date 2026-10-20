import {
  applyListFilter,
  applyTreeFilter,
  currentRequestId as readRequestId,
  expandTreeGroups,
  filterContainerOf,
  paintCodeBlocks,
  setRowPressed,
  treeScopeFor,
} from './core.js';
import { registerPanelScripts, registerSelectionConfigs } from './panels.js';
import {
  COPY_FEEDBACK_CLEAR_DELAY,
  COPY_FEEDBACK_FAILED,
  COPY_FEEDBACK_OK,
  flashButtonIcon,
} from '../copy.js';
import {
  refreshRequestOptions,
  refreshRequestPickers,
  syncTriggerLabels,
} from './picker.js';
import { createQueriesInspector } from '../../inspectors/queries.js';
import {
  hostAjaxRequestId,
  requestPanelsUrl,
  requestToolbarUrl,
} from '../requests.js';
import { initVariablesPanels } from '../panels/variables.js';
import { initViewsPanels, loadViewsRender } from '../panels/views.js';

function isIgnoredUrl(ctx, path) {
  let masks = [];
  try {
    masks = ctx.summary.ignored_url_masks || [];
  } catch (error) {
    masks = [];
  }
  let pathOnly = String(path || '');
  if (pathOnly.indexOf('http://') === 0 || pathOnly.indexOf('https://') === 0) {
    const slash = pathOnly.indexOf('/', pathOnly.indexOf('://') + 3);
    pathOnly = slash === -1 ? '/' : pathOnly.slice(slash);
  }
  const question = pathOnly.indexOf('?');
  if (question !== -1) {
    pathOnly = pathOnly.slice(0, question);
  }
  for (let i = 0; i < masks.length; i++) {
    const mask = String(masks[i] || '');
    if (mask === '') {
      continue;
    }
    let pattern = '^';
    for (let j = 0; j < mask.length; j++) {
      const ch = mask[j];
      if (ch === '*') {
        pattern += '.*';
      } else if (ch === '?') {
        pattern += '.';
      } else if (ch === '[') {
        const close = mask.indexOf(']', j + 1);
        if (close !== -1) {
          pattern += mask.slice(j, close + 1);
          j = close;
        } else {
          pattern += '\\[';
        }
      } else if ('\\^$.|+(){}]'.indexOf(ch) !== -1) {
        pattern += '\\' + ch;
      } else {
        pattern += ch;
      }
    }
    pattern += '$';
    try {
      if (new RegExp(pattern).test(pathOnly)) {
        return true;
      }
    } catch (error) {}
    if (mask.slice(-2) === '/*' && pathOnly === mask.slice(0, -2)) {
      return true;
    }
  }

  return false;
}

export function handleHostAjaxMessage(event, ctx) {
  try {
    if (!event || event.origin !== window.location.origin) {
      return;
    }
  } catch (error) {
    return;
  }
  const parse = hostAjaxRequestId;
  let id = null;
  try {
    id = parse(event.data);
  } catch (error) {
    id = null;
  }
  if (id === null || id === '') {
    return;
  }
  try {
    let bridgeParams = null;
    if (
      typeof event.data === 'string' &&
      event.data.indexOf('ajax-completed$$') === 0
    ) {
      bridgeParams = JSON.parse(event.data.slice('ajax-completed$$'.length));
    }
    const announcedUrl =
      bridgeParams && typeof bridgeParams.url === 'string'
        ? bridgeParams.url
        : '';
    if (announcedUrl !== '' && isIgnoredUrl(ctx, announcedUrl)) {
      return;
    }
  } catch (error) {}
  let announced = null;
  try {
    announced = ctx.shell.noticeRequest
      ? ctx.shell.noticeRequest(id, { foreground: false })
      : null;
  } catch (error) {
    announced = null;
  }
  const afterNotice = function (found) {
    if (found === null || found === undefined) {
      return;
    }
    refreshRequestOptions(ctx);
    refreshRequestPickers(ctx);
  };
  if (announced && typeof announced.then === 'function') {
    announced.then(afterNotice, function () {});
  } else {
    afterNotice(announced);
  }
}

let queriesInspectors = [];

function currentRequestId(ctx) {
  return readRequestId({
    root: ctx.root,
    shell: ctx.shell,
    summary: ctx.summary,
  });
}

function makeQueriesInspector(ctx, container) {
  const inspector = createQueriesInspector(container, {
    requestId: currentRequestId(ctx),
    getRequestId: function () {
      return currentRequestId(ctx);
    },
  });
  queriesInspectors.push({ container: container, inspector: inspector });

  return inspector;
}

export function initQueriesInspectors(ctx) {
  queriesInspectors = queriesInspectors.filter(function (entry) {
    return entry && entry.container && entry.container.isConnected === true;
  });
  ctx.root.querySelectorAll('[data-dk-queries]').forEach(function (container) {
    let known = false;
    for (let i = 0; i < queriesInspectors.length; i++) {
      if (queriesInspectors[i].container === container) {
        known = true;
        break;
      }
    }
    if (known) {
      return;
    }
    const inspector = makeQueriesInspector(ctx, container);
    if (!inspector) {
      return;
    }
    const first = container.querySelector('[data-dk-query-item]');
    if (first) {
      inspector.selectRecord(
        first.getAttribute('data-dk-query-key') ||
          first.getAttribute('data-dk-query-item'),
      );
    }
  });
  paintCodeBlocks(undefined, ctx.root);
}

export function queriesInspectorFor(ctx, node) {
  const container =
    node && node.closest ? node.closest('[data-dk-queries]') : null;
  if (!container) {
    return null;
  }
  for (let i = 0; i < queriesInspectors.length; i++) {
    if (queriesInspectors[i].container === container) {
      return queriesInspectors[i].inspector;
    }
  }

  return makeQueriesInspector(ctx, container);
}

export function swapRequestPanels(ctx, id) {
  let url = null;
  try {
    url = requestPanelsUrl(id);
  } catch (error) {
    url = null;
  }
  if (url === null) {
    try {
      url = '/debug-bar/requests/panels/' + encodeURIComponent(id) + '.json';
    } catch (error) {
      url = null;
    }
  }
  if (url === null) {
    return;
  }
  fetch(url, { headers: { Accept: 'application/json' } })
    .then(function (response) {
      if (!response || response.ok !== true) {
        throw new Error('panels');
      }

      return response.json();
    })
    .then(function (payload) {
      applyRequestPanels(ctx, payload);
    })
    .catch(function () {
      let toolbarUrl = '/debug-bar/toolbar/' + encodeURIComponent(id);
      try {
        toolbarUrl = requestToolbarUrl(id) || toolbarUrl;
      } catch (error) {}
      window.location.assign(toolbarUrl);
    });
}

function applyRequestPanels(ctx, payload) {
  if (!payload || !payload.request || !Array.isArray(payload.panels)) {
    throw new Error('panels');
  }
  const bootVersion = ctx.root.getAttribute('data-dk-asset-version') || '';
  const payloadVersion =
    payload.asset_version !== undefined && payload.asset_version !== null
      ? String(payload.asset_version)
      : '';
  if (
    bootVersion !== '' &&
    payloadVersion !== '' &&
    bootVersion !== payloadVersion
  ) {
    ctx.root.setAttribute('data-dk-asset-version', payloadVersion);
    window.location.reload();

    return;
  }
  const req = payload.request;
  let swapped = 0;
  payload.panels.forEach(function (panel) {
    if (!panel || typeof panel.html !== 'string' || !panel.key) {
      return;
    }
    const section = ctx.root.querySelector(
      '[data-dk-inspector-panel="' + panel.key + '"]',
    );
    if (!section) {
      return;
    }
    section.innerHTML = panel.html;
    if (panel.title) {
      section.setAttribute('aria-label', panel.title);
    }
    swapped += 1;
  });
  if (swapped === 0) {
    throw new Error('panels');
  }
  initQueriesInspectors(ctx);
  initViewsPanels(ctx);
  initVariablesPanels(ctx);
  registerSelectionConfigs(ctx.root);
  registerPanelScripts(ctx.root);
  if (req.id !== undefined && req.id !== null && req.id !== '') {
    ctx.root.setAttribute('data-dk-request-id', req.id);
    syncRequestIdCopy(ctx, req.id);
  }
  try {
    ctx.summary.id = req.id || ctx.summary.id;
    ctx.summary.method = req.method || ctx.summary.method;
    ctx.summary.url = req.url || ctx.summary.url;
    ctx.summary.status_code =
      req.status_code !== undefined ? req.status_code : ctx.summary.status_code;
  } catch (error) {}
  const durationEl = ctx.root.querySelector('[data-dk-header-status]');
  if (durationEl && req.duration_label !== undefined) {
    durationEl.textContent = req.duration_label;
  }
  const memoryEl = ctx.root.querySelector('[data-dk-header-memory]');
  if (memoryEl && req.peak_memory_label !== undefined) {
    memoryEl.textContent = req.peak_memory_label;
  }
  const queryCountEl = ctx.root.querySelector('[data-dk-header-query-count]');
  if (queryCountEl && req.query_count !== undefined) {
    queryCountEl.textContent = req.query_count;
  }
  const warningDot = ctx.root.querySelector('[data-dk-warning-dot]');
  if (warningDot) {
    warningDot.setAttribute(
      'data-dk-status-tone',
      req.warning ? 'warn' : 'good',
    );
  }
  refreshRequestOptions(ctx);
  syncTriggerLabels(ctx);
  ctx.syncNavCounts(payload.panels);
  ctx.renderNavGroups();
  ctx.syncNavDom(ctx.state.swapKeepKey || 'request');
  ctx.syncPanels();
  const content = ctx.root.querySelector('[data-dk-inspector-content]');
  if (content) {
    content.scrollTop = 0;
  }
  paintCodeBlocks(undefined, ctx.root);
}

export function handleFilterClick(target, ctx) {
  if (!target.hasAttribute('data-dk-filter')) {
    return false;
  }
  const tablist = target.closest('[data-dk-filter-tabs]');
  const filterScope = tablist ? tablist : ctx.root;
  filterScope.querySelectorAll('[data-dk-filter]').forEach(function (tab) {
    tab.setAttribute('aria-selected', tab === target ? 'true' : 'false');
  });
  applyListFilter(filterContainerOf(target));

  return true;
}

export function handleTreeClick(target) {
  if (!target.hasAttribute('data-dk-tree-expand')) {
    return false;
  }
  expandTreeGroups(treeScopeFor(target));

  return true;
}

export function handleExceptionClick(target, ctx) {
  if (!target.hasAttribute('data-dk-exception-entry')) {
    return false;
  }
  const exceptionsPanel = target.closest
    ? target.closest('[data-dk-exceptions-panel]')
    : null;
  const exceptionsScope = exceptionsPanel || ctx.root;
  const exceptionKey = target.getAttribute('data-dk-exception-key') || '';
  exceptionsScope
    .querySelectorAll('[data-dk-exception-entry]')
    .forEach(function (row) {
      setRowPressed(
        row,
        exceptionKey !== '' &&
          row.getAttribute('data-dk-exception-key') === exceptionKey,
      );
    });
  exceptionsScope
    .querySelectorAll('[data-dk-exception-detail]')
    .forEach(function (detail) {
      const show =
        exceptionKey !== '' &&
        detail.getAttribute('data-dk-exception-detail') === exceptionKey;
      if (show) {
        detail.removeAttribute('hidden');
      } else {
        detail.setAttribute('hidden', '');
      }
    });

  return true;
}

export function handleQueryClick(target, ctx) {
  if (target.hasAttribute('data-dk-query-item')) {
    const queryInspector = queriesInspectorFor(ctx, target);
    if (queryInspector) {
      const queryKey =
        target.getAttribute('data-dk-query-key') ||
        target.getAttribute('data-dk-query-item');
      queryInspector.selectRecord(queryKey);
    }

    return true;
  }
  const sortScope = target.getAttribute('data-dk-sort') || '';
  if (sortScope.indexOf('queries:') === 0) {
    const sortInspector = queriesInspectorFor(ctx, target);
    if (sortInspector) {
      sortInspector.onSortToggle();
    }

    return true;
  }

  return false;
}

export function handleSortClick(target) {
  const modelsSortScope = target.getAttribute('data-dk-sort') || '';
  if (modelsSortScope.indexOf('models:') === 0) {
    const modelsSortPanel = target.closest
      ? target.closest('[data-dk-models-panel]')
      : null;
    const modelsSortList = modelsSortPanel
      ? modelsSortPanel.querySelector('[data-dk-models-list]')
      : null;
    if (modelsSortList) {
      const sortKey = modelsSortScope.slice('models:'.length);
      let sortState =
        modelsSortPanel.getAttribute('data-dk-sort-state') || 'capture';
      let sortDirection =
        modelsSortPanel.getAttribute('data-dk-sort-direction') || 'asc';
      const firstDirection = sortKey === 'model' ? 'asc' : 'desc';
      if (sortState !== sortKey) {
        sortState = sortKey;
        sortDirection = firstDirection;
      } else if (sortDirection === firstDirection) {
        sortDirection = firstDirection === 'asc' ? 'desc' : 'asc';
      } else {
        sortState = 'capture';
        sortDirection = 'asc';
      }
      modelsSortPanel.setAttribute('data-dk-sort-state', sortState);
      modelsSortPanel.setAttribute('data-dk-sort-direction', sortDirection);
      modelsSortPanel
        .querySelectorAll('[data-dk-sort]')
        .forEach(function (heading) {
          const headingKey = (heading.getAttribute('data-dk-sort') || '').slice(
            'models:'.length,
          );
          const headingActive = headingKey !== '' && headingKey === sortState;
          heading.setAttribute(
            'aria-pressed',
            headingActive ? 'true' : 'false',
          );
          const headingLabel = heading.querySelector('[data-dk-sort-label]');
          if (headingLabel) {
            const headingName =
              headingKey.charAt(0).toUpperCase() + headingKey.slice(1);
            headingLabel.textContent = headingActive
              ? headingName +
                ', sorted ' +
                (sortDirection === 'asc' ? 'ascending' : 'descending')
              : headingName + ', not sorted';
          }
          const headingIcon = heading.querySelector('[data-dk-sort-icon]');
          if (headingIcon) {
            if (headingActive) {
              headingIcon.removeAttribute('hidden');
              headingIcon.style.transform =
                sortDirection === 'asc' ? 'rotate(180deg)' : '';
            } else {
              headingIcon.setAttribute('hidden', '');
            }
          }
        });
      const modelRows = Array.prototype.slice.call(
        modelsSortList.querySelectorAll('[data-dk-model-entry]'),
      );
      modelRows.sort(function (left, right) {
        const captureOrder =
          (parseInt(left.getAttribute('data-dk-model-index') || '0', 10) || 0) -
          (parseInt(right.getAttribute('data-dk-model-index') || '0', 10) || 0);
        if (sortState === 'capture') {
          return captureOrder;
        }
        let comparison = 0;
        if (sortState === 'model') {
          comparison = (
            left.getAttribute('data-dk-model-sort-name') || ''
          ).localeCompare(right.getAttribute('data-dk-model-sort-name') || '');
        } else {
          const sortAttr =
            sortState === 'retrieved'
              ? 'data-dk-model-sort-retrieved'
              : sortState === 'writes'
                ? 'data-dk-model-sort-writes'
                : 'data-dk-model-sort-reloads';
          comparison =
            (parseInt(left.getAttribute(sortAttr) || '0', 10) || 0) -
            (parseInt(right.getAttribute(sortAttr) || '0', 10) || 0);
        }

        return (
          (sortDirection === 'asc' ? comparison : -comparison) || captureOrder
        );
      });
      modelRows.forEach(function (row) {
        modelsSortList.appendChild(row);
      });
    }

    return true;
  }
  if (target.getAttribute('data-dk-sort') === 'http-client:duration') {
    const httpPanel = target.closest
      ? target.closest('[data-dk-http-panel]')
      : null;
    const httpList = httpPanel
      ? httpPanel.querySelector('[data-dk-http-list]')
      : null;
    if (httpList) {
      let state = target.getAttribute('data-dk-sort-state') || 'execution';
      state =
        state === 'execution'
          ? 'duration-desc'
          : state === 'duration-desc'
            ? 'duration-asc'
            : 'execution';
      target.setAttribute('data-dk-sort-state', state);
      target.setAttribute(
        'aria-pressed',
        state === 'execution' ? 'false' : 'true',
      );
      const sortLabel = target.querySelector('[data-dk-sort-label]');
      if (sortLabel) {
        sortLabel.textContent =
          state === 'execution'
            ? 'Time, not sorted'
            : 'Time, sorted ' +
              (state === 'duration-asc' ? 'ascending' : 'descending');
      }
      const sortIcon = target.querySelector('[data-dk-sort-icon]');
      if (sortIcon) {
        if (state === 'execution') {
          sortIcon.setAttribute('hidden', '');
        } else {
          sortIcon.removeAttribute('hidden');
          sortIcon.style.transform =
            state === 'duration-asc' ? 'rotate(180deg)' : '';
        }
      }
      const httpRows = Array.prototype.slice.call(
        httpList.querySelectorAll('[data-dk-http-entry]'),
      );
      httpRows.sort(function (left, right) {
        if (state === 'execution') {
          return (
            (parseInt(left.getAttribute('data-dk-execution') || '0', 10) || 0) -
            (parseInt(right.getAttribute('data-dk-execution') || '0', 10) || 0)
          );
        }
        const leftDuration = parseFloat(
          left.getAttribute('data-dk-duration') || '-1',
        );
        const rightDuration = parseFloat(
          right.getAttribute('data-dk-duration') || '-1',
        );
        if (leftDuration < 0 || rightDuration < 0) {
          if (leftDuration < 0 && rightDuration >= 0) {
            return 1;
          }
          if (rightDuration < 0 && leftDuration >= 0) {
            return -1;
          }
        }
        const comparison = leftDuration - rightDuration;

        return state === 'duration-asc' ? comparison : -comparison;
      });
      httpRows.forEach(function (row) {
        httpList.appendChild(row);
      });
    }

    return true;
  }

  return false;
}

export function handleTabsClick(target, ctx) {
  if (!target.hasAttribute('data-dk-tab')) {
    return false;
  }
  const spec = target.getAttribute('data-dk-tab') || '';
  const tabsRoot = target.closest('[data-dk-detail-tabs]');
  const tabScope = tabsRoot ? tabsRoot : ctx.root;
  tabScope.querySelectorAll('[data-dk-tab]').forEach(function (tab) {
    const active = (tab.getAttribute('data-dk-tab') || '') === spec;
    tab.setAttribute('aria-selected', active ? 'true' : 'false');
  });
  const panelsScope =
    tabsRoot && tabsRoot.parentElement ? tabsRoot.parentElement : ctx.root;
  panelsScope
    .querySelectorAll('[data-dk-tab-panel]')
    .forEach(function (tabPanel) {
      const show = (tabPanel.getAttribute('data-dk-tab-panel') || '') === spec;
      if (show) {
        tabPanel.removeAttribute('hidden');
        paintCodeBlocks(tabPanel, ctx.root);
      } else {
        tabPanel.setAttribute('hidden', '');
      }
    });
  if (spec.indexOf('query:') === 0 && spec.slice(-8) === ':explain') {
    const explainDetail = target.closest
      ? target.closest('[data-dk-query-detail]')
      : null;
    const explainInspector = queriesInspectorFor(ctx, target);
    if (explainInspector && explainDetail) {
      explainInspector.runExplain(explainDetail);
    }
  }

  return true;
}

/**
 * Points the header request-id copy control at the swapped request.
 *
 * The header survives panel swaps (only sections re-render), so without
 * this the copy button keeps offering the first loaded id forever.
 *
 * @param {object} ctx Shell context.
 * @param {string} id Swapped request id.
 * @return {boolean} True when at least one control was repointed.
 */
export function syncRequestIdCopy(ctx, id) {
  if (typeof id !== 'string' || id === '') {
    return false;
  }
  let updated = false;
  ctx.root
    .querySelectorAll('[data-dk-copy-request-id]')
    .forEach(function (control) {
      control.setAttribute('data-dk-copy-value', id);
      updated = true;
    });

  return updated;
}

function feedbackFor(target) {
  const copyLabel = target.querySelector('[data-dk-copy-label]');
  const copyIcon = target.querySelector('svg');
  const originalLabel = copyLabel ? copyLabel.textContent : null;

  return function showCopyFeedback(ok) {
    if (copyLabel) {
      copyLabel.textContent =
        ok === true ? COPY_FEEDBACK_OK : COPY_FEEDBACK_FAILED;
    }
    if (ok === true) {
      flashButtonIcon(target, copyIcon, 'dk-copy-flash');
    }
    window.setTimeout(function () {
      if (copyLabel && originalLabel !== null) {
        copyLabel.textContent = originalLabel;
      }
    }, COPY_FEEDBACK_CLEAR_DELAY);
  };
}

export function handleCopyClick(target, ctx) {
  if (!target.hasAttribute('data-dk-copy')) {
    return false;
  }
  const copyValue = target.getAttribute('data-dk-copy-value') || '';
  const showCopyFeedback = feedbackFor(target);
  try {
    const copied = ctx.shell.copyWithFeedback(copyValue);
    if (copied && typeof copied.then === 'function') {
      copied.then(showCopyFeedback);
    } else {
      showCopyFeedback(copied);
    }
  } catch (error) {
    showCopyFeedback(false);
  }

  return true;
}

/**
 * Copies every `data-dk-copy-value` in scope as one text.
 *
 * Scope is the closest `[data-dk-copy-scope]`, then the closest
 * inspector panel, then the root — so a panel opts in by marking
 * one wrapper, with zero panel JavaScript. Values join with a
 * newline; an empty scope reports a failed copy instead of
 * copying nothing.
 *
 * @param {object} target Clicked element.
 * @param {object} ctx Shell context.
 * @return {boolean} True when a copy-all button was handled.
 */
export function handleCopyAllClick(target, ctx) {
  if (!target.hasAttribute('data-dk-copy-all')) {
    return false;
  }
  const showCopyFeedback = feedbackFor(target);
  let scope = null;
  if (target.closest && typeof target.closest === 'function') {
    scope =
      target.closest('[data-dk-copy-scope]') ||
      target.closest('[data-dk-inspector-panel]');
  }
  const list = scope || ctx.root;
  const values = [];
  if (list && typeof list.querySelectorAll === 'function') {
    list.querySelectorAll('[data-dk-copy-value]').forEach(function (node) {
      const value =
        node && typeof node.getAttribute === 'function'
          ? node.getAttribute('data-dk-copy-value')
          : null;
      if (typeof value === 'string' && value !== '') {
        values.push(value);
      }
    });
  }
  if (values.length === 0) {
    showCopyFeedback(false);

    return true;
  }
  try {
    const copied = ctx.shell.copyWithFeedback(values.join('\n'));
    if (copied && typeof copied.then === 'function') {
      copied.then(showCopyFeedback);
    } else {
      showCopyFeedback(copied);
    }
  } catch (error) {
    showCopyFeedback(false);
  }

  return true;
}

export function handleListInput(event, ctx) {
  if (
    event.target instanceof Element &&
    event.target.closest('[data-dk-tree-search]') &&
    ctx.root.contains(event.target)
  ) {
    applyTreeFilter(treeScopeFor(event.target));

    return;
  }
  const input =
    event.target instanceof Element
      ? event.target.closest('[data-dk-search]')
      : null;
  if (!input || !ctx.root.contains(input)) {
    return;
  }
  if (input.closest && input.closest('[data-dk-queries]')) {
    const searchInspector = queriesInspectorFor(ctx, input);
    if (searchInspector) {
      searchInspector.onSearch(input.value);
    }

    return;
  }
  applyListFilter(filterContainerOf(input));
}

export function handleListChange(event, ctx) {
  const select =
    event.target instanceof Element
      ? event.target.closest('[data-dk-select]')
      : null;
  if (!select || !ctx.root.contains(select)) {
    return;
  }
  const selectScope = select.getAttribute('data-dk-select') || '';
  if (selectScope === 'views-render') {
    const renderDetail = select.closest
      ? select.closest('[data-dk-view-detail]')
      : null;
    const renderPanel = select.closest
      ? select.closest('[data-dk-views-panel]')
      : null;
    if (renderPanel) {
      loadViewsRender(ctx, renderDetail || renderPanel, select.value);
    }

    return;
  }
  const selectInspector = queriesInspectorFor(ctx, select);
  if (selectScope === 'queries-filter') {
    if (selectInspector) {
      selectInspector.onFilter(select.value);
    }

    return;
  }
  if (selectScope === 'mail-preview-format') {
    const previewDetail = select.closest
      ? select.closest('[data-dk-mail-detail]')
      : null;
    const previewFrame = previewDetail
      ? previewDetail.querySelector('[data-dk-mail-preview-frame]')
      : null;
    if (previewFrame) {
      const partUrl =
        select.value === 'text'
          ? previewFrame.getAttribute('data-dk-mail-text-url') || ''
          : previewFrame.getAttribute('data-dk-mail-html-url') || '';
      if (partUrl !== '') {
        previewFrame.setAttribute('src', partUrl);
      }
    }

    return;
  }
  if (selectScope.indexOf('query-execution:') === 0) {
    const executionDetail = select.closest
      ? select.closest('[data-dk-query-detail]')
      : null;
    if (selectInspector && executionDetail) {
      selectInspector.onExecutionChange(executionDetail, select.value);
    }

    return;
  }
  // Any other select scopes a list filter: the closest filterable panel
  // root (marked with `data-dk-active-filter`) takes the value, so new
  // panels filter without shell changes.
  const filterPanel = select.closest
    ? select.closest('[data-dk-active-filter]')
    : null;
  if (filterPanel) {
    filterPanel.setAttribute('data-dk-active-filter', select.value || 'all');
    applyListFilter(filterPanel);
  }
}
