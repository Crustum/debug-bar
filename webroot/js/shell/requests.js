import { DEFAULT_INSPECTOR, sanitizeInspectorKey } from './navigation.js';

export const MAX_RECENT_REQUESTS = 20;
export const RECENT_REQUESTS_URL = '/debug-bar/requests/recent.json';
export const REQUEST_PICKER_SCOPES = [
  'toolbar',
  'corner',
  'header',
  'header-mobile',
];

export function requestPanelUrl(panelId) {
  return `/debug-bar/panels/view/${encodeURIComponent(String(panelId))}.json`;
}

export function requestToolbarUrl(requestId) {
  if (typeof requestId !== 'string' || requestId === '') return null;

  return `/debug-bar/toolbar/${encodeURIComponent(requestId)}`;
}

export function requestPanelsUrl(requestId) {
  if (typeof requestId !== 'string' || requestId === '') return null;

  return `/debug-bar/requests/panels/${encodeURIComponent(requestId)}.json`;
}

export function recentRequestsUrl(limit = null) {
  if (Number.isInteger(limit) && limit > 0)
    return `${RECENT_REQUESTS_URL}?limit=${limit}`;

  return RECENT_REQUESTS_URL;
}

export function mergeRecentRequests(
  existing = [],
  incoming = [],
  limit = MAX_RECENT_REQUESTS,
) {
  const base = [
    ...(Array.isArray(incoming) ? incoming : []),
    ...(Array.isArray(existing) ? existing : []),
  ];
  const seen = new Set();
  const merged = [];
  for (const entry of base) {
    const id = entry?.id;
    if (typeof id !== 'string' || id === '' || seen.has(id)) continue;
    seen.add(id);
    merged.push(entry);
  }

  return merged.slice(0, Math.max(0, limit));
}

export function seedRecentRequests(
  summary = null,
  extra = [],
  limit = MAX_RECENT_REQUESTS,
) {
  const candidates = [summary, ...(Array.isArray(extra) ? extra : [])].filter(
    (entry) => entry && typeof entry.id === 'string' && entry.id !== '',
  );

  return mergeRecentRequests([], candidates, limit);
}

export function isRequestUnread(entry, currentId, viewedIds = []) {
  if (!entry || typeof entry.id !== 'string') return false;

  return entry.id !== currentId && !(viewedIds ?? []).includes(entry.id);
}

export function requestStatusClass(status) {
  const code = Number(status);
  if (code >= 500) return 'dk:text-red-600 dk:dark:text-red-300';
  if (code >= 400) return 'dk:text-amber-600 dk:dark:text-amber-300';
  if (code >= 300) return 'dk:text-sky-600 dk:dark:text-sky-300';
  if (code >= 200) return 'dk:text-emerald-600 dk:dark:text-emerald-300';

  return 'dk:text-zinc-500 dk:dark:text-zinc-400';
}

export function requestStatusTone(status) {
  const code = parseInt(status, 10) || 0;
  if (code >= 500) return 'bad';
  if (code >= 400) return 'warn';

  return 'good';
}

export function relativeRequestTime(entry, now = Date.now()) {
  const recordedAt = Date.parse(
    entry?.requested_at ?? entry?.recorded_at ?? '',
  );
  if (!Number.isFinite(recordedAt)) return entry?.recorded_time ?? '';

  const seconds = Math.max(0, Math.floor((now - recordedAt) / 1000));
  if (seconds < 5) return 'now';
  if (seconds < 60) return `${seconds}s ago`;

  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;

  return entry?.recorded_time ?? entry?.requested_at ?? '';
}

export function pickerNextIndex(index, direction, length) {
  if (!Number.isInteger(length) || length <= 0) return -1;
  const current = Number.isInteger(index) && index >= 0 ? index : 0;

  return (((current + direction) % length) + length) % length;
}

export function pickerEdgeIndex(edge, length) {
  if (!Number.isInteger(length) || length <= 0) return -1;

  return edge === 'end' ? length - 1 : 0;
}

export const HOST_AJAX_MESSAGE_PREFIX = 'ajax-completed$$';

export function hostAjaxRequestId(data) {
  if (typeof data !== 'string' || !data.startsWith(HOST_AJAX_MESSAGE_PREFIX))
    return null;
  let params = null;
  try {
    params = JSON.parse(data.slice(HOST_AJAX_MESSAGE_PREFIX.length));
  } catch {
    return null;
  }
  const id = params?.requestId;

  return typeof id === 'string' && id !== '' ? id : null;
}

export function requestOptionId(option) {
  if (!option) return null;
  const datasetId = option.dataset?.dkRequestId ?? option.dataset?.dkProfileId;
  if (typeof datasetId === 'string' && datasetId !== '') return datasetId;
  const attribute =
    typeof option.getAttribute === 'function'
      ? (option.getAttribute('data-dk-request-option') ?? null)
      : null;

  return typeof attribute === 'string' && attribute !== '' ? attribute : null;
}

export function createRequestsPart(context = {}) {
  const browser = context.browser ?? {};
  const fetchClient = context.fetchClient ?? null;
  const summary = context.summary ?? {};

  const readClient = (shell) =>
    typeof shell?.fetchClient === 'function' ? shell.fetchClient : fetchClient;
  const readBrowser = (shell) =>
    shell?.browser && typeof shell.browser === 'object'
      ? shell.browser
      : browser;
  const readSummary = (shell) => shell?.summary ?? summary;
  const readLimit = (shell) =>
    Number.isInteger(shell?.recentLimit) && shell.recentLimit > 0
      ? shell.recentLimit
      : MAX_RECENT_REQUESTS;
  const unpend = (shell, id) => {
    shell.pendingRequestIds = (shell.pendingRequestIds ?? []).filter(
      (pending) => pending !== id,
    );
  };

  return {
    recentRequests: seedRecentRequests(
      summary,
      context.recentRequests,
      Number.isInteger(context.requestLimit) && context.requestLimit > 0
        ? context.requestLimit
        : MAX_RECENT_REQUESTS,
    ),
    currentRequestId: typeof summary?.id === 'string' ? summary.id : null,
    viewedRequestIds:
      summary && typeof summary.id === 'string' && summary.id !== ''
        ? [summary.id]
        : [],
    pendingRequestIds: [],
    requestSelectionPending: null,
    requestPickerScope: null,
    requestPickerReturnFocus: null,
    requestPickerIndex: 0,
    recentLimit:
      Number.isInteger(context.requestLimit) && context.requestLimit > 0
        ? context.requestLimit
        : MAX_RECENT_REQUESTS,

    get laterRequests() {
      return (this.recentRequests ?? []).filter(
        (entry) => entry?.id !== this.currentRequestId,
      );
    },

    get laterRequestCount() {
      return this.laterRequests.length;
    },

    get hasOtherRequests() {
      return this.laterRequestCount > 0;
    },

    get unreadRequestCount() {
      return this.laterRequests.filter((entry) =>
        isRequestUnread(entry, this.currentRequestId, this.viewedRequestIds),
      ).length;
    },

    get requestBadgeCount() {
      return this.unreadRequestCount > 9
        ? '9+'
        : String(this.unreadRequestCount);
    },

    get currentRequest() {
      return (
        (this.recentRequests ?? []).find(
          (entry) => entry?.id === this.currentRequestId,
        ) ?? readSummary(this)
      );
    },

    get requestPickerButtonLabel() {
      if (this.hasOtherRequests !== true) return 'No later requests yet';
      if (this.unreadRequestCount === 0) return 'Choose request';

      return `Choose request, ${this.unreadRequestCount} unread ${this.unreadRequestCount === 1 ? 'request' : 'requests'}`;
    },

    requestIsUnread(entry) {
      return isRequestUnread(
        entry,
        this.currentRequestId,
        this.viewedRequestIds,
      );
    },

    workerInspector(id) {
      if (typeof id !== 'string' || id === '') return null;
      let found = null;
      try {
        found =
          (this.recentRequests ?? []).find((entry) => entry?.id === id) ?? null;
      } catch {
        found = null;
      }
      // Job rows open on the queue inspector: the method names them,
      // never a URL prefix.
      if (found && found.method === 'JOB') return 'queue';

      return null;
    },

    rememberRequest(entry) {
      if (!entry || typeof entry.id !== 'string' || entry.id === '')
        return false;
      this.recentRequests = mergeRecentRequests(
        this.recentRequests,
        [entry],
        readLimit(this),
      );
      const present = new Set(
        (this.recentRequests ?? []).map((known) => known?.id),
      );
      this.viewedRequestIds = (this.viewedRequestIds ?? []).filter((id) =>
        present.has(id),
      );

      return true;
    },

    receiveRequest(entry) {
      if (!entry || typeof entry.id !== 'string' || entry.id === '')
        return false;
      unpend(this, entry.id);

      return this.rememberRequest(entry);
    },

    async loadRecentRequests({ limit = null } = {}) {
      const client = readClient(this);
      if (typeof client !== 'function') return null;
      let payload = null;
      try {
        const response = await client(recentRequestsUrl(limit));
        payload =
          response && typeof response.json === 'function'
            ? await response.json()
            : response;
      } catch {
        return null;
      }
      const list = Array.isArray(payload?.requests) ? payload.requests : null;
      if (list === null) return null;
      this.recentRequests = mergeRecentRequests(
        this.recentRequests,
        list,
        readLimit(this),
      );
      const present = new Set(
        (this.recentRequests ?? []).map((known) => known?.id),
      );
      this.viewedRequestIds = (this.viewedRequestIds ?? []).filter((id) =>
        present.has(id),
      );

      return this.recentRequests;
    },

    async switchRequest(id, options = {}) {
      if (typeof id !== 'string' || id === '') return null;
      let known =
        (this.recentRequests ?? []).find((entry) => entry?.id === id) ?? null;
      if (known === null) {
        await this.loadRecentRequests();
        known =
          (this.recentRequests ?? []).find((entry) => entry?.id === id) ?? null;
      }
      if (known === null) return null;

      let panel = null;
      if (options.panelId !== undefined && options.panelId !== null) {
        const client = readClient(this);
        if (typeof client === 'function') {
          try {
            const response = await client(requestPanelUrl(options.panelId));
            panel =
              response && typeof response.json === 'function'
                ? await response.json()
                : response;
          } catch {
            panel = null;
          }
        }
      }

      this.summary = { ...known };
      if (
        (this.summary.inspectors === undefined ||
          this.summary.inspectors === null) &&
        Array.isArray(summary?.inspectors)
      ) {
        this.summary.inspectors = summary.inspectors;
      }
      this.currentRequestId = id;
      this.viewedRequestIds = [
        ...new Set([...(this.viewedRequestIds ?? []), id]),
      ];
      this.requestSelectionPending = null;
      unpend(this, id);
      this.requestPickerScope = null;
      this.requestPickerReturnFocus = null;
      this.inspectorRequestVersion = (this.inspectorRequestVersion ?? 0) + 1;
      this.loadedInspector = null;
      this.requestedInspector = null;
      this.inspectorLoading = false;
      this.inspectorError = false;
      const keys = Array.isArray(this.inspectorKeys)
        ? this.inspectorKeys
        : null;
      this.selected = sanitizeInspectorKey(
        options.inspector ?? DEFAULT_INSPECTOR,
        keys,
      );
      if (typeof this.switchProfile === 'function')
        this.switchProfile(id, panel ?? known);
      if (typeof this.markContentUpdated === 'function') {
        this.markContentUpdated({ profileId: id });
      }

      return known;
    },

    async noticeRequest(id, options = {}) {
      if (typeof id !== 'string' || id === '') return null;
      if (
        id === this.currentRequestId ||
        (this.recentRequests ?? []).some((entry) => entry?.id === id) ||
        (this.pendingRequestIds ?? []).includes(id)
      ) {
        return null;
      }
      this.pendingRequestIds = [...(this.pendingRequestIds ?? []), id];

      try {
        if (options.foreground === true)
          return await this.switchRequest(id, options);
        await this.loadRecentRequests();
        const found =
          (this.recentRequests ?? []).find((entry) => entry?.id === id) ?? null;
        if (found === null) {
          unpend(this, id);

          return null;
        }
        if (typeof this.noticeProfile === 'function')
          this.noticeProfile(id, found);

        return found;
      } catch {
        unpend(this, id);

        return null;
      }
    },

    async selectRequest(id, options = {}) {
      if (typeof id !== 'string' || id === '') return null;
      if (typeof this.closeRequestPicker === 'function')
        this.closeRequestPicker(false);
      if (id === this.currentRequestId) {
        if (typeof this.openInspector === 'function')
          this.openInspector('request');

        return { same: true, id };
      }
      if (this.requestSelectionPending === id) return { pending: true, id };
      this.requestSelectionPending = id;
      try {
        // Worker rows open on the queue inspector: their request panel
        // only carries the run headline, the job status lives in queue.
        const resolved = { ...(options ?? {}) };
        if (
          (resolved.inspector === undefined || resolved.inspector === null) &&
          this.workerInspector(id) !== null
        ) {
          resolved.inspector = this.workerInspector(id);
        }
        const known = await this.switchRequest(id, resolved);
        this.requestSelectionPending = null;
        if (known === null) return null;

        return known;
      } catch {
        if (this.requestSelectionPending === id)
          this.requestSelectionPending = null;

        return null;
      }
    },

    openRequestPicker(scope, returnFocus = null) {
      if (!REQUEST_PICKER_SCOPES.includes(scope)) return null;
      if (this.hasOtherRequests !== true) return null;
      if (['toolbar', 'corner'].includes(scope) && this.inspectorOpen === true)
        return null;
      if (
        ['header', 'header-mobile'].includes(scope) &&
        this.inspectorOpen !== true
      )
        return null;
      this.requestPickerReturnFocus =
        returnFocus ?? readBrowser(this).activeElement?.() ?? null;
      this.requestPickerScope = scope;
      this.requestPickerIndex = 0;

      return scope;
    },

    closeRequestPicker(restoreFocus = true) {
      if (
        this.requestPickerScope === null ||
        this.requestPickerScope === undefined
      )
        return false;
      const returnFocus = this.requestPickerReturnFocus;
      this.requestPickerScope = null;
      this.requestPickerReturnFocus = null;
      if (restoreFocus === true) {
        const shellBrowser = readBrowser(this);
        const focus = () => returnFocus?.focus?.();
        if (typeof shellBrowser.afterPaint === 'function')
          shellBrowser.afterPaint(focus);
        else focus();
      }

      return true;
    },

    toggleRequestPicker(scope, returnFocus = null) {
      if (this.requestPickerScope === scope) {
        this.closeRequestPicker();

        return null;
      }

      return this.openRequestPicker(scope, returnFocus);
    },

    moveRequestPicker(direction, options = []) {
      const list = [...(options ?? [])];
      if (list.length === 0) return -1;
      const next = pickerNextIndex(
        this.requestPickerIndex ?? 0,
        direction,
        list.length,
      );
      this.requestPickerIndex = next;
      list[next]?.focus?.();

      return next;
    },

    focusRequestPickerEdge(edge, options = []) {
      const list = [...(options ?? [])];
      if (list.length === 0) return -1;
      const next = pickerEdgeIndex(edge, list.length);
      this.requestPickerIndex = next;
      list[next]?.focus?.();

      return next;
    },
  };
}
