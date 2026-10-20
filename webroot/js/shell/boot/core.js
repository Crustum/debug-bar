import { syncQueryRowTone } from '../../inspectors/queries.js';
import { highlightCodeBlocks } from '../../highlight.js';

// Third-party panel scripts paint async content (e.g. EXPLAIN results)
// through the documented `window.DebugBarHighlight` hook. Install it
// here: `debugbar.js` is not part of the shell entry, so its own install
// never runs in the built bundle and the hook stays undefined.
try {
  if (
    typeof globalThis !== 'undefined' &&
    globalThis !== null &&
    typeof globalThis.DebugBarHighlight !== 'function'
  ) {
    globalThis.DebugBarHighlight = highlightCodeBlocks;
  }
} catch {}

export function filterKeyOf(container) {
  const stored = container
    ? container.getAttribute('data-dk-active-filter')
    : null;
  if (stored) {
    return stored;
  }
  const active = container
    ? container.querySelector('[data-dk-filter][aria-selected="true"]')
    : null;
  const spec = active ? active.getAttribute('data-dk-filter') || '' : 'all';
  const sep = spec.lastIndexOf(':');

  return sep === -1 ? spec : spec.slice(sep + 1);
}

export function filterContainerOf(node) {
  return node && node.closest ? node.closest('[data-dk-active-filter]') : null;
}

export function treeScopeFor(node) {
  if (!node || !node.closest) {
    return null;
  }

  return node.closest(
    '[data-dk-tree-scope],[data-dk-variable-detail],[data-dk-view-detail]',
  );
}

export function applyTreeFilter(scope) {
  if (!scope || typeof scope.querySelectorAll !== 'function') {
    return;
  }
  const input =
    typeof scope.querySelector === 'function'
      ? scope.querySelector('[data-dk-tree-search]')
      : null;
  const needle =
    input && typeof input.value === 'string' ? input.value.toLowerCase() : '';
  scope.querySelectorAll('[data-dk-debug-tree]').forEach(function (tree) {
    if (needle === '') {
      tree.querySelectorAll('[data-dk-tree-row]').forEach(function (row) {
        row.removeAttribute('hidden');
      });

      return;
    }
    tree.querySelectorAll('[data-dk-tree-group]').forEach(function (group) {
      group.setAttribute('open', '');
    });
    tree.querySelectorAll('[data-dk-tree-row]').forEach(function (row) {
      const text =
        typeof row.textContent === 'string'
          ? row.textContent.toLowerCase()
          : '';
      if (text.indexOf(needle) !== -1) {
        row.removeAttribute('hidden');
      } else {
        row.setAttribute('hidden', '');
      }
    });
  });
}

export function expandTreeGroups(scope) {
  if (!scope || typeof scope.querySelectorAll !== 'function') {
    return;
  }
  scope.querySelectorAll('[data-dk-tree-group]').forEach(function (group) {
    group.setAttribute('open', '');
  });
}

export function applyListFilter(container) {
  if (!container) {
    return;
  }
  const key = filterKeyOf(container);
  const input = container.querySelector('[data-dk-search]');
  const needle =
    input && typeof input.value === 'string' ? input.value.toLowerCase() : '';
  let visible = 0;
  let total = 0;
  container.querySelectorAll('[data-dk-search-text]').forEach(function (row) {
    total += 1;
    const group = row.getAttribute('data-dk-filter-group') || '';
    const attention = row.getAttribute('data-dk-filter-attention') === 'true';
    // Groups are space-separated tokens, so one row can sit behind several
    // filter facets (status plus recipient plus class). Single-token rows
    // behave exactly as before.
    const inFilter =
      key === 'all' ||
      (key === 'attention' ? attention : group.split(' ').indexOf(key) !== -1);
    const hay = (row.getAttribute('data-dk-search-text') || '').toLowerCase();
    const inSearch = needle === '' || hay.indexOf(needle) !== -1;
    if (inFilter && inSearch) {
      row.removeAttribute('hidden');
      visible += 1;
    } else {
      row.setAttribute('hidden', '');
    }
  });
  const emptyNote = container.querySelector('[data-dk-list-empty]');
  if (emptyNote) {
    if (visible === 0 && total > 0) {
      emptyNote.removeAttribute('hidden');
    } else {
      emptyNote.setAttribute('hidden', '');
    }
  }
  const visibleCount = container.querySelector(
    '[data-dk-http-visible-count],[data-dk-visible-count]',
  );
  if (visibleCount) {
    if (visible !== total) {
      visibleCount.textContent = total === 0 ? '' : String(visible) + ' shown';
      if (visibleCount.textContent !== '') {
        visibleCount.removeAttribute('hidden');
      } else {
        visibleCount.setAttribute('hidden', '');
      }
    } else {
      visibleCount.textContent = '';
      visibleCount.setAttribute('hidden', '');
    }
  }
}

export function notifyParent(message) {
  try {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage(message, window.location.origin);
    }
  } catch (error) {}
}

export function setHidden(element, hidden) {
  if (!element) {
    return;
  }
  if (hidden) {
    element.setAttribute('hidden', '');
  } else {
    element.removeAttribute('hidden');
  }
}

function hasQueryToneMarkers(row) {
  return (
    typeof row.hasAttribute === 'function' &&
    (row.hasAttribute('data-dk-query-key') ||
      row.hasAttribute('data-dk-slow') ||
      row.hasAttribute('data-dk-repeated'))
  );
}

export function setRowPressed(row, active) {
  row.setAttribute('aria-pressed', active ? 'true' : 'false');
  if (hasQueryToneMarkers(row)) {
    syncQueryRowTone(row, active);

    return;
  }
  const selected = ['dk:bg-indigo-50/65', 'dk:dark:bg-indigo-950/20'];
  const idle = ['dk:hover:bg-zinc-50/80', 'dk:dark:hover:bg-zinc-900/60'];
  let hasMarkers = false;
  for (let i = 0; i < selected.length; i++) {
    if (
      row.classList.contains(selected[i]) ||
      row.classList.contains(idle[i])
    ) {
      hasMarkers = true;
      break;
    }
  }
  if (!hasMarkers) {
    return;
  }
  for (let j = 0; j < selected.length; j++) {
    if (active) {
      row.classList.add(selected[j]);
      row.classList.remove(idle[j]);
    } else {
      row.classList.remove(selected[j]);
      row.classList.add(idle[j]);
    }
  }
}

/**
 * Detects the embedded toolbar frame.
 *
 * Inside the host page iframe the viewport is the 40px bar itself —
 * vertical fitting must stay off there, the parent protocol resizes
 * the frame around the open picker instead.
 *
 * @return {boolean} True inside an iframe.
 */
export function isEmbeddedFrame() {
  try {
    return (
      typeof window !== 'undefined' &&
      typeof window.self !== 'undefined' &&
      typeof window.top !== 'undefined' &&
      window.self !== window.top
    );
  } catch (error) {
    return false;
  }
}

export function fitPopupInViewport(popup) {
  if (!popup || typeof popup.getBoundingClientRect !== 'function') {
    return;
  }
  let rect = null;
  try {
    rect = popup.getBoundingClientRect();
  } catch (error) {
    return;
  }
  if (!rect) {
    return;
  }
  // Horizontal nudge only. Bottom-docked frames open pickers upward
  // through CSS (`bottom: calc(100% + 4px)` under `data-dk-frame`),
  // so script-side vertical lifts only fight the frame protocol and
  // throw the dropdown off-screen — never translate vertically here.
  const margin = 12;
  const width = window.innerWidth || 0;
  let shift = 0;
  if (rect.right > width - margin) {
    shift = width - margin - rect.right;
  }
  if (rect.left + shift < margin) {
    shift = margin - rect.left;
  }
  try {
    popup.style.transform = shift !== 0 ? 'translateX(' + shift + 'px)' : '';
  } catch (error) {}
}

export function paintCodeBlocks(scope, root) {
  try {
    highlightCodeBlocks(scope || root);
  } catch (error) {}
}

export function currentRequestId(context = {}) {
  const root = context.root ?? null;
  const shell = context.shell ?? null;
  const summary = context.summary ?? null;
  try {
    const rootId = root ? root.getAttribute('data-dk-request-id') : null;
    if (rootId !== null && rootId !== '') {
      return rootId;
    }
  } catch (error) {}
  try {
    if (shell) {
      if (shell.currentProfileId) {
        return shell.currentProfileId;
      }
      if (shell.currentRequestId) {
        return shell.currentRequestId;
      }
      if (shell.summary && shell.summary.id) {
        return shell.summary.id;
      }
    }
  } catch (error) {}
  try {
    if (summary && summary.id) {
      return summary.id;
    }
  } catch (error) {}

  return null;
}
