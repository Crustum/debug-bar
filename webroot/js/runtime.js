export const DK_TOOLBAR_PLACEMENTS = [
  'top-left',
  'top',
  'top-right',
  'bottom-left',
  'bottom',
  'bottom-right',
];

export function dkToolbarHorizontal(placement) {
  if (typeof placement === 'string' && placement.endsWith('-left'))
    return 'left';
  if (typeof placement === 'string' && placement.endsWith('-right'))
    return 'right';

  return 'center';
}

export function dkToolbarVertical(placement) {
  if (typeof placement === 'string' && placement.startsWith('top'))
    return 'top';

  return 'bottom';
}

export function resolveToolbarPlacement(options = {}) {
  const {
    preferred = 'bottom',
    toolbarWidth = 0,
    toolbarHeight = 0,
    viewportWidth = 0,
    viewportHeight = 0,
    dialogs = [],
  } = options;
  const validPreferred = DK_TOOLBAR_PLACEMENTS.includes(preferred)
    ? preferred
    : 'bottom';
  const width = Math.min(toolbarWidth, Math.max(0, viewportWidth - 24));
  const height = toolbarHeight;
  const horizontal = dkToolbarHorizontal(validPreferred);
  const left =
    horizontal === 'left'
      ? 12
      : horizontal === 'right'
        ? Math.max(12, viewportWidth - width - 12)
        : (viewportWidth - width) / 2;
  const topPlacement = horizontal === 'center' ? 'top' : `top-${horizontal}`;
  const bottomPlacement =
    horizontal === 'center' ? 'bottom' : `bottom-${horizontal}`;
  const candidates = {
    [topPlacement]: {
      left,
      right: left + width,
      top: 12,
      bottom: 12 + height,
    },
    [bottomPlacement]: {
      left,
      right: left + width,
      top: viewportHeight - height - 12,
      bottom: viewportHeight - 12,
    },
  };
  const overlap = (candidate) =>
    dialogs.reduce((area, dialog) => {
      const widthOverlap = Math.max(
        0,
        Math.min(candidate.right, dialog.right) -
          Math.max(candidate.left, dialog.left),
      );
      const heightOverlap = Math.max(
        0,
        Math.min(candidate.bottom, dialog.bottom) -
          Math.max(candidate.top, dialog.top),
      );

      return area + widthOverlap * heightOverlap;
    }, 0);

  const topOverlap = overlap(candidates[topPlacement]);
  const bottomOverlap = overlap(candidates[bottomPlacement]);

  if (topOverlap === bottomOverlap) return validPreferred;

  return topOverlap < bottomOverlap ? topPlacement : bottomPlacement;
}

export function decodeInspectorPayload(encoded) {
  const text = typeof encoded === 'string' ? encoded.trim() : '';
  if (!text) return null;

  return JSON.parse(
    new TextDecoder().decode(
      Uint8Array.from(atob(text), (character) => character.charCodeAt(0)),
    ),
  );
}

export function readInspectorPayload(root, selector) {
  const encoded = root?.querySelector?.(selector)?.textContent?.trim();
  if (!encoded) return null;

  return decodeInspectorPayload(encoded);
}

export async function writeTextToClipboard(value) {
  if (
    typeof window !== 'undefined' &&
    typeof window.navigator !== 'undefined' &&
    window.navigator.clipboard &&
    typeof window.navigator.clipboard.writeText === 'function'
  ) {
    try {
      await window.navigator.clipboard.writeText(value);

      return true;
    } catch {}
  }

  return copyWithHiddenTextarea(value);
}

function copyWithHiddenTextarea(value) {
  if (typeof document === 'undefined' || !document.body) return false;

  const target = document.createElement('textarea');
  const activeElement = document.activeElement;
  const selection =
    typeof document.getSelection === 'function'
      ? document.getSelection()
      : null;
  const selectedRanges =
    selection && typeof selection.rangeCount === 'number'
      ? Array.from({ length: selection.rangeCount }, (_, index) =>
          selection.getRangeAt(index),
        )
      : [];

  target.value = value;
  target.readOnly = true;
  target.tabIndex = -1;
  target.style.setProperty('all', 'initial', 'important');
  target.style.setProperty('display', 'block', 'important');
  target.style.setProperty('position', 'fixed', 'important');
  target.style.setProperty('inset', '0 auto auto 0', 'important');
  target.style.setProperty('width', '1px', 'important');
  target.style.setProperty('height', '1px', 'important');
  target.style.setProperty('padding', '0', 'important');
  target.style.setProperty('border', '0', 'important');
  target.style.setProperty('opacity', '0', 'important');
  target.style.setProperty('pointer-events', 'none', 'important');

  document.body.append(target);
  target.focus({ preventScroll: true });
  target.select();

  let copied = false;

  try {
    copied =
      typeof document.execCommand === 'function' &&
      document.execCommand('copy') === true;
  } catch {
  } finally {
    target.remove();
    if (activeElement && typeof activeElement.focus === 'function') {
      activeElement.focus({ preventScroll: true });
    }

    if (selection && typeof selection.removeAllRanges === 'function') {
      try {
        selection.removeAllRanges();
        selectedRanges.forEach((range) => selection.addRange(range));
      } catch {}
    }
  }

  return copied;
}

export function composeState(...parts) {
  return Object.defineProperties(
    {},
    Object.assign(
      {},
      ...parts.map((part) => Object.getOwnPropertyDescriptors(part)),
    ),
  );
}

function memoryStorage() {
  const store = new Map();

  return {
    getItem: (key) => (store.has(key) ? store.get(key) : null),
    setItem: (key, value) => {
      store.set(String(key), String(value));
    },
  };
}

function safeStorage() {
  const fallback = memoryStorage();

  return {
    getItem: (key) => {
      try {
        return window.localStorage.getItem(key);
      } catch {
        return fallback.getItem(key);
      }
    },
    setItem: (key, value) => {
      try {
        window.localStorage.setItem(key, value);
      } catch {
        fallback.setItem(key, value);
      }
    },
  };
}

export function defaultRuntime(overrides = {}) {
  const runtime = {
    storage: safeStorage(),
    matchMedia: (query) => window.matchMedia(query),
    activeElement: () => document.activeElement,
    queryAll: (selector) => document.querySelectorAll(selector),
    writeClipboard: writeTextToClipboard,
    highlight: (root) =>
      window.DebugBarHighlight?.(root ?? document.getElementById('debugbar')),
    afterPaint: (callback) =>
      window.requestAnimationFrame(() =>
        window.requestAnimationFrame(callback),
      ),
    nextFrame: (callback) => window.requestAnimationFrame(callback),
    schedule: (callback, delay) => window.setTimeout(callback, delay),
    cancelSchedule: (timer) => window.clearTimeout(timer),
    now: () => Date.now(),
    viewportWidth: () => window.innerWidth,
    viewportHeight: () => window.innerHeight,
    lockHost: (root) => {
      if (!root || root.__dkHostLock) return;

      const body = document.body;
      const scrollbarWidth = Math.max(
        0,
        window.innerWidth - document.documentElement.clientWidth,
      );
      const previous = {
        overflow: body.style.overflow,
        paddingRight: body.style.paddingRight,
        inert: [],
      };

      [...body.children].forEach((element) => {
        if (
          element === root ||
          element.contains(root) ||
          !(element instanceof HTMLElement) ||
          element.matches('script, style, link')
        )
          return;

        previous.inert.push([element, element.inert]);
        element.inert = true;
      });

      body.style.overflow = 'hidden';

      if (scrollbarWidth > 0) {
        body.style.paddingRight = `${Number.parseFloat(window.getComputedStyle(body).paddingRight || '0') + scrollbarWidth}px`;
      }

      root.__dkHostLock = previous;
    },
    unlockHost: (root) => {
      const previous = root?.__dkHostLock;
      if (!previous) return;

      document.body.style.overflow = previous.overflow;
      document.body.style.paddingRight = previous.paddingRight;
      previous.inert.forEach(([element, inert]) => {
        element.inert = inert;
      });
      delete root.__dkHostLock;
    },
    toolbarPlacement: (root, preferred = 'bottom') => {
      const toolbar = root?.querySelector?.('[data-dk-toolbar-shell]');
      if (!toolbar) return 'bottom';

      const toolbarBox = toolbar.getBoundingClientRect();
      const dialogs = [
        ...document.querySelectorAll('dialog[open], [role="dialog"]'),
      ]
        .filter((dialog) => !root.contains(dialog))
        .map((dialog) => ({ dialog, box: dialog.getBoundingClientRect() }))
        .filter(({ dialog, box }) => {
          const style = window.getComputedStyle(dialog);

          return (
            box.width > 0 &&
            box.height > 0 &&
            style.display !== 'none' &&
            style.visibility !== 'hidden'
          );
        })
        .map(({ box }) => box);

      return resolveToolbarPlacement({
        preferred,
        toolbarWidth: toolbarBox.width,
        toolbarHeight: toolbarBox.height,
        viewportWidth: window.innerWidth,
        viewportHeight: window.innerHeight,
        dialogs,
      });
    },
    watchHostDialogs: (_root, callback) => {
      let frame = null;
      const schedule = () => {
        if (frame !== null) return;
        frame = window.requestAnimationFrame(() => {
          frame = null;
          callback();
        });
      };
      const observer = new MutationObserver(schedule);
      observer.observe(document.body, {
        attributes: true,
        attributeFilter: [
          'open',
          'aria-hidden',
          'aria-modal',
          'class',
          'style',
        ],
        childList: true,
        subtree: true,
      });
      window.addEventListener('resize', schedule);
      window.addEventListener('scroll', schedule, true);

      return () => {
        observer.disconnect();
        window.removeEventListener('resize', schedule);
        window.removeEventListener('scroll', schedule, true);
        if (frame !== null) window.cancelAnimationFrame(frame);
      };
    },
    observeNearEnd: (target, scrollOwner, callback) => {
      if (!target || !scrollOwner || typeof callback !== 'function')
        return null;

      if (typeof window.IntersectionObserver === 'function') {
        const observer = new window.IntersectionObserver(
          (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) callback();
          },
          {
            root: scrollOwner,
            rootMargin: '0px 0px 160px 0px',
            threshold: 0,
          },
        );
        observer.observe(target);

        return () => observer.disconnect();
      }

      let frame = null;
      const check = () => {
        frame = null;
        if (!target.isConnected || !scrollOwner.isConnected) return;

        const targetBox = target.getBoundingClientRect();
        const ownerBox = scrollOwner.getBoundingClientRect();

        if (
          targetBox.top <= ownerBox.bottom + 160 &&
          targetBox.bottom >= ownerBox.top
        )
          callback();
      };
      const schedule = () => {
        if (frame !== null) return;
        frame = window.requestAnimationFrame(check);
      };

      scrollOwner.addEventListener('scroll', schedule, { passive: true });
      window.addEventListener('resize', schedule);
      schedule();

      return () => {
        scrollOwner.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', schedule);
        if (frame !== null) window.cancelAnimationFrame(frame);
      };
    },
  };

  return { ...runtime, ...overrides };
}
