import { DEFAULT_INSPECTOR, sanitizeInspectorKey } from './navigation.js';

export const INSPECTOR_SKELETON_DELAY = 200;
export const INSPECTOR_PANEL_ATTRIBUTE = 'data-dk-inspector-panel';
export const INSPECTOR_FOCUSABLE_SELECTOR =
  'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])';
export const INSPECTOR_HEADING_SELECTOR = '[data-dk-inspector-heading]';

export function inspectorPanelUrl(panelId) {
  return `/debug-bar/panels/view/${encodeURIComponent(String(panelId))}.json`;
}

export function resolveVisibleInspector({
  selected = null,
  loaded = null,
  loading = false,
} = {}) {
  if (loading === true) return loaded;

  return selected ?? loaded;
}

export function panelKeyOf(panel) {
  if (!panel) return null;
  const datasetKey = panel.dataset?.dkInspectorPanel;
  if (typeof datasetKey === 'string' && datasetKey !== '') return datasetKey;
  const attribute =
    typeof panel.getAttribute === 'function'
      ? panel.getAttribute(INSPECTOR_PANEL_ATTRIBUTE)
      : null;

  return typeof attribute === 'string' && attribute !== '' ? attribute : null;
}

function readPanelId(summary, key) {
  const panels = summary?.panels;
  if (!Array.isArray(panels)) return null;
  const match =
    panels.find((panel) => panel?.key === key) ??
    panels.find((panel) => panel?.id === key) ??
    panels.find(
      (panel) =>
        typeof panel?.title === 'string' &&
        typeof key === 'string' &&
        panel.title.toLowerCase() === key.toLowerCase(),
    );

  return typeof match?.id === 'string' && match.id !== '' ? match.id : null;
}

export function createInspectorPart(context = {}) {
  const browser = context.browser ?? {};
  const fetchClient = context.fetchClient ?? null;

  const readClient = (shell) =>
    typeof shell?.fetchClient === 'function' ? shell.fetchClient : fetchClient;
  const readBrowser = (shell) =>
    shell?.browser && typeof shell.browser === 'object'
      ? shell.browser
      : browser;
  const readKeys = (shell) =>
    Array.isArray(shell?.inspectorKeys) ? shell.inspectorKeys : null;
  const readPanels = (shell) =>
    Array.isArray(shell?.inspectorPanels) ? shell.inspectorPanels : [];
  const paint = (shell, callback) => {
    const shellBrowser = readBrowser(shell);
    if (typeof shellBrowser.afterPaint === 'function')
      shellBrowser.afterPaint(callback);
    else callback();
  };

  return {
    inspectorOpen: false,
    inspectorReturnFocus: null,
    loadedInspector: null,
    requestedInspector: null,
    inspectorRequestOptions: null,
    inspectorLoading: false,
    inspectorLoadingIndicator: false,
    inspectorLoadingTimer: null,
    inspectorTransitioning: false,
    inspectorError: false,
    inspectorRequestVersion: 0,
    inspectorPanels: null,

    clearInspectorLoadingIndicator() {
      readBrowser(this).cancelSchedule?.(this.inspectorLoadingTimer);
      this.inspectorLoadingTimer = null;
      this.inspectorLoadingIndicator = false;
    },

    scheduleInspectorSkeleton(version, target) {
      const shellBrowser = readBrowser(this);
      this.clearInspectorLoadingIndicator();
      const profileId = this.currentProfileId ?? null;
      const show = () => {
        this.inspectorLoadingTimer = null;
        if (
          version === this.inspectorRequestVersion &&
          (profileId === null ||
            profileId === (this.currentProfileId ?? null)) &&
          this.requestedInspector === target &&
          this.inspectorLoading === true
        ) {
          this.inspectorLoadingIndicator = true;
        }
      };
      this.inspectorLoadingTimer =
        typeof shellBrowser.schedule === 'function'
          ? (shellBrowser.schedule(show, INSPECTOR_SKELETON_DELAY) ?? null)
          : null;

      return this.inspectorLoadingTimer;
    },

    syncInspectorPanels(panels = readPanels(this)) {
      const visible = resolveVisibleInspector({
        selected: this.selected ?? DEFAULT_INSPECTOR,
        loaded: this.loadedInspector,
        loading: this.inspectorLoading,
      });
      for (const panel of panels ?? []) {
        const key = panelKeyOf(panel);
        if (key === null) continue;
        panel.hidden = key !== visible;
      }

      return visible;
    },

    openInspector(key = null, returnFocus = undefined, dialog = null) {
      const shellBrowser = readBrowser(this);
      const target = sanitizeInspectorKey(key ?? this.selected, readKeys(this));
      if (this.inspectorOpen !== true) {
        this.inspectorReturnFocus =
          returnFocus !== undefined
            ? returnFocus
            : (shellBrowser.activeElement?.() ?? null);
      }
      this.inspectorOpen = true;
      if (typeof this.selectInspector === 'function')
        this.selectInspector(target);
      else this.selected = target;
      paint(this, () => {
        const heading =
          dialog?.querySelector?.(INSPECTOR_HEADING_SELECTOR) ?? null;
        (heading ?? dialog)?.focus?.();
      });

      return target;
    },

    closeInspector() {
      if (this.inspectorOpen !== true) return false;
      const returnFocus = this.inspectorReturnFocus;
      this.inspectorOpen = false;
      this.inspectorReturnFocus = null;
      paint(this, () => returnFocus?.focus?.());

      return true;
    },

    failInspectorRequest() {
      this.inspectorLoading = false;
      this.inspectorTransitioning = false;
      this.inspectorError = true;
      this.clearInspectorLoadingIndicator();
      this.syncInspectorPanels();
    },

    receiveInspector(key, data = null) {
      this.loadedInspector = key;
      this.requestedInspector = null;
      this.inspectorRequestOptions = null;
      this.inspectorLoading = false;
      this.inspectorTransitioning = false;
      this.inspectorError = false;
      this.clearInspectorLoadingIndicator();
      this.syncInspectorPanels();
      if (typeof this.markInspectorLoaded === 'function')
        this.markInspectorLoaded(key);

      return { key, data };
    },

    async requestInspector(key = null, options = {}) {
      const target = sanitizeInspectorKey(key ?? this.selected, readKeys(this));
      const force = options.force === true;
      if (this.loadedInspector === target && force === false)
        return { key: target, cached: true };
      if (
        this.inspectorLoading === true &&
        this.requestedInspector === target &&
        force === false
      ) {
        return null;
      }

      const version = (this.inspectorRequestVersion ?? 0) + 1;
      this.inspectorRequestVersion = version;
      const profileId = this.currentProfileId ?? null;
      this.requestedInspector = target;
      this.inspectorRequestOptions = { ...options };
      this.inspectorLoading = true;
      this.inspectorTransitioning = true;
      this.inspectorError = false;
      this.scheduleInspectorSkeleton(version, target);
      this.syncInspectorPanels();

      const summary = this.summary ?? context.summary ?? {};
      const panelId = options.panelId ?? readPanelId(summary, target) ?? null;
      const url =
        options.url ?? (panelId === null ? null : inspectorPanelUrl(panelId));
      const client = readClient(this);
      if (url === null || typeof client !== 'function') {
        if (version !== this.inspectorRequestVersion) return null;

        return this.receiveInspectorLocal(target, version, profileId);
      }

      let payload = null;
      try {
        const response = await client(url);
        payload =
          response && typeof response.json === 'function'
            ? await response.json()
            : response;
      } catch {
        if (version !== this.inspectorRequestVersion) return null;
        this.failInspectorRequest();

        return null;
      }

      if (version !== this.inspectorRequestVersion) return null;
      if (
        profileId !== null &&
        this.currentProfileId !== undefined &&
        this.currentProfileId !== null &&
        profileId !== this.currentProfileId
      ) {
        return null;
      }

      return this.receiveInspector(target, payload);
    },

    receiveInspectorLocal(target, version, profileId = null) {
      if (version !== this.inspectorRequestVersion) return null;
      if (
        profileId !== null &&
        this.currentProfileId !== undefined &&
        this.currentProfileId !== null &&
        profileId !== this.currentProfileId
      ) {
        return null;
      }

      return this.receiveInspector(target, null);
    },

    retryInspector() {
      const target =
        this.requestedInspector ?? this.selected ?? DEFAULT_INSPECTOR;
      const options = { ...(this.inspectorRequestOptions ?? {}), force: true };

      return this.requestInspector(target, options);
    },

    keepFocusWithin(event, container) {
      if (!event || event.key !== 'Tab' || !container) return false;
      const nodes =
        container.querySelectorAll?.(INSPECTOR_FOCUSABLE_SELECTOR) ?? [];
      const focusable = [...nodes].filter(
        (element) =>
          element.hidden !== true &&
          (element.getClientRects?.().length ?? 1) > 0,
      );
      if (focusable.length === 0) return false;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      const active = readBrowser(this).activeElement?.() ?? null;
      const contains =
        typeof container.contains === 'function'
          ? container.contains(active)
          : false;
      if (event.shiftKey === true && (active === first || contains === false)) {
        event.preventDefault?.();
        last.focus?.();

        return true;
      }
      if (event.shiftKey !== true && (active === last || contains === false)) {
        event.preventDefault?.();
        first.focus?.();

        return true;
      }

      return false;
    },
  };
}
