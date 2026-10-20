import {
  applyTreeFilter,
  paintCodeBlocks,
  setRowPressed,
  treeScopeFor,
} from '../boot/core.js';

function viewsScopeFor(node) {
  const detail =
    node && node.closest ? node.closest('[data-dk-view-detail]') : null;
  if (detail) {
    return detail;
  }

  return node && node.closest ? node.closest('[data-dk-views-panel]') : null;
}

function setViewsState(panel, state) {
  const loading = panel.querySelector('[data-dk-views-loading]');
  const data = panel.querySelector('[data-dk-views-data]');
  const empty = panel.querySelector('[data-dk-views-empty]');
  const error = panel.querySelector('[data-dk-views-error]');
  [
    [loading, 'loading'],
    [data, 'data'],
    [empty, 'empty'],
    [error, 'error'],
  ].forEach(function (pair) {
    if (!pair[0]) {
      return;
    }
    if (pair[1] === state) {
      pair[0].removeAttribute('hidden');
    } else {
      pair[0].setAttribute('hidden', '');
    }
  });
}

export function loadViewsRender(ctx, panel, render) {
  const scope = viewsScopeFor(panel) || panel;
  const holder = scope.closest ? scope.closest('[data-dk-views-panel]') : null;
  const base =
    scope.getAttribute('data-dk-views-url') ||
    (holder ? holder.getAttribute('data-dk-views-url') : '') ||
    '';
  if (base === '') {
    setViewsState(scope, 'error');

    return;
  }
  setViewsState(scope, 'loading');
  fetch(base + '.json?render=' + encodeURIComponent(render || '1'), {
    headers: { Accept: 'application/json' },
  })
    .then(function (response) {
      if (!response || response.ok !== true) {
        throw new Error('view-data');
      }

      return response.json();
    })
    .then(function (payload) {
      const settled = viewsScopeFor(panel) || panel;
      const data = settled.querySelector('[data-dk-views-data]');
      const html =
        payload && typeof payload.html === 'string' ? payload.html : '';
      if (html === '') {
        setViewsState(settled, 'empty');

        return;
      }
      if (data) {
        data.innerHTML = html;
      }
      setViewsState(settled, 'data');
      applyTreeFilter(treeScopeFor(settled) || settled);
      paintCodeBlocks(settled, ctx.root);
    })
    .catch(function () {
      setViewsState(viewsScopeFor(panel) || panel, 'error');
    });
}

export function initViewsPanels(ctx) {
  ctx.root.querySelectorAll('[data-dk-views-panel]').forEach(function (panel) {
    let detail = null;
    panel
      .querySelectorAll('[data-dk-view-detail]')
      .forEach(function (candidate) {
        if (!detail && !candidate.hasAttribute('hidden')) {
          detail = candidate;
        }
      });
    if (!detail) {
      detail = panel.querySelector('[data-dk-view-detail]');
    }
    if (!detail) {
      return;
    }
    const renderSelect = detail.querySelector(
      '[data-dk-select="views-render"]',
    );
    const renderPanel = detail.querySelector('[data-dk-view-data-panel]');
    loadViewsRender(
      ctx,
      detail,
      renderSelect
        ? renderSelect.value
        : renderPanel
          ? renderPanel.getAttribute('data-dk-views-render')
          : '1',
    );
  });
}
export function handleViewsClick(target, ctx) {
  if (target.hasAttribute('data-dk-views-retry')) {
    const retryScope = viewsScopeFor(target);
    if (retryScope) {
      const retrySelect = retryScope.querySelector(
        '[data-dk-select="views-render"]',
      );
      const retryPanel = retryScope.closest
        ? retryScope.closest('[data-dk-view-detail]')
        : null;
      loadViewsRender(
        ctx,
        retryPanel || retryScope,
        retrySelect ? retrySelect.value : '1',
      );
    }

    return true;
  }
  if (!target.hasAttribute('data-dk-view-entry')) {
    return false;
  }
  const viewsPanel = target.closest
    ? target.closest('[data-dk-views-panel]')
    : null;
  const viewsScope = viewsPanel || ctx.root;
  const viewKey = target.getAttribute('data-dk-view-key') || '';
  viewsScope.querySelectorAll('[data-dk-view-entry]').forEach(function (row) {
    setRowPressed(
      row,
      viewKey !== '' && row.getAttribute('data-dk-view-key') === viewKey,
    );
  });
  viewsScope
    .querySelectorAll('[data-dk-view-detail]')
    .forEach(function (detail) {
      const show =
        viewKey !== '' &&
        detail.getAttribute('data-dk-view-detail') === viewKey;
      if (show) {
        detail.removeAttribute('hidden');
        const renderSelect = detail.querySelector(
          '[data-dk-select="views-render"]',
        );
        const renderPanel = detail.querySelector('[data-dk-view-data-panel]');
        loadViewsRender(
          ctx,
          detail,
          renderSelect
            ? renderSelect.value
            : renderPanel
              ? renderPanel.getAttribute('data-dk-views-render')
              : '1',
        );
      } else {
        detail.setAttribute('hidden', '');
      }
    });

  return true;
}
