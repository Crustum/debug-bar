import { fitPopupInViewport, isEmbeddedFrame, notifyParent } from './core.js';
import { requestStatusTone } from '../requests.js';

function isToolbarScope(scope) {
  return scope === 'toolbar' || scope === 'corner';
}

export function pickers(ctx) {
  return Array.prototype.slice.call(
    ctx.root.querySelectorAll(
      '[data-dk-request-picker], [data-dk-theme-options], [data-dk-mobile-popover]',
    ),
  );
}

export function closeAllPickers(ctx, restoreFocus) {
  pickers(ctx).forEach(function (picker) {
    picker.setAttribute('hidden', '');
    try {
      picker.style.transform = '';
    } catch (error) {}
  });
  ctx.root
    .querySelectorAll(
      '[data-dk-request-trigger], [data-dk-theme-trigger], [data-dk-mobile-trigger]',
    )
    .forEach(function (trigger) {
      trigger.setAttribute('aria-expanded', 'false');
    });
  try {
    ctx.shell.closeRequestPicker(false);
  } catch (error) {}
  try {
    ctx.shell.closeThemeMenu(false);
  } catch (error) {}
  if (ctx.root.getAttribute('data-dk-frame') === 'picker') {
    if (ctx.inspector && !ctx.inspector.hasAttribute('hidden')) {
      ctx.setFrame('inspector');
    } else {
      ctx.setFrame('bar');
      notifyParent('toolbar');
    }
  }
  if (restoreFocus !== false) {
    return;
  }
}

export function togglePicker(ctx, trigger, picker) {
  const willOpen = picker.hasAttribute('hidden');
  closeAllPickers(ctx, false);
  if (willOpen) {
    picker.removeAttribute('hidden');
    trigger.setAttribute('aria-expanded', 'true');
    fitPopupInViewport(picker);
  }
}

export function shrinkAfterPicker(ctx, scope) {
  if (!isToolbarScope(scope)) {
    return;
  }
  if (ctx.inspector && !ctx.inspector.hasAttribute('hidden')) {
    ctx.setFrame('inspector');
  } else {
    ctx.setFrame('bar');
    notifyParent('toolbar');
  }
}

export function syncTriggerLabels(ctx) {
  let current = {};
  try {
    current = ctx.shell.currentRequest || ctx.shell.summary || {};
  } catch (error) {
    current = {};
  }
  const method = current.method || 'GET';
  const url = current.url || '/';
  const status = parseInt(current.status_code, 10) || 0;
  const size =
    typeof current.response_size === 'string' && current.response_size !== ''
      ? current.response_size
      : null;
  ctx.root
    .querySelectorAll('[data-dk-current-request-method]')
    .forEach(function (el) {
      el.textContent = method;
    });
  ctx.root
    .querySelectorAll('[data-dk-current-request-url]')
    .forEach(function (el) {
      el.textContent = url;
    });
  ctx.root
    .querySelectorAll('[data-dk-current-request-status]')
    .forEach(function (el) {
      el.textContent = String(status);
      el.setAttribute('data-dk-status-code', String(status));
      el.className = statusClass(status);
    });
  ctx.root
    .querySelectorAll('[data-dk-current-request-size]')
    .forEach(function (el) {
      if (size === null) {
        el.textContent = '';
        el.setAttribute('hidden', '');
      } else {
        el.textContent = size;
        el.removeAttribute('hidden');
      }
    });
}

function statusClass(status) {
  const code = Number(status);
  if (code >= 500) return 'dk:text-red-600 dk:dark:text-red-300';
  if (code >= 400) return 'dk:text-amber-600 dk:dark:text-amber-300';
  if (code >= 300) return 'dk:text-sky-600 dk:dark:text-sky-300';
  if (code >= 200) return 'dk:text-emerald-600 dk:dark:text-emerald-300';

  return 'dk:text-zinc-500 dk:dark:text-zinc-400';
}

export function refreshRequestOptions(ctx) {
  let currentId = null;
  try {
    currentId = ctx.shell.currentRequestId || null;
  } catch (error) {
    currentId = null;
  }
  ctx.root
    .querySelectorAll('[data-dk-request-option]')
    .forEach(function (option) {
      const selected =
        currentId !== null &&
        option.getAttribute('data-dk-request-option') === currentId;
      option.setAttribute('aria-selected', selected ? 'true' : 'false');
    });
  ctx.root.querySelectorAll('[data-dk-request-unread]').forEach(function (dot) {
    const option = dot.closest ? dot.closest('[data-dk-request-option]') : null;
    const id = option ? option.getAttribute('data-dk-request-option') : null;
    let unread = false;
    try {
      unread = ctx.shell.requestIsUnread
        ? !!ctx.shell.requestIsUnread({ id: id })
        : false;
    } catch (error) {
      unread = false;
    }
    if (unread) {
      dot.removeAttribute('hidden');
    } else {
      dot.setAttribute('hidden', '');
    }
  });
  const badge = ctx.root.querySelector('[data-dk-request-badge]');
  if (badge) {
    let count = 0;
    try {
      count = ctx.shell.unreadRequestCount || 0;
    } catch (error) {
      count = 0;
    }
    if (count > 0) {
      badge.textContent = count > 9 ? '9+' : String(count);
      badge.removeAttribute('hidden');
    } else {
      badge.textContent = '';
      badge.setAttribute('hidden', '');
    }
  }
}

export function refreshRequestPickers(ctx) {
  let entries = [];
  let currentId = null;
  try {
    entries = ctx.shell.recentRequests || [];
    currentId = ctx.shell.currentRequestId || null;
  } catch (error) {
    entries = [];
  }
  ctx.root
    .querySelectorAll('[data-dk-request-picker]')
    .forEach(function (picker) {
      while (picker.firstChild) {
        picker.removeChild(picker.firstChild);
      }
      entries.forEach(function (entry, index) {
        if (!entry || !entry.id) {
          return;
        }
        const status = parseInt(entry.status_code, 10) || 0;
        const rawSize =
          typeof entry.response_size === 'string' && entry.response_size !== ''
            ? entry.response_size
            : null;
        const size =
          entry.method === 'CLI' || entry.method === 'JOB' ? null : rawSize;
        let unread = false;
        try {
          unread = ctx.shell.requestIsUnread
            ? !!ctx.shell.requestIsUnread(entry)
            : false;
        } catch (error) {
          unread = false;
        }
        const item = document.createElement('li');
        item.setAttribute('role', 'option');
        item.setAttribute('tabindex', '-1');
        item.setAttribute(
          'aria-selected',
          entry.id === currentId ? 'true' : 'false',
        );
        item.setAttribute('data-dk-request-option', entry.id);
        item.setAttribute('data-dk-request-index', String(index));
        item.setAttribute('data-dk-status-code', String(status));
        if (size !== null) {
          item.setAttribute('data-dk-response-size', size);
        }
        item.setAttribute(
          'title',
          (entry.method || 'GET') + ' ' + (entry.url || '/'),
        );
        item.className = 'dk-picker-option';
        const dot = document.createElement('span');
        dot.setAttribute('data-dk-status-tone', requestStatusTone(status));
        dot.className = 'dk-status-dot';
        const methodTag = document.createElement('span');
        methodTag.className = 'dk-method-tag';
        methodTag.textContent = entry.method || 'GET';
        const body = document.createElement('span');
        body.className = 'dk-picker-option-body';
        const label = document.createElement('span');
        label.className = 'dk-picker-option-label';
        label.textContent = entry.url || '/';
        const meta = document.createElement('span');
        meta.className = 'dk-picker-option-meta';
        const statusEl = document.createElement('span');
        statusEl.setAttribute('data-dk-option-status', '');
        statusEl.className = statusClass(status);
        statusEl.textContent = String(status);
        meta.appendChild(statusEl);
        if (size !== null) {
          const sizeEl = document.createElement('span');
          sizeEl.setAttribute('data-dk-option-size', '');
          sizeEl.className = 'dk-request-size';
          sizeEl.textContent = size;
          meta.appendChild(sizeEl);
        }
        body.appendChild(label);
        body.appendChild(meta);
        const unreadDot = document.createElement('span');
        unreadDot.setAttribute('data-dk-request-unread', '');
        unreadDot.className = 'dk-unread-dot';
        if (!unread) {
          unreadDot.setAttribute('hidden', '');
        }
        item.appendChild(dot);
        item.appendChild(methodTag);
        item.appendChild(body);
        item.appendChild(unreadDot);
        picker.appendChild(item);
      });
    });
}

export function handlePickerClick(target, ctx) {
  if (target.hasAttribute('data-dk-request-trigger')) {
    const scope = target.getAttribute('data-dk-request-trigger');
    const picker = ctx.root.querySelector(
      '[data-dk-request-picker="' + scope + '"]',
    );
    if (picker) {
      const willOpen = picker.hasAttribute('hidden');
      closeAllPickers(ctx, false);
      if (willOpen) {
        try {
          if (typeof ctx.shell.toggleRequestPicker === 'function') {
            ctx.shell.toggleRequestPicker(scope, target);
          } else if (typeof ctx.shell.openRequestPicker === 'function') {
            ctx.shell.openRequestPicker(scope, target);
          }
        } catch (error) {}
        picker.removeAttribute('hidden');
        target.setAttribute('aria-expanded', 'true');
        fitPopupInViewport(picker);
        if (isToolbarScope(scope) && isEmbeddedFrame()) {
          ctx.setFrame('picker');
          notifyParent('picker');
        }
      } else {
        shrinkAfterPicker(ctx, scope);
      }
    }

    return true;
  }
  if (target.hasAttribute('data-dk-request-option')) {
    const id = target.getAttribute('data-dk-request-option');
    closeAllPickers(ctx, false);
    try {
      ctx.state.swapKeepKey = 'request';
      try {
        if (ctx.shell && ctx.shell.selected) {
          ctx.state.swapKeepKey = ctx.shell.selected;
        }
      } catch (error) {}
      const selected = ctx.shell.selectRequest(
        id,
        ctx.state.swapKeepKey && ctx.state.swapKeepKey !== 'request'
          ? { inspector: ctx.state.swapKeepKey }
          : {},
      );
      const afterSelect = function (res) {
        refreshRequestOptions(ctx);
        syncTriggerLabels(ctx);
        if (res && res.same === true) {
          ctx.openInspector('request');

          return;
        }
        if (res && !res.pending && res.id) {
          ctx.swapRequestPanels(res.id);
        }
      };
      if (selected && typeof selected.then === 'function') {
        selected.then(afterSelect);
      } else {
        afterSelect(selected);
      }
    } catch (error) {}

    return true;
  }
  if (target.hasAttribute('data-dk-theme-trigger')) {
    const menuScope = target.getAttribute('data-dk-theme-trigger');
    const menu = ctx.root.querySelector(
      '[data-dk-theme-options="' + menuScope + '"]',
    );
    if (menu) {
      togglePicker(ctx, target, menu);
      if (
        !menu.hasAttribute('hidden') &&
        ctx.root.getAttribute('data-dk-frame') === 'bar' &&
        isEmbeddedFrame()
      ) {
        ctx.setFrame('picker');
        notifyParent('picker');
      }
    }

    return true;
  }
  if (target.hasAttribute('data-dk-theme-option')) {
    try {
      ctx.shell.setTheme(target.getAttribute('data-dk-theme-option'));
      ctx.shell.applyTheme(ctx.root);
    } catch (error) {}
    closeAllPickers(ctx, false);

    return true;
  }
  if (target.hasAttribute('data-dk-mobile-trigger')) {
    const popover = ctx.root.querySelector(
      '[data-dk-mobile-popover="' +
        target.getAttribute('data-dk-mobile-trigger') +
        '"]',
    );
    if (popover) {
      togglePicker(ctx, target, popover);
      if (
        !popover.hasAttribute('hidden') &&
        ctx.root.getAttribute('data-dk-frame') === 'bar' &&
        isEmbeddedFrame()
      ) {
        ctx.setFrame('picker');
        notifyParent('picker');
      }
    }

    return true;
  }

  return false;
}

/**
 * Moves focus across request picker options with the keyboard.
 *
 * Arrow keys walk the open dropdown, Home/End jump to its edges, and
 * Enter/Space activates the focused row through the same click path.
 * Plain ArrowUp/ArrowDown (no Shift — that pair belongs to the nav
 * groups) work from an option or from an open trigger, which jumps
 * straight into the list.
 *
 * @param {object} event Keydown event.
 * @param {object} ctx Shell context.
 * @return {boolean} True when the key was consumed.
 */
export function handlePickerKey(event, ctx) {
  const key = event ? event.key : null;
  if (
    key !== 'ArrowDown' &&
    key !== 'ArrowUp' &&
    key !== 'Home' &&
    key !== 'End' &&
    key !== 'Enter' &&
    key !== ' '
  ) {
    return false;
  }
  if (
    event.shiftKey === true ||
    event.ctrlKey === true ||
    event.metaKey === true ||
    event.altKey === true
  ) {
    return false;
  }
  const target = event.target || null;
  const closest =
    target && typeof target.closest === 'function'
      ? function (selector) {
          try {
            return target.closest(selector);
          } catch (error) {
            return null;
          }
        }
      : function () {
          return null;
        };

  const focusOption = function (option) {
    try {
      if (option && typeof option.focus === 'function') {
        option.focus();
      }
    } catch (error) {}
  };

  const option = closest('[data-dk-request-option]');
  if (option !== null) {
    const picker = closest('[data-dk-request-picker]');
    if (picker === null || picker.hasAttribute('hidden')) {
      return false;
    }
    const options = Array.prototype.slice.call(
      picker.querySelectorAll('[data-dk-request-option]'),
    );
    if (options.length === 0) {
      return false;
    }
    if (key === 'Enter' || key === ' ') {
      try {
        event.preventDefault();
      } catch (error) {}
      handlePickerClick(option, ctx);

      return true;
    }
    const current = options.indexOf(option);
    let next = current;
    if (key === 'ArrowDown') {
      next = (current + 1) % options.length;
    } else if (key === 'ArrowUp') {
      next = (current - 1 + options.length) % options.length;
    } else if (key === 'Home') {
      next = 0;
    } else {
      next = options.length - 1;
    }
    try {
      event.preventDefault();
    } catch (error) {}
    focusOption(options[next]);
    try {
      if (ctx.shell) {
        ctx.shell.requestPickerIndex = next;
      }
    } catch (error) {}

    return true;
  }

  const trigger = closest('[data-dk-request-trigger]');
  if (trigger !== null && (key === 'ArrowDown' || key === 'ArrowUp')) {
    const scope = trigger.getAttribute('data-dk-request-trigger');
    const picker = ctx.root.querySelector(
      '[data-dk-request-picker="' + scope + '"]',
    );
    if (picker === null || picker.hasAttribute('hidden')) {
      return false;
    }
    const options = Array.prototype.slice.call(
      picker.querySelectorAll('[data-dk-request-option]'),
    );
    if (options.length === 0) {
      return false;
    }
    try {
      event.preventDefault();
    } catch (error) {}
    const next = key === 'ArrowDown' ? 0 : options.length - 1;
    focusOption(options[next]);
    try {
      if (ctx.shell) {
        ctx.shell.requestPickerIndex = next;
      }
    } catch (error) {}

    return true;
  }

  return false;
}

/**
 * Reloads the recent list in place, keeping the refresh control.
 *
 * Lets long-lived pages (queue workers landing new rows) catch up
 * without a full reload: refetches recent summaries, rebuilds picker
 * options with unread states, and preserves the refresh row itself.
 *
 * @param {object} target Clicked element.
 * @param {object} ctx Shell context.
 * @return {boolean} False when the target carries no refresh hook.
 */
export function handleRequestsRefreshClick(target, ctx) {
  if (!target.hasAttribute('data-dk-requests-refresh')) {
    return false;
  }

  if (!ctx.shell || typeof ctx.shell.loadRecentRequests !== 'function') {
    return true;
  }

  try {
    target.setAttribute('aria-busy', 'true');
  } catch (error) {}
  const done = function () {
    try {
      target.removeAttribute('aria-busy');
    } catch (error) {}
  };
  const loaded = ctx.shell.loadRecentRequests();
  const settle = function () {
    done();
    refreshRequestPickers(ctx);
    refreshRequestOptions(ctx);
    syncTriggerLabels(ctx);
    // Fresh rows change the picker height — re-fit every open picker
    // so the list never ends up off-screen after a reload.
    ctx.root
      .querySelectorAll('[data-dk-request-picker]')
      .forEach(function (picker) {
        if (!picker.hasAttribute('hidden')) {
          fitPopupInViewport(picker);
        }
      });
  };
  if (loaded && typeof loaded.then === 'function') {
    loaded.then(settle, done);
  } else {
    settle();
  }

  return true;
}

/**
 * Opens a related request resolved through a lookup URL.
 *
 * Third-party contract for cross-row links (e.g. queue push to worker
 * run): the element renders `data-dk-request-link` with a URL answering
 * `{request_id, inspector?}` JSON, the shell switches to that request in
 * place through the same path as the request picker. Unknown ids and
 * lookup failures resolve to no navigation.
 *
 * @param {object} target Clicked element.
 * @param {object} ctx Shell context.
 * @return {boolean} False when the target carries no lookup URL.
 */
export function handleRequestLinkClick(target, ctx) {
  if (!target.hasAttribute('data-dk-request-link')) {
    return false;
  }

  const url = target.getAttribute('data-dk-request-link') || '';
  if (
    url === '' ||
    !ctx.shell ||
    typeof ctx.shell.selectRequest !== 'function'
  ) {
    return true;
  }

  try {
    target.setAttribute('aria-busy', 'true');
  } catch (error) {}

  const done = function () {
    try {
      target.removeAttribute('aria-busy');
    } catch (error) {}
  };
  const afterSelect = function (res) {
    done();
    refreshRequestOptions(ctx);
    syncTriggerLabels(ctx);
    if (res && res.same === true) {
      ctx.openInspector('request');

      return;
    }
    if (res && !res.pending && res.id) {
      ctx.swapRequestPanels(res.id);
    }
  };
  fetch(url, { headers: { Accept: 'application/json' } })
    .then(function (response) {
      if (!response || response.ok !== true) {
        throw new Error('request-link');
      }

      return response.json();
    })
    .then(function (payload) {
      const id =
        payload && typeof payload.request_id === 'string'
          ? payload.request_id
          : '';
      if (id === '') {
        done();

        return;
      }

      const inspector =
        payload &&
        typeof payload.inspector === 'string' &&
        payload.inspector !== ''
          ? payload.inspector
          : '';
      try {
        ctx.state.swapKeepKey = inspector !== '' ? inspector : 'request';
      } catch (error) {}
      const options = inspector !== '' ? { inspector: inspector } : {};
      const selected = ctx.shell.selectRequest(id, options);
      if (selected && typeof selected.then === 'function') {
        selected.then(afterSelect, done);
      } else {
        afterSelect(selected);
      }
    })
    .catch(done);

  return true;
}
