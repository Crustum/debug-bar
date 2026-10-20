export const DK_PROFILE_HEADER = 'X-DEBUGBAR-ID';
export const DK_OWN_PATH_PREFIX = '/debug-bar/';
export const DK_PROFILE_ID_MAX_LENGTH = 256;
const XHR_URL = Symbol('debugBarUrl');

export function isProfileId(value) {
  return (
    typeof value === 'string' &&
    value.trim() !== '' &&
    value.length <= DK_PROFILE_ID_MAX_LENGTH
  );
}

export function discoveryEligible(input, location = null) {
  try {
    if (
      !location ||
      typeof location.origin !== 'string' ||
      location.origin === ''
    ) {
      return false;
    }
    const raw =
      typeof input === 'string' || input instanceof URL
        ? String(input)
        : input?.url;
    if (typeof raw !== 'string' || raw === '') return false;
    const url =
      typeof location.href === 'string' && location.href !== ''
        ? new URL(raw, location.href)
        : new URL(raw);
    if (url.origin !== location.origin) return false;

    return !url.pathname.startsWith(DK_OWN_PATH_PREFIX);
  } catch {
    return false;
  }
}

export function responseHeaderId(headers) {
  if (!headers) return null;
  if (typeof headers.get === 'function') {
    try {
      return headers.get(DK_PROFILE_HEADER);
    } catch {
      return null;
    }
  }
  if (Array.isArray(headers)) {
    const pair = headers.find(
      ([key]) => String(key).toLowerCase() === DK_PROFILE_HEADER.toLowerCase(),
    );

    return pair?.[1] ?? null;
  }
  if (typeof headers === 'object') {
    const key = Object.keys(headers).find(
      (candidate) =>
        candidate.toLowerCase() === DK_PROFILE_HEADER.toLowerCase(),
    );

    return key ? headers[key] : null;
  }

  return null;
}

export function announceDiscoveredProfile(shell, profileId, detail = {}) {
  if (!shell || !isProfileId(profileId)) return null;
  const facts = detail && typeof detail === 'object' ? detail : {};

  try {
    if (
      facts.foreground === true &&
      typeof shell.switchRequest === 'function'
    ) {
      return settleAnnouncement(shell.switchRequest(profileId, facts));
    }
    if (typeof shell.noticeRequest === 'function') {
      return settleAnnouncement(shell.noticeRequest(profileId, facts));
    }
    if (typeof shell.discoverProfile === 'function') {
      return settleAnnouncement(shell.discoverProfile(profileId, facts));
    }
  } catch {
    return null;
  }

  return null;
}

function settleAnnouncement(result) {
  if (result && typeof result.catch === 'function') result.catch(() => {});

  return result ?? true;
}

function resolveShell(hooks) {
  if (typeof hooks === 'function') {
    try {
      return hooks();
    } catch {
      return null;
    }
  }

  return hooks ?? null;
}

function notifyDiscovered(runtime, hooks, response, transport, eligible) {
  try {
    if (eligible !== true) return;
    const location = runtime?.location ?? null;
    if (response?.url) {
      try {
        const seen = new URL(response.url, location?.href ?? undefined);
        if (!location || seen.origin !== location.origin) return;
      } catch {
        return;
      }
    }
    const profileId = responseHeaderId(response?.headers);
    if (!isProfileId(profileId)) return;
    announceDiscoveredProfile(resolveShell(hooks), profileId.trim(), {
      transport,
      foreground: false,
    });
  } catch {}
}

function notifyAfterHostUpdate(runtime, notify) {
  if (typeof runtime?.setTimeout === 'function') {
    try {
      runtime.setTimeout(notify, 0);

      return;
    } catch {}
  }
  notify();
}

export function defaultDiscoveryRuntime() {
  if (typeof window !== 'undefined') return window;
  if (typeof globalThis !== 'undefined' && globalThis?.location)
    return globalThis;

  return null;
}

export function installRequestDiscovery(runtime = null, hooks = null) {
  const target = runtime ?? defaultDiscoveryRuntime();
  if (!target) return false;
  if (target.__dkRequestDiscovery === true) return true;
  target.__dkRequestDiscovery = true;

  if (typeof target.fetch === 'function') {
    const originalFetch = target.fetch;
    target.fetch = function debugBarFetch(input, init) {
      const eligible = discoveryEligible(input, target.location);

      return originalFetch
        .apply(this, [input, init].slice(0, arguments.length))
        .then((response) => {
          notifyAfterHostUpdate(target, () =>
            notifyDiscovered(target, hooks, response, 'fetch', eligible),
          );

          return response;
        });
    };
  }

  const prototype = target.XMLHttpRequest?.prototype;
  if (prototype?.open && prototype?.send) {
    const originalOpen = prototype.open;
    const originalSend = prototype.send;

    prototype.open = function debugBarOpen(_method, url) {
      this[XHR_URL] = url;

      return originalOpen.apply(this, arguments);
    };

    prototype.send = function debugBarSend() {
      const eligible = discoveryEligible(this[XHR_URL], target.location);
      const request = this;
      this.addEventListener?.(
        'loadend',
        () =>
          notifyAfterHostUpdate(target, () =>
            notifyDiscovered(
              target,
              hooks,
              {
                url: request.responseURL,
                headers: { get: (name) => request.getResponseHeader?.(name) },
              },
              'xhr',
              eligible,
            ),
          ),
        { once: true },
      );

      return originalSend.apply(this, arguments);
    };
  }

  return true;
}

export function createDiscoveryPart() {
  return {
    requestDiscoveryInstalled: false,

    startRequestDiscovery(runtime = null, hooks = null) {
      const target = runtime ?? defaultDiscoveryRuntime();
      if (!target) return false;
      const installed = installRequestDiscovery(target, hooks ?? this);
      if (installed === true) this.requestDiscoveryInstalled = true;

      return installed;
    },
  };
}
