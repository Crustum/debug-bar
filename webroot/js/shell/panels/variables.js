import {
  applyTreeFilter,
  paintCodeBlocks,
  setRowPressed,
  treeScopeFor,
} from '../boot/core.js';

function variablesScopeFor(node) {
  const detail =
    node && node.closest ? node.closest('[data-dk-variable-detail]') : null;
  if (detail) {
    return detail;
  }

  return node && node.closest
    ? node.closest('[data-dk-variables-panel]')
    : null;
}

function setVariablesState(panel, state) {
  const loading = panel.querySelector('[data-dk-variables-loading]');
  const data = panel.querySelector('[data-dk-variables-data]');
  const empty = panel.querySelector('[data-dk-variables-empty]');
  const error = panel.querySelector('[data-dk-variables-error]');
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

function loadVariablesVar(ctx, panel, name) {
  const scope = variablesScopeFor(panel) || panel;
  const holder = scope.closest
    ? scope.closest('[data-dk-variables-panel]')
    : null;
  const base =
    scope.getAttribute('data-dk-variables-url') ||
    (holder ? holder.getAttribute('data-dk-variables-url') : '') ||
    '';
  if (base === '' || name === '') {
    setVariablesState(scope, 'error');

    return;
  }
  setVariablesState(scope, 'loading');
  fetch(base + '.json?var=' + encodeURIComponent(name), {
    headers: { Accept: 'application/json' },
  })
    .then(function (response) {
      if (!response || response.ok !== true) {
        throw new Error('variable-data');
      }

      return response.json();
    })
    .then(function (payload) {
      const settled = variablesScopeFor(panel) || panel;
      const data = settled.querySelector('[data-dk-variables-data]');
      const html =
        payload && typeof payload.html === 'string' ? payload.html : '';
      if (html === '') {
        setVariablesState(settled, 'empty');

        return;
      }
      if (data) {
        data.innerHTML = html;
      }
      setVariablesState(settled, 'data');
      applyTreeFilter(treeScopeFor(settled) || settled);
      paintCodeBlocks(settled, ctx.root);
    })
    .catch(function () {
      setVariablesState(variablesScopeFor(panel) || panel, 'error');
    });
}

export function initVariablesPanels(ctx) {
  ctx.root
    .querySelectorAll('[data-dk-variables-panel]')
    .forEach(function (panel) {
      let detail = null;
      panel
        .querySelectorAll('[data-dk-variable-detail]')
        .forEach(function (candidate) {
          if (!detail && !candidate.hasAttribute('hidden')) {
            detail = candidate;
          }
        });
      if (!detail) {
        detail = panel.querySelector('[data-dk-variable-detail]');
      }
      if (!detail) {
        return;
      }
      const dataPanel = detail.querySelector('[data-dk-variable-data-panel]');
      loadVariablesVar(
        ctx,
        detail,
        dataPanel ? dataPanel.getAttribute('data-dk-variable-name') : '',
      );
    });
}

export function handleVariablesClick(target, ctx) {
  if (target.hasAttribute('data-dk-variables-retry')) {
    const retryScope = variablesScopeFor(target);
    if (retryScope) {
      const retryPanel = retryScope.closest
        ? retryScope.closest('[data-dk-variable-detail]')
        : null;
      const holder = (retryPanel || retryScope).querySelector(
        '[data-dk-variable-data-panel]',
      );
      loadVariablesVar(
        ctx,
        retryPanel || retryScope,
        holder ? holder.getAttribute('data-dk-variable-name') : '',
      );
    }

    return true;
  }
  if (!target.hasAttribute('data-dk-variable-entry')) {
    return false;
  }
  const variablesPanel = target.closest
    ? target.closest('[data-dk-variables-panel]')
    : null;
  const variablesScope = variablesPanel || ctx.root;
  const variableKey = target.getAttribute('data-dk-variable-key') || '';
  variablesScope
    .querySelectorAll('[data-dk-variable-entry]')
    .forEach(function (row) {
      setRowPressed(
        row,
        variableKey !== '' &&
          row.getAttribute('data-dk-variable-key') === variableKey,
      );
    });
  variablesScope
    .querySelectorAll('[data-dk-variable-detail]')
    .forEach(function (detail) {
      const show =
        variableKey !== '' &&
        detail.getAttribute('data-dk-variable-detail') === variableKey;
      if (show) {
        detail.removeAttribute('hidden');
        const dataPanel = detail.querySelector('[data-dk-variable-data-panel]');
        loadVariablesVar(
          ctx,
          detail,
          dataPanel ? dataPanel.getAttribute('data-dk-variable-name') : '',
        );
      } else {
        detail.setAttribute('hidden', '');
      }
    });

  return true;
}
