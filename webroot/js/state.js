import { composeState, defaultRuntime } from './runtime.js';
import { createPanelRegistry } from './registry.js';
import { createInspectorPart } from './shell/inspector.js';
import { createToolbarPart } from './shell/toolbar.js';
import { createNavigationPart } from './shell/navigation.js';
import { createRequestsPart } from './shell/requests.js';
import { createPreferencesPart } from './shell/preferences.js';
import { createPalettePart } from './shell/palette.js';
import { createCopyPart } from './shell/copy.js';
import { createActivityPart } from './shell/activity-refresh.js';
import { createDiscoveryPart } from './shell/discovery.js';

export const SHELL_EVENTS = {
  profileSwitched: 'debugbar:profile-switched',
  profileNoticed: 'debugbar:profile-noticed',
  profileRefreshed: 'debugbar:profile-refreshed',
  inspectorLoaded: 'debugbar:inspector-loaded',
  contentUpdated: 'debugbar:content-updated',
  profileDiscovered: 'debugbar:profile-discovered',
};

export const SHELL_ROOT_ID = 'debugbar';

function defaultFetchClient() {
  if (typeof fetch === 'function') return fetch.bind(globalThis);

  return null;
}

function findRoot() {
  if (
    typeof document === 'undefined' ||
    typeof document.getElementById !== 'function'
  ) {
    return null;
  }

  return document.getElementById(SHELL_ROOT_ID);
}

function dispatchCustomEvent(target, name, detail) {
  if (!target || typeof target.dispatchEvent !== 'function') return false;
  if (typeof CustomEvent !== 'function') return false;
  target.dispatchEvent(new CustomEvent(name, { detail, bubbles: true }));

  return true;
}

function createEventBus() {
  if (typeof EventTarget === 'function') return new EventTarget();

  return null;
}

function isRecord(value) {
  return Boolean(value) && typeof value === 'object';
}

export function createShell(summary = {}, options = {}) {
  const input = summary ?? {};
  const browser = options.runtime ?? defaultRuntime();
  const fetchClient = options.fetchClient ?? defaultFetchClient();
  const parts = Array.isArray(options.parts) ? options.parts : [];
  const context = { browser, fetchClient, summary: input, shell: null };
  if (Array.isArray(options.recentRequests))
    context.recentRequests = options.recentRequests;
  else if (Array.isArray(input.recent)) context.recentRequests = input.recent;
  if (Number.isInteger(options.requestLimit) && options.requestLimit > 0) {
    context.requestLimit = options.requestLimit;
  }
  const registry = options.registry ?? createPanelRegistry(context);
  const shellParts = [
    createInspectorPart(context),
    createToolbarPart(context),
    createNavigationPart(context),
    createRequestsPart(context),
    createPreferencesPart(context),
    createPalettePart(context),
    createCopyPart(context),
    createActivityPart(context),
    createDiscoveryPart(),
    ...parts,
  ];
  const bus = createEventBus();

  let currentProfileId = input.id ?? null;
  const noticedProfileIds = new Set(
    Array.isArray(input.noticedIds) ? input.noticedIds : [],
  );

  const emit = (name, detail) => {
    let delivered = dispatchCustomEvent(bus, name, detail);
    const root = findRoot();
    if (root && dispatchCustomEvent(root, name, detail)) delivered = true;

    return delivered;
  };

  const observe = (name, listener) => {
    const handler = (event) => listener(event.detail, event);
    if (bus) bus.addEventListener(name, handler);

    return () => {
      if (bus) bus.removeEventListener(name, handler);
    };
  };

  const base = {
    summary: input,
    registry,
    browser,
    fetchClient,
    events: { ...SHELL_EVENTS },
    get currentProfileId() {
      return currentProfileId;
    },
    get noticedProfileIds() {
      return [...noticedProfileIds];
    },
    emit(eventName, detail) {
      return emit(eventName, detail);
    },
    on(eventName, listener) {
      return observe(eventName, listener);
    },
    async fetchJson(url, init) {
      if (typeof fetchClient !== 'function') {
        throw new Error('DebugBar shell has no fetch client.');
      }
      const response = await fetchClient(url, init);
      if (response && typeof response.json === 'function')
        return response.json();

      return response;
    },
    switchProfile(id, profile = null) {
      if (id !== undefined && id !== null) currentProfileId = id;
      const detail = { profileId: currentProfileId };
      if (profile !== null && profile !== undefined) detail.profile = profile;
      this.emit(SHELL_EVENTS.profileSwitched, detail);

      return currentProfileId;
    },
    noticeProfile(id, meta = null) {
      if (id === undefined || id === null) return null;
      noticedProfileIds.add(id);
      const detail = { profileId: id };
      if (meta !== null && meta !== undefined) detail.meta = meta;
      this.emit(SHELL_EVENTS.profileNoticed, detail);

      return id;
    },
    refreshProfile(detail = null) {
      const payload = { profileId: currentProfileId };
      if (detail !== null && detail !== undefined) payload.detail = detail;
      this.emit(SHELL_EVENTS.profileRefreshed, payload);

      return payload;
    },
    markInspectorLoaded(key, detail = null) {
      const payload = { key, profileId: currentProfileId };
      if (detail !== null && detail !== undefined) payload.detail = detail;
      this.emit(SHELL_EVENTS.inspectorLoaded, payload);

      return payload;
    },
    markContentUpdated(detail = null) {
      const payload = isRecord(detail) ? { ...detail } : {};
      if (payload.profileId === undefined) payload.profileId = currentProfileId;
      this.emit(SHELL_EVENTS.contentUpdated, payload);

      return payload;
    },
    discoverProfile(profileId, meta = null) {
      if (typeof profileId !== 'string' || profileId === '') return null;
      const payload = { profileId };
      if (isRecord(meta)) Object.assign(payload, meta);
      this.emit(SHELL_EVENTS.profileDiscovered, payload);

      return payload;
    },
    createPanel(type, panelOptions = {}) {
      return registry.create(type, {
        profileId: currentProfileId,
        ...panelOptions,
      });
    },
  };

  const shell = composeState(...shellParts, base);
  context.shell = shell;

  return shell;
}
