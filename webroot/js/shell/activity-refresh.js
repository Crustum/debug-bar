import { recentRequestsUrl } from './requests.js';

export const ACTIVITY_POLL_LIMIT = 30;
export const ACTIVITY_POLL_INTERVAL = 1000;
export const ACTIVITY_REFRESH_ERROR =
  'Background activity could not be refreshed. Showing the last captured details.';
export const ACTIVITY_RELOAD_INSPECTORS = [
  'timeline',
  'queue',
  'mail',
  'notifications',
];
const ACTIVITY_COMPARED_KEYS = [
  'background_pending',
  'background_error',
  'background_activity_count',
  'related_profile_ids',
];

export function hasPendingActivity(summary = null, error = null) {
  if (summary?.completion_state === 'terminating') return true;
  if (summary?.background_pending === true) return true;
  if (Boolean(summary?.background_error) === true) return true;
  if (error && error.profileId === summary?.id) return true;

  return false;
}

export function activityChanged(previous = null, next = null) {
  const before = previous ?? {};
  const after = next ?? {};

  return ACTIVITY_COMPARED_KEYS.some(
    (key) =>
      JSON.stringify(before[key] ?? null) !==
      JSON.stringify(after[key] ?? null),
  );
}

export function findActivityEntry(payload = null, id = null) {
  if (typeof id !== 'string' || id === '') return null;
  const list = Array.isArray(payload?.requests) ? payload.requests : [];

  return list.find((entry) => entry?.id === id) ?? null;
}

export function createActivityPart(context = {}) {
  const browser = context.browser ?? {};
  const fetchClient = context.fetchClient ?? null;
  const summary = context.summary ?? {};

  const readBrowser = (shell) =>
    shell?.browser && typeof shell.browser === 'object'
      ? shell.browser
      : browser;
  const readClient = (shell) =>
    typeof shell?.fetchClient === 'function' ? shell.fetchClient : fetchClient;
  const readSummary = (shell) => shell?.summary ?? summary;
  const readProfileId = (shell) =>
    shell?.currentProfileId ?? readSummary(shell)?.id ?? null;

  return {
    activityPollAttempts: 0,
    activityPollTimer: null,
    activityRefreshPending: false,
    activityRefreshError: null,

    get backgroundActivityError() {
      const profileId = readProfileId(this);
      if (
        this.activityRefreshError !== null &&
        this.activityRefreshError !== undefined
      ) {
        if (this.activityRefreshError.profileId === profileId) {
          return this.activityRefreshError.message;
        }
      }

      return readSummary(this)?.background_error ?? null;
    },

    hasPendingActivity(summaryOverride = null) {
      const target = summaryOverride ?? readSummary(this);

      return hasPendingActivity(target, this.activityRefreshError);
    },

    cancelActivityRefresh(reset = false) {
      readBrowser(this).cancelSchedule?.(this.activityPollTimer);
      this.activityPollTimer = null;
      if (reset === true) this.activityPollAttempts = 0;

      return true;
    },

    scheduleActivityRefresh(reset = false) {
      if (reset === true) this.cancelActivityRefresh(true);
      if (
        this.inspectorOpen !== true ||
        this.hasPendingActivity() !== true ||
        this.activityRefreshPending === true ||
        this.activityPollTimer !== null ||
        (this.activityPollAttempts ?? 0) >= ACTIVITY_POLL_LIMIT
      ) {
        return false;
      }
      const shell = this;
      this.activityPollTimer =
        typeof readBrowser(this).schedule === 'function'
          ? (readBrowser(this).schedule(() => {
              shell.activityPollTimer = null;
              shell.refreshBackgroundActivity();
            }, ACTIVITY_POLL_INTERVAL) ?? null)
          : null;

      return this.activityPollTimer !== null;
    },

    async refreshBackgroundActivity(reset = false) {
      if (reset === true) this.cancelActivityRefresh(true);
      if (this.inspectorOpen !== true || this.activityRefreshPending === true)
        return null;
      if (
        reset !== true &&
        (this.hasPendingActivity() !== true ||
          (this.activityPollAttempts ?? 0) >= ACTIVITY_POLL_LIMIT)
      ) {
        return null;
      }
      const client = readClient(this);
      if (typeof client !== 'function') return null;

      const profileId = readProfileId(this);
      this.activityPollAttempts = (this.activityPollAttempts ?? 0) + 1;
      this.activityRefreshPending = true;

      let payload = null;
      try {
        const response = await client(recentRequestsUrl());
        payload =
          response && typeof response.json === 'function'
            ? await response.json()
            : response;
      } catch {
        return this.failActivityRefresh(profileId);
      }

      if (profileId !== readProfileId(this)) {
        this.activityRefreshPending = false;

        return null;
      }

      const entry = findActivityEntry(payload, profileId);
      if (entry === null) {
        this.activityRefreshPending = false;
        this.scheduleActivityRefresh();

        return null;
      }

      return this.receiveActivityRefresh(entry);
    },

    failActivityRefresh(profileId = null) {
      const current = readProfileId(this);
      this.activityRefreshPending = false;
      if (profileId !== null && profileId !== current) return null;
      this.activityRefreshError = {
        profileId: current,
        message: ACTIVITY_REFRESH_ERROR,
      };
      this.scheduleActivityRefresh();

      return null;
    },

    receiveActivityRefresh(entry = null, relatedProfiles = []) {
      const profileId = readProfileId(this);
      if (!entry || entry.id !== profileId) return null;

      const previous = readSummary(this) ?? {};
      const nextSummary = { ...previous, ...entry };
      const changed = activityChanged(previous, nextSummary);
      const related = Array.isArray(relatedProfiles) ? relatedProfiles : [];
      const knownIds = new Set(
        (this.recentRequests ?? []).map((known) => known?.id),
      );
      const relatedChanged = related.some(
        (profile) =>
          typeof profile?.id === 'string' && !knownIds.has(profile.id),
      );

      this.summary = nextSummary;
      this.activityRefreshError = null;
      this.activityRefreshPending = false;
      if (typeof this.rememberRequest === 'function')
        this.rememberRequest(this.summary);
      for (const profile of related) {
        if (typeof this.receiveRequest === 'function')
          this.receiveRequest(profile);
        else if (typeof this.rememberRequest === 'function')
          this.rememberRequest(profile);
      }

      let reloaded = false;
      if (
        ACTIVITY_RELOAD_INSPECTORS.includes(this.selected) &&
        (changed === true || relatedChanged === true) &&
        this.inspectorOpen === true &&
        this.loadedInspector === this.selected &&
        this.inspectorLoading !== true &&
        typeof this.requestInspector === 'function'
      ) {
        try {
          const reloadedResult = this.requestInspector(this.selected, {
            force: true,
          });
          if (reloadedResult && typeof reloadedResult.catch === 'function') {
            reloadedResult.catch(() => {});
          }
          reloaded = true;
        } catch {
          reloaded = false;
        }
      }

      if (this.hasPendingActivity(nextSummary)) this.scheduleActivityRefresh();
      else this.cancelActivityRefresh();

      return { changed, relatedChanged, reloaded };
    },
  };
}
