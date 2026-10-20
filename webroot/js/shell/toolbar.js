import {
  DK_TOOLBAR_PLACEMENTS,
  dkToolbarHorizontal,
  dkToolbarVertical,
  resolveToolbarPlacement,
} from '../runtime.js';

export const TOOLBAR_DRAG_THRESHOLD = 6;
export const TOOLBAR_VIEWPORT_MARGIN = 12;
export const TOOLBAR_STORAGE_KEY = 'dk.toolbar.preferredPlacement';
export const TOOLBAR_SNAP_DELAY = 500;
export const TOOLBAR_CLICK_SUPPRESS_DELAY = 250;

export function toolbarPreviewTarget(dragging, target) {
  if (dragging !== true) return null;
  if (!DK_TOOLBAR_PLACEMENTS.includes(target)) return null;

  return target;
}

export function sanitizeToolbarPlacement(placement, fallback = 'bottom') {
  if (DK_TOOLBAR_PLACEMENTS.includes(placement)) return placement;
  if (DK_TOOLBAR_PLACEMENTS.includes(fallback)) return fallback;

  return 'bottom';
}

export function toolbarTargetAt(
  clientX,
  clientY,
  viewportWidth,
  viewportHeight,
) {
  const width = Math.max(1, viewportWidth);
  const height = Math.max(1, viewportHeight);
  const vertical = clientY < height / 2 ? 'top' : 'bottom';
  const horizontal =
    clientX < width / 3
      ? 'left'
      : clientX > (width * 2) / 3
        ? 'right'
        : 'center';

  return horizontal === 'center' ? vertical : `${vertical}-${horizontal}`;
}

export function clampToolbarPosition(
  left,
  top,
  width,
  height,
  viewportWidth,
  viewportHeight,
) {
  return {
    left: Math.min(
      Math.max(
        TOOLBAR_VIEWPORT_MARGIN,
        viewportWidth - width - TOOLBAR_VIEWPORT_MARGIN,
      ),
      Math.max(TOOLBAR_VIEWPORT_MARGIN, left),
    ),
    top: Math.min(
      Math.max(
        TOOLBAR_VIEWPORT_MARGIN,
        viewportHeight - height - TOOLBAR_VIEWPORT_MARGIN,
      ),
      Math.max(TOOLBAR_VIEWPORT_MARGIN, top),
    ),
  };
}

export function toolbarAnchorLeft(placement, width, viewportWidth) {
  const horizontal = dkToolbarHorizontal(placement);
  if (horizontal === 'left') return TOOLBAR_VIEWPORT_MARGIN;
  if (horizontal === 'right') {
    return Math.max(
      TOOLBAR_VIEWPORT_MARGIN,
      viewportWidth - width - TOOLBAR_VIEWPORT_MARGIN,
    );
  }

  return Math.max(TOOLBAR_VIEWPORT_MARGIN, (viewportWidth - width) / 2);
}

export function toolbarAnchorTop(placement, height, viewportHeight) {
  if (dkToolbarVertical(placement) === 'top') return TOOLBAR_VIEWPORT_MARGIN;

  return Math.max(
    TOOLBAR_VIEWPORT_MARGIN,
    viewportHeight - height - TOOLBAR_VIEWPORT_MARGIN,
  );
}

export function createToolbarPart(context = {}) {
  const browser = context.browser ?? {};

  const readBrowser = (shell) =>
    shell?.browser && typeof shell.browser === 'object'
      ? shell.browser
      : browser;
  const viewportWidth = (shell) => readBrowser(shell).viewportWidth?.() ?? 0;
  const viewportHeight = (shell) => readBrowser(shell).viewportHeight?.() ?? 0;

  return {
    toolbarPlacement: 'bottom',
    toolbarPreferredPlacement: 'bottom',
    toolbarDragging: false,
    toolbarRebasing: false,
    toolbarSnapping: false,
    toolbarDragPointerId: null,
    toolbarDragStartX: 0,
    toolbarDragStartY: 0,
    toolbarDragPointerOffsetX: 0,
    toolbarDragPointerOffsetY: 0,
    toolbarDragWidth: 0,
    toolbarDragHeight: 0,
    toolbarDragOffsetX: 0,
    toolbarDragOffsetY: 0,
    toolbarDragTarget: 'bottom',
    toolbarDragOriginPlacement: 'bottom',
    toolbarSuppressClick: false,
    toolbarSnapVersion: 0,
    toolbarSnapTimer: null,
    toolbarClickTimer: null,

    get toolbarIsCorner() {
      return this.toolbarPlacement.includes('-');
    },

    get toolbarIsTop() {
      return dkToolbarVertical(this.toolbarPlacement) === 'top';
    },

    get toolbarIsLeft() {
      return dkToolbarHorizontal(this.toolbarPlacement) === 'left';
    },

    get toolbarIsRight() {
      return dkToolbarHorizontal(this.toolbarPlacement) === 'right';
    },

    get toolbarVerticalPlacement() {
      return dkToolbarVertical(this.toolbarPlacement);
    },

    get toolbarHorizontalPlacement() {
      return dkToolbarHorizontal(this.toolbarPlacement);
    },

    persistToolbarPlacement() {
      try {
        readBrowser(this).storage?.setItem?.(
          TOOLBAR_STORAGE_KEY,
          this.toolbarPreferredPlacement,
        );
      } catch {
        return false;
      }

      return true;
    },

    restoreToolbarPlacement() {
      let stored = null;
      try {
        stored =
          readBrowser(this).storage?.getItem?.(TOOLBAR_STORAGE_KEY) ?? null;
      } catch {
        return null;
      }
      if (!DK_TOOLBAR_PLACEMENTS.includes(stored)) return null;
      this.toolbarPlacement = stored;
      this.toolbarPreferredPlacement = stored;
      this.toolbarDragTarget = stored;

      return stored;
    },

    syncToolbarPlacement(root = null) {
      if (
        this.toolbarDragging === true ||
        this.toolbarRebasing === true ||
        this.toolbarSnapping === true
      ) {
        return this.toolbarPlacement;
      }
      const shellBrowser = readBrowser(this);
      const resolved = shellBrowser.toolbarPlacement?.(
        root,
        this.toolbarPreferredPlacement,
      );
      if (!DK_TOOLBAR_PLACEMENTS.includes(resolved))
        return this.toolbarPlacement;
      if (resolved !== this.toolbarPlacement) this.moveToolbarTo(resolved);

      return this.toolbarPlacement;
    },

    startToolbarDrag(event = {}) {
      if ((this.barVisible ?? true) === false) return false;
      if (this.inspectorOpen === true) return false;
      if (
        this.toolbarDragPointerId !== null &&
        this.toolbarDragPointerId !== undefined
      ) {
        return false;
      }
      if (event.isPrimary === false) return false;
      if (
        (event.pointerType ?? 'mouse') === 'mouse' &&
        (event.button ?? 0) !== 0
      )
        return false;
      const box = event.currentTarget?.getBoundingClientRect?.() ?? null;
      if (!box || !(box.width > 0) || !(box.height > 0)) return false;

      const shellBrowser = readBrowser(this);
      shellBrowser.cancelSchedule?.(this.toolbarSnapTimer);
      this.toolbarSnapTimer = null;
      this.toolbarSnapVersion = (this.toolbarSnapVersion ?? 0) + 1;
      this.toolbarRebasing = true;
      this.toolbarSnapping = false;
      this.toolbarDragging = false;
      this.toolbarDragPointerId = event.pointerId ?? 0;
      this.toolbarDragStartX = event.clientX ?? 0;
      this.toolbarDragStartY = event.clientY ?? 0;
      this.toolbarDragPointerOffsetX = Math.min(
        Math.max((event.clientX ?? 0) - (box.left ?? 0), 0),
        box.width,
      );
      this.toolbarDragPointerOffsetY = Math.min(
        Math.max((event.clientY ?? 0) - (box.top ?? 0), 0),
        box.height,
      );
      this.toolbarDragWidth = box.width;
      this.toolbarDragHeight = box.height;
      this.toolbarDragTarget = this.toolbarPlacement;
      this.toolbarDragOriginPlacement = this.toolbarPlacement;

      return true;
    },

    moveToolbarDrag(event = {}) {
      if (
        this.toolbarDragPointerId === null ||
        this.toolbarDragPointerId === undefined
      ) {
        return false;
      }
      if (event.pointerId !== this.toolbarDragPointerId) return false;

      const distance = Math.hypot(
        (event.clientX ?? 0) - this.toolbarDragStartX,
        (event.clientY ?? 0) - this.toolbarDragStartY,
      );
      if (this.toolbarDragging !== true && distance < TOOLBAR_DRAG_THRESHOLD)
        return false;

      if (this.toolbarDragging !== true) {
        this.toolbarDragging = true;
        if (typeof this.closeRequestPicker === 'function')
          this.closeRequestPicker(false);
        event.currentTarget?.setPointerCapture?.(this.toolbarDragPointerId);
      }
      event.preventDefault?.();

      const clamped = clampToolbarPosition(
        (event.clientX ?? 0) - this.toolbarDragPointerOffsetX,
        (event.clientY ?? 0) - this.toolbarDragPointerOffsetY,
        this.toolbarDragWidth,
        this.toolbarDragHeight,
        viewportWidth(this),
        viewportHeight(this),
      );
      this.toolbarDragOffsetX =
        clamped.left -
        toolbarAnchorLeft(
          this.toolbarPlacement,
          this.toolbarDragWidth,
          viewportWidth(this),
        );
      this.toolbarDragOffsetY =
        clamped.top -
        toolbarAnchorTop(
          this.toolbarPlacement,
          this.toolbarDragHeight,
          viewportHeight(this),
        );
      this.toolbarDragTarget = toolbarTargetAt(
        event.clientX ?? 0,
        event.clientY ?? 0,
        viewportWidth(this),
        viewportHeight(this),
      );

      return true;
    },

    endToolbarDrag(event = {}) {
      if (
        this.toolbarDragPointerId === null ||
        this.toolbarDragPointerId === undefined
      ) {
        return false;
      }
      if (event.pointerId !== this.toolbarDragPointerId) return false;
      event.currentTarget?.releasePointerCapture?.(this.toolbarDragPointerId);
      this.toolbarDragPointerId = null;

      if (this.toolbarDragging !== true) {
        this.moveToolbarTo(this.toolbarPlacement);

        return 'reset';
      }
      this.toolbarDragging = false;
      this.suppressToolbarClick();
      this.moveToolbarTo(this.toolbarDragTarget, true);

      return this.toolbarDragTarget;
    },

    cancelToolbarDrag(event = {}) {
      if (
        this.toolbarDragPointerId === null ||
        this.toolbarDragPointerId === undefined
      ) {
        return false;
      }
      if (event.pointerId !== this.toolbarDragPointerId) return false;
      event.currentTarget?.releasePointerCapture?.(this.toolbarDragPointerId);
      this.toolbarDragPointerId = null;

      if (this.toolbarDragging !== true) {
        this.moveToolbarTo(this.toolbarPlacement);

        return 'reset';
      }
      this.toolbarDragging = false;
      this.suppressToolbarClick();
      this.moveToolbarTo(this.toolbarDragOriginPlacement, false);

      return this.toolbarDragOriginPlacement;
    },

    suppressToolbarClick() {
      const shellBrowser = readBrowser(this);
      this.toolbarSuppressClick = true;
      shellBrowser.cancelSchedule?.(this.toolbarClickTimer);
      this.toolbarClickTimer =
        typeof shellBrowser.schedule === 'function'
          ? (shellBrowser.schedule(() => {
              this.toolbarSuppressClick = false;
              this.toolbarClickTimer = null;
            }, TOOLBAR_CLICK_SUPPRESS_DELAY) ?? null)
          : null;

      return true;
    },

    consumeToolbarClick(event = {}) {
      if (this.toolbarSuppressClick !== true) return false;
      event.preventDefault?.();
      event.stopPropagation?.();
      this.toolbarSuppressClick = false;
      readBrowser(this).cancelSchedule?.(this.toolbarClickTimer);
      this.toolbarClickTimer = null;

      return true;
    },

    pinToolbar(placement) {
      if (!DK_TOOLBAR_PLACEMENTS.includes(placement))
        return this.toolbarPlacement;

      return this.moveToolbarTo(placement, true);
    },

    moveToolbarTo(placement, remember = false) {
      const shellBrowser = readBrowser(this);
      const target = sanitizeToolbarPlacement(placement, this.toolbarPlacement);
      const version = (this.toolbarSnapVersion ?? 0) + 1;
      this.toolbarSnapVersion = version;
      shellBrowser.cancelSchedule?.(this.toolbarSnapTimer);
      this.toolbarSnapTimer = null;

      if (remember === true) {
        this.toolbarPreferredPlacement = target;
        this.persistToolbarPlacement();
      }

      this.toolbarPlacement = target;
      this.toolbarDragTarget = target;
      this.toolbarRebasing = true;
      this.toolbarSnapping = false;

      const rebase = () => {
        if (version !== this.toolbarSnapVersion) return;
        this.toolbarRebasing = false;
        this.toolbarSnapping = true;
        const settle = () => this.finishToolbarSnap(version);
        const arm = () => {
          if (version !== this.toolbarSnapVersion) return;
          if (typeof shellBrowser.schedule === 'function') {
            this.toolbarSnapTimer =
              shellBrowser.schedule(settle, TOOLBAR_SNAP_DELAY) ?? null;
            if (this.toolbarSnapTimer !== null) return;
          }
          settle();
        };
        if (typeof shellBrowser.nextFrame === 'function')
          shellBrowser.nextFrame(arm);
        else arm();
      };
      if (typeof shellBrowser.afterPaint === 'function')
        shellBrowser.afterPaint(rebase);
      else rebase();

      return target;
    },

    finishToolbarSnap(version = null) {
      if (version !== null && version !== this.toolbarSnapVersion) return false;
      if (
        this.toolbarSnapping !== true ||
        this.toolbarRebasing === true ||
        this.toolbarDragging === true
      ) {
        return false;
      }
      readBrowser(this).cancelSchedule?.(this.toolbarSnapTimer);
      this.toolbarSnapTimer = null;
      this.toolbarDragOffsetX = 0;
      this.toolbarDragOffsetY = 0;
      this.toolbarRebasing = false;
      this.toolbarSnapping = false;
      this.syncToolbarPlacement();

      return true;
    },

    resolveToolbarPlacement,
  };
}
