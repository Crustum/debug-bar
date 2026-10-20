import {
  notifyParent,
  paintCodeBlocks,
  setHidden,
  setRowPressed,
} from './core.js';
import {
  buildClickSelector,
  findAutoEntryTarget,
  handleWorkspaceBackClick,
  registerPanelScripts,
  registerSelectionConfigs,
  resetFocusWorkspaces,
  selectPanelRecord,
} from './panels.js';
import {
  closeAllPickers,
  handlePickerClick,
  handlePickerKey,
  handleRequestLinkClick,
  handleRequestsRefreshClick,
  pickers,
} from './picker.js';
import { initDrag, syncToolbarForm } from './drag.js';
import { createShell } from '../../state.js';
import {
  renderNavGroups as renderShellNavGroups,
  syncNavCounts as syncShellNavCounts,
} from '../nav-groups.js';
import {
  closePalette,
  handlePaletteClick,
  handlePaletteKey,
  handlePaletteMessage,
  initPaletteSearch,
  openPalette,
  syncMiniToggle,
} from './palette.js';
import {
  handleCopyAllClick,
  handleCopyClick,
  handleExceptionClick,
  handleFilterClick,
  handleHostAjaxMessage,
  handleListChange,
  handleListInput,
  handleQueryClick,
  handleSortClick,
  handleTabsClick,
  handleTreeClick,
  initQueriesInspectors,
  swapRequestPanels,
} from './bridges.js';
import { handleAttributesClick } from '../panels/attributes.js';
import { handleCacheClearClick } from '../panels/cache.js';
import { handleMailClick } from '../panels/mail.js';
import { handlePackagesClick } from '../panels/packages.js';
import {
  handleVariablesClick,
  initVariablesPanels,
} from '../panels/variables.js';
import { handleViewsClick, initViewsPanels } from '../panels/views.js';

const root = document.getElementById('debugbar');
if (root) {
  let summary = {};
  try {
    const summaryEl = root.querySelector('[data-dk-summary]');
    summary = JSON.parse(summaryEl ? summaryEl.textContent : '{}') || {};
    if (typeof summary === 'string') {
      summary = JSON.parse(summary) || {};
    }
    if (!summary || typeof summary !== 'object' || Array.isArray(summary)) {
      summary = {};
    }
  } catch (error) {
    summary = {};
  }
  let recent = [];
  try {
    const recentEl = root.querySelector('[data-dk-recent]');
    const parsedRecent = JSON.parse(recentEl ? recentEl.textContent : '[]');
    if (typeof parsedRecent === 'string') {
      recent = JSON.parse(parsedRecent);
    }
    if (Array.isArray(parsedRecent)) {
      recent = parsedRecent;
    }
  } catch (error) {
    recent = [];
  }
  const shell = createShell(summary, {
    recentRequests: recent,
    requestLimit:
      Number.isInteger(summary.max_requests) && summary.max_requests > 0
        ? summary.max_requests
        : undefined,
  });
  try {
    shell.restorePreferences();
  } catch (error) {}
  try {
    const stored = shell.browser?.storage?.getItem?.('debugbar.preferences.v1');
    const parsed = stored ? JSON.parse(stored) : null;
    const hasStoredAction =
      parsed !== null &&
      typeof parsed === 'object' &&
      typeof parsed.miniAction === 'string';
    if (hasStoredAction !== true) {
      const rootAction = root.getAttribute('data-dk-mini-action');
      if (rootAction === 'bar' || rootAction === 'inspector') {
        shell.miniAction = rootAction;
      }
    }
  } catch (error) {}
  try {
    shell.restoreToolbarPlacement();
  } catch (error) {}
  try {
    shell.applyTheme(root);
  } catch (error) {}
  root.__dkShell = shell;

  const toolbar = root.querySelector('[data-dk-toolbar-shell]');
  const inspector = root.querySelector('[data-dk-inspector]');
  const dialog = root.querySelector('#debugbar-inspector');
  const mini = root.querySelector('[data-dk-mini-toggle]');

  function setFrame(mode) {
    root.setAttribute('data-dk-frame', mode);
  }

  function inspectorPanels() {
    return Array.prototype.slice.call(
      root.querySelectorAll('[data-dk-inspector-panel]'),
    );
  }

  function syncPanels() {
    try {
      shell.syncInspectorPanels(inspectorPanels());
    } catch (error) {}
    resetFocusWorkspaces(root);
  }

  function syncNavDom(key) {
    let selected = null;
    try {
      selected = shell.selectedInspector || null;
    } catch (error) {
      selected = null;
    }
    let label = null;
    let description = '';
    if (selected && selected.key === key) {
      label = selected.label || key;
      description = selected.description || '';
    }
    if (label === null) {
      const navItem = root.querySelector(
        '[data-dk-inspector-nav-item="' + key + '"]',
      );
      const navLabel = navItem ? navItem.querySelector('.dk-nav-label') : null;
      label = navLabel && navLabel.textContent ? navLabel.textContent : key;
    }
    root
      .querySelectorAll('[data-dk-inspector-nav-item]')
      .forEach(function (item) {
        const active = item.getAttribute('data-dk-inspector-nav-item') === key;
        item.setAttribute('aria-current', active ? 'page' : 'false');
      });
    const heading = root.querySelector('[data-dk-inspector-heading]');
    if (heading) {
      heading.textContent = label;
    }
    const desc = root.querySelector('[data-dk-inspector-description]');
    if (desc) {
      if (description) {
        desc.textContent = description;
        desc.removeAttribute('hidden');
      } else {
        desc.textContent = '';
        desc.setAttribute('hidden', '');
      }
    }
    const loadingLabel = root.querySelector(
      '[data-dk-inspector-loading-label]',
    );
    if (loadingLabel) {
      loadingLabel.textContent = String(label).toLowerCase();
    }
    const content = root.querySelector('[data-dk-inspector-content]');
    if (content) {
      content.scrollTop = 0;
    }
  }

  function renderNavGroups() {
    try {
      renderShellNavGroups(root, shell);
    } catch (error) {}
  }

  function syncNavCounts(panels) {
    try {
      syncShellNavCounts(root, panels);
    } catch (error) {}
  }

  function showMini() {
    setHidden(mini, false);
    if (toolbar) {
      toolbar.setAttribute('hidden', '');
    }
    setHidden(inspector, true);
    setFrame('mini');
  }

  function showBar() {
    setHidden(mini, true);
    if (toolbar) {
      toolbar.removeAttribute('hidden');
    }
    setHidden(inspector, true);
    setFrame('bar');
  }

  function openInspector(key) {
    const target = typeof key === 'string' && key !== '' ? key : 'request';
    try {
      shell.openInspector(target, null, dialog);
    } catch (error) {}
    setHidden(mini, true);
    setHidden(inspector, false);
    if (toolbar) {
      toolbar.setAttribute('hidden', '');
    }
    setFrame('inspector');
    notifyParent('expand');
    closeAllPickers(ctx, false);
    syncNavDom(target);
    syncPanels();
  }

  function closeInspector(restoreFocus) {
    try {
      shell.closeInspector();
    } catch (error) {}
    setHidden(mini, true);
    if (toolbar) {
      toolbar.removeAttribute('hidden');
    }
    setHidden(inspector, true);
    setFrame('bar');
    notifyParent('toolbar');
    if (restoreFocus !== false) {
      const trigger = root.querySelector('[data-dk-window="expand"]');
      if (trigger && typeof trigger.focus === 'function') {
        trigger.focus({ preventScroll: true });
      }
    }
  }

  function hideBar() {
    try {
      shell.closeInspector();
    } catch (error) {}
    showMini();
    notifyParent('collapse');
  }

  const ctx = {
    root,
    shell,
    summary,
    toolbar,
    inspector,
    dialog,
    mini,
    state: { swapKeepKey: 'request' },
    setFrame,
    openInspector,
    closeInspector,
    syncNavDom,
    syncPanels,
    renderNavGroups,
    syncNavCounts,
    swapRequestPanels: (id) => swapRequestPanels(ctx, id),
    syncToolbarForm: () => syncToolbarForm(ctx),
  };

  root.addEventListener('click', function (event) {
    let target =
      event.target instanceof Element
        ? event.target.closest(buildClickSelector())
        : null;
    if (!target && event.target instanceof Element) {
      target = findAutoEntryTarget(event.target, root);
    }
    if (!target || !root.contains(target)) {
      const paletteBackdrop =
        event.target instanceof Element
          ? event.target.closest('[data-dk-palette]')
          : null;
      if (
        paletteBackdrop &&
        event.target === paletteBackdrop &&
        !paletteBackdrop.hasAttribute('hidden')
      ) {
        closePalette(ctx);

        return;
      }
      const inOpenMenu =
        event.target instanceof Element
          ? event.target.closest(
              '[data-dk-request-picker], [data-dk-theme-options], [data-dk-mobile-popover]',
            )
          : null;
      if (!inOpenMenu) {
        closeAllPickers(ctx, false);
      }

      return;
    }
    if (target.hasAttribute('data-dk-mini-toggle')) {
      let action = 'bar';
      try {
        action = shell.miniAction === 'inspector' ? 'inspector' : 'bar';
      } catch (error) {
        action = 'bar';
      }
      if (action === 'inspector') {
        openInspector();
      } else {
        showBar();
        notifyParent('toolbar');
      }

      return;
    }
    if (target.hasAttribute('data-dk-backdrop')) {
      closeInspector();

      return;
    }
    if (target.hasAttribute('data-dk-open-inspector')) {
      openInspector(target.getAttribute('data-dk-open-inspector'));

      return;
    }
    if (handlePaletteClick(target, ctx)) {
      return;
    }
    if (target.hasAttribute('data-dk-window')) {
      const action = target.getAttribute('data-dk-window');
      if (action === 'expand') {
        openInspector();
      } else if (action === 'shrink') {
        closeInspector();
      } else {
        hideBar();
      }

      return;
    }
    if (handlePickerClick(target, ctx)) {
      return;
    }
    if (handleRequestsRefreshClick(target, ctx)) {
      return;
    }
    if (handleRequestLinkClick(target, ctx)) {
      return;
    }
    if (handleFilterClick(target, ctx)) {
      return;
    }
    if (handleTreeClick(target)) {
      return;
    }
    if (handleWorkspaceBackClick(target)) {
      return;
    }
    if (
      selectPanelRecord(target, root, {
        setRowPressed,
        paintCodeBlocks: (scope) => paintCodeBlocks(scope, root),
      })
    ) {
      return;
    }
    if (handleExceptionClick(target, ctx)) {
      return;
    }
    if (handleViewsClick(target, ctx)) {
      return;
    }
    if (handleVariablesClick(target, ctx)) {
      return;
    }
    if (handleCacheClearClick(target)) {
      return;
    }
    if (handlePackagesClick(target, ctx)) {
      return;
    }
    if (handleAttributesClick(target, ctx)) {
      return;
    }
    if (handleMailClick(target, ctx)) {
      return;
    }
    if (handleQueryClick(target, ctx)) {
      return;
    }
    if (handleSortClick(target)) {
      return;
    }
    if (handleTabsClick(target, ctx)) {
      return;
    }
    if (handleCopyClick(target, ctx)) {
      return;
    }
    if (handleCopyAllClick(target, ctx)) {
      return;
    }
    if (target.hasAttribute('data-dk-inspector-nav-item')) {
      const navKey = target.getAttribute('data-dk-inspector-nav-item');
      try {
        shell.selectInspector(navKey);
      } catch (error) {}
      syncNavDom(navKey);
      syncPanels();

      return;
    }
    if (target.hasAttribute('data-dk-inspector-favorite')) {
      try {
        shell.toggleFavorite(target.getAttribute('data-dk-inspector-favorite'));
        shell.persistPreferences();
      } catch (error) {}
      renderNavGroups();

      return;
    }
    if (target.hasAttribute('data-dk-inspector-retry')) {
      try {
        shell.retryInspector();
      } catch (error) {}

      return;
    }
    if (target.hasAttribute('data-dk-inspector-reload')) {
      window.location.reload();

      return;
    }
    if (target.hasAttribute('data-dk-activity-retry')) {
      try {
        shell.refreshBackgroundActivity(true);
      } catch (error) {}
    }
  });

  root.addEventListener('input', function (event) {
    handleListInput(event, ctx);
  });

  root.addEventListener('change', function (event) {
    handleListChange(event, ctx);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' || event.key === ' ') {
      const row =
        event.target instanceof Element
          ? event.target.closest(
              '[data-dk-model-entry],[data-dk-event-entry],[data-dk-authorization-entry],[data-dk-http-entry],[data-dk-log-entry],[data-dk-cache-entry],[data-dk-plugin-entry],[data-dk-route-entry],[data-dk-package-entry],[data-dk-mail-entry],[data-dk-exception-entry],[data-dk-view-entry],[data-dk-variable-entry],[data-dk-timeline-entry]',
            )
          : null;
      if (row && root.contains(row)) {
        event.preventDefault();
        row.click();

        return;
      }
    }
    if (event.key === 'Escape') {
      const palette = root.querySelector('[data-dk-palette]');
      if (palette && !palette.hasAttribute('hidden')) {
        closePalette(ctx);
        event.preventDefault();

        return;
      }
      const openPicker = pickers(ctx).some(function (picker) {
        return !picker.hasAttribute('hidden');
      });
      if (openPicker) {
        closeAllPickers(ctx, false);
        event.preventDefault();

        return;
      }
      if (inspector && !inspector.hasAttribute('hidden')) {
        closeInspector();
        event.preventDefault();

        return;
      }
      if (toolbar && !toolbar.hasAttribute('hidden')) {
        let embedded = false;
        try {
          embedded =
            typeof window !== 'undefined' &&
            typeof window.self !== 'undefined' &&
            typeof window.top !== 'undefined' &&
            window.self !== window.top;
        } catch (error) {
          embedded = false;
        }
        if (embedded) {
          hideBar();
          event.preventDefault();
        }

        return;
      }

      return;
    }
    if (handlePaletteKey(event, ctx)) {
      return;
    }
    if (handlePickerKey(event, ctx)) {
      return;
    }
    if (
      event.shiftKey === true &&
      (event.key === 'ArrowUp' || event.key === 'ArrowDown') &&
      event.target instanceof Element
    ) {
      const row = event.target.closest('[data-dk-nav-row]');
      const navItem =
        row && typeof row.querySelector === 'function'
          ? row.querySelector('[data-dk-inspector-nav-item]')
          : event.target.closest('[data-dk-inspector-nav-item]');
      if (navItem && root.contains(navItem)) {
        const navKey = navItem.getAttribute('data-dk-inspector-nav-item');
        const fromStar =
          event.target.closest('[data-dk-inspector-favorite]') !== null;
        let moved = false;
        try {
          if (shell && typeof shell.moveInspector === 'function') {
            moved = shell.moveInspector(
              navKey,
              event.key === 'ArrowUp' ? -1 : 1,
            );
          }
        } catch (error) {
          moved = false;
        }
        if (moved) {
          event.preventDefault();
          renderNavGroups();
          // Reordering moves rows with appendChild, which drops focus to
          // body in some browsers (seen in Firefox) — the next Shift+Arrow
          // then lands on body and is ignored. Put focus back on the same
          // control of the moved row so repeats keep working.
          try {
            const fresh =
              typeof root.querySelector === 'function'
                ? root.querySelector('[data-dk-nav-row="' + navKey + '"]')
                : null;
            const back =
              fresh && typeof fresh.querySelector === 'function'
                ? fresh.querySelector(
                    fromStar
                      ? '[data-dk-inspector-favorite]'
                      : '[data-dk-inspector-nav-item]',
                  )
                : null;
            if (back && typeof back.focus === 'function') back.focus();
          } catch (error) {}
        }

        return;
      }
    }
    const editing =
      event.target instanceof Element &&
      event.target.closest(
        'input, textarea, select, [contenteditable="true"]',
      ) !== null &&
      event.target.closest('[data-dk-palette]') === null;
    if (
      editing !== true &&
      (event.metaKey === true || event.ctrlKey === true) &&
      event.altKey === true &&
      event.shiftKey !== true &&
      event.code === 'KeyP'
    ) {
      event.preventDefault();
      const open = root.querySelector('[data-dk-palette]');
      if (open && !open.hasAttribute('hidden')) {
        closePalette(ctx);
      } else {
        openPalette(ctx);
      }
    }
  });

  syncPanels();
  renderNavGroups();
  syncToolbarForm(ctx);
  try {
    syncMiniToggle(ctx);
  } catch (error) {}
  registerSelectionConfigs(root);
  registerPanelScripts(root);
  initQueriesInspectors(ctx);
  initViewsPanels(ctx);
  initVariablesPanels(ctx);
  initPaletteSearch(ctx);
  initDrag(ctx);
  window.addEventListener('message', function (event) {
    if (handlePaletteMessage(event, ctx)) {
      return;
    }
    handleHostAjaxMessage(event, ctx);
  });
  if (window.self === window.top) {
    showBar();
    setFrame('full');
  } else {
    showMini();
  }
}
