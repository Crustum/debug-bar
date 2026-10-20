import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { createShell } from '../../../webroot/js/state.js';
import {
  createToolbarPart,
  toolbarTargetAt,
  clampToolbarPosition,
  toolbarAnchorLeft,
  toolbarAnchorTop,
  sanitizeToolbarPlacement,
  toolbarPreviewTarget,
  TOOLBAR_DRAG_THRESHOLD,
  TOOLBAR_VIEWPORT_MARGIN,
  TOOLBAR_STORAGE_KEY,
} from '../../../webroot/js/shell/toolbar.js';

function fakeBrowser(overrides = {}) {
  const scheduled = [];
  let nextId = 0;
  return {
    scheduled,
    schedule: (callback, delay) => {
      nextId += 1;
      scheduled.push({ id: nextId, callback, delay });

      return nextId;
    },
    cancelSchedule: (timer) => {
      const index = scheduled.findIndex((entry) => entry.id === timer);
      if (index !== -1) scheduled.splice(index, 1);
    },
    afterPaint: (callback) => callback(),
    nextFrame: (callback) => callback(),
    viewportWidth: () => 1200,
    viewportHeight: () => 800,
    storage: {
      values: {},
      getItem(key) {
        return this.values[key] ?? null;
      },
      setItem(key, value) {
        this.values[key] = value;
      },
    },
    ...overrides,
  };
}

function dragEvent(overrides = {}) {
  return {
    pointerId: 7,
    clientX: 100,
    clientY: 100,
    button: 0,
    pointerType: 'mouse',
    isPrimary: true,
    currentTarget: {
      getBoundingClientRect: () => ({
        left: 90,
        top: 90,
        width: 196,
        height: 56,
      }),
    },
    preventDefault: () => {},
    ...overrides,
  };
}

describe('toolbar placement math', () => {
  it('maps pointer positions to the 6 placements', () => {
    assert.equal(toolbarTargetAt(100, 100, 1200, 800), 'top-left');
    assert.equal(toolbarTargetAt(1100, 100, 1200, 800), 'top-right');
    assert.equal(toolbarTargetAt(600, 100, 1200, 800), 'top');
    assert.equal(toolbarTargetAt(100, 700, 1200, 800), 'bottom-left');
    assert.equal(toolbarTargetAt(1100, 700, 1200, 800), 'bottom-right');
    assert.equal(toolbarTargetAt(600, 700, 1200, 800), 'bottom');
    assert.equal(toolbarTargetAt(0, 0, 0, 0), 'top-left');
  });

  it('clamps dragged boxes to the 12px margins', () => {
    assert.deepEqual(clampToolbarPosition(600, 400, 196, 56, 1200, 800), {
      left: 600,
      top: 400,
    });
    assert.deepEqual(clampToolbarPosition(-50, -50, 196, 56, 1200, 800), {
      left: 12,
      top: 12,
    });
    assert.deepEqual(clampToolbarPosition(2000, 2000, 196, 56, 1200, 800), {
      left: 1200 - 196 - 12,
      top: 800 - 56 - 12,
    });
    assert.equal(TOOLBAR_VIEWPORT_MARGIN, 12);
  });

  it('anchors left/center/right and top/bottom', () => {
    assert.equal(toolbarAnchorLeft('top-left', 196, 1200), 12);
    assert.equal(toolbarAnchorLeft('top-right', 196, 1200), 1200 - 196 - 12);
    assert.equal(toolbarAnchorLeft('top', 200, 1200), 500);
    assert.equal(toolbarAnchorTop('top', 56, 800), 12);
    assert.equal(toolbarAnchorTop('bottom', 56, 800), 800 - 56 - 12);
  });

  it('sanitizes placements', () => {
    assert.equal(sanitizeToolbarPlacement('top-left', 'bottom'), 'top-left');
    assert.equal(sanitizeToolbarPlacement('nowhere', 'top'), 'top');
    assert.equal(sanitizeToolbarPlacement('nowhere', 'nowhere'), 'bottom');
  });

  it('exposes the drag threshold and storage key', () => {
    assert.equal(TOOLBAR_DRAG_THRESHOLD, 6);
    assert.equal(TOOLBAR_STORAGE_KEY, 'dk.toolbar.preferredPlacement');
  });

  it('resolves the visible anchor preview while dragging', () => {
    assert.equal(toolbarPreviewTarget(true, 'top-left'), 'top-left');
    assert.equal(toolbarPreviewTarget(true, 'bottom'), 'bottom');
    assert.equal(toolbarPreviewTarget(false, 'bottom'), null);
    assert.equal(toolbarPreviewTarget(true, 'nowhere'), null);
    assert.equal(toolbarPreviewTarget(true, null), null);
  });
});

describe('toolbar drag', () => {
  it('rejects drags that must not start', () => {
    const part = createToolbarPart({ browser: fakeBrowser() });
    assert.equal(
      part.startToolbarDrag({ ...dragEvent(), isPrimary: false }),
      false,
    );
    assert.equal(part.startToolbarDrag({ ...dragEvent(), button: 2 }), false);
    assert.equal(
      part.startToolbarDrag({
        ...dragEvent(),
        currentTarget: {
          getBoundingClientRect: () => ({ width: 0, height: 0 }),
        },
      }),
      false,
    );

    const openPart = createToolbarPart({ browser: fakeBrowser() });
    openPart.inspectorOpen = true;
    assert.equal(openPart.startToolbarDrag(dragEvent()), false);
  });

  it('requires a 6px move before dragging, then clamps and captures', () => {
    const captured = [];
    const part = createToolbarPart({ browser: fakeBrowser() });
    assert.equal(part.startToolbarDrag(dragEvent()), true);

    assert.equal(
      part.moveToolbarDrag({
        ...dragEvent(),
        pointerId: 99,
        clientX: 500,
        clientY: 500,
      }),
      false,
    );
    assert.equal(
      part.moveToolbarDrag(dragEvent({ clientX: 103, clientY: 102 })),
      false,
    );
    assert.equal(part.toolbarDragging, false);

    const moving = dragEvent({
      clientX: 500,
      clientY: 700,
      currentTarget: {
        getBoundingClientRect: () => ({}),
        setPointerCapture: (id) => captured.push(id),
      },
    });
    assert.equal(part.moveToolbarDrag(moving), true);
    assert.equal(part.toolbarDragging, true);
    assert.deepEqual(captured, [7]);
    assert.equal(part.toolbarDragTarget, 'bottom');
  });

  it('resets when released without dragging', () => {
    const part = createToolbarPart({ browser: fakeBrowser() });
    part.startToolbarDrag(dragEvent());
    const released = [];
    const event = dragEvent({
      currentTarget: { releasePointerCapture: (id) => released.push(id) },
    });

    assert.equal(part.endToolbarDrag(event), 'reset');
    assert.deepEqual(released, [7]);
    assert.equal(part.toolbarPlacement, 'bottom');
  });

  it('snaps to the drag target and remembers it', () => {
    const browser = fakeBrowser();
    const part = createToolbarPart({ browser });
    part.startToolbarDrag(dragEvent());
    part.moveToolbarDrag(dragEvent({ clientX: 50, clientY: 50 }));
    assert.equal(part.endToolbarDrag(dragEvent()), 'top-left');
    assert.equal(part.toolbarPlacement, 'top-left');
    assert.equal(part.toolbarPreferredPlacement, 'top-left');
    assert.equal(browser.storage.values[TOOLBAR_STORAGE_KEY], 'top-left');
    // Phase two settles after the snap delay.
    assert.equal(part.toolbarSnapping, true);
    assert.equal(browser.scheduled.at(-1).delay, 500);
    browser.scheduled.at(-1).callback();
    assert.equal(part.toolbarSnapping, false);
  });

  it('cancels back to the origin placement', () => {
    const part = createToolbarPart({ browser: fakeBrowser() });
    part.startToolbarDrag(dragEvent());
    part.moveToolbarDrag(dragEvent({ clientX: 50, clientY: 50 }));
    assert.equal(part.cancelToolbarDrag(dragEvent()), 'bottom');
    assert.equal(part.toolbarPlacement, 'bottom');
    assert.equal(part.toolbarPreferredPlacement, 'bottom');
  });
});

describe('toolbar persistence and placement', () => {
  it('restores persisted placements and ignores unknown values', () => {
    const browser = fakeBrowser();
    browser.storage.values[TOOLBAR_STORAGE_KEY] = 'top-right';
    const part = createToolbarPart({ browser });
    assert.equal(part.restoreToolbarPlacement(), 'top-right');
    assert.equal(part.toolbarPlacement, 'top-right');

    browser.storage.values[TOOLBAR_STORAGE_KEY] = 'nowhere';
    assert.equal(part.restoreToolbarPlacement(), null);
  });

  it('survives hostile storage', () => {
    const part = createToolbarPart({
      browser: fakeBrowser({
        storage: {
          getItem: () => {
            throw new Error('denied');
          },
          setItem: () => {
            throw new Error('denied');
          },
        },
      }),
    });
    assert.equal(part.persistToolbarPlacement(), false);
    assert.equal(part.restoreToolbarPlacement(), null);
  });

  it('flips on dialog overlap via the runtime helper', () => {
    const part = createToolbarPart({
      browser: fakeBrowser({
        toolbarPlacement: () => 'top',
        afterPaint: null,
        nextFrame: null,
        schedule: null,
      }),
    });
    assert.equal(part.syncToolbarPlacement(null), 'top');
    assert.equal(part.toolbarPlacement, 'top');
  });

  it('skips the flip while dragging or on unknown output', () => {
    const part = createToolbarPart({
      browser: fakeBrowser({ toolbarPlacement: () => 'top' }),
    });
    part.toolbarDragging = true;
    assert.equal(part.syncToolbarPlacement(null), 'bottom');

    const unknown = createToolbarPart({
      browser: fakeBrowser({ toolbarPlacement: () => 'nowhere' }),
    });
    assert.equal(unknown.syncToolbarPlacement(null), 'bottom');
  });

  it('settles the two-phase snap and drops stale versions', () => {
    const browser = fakeBrowser();
    const part = createToolbarPart({ browser });
    part.moveToolbarTo('top-left', true);
    assert.equal(part.toolbarPlacement, 'top-left');
    assert.equal(part.toolbarSnapping, true);
    const staleTimer = browser.scheduled.at(-1);

    part.moveToolbarTo('top-right', true);
    staleTimer.callback();
    assert.equal(part.toolbarSnapping, true);
    assert.equal(part.toolbarPlacement, 'top-right');
    browser.scheduled.at(-1).callback();
    assert.equal(part.toolbarSnapping, false);

    assert.equal(part.finishToolbarSnap(999), false);
    assert.equal(part.finishToolbarSnap(), false);
  });

  it('suppresses the click after a drag', () => {
    const part = createToolbarPart({ browser: fakeBrowser() });
    part.suppressToolbarClick();
    assert.equal(part.toolbarSuppressClick, true);
    let stopped = 0;
    assert.equal(
      part.consumeToolbarClick({
        preventDefault: () => {},
        stopPropagation: () => {
          stopped += 1;
        },
      }),
      true,
    );
    assert.equal(stopped, 1);
    assert.equal(part.consumeToolbarClick({}), false);
  });

  it('pins valid placements only', () => {
    const part = createToolbarPart({ browser: fakeBrowser() });
    assert.equal(part.pinToolbar('bottom-right'), 'bottom-right');
    assert.equal(part.toolbarPreferredPlacement, 'bottom-right');
    assert.equal(part.pinToolbar('nowhere'), 'bottom-right');
  });

  it('exposes placement getters', () => {
    const part = createToolbarPart({ browser: fakeBrowser() });
    part.toolbarPlacement = 'top-left';
    assert.equal(part.toolbarIsCorner, true);
    assert.equal(part.toolbarIsTop, true);
    assert.equal(part.toolbarIsLeft, true);
    assert.equal(part.toolbarIsRight, false);
    assert.equal(part.toolbarVerticalPlacement, 'top');
    assert.equal(part.toolbarHorizontalPlacement, 'left');
  });
});

describe('toolbar drag guards', () => {
  it('rejects overlapping and missing drags', () => {
    const part = createToolbarPart({ browser: fakeBrowser() });
    assert.equal(part.moveToolbarDrag(dragEvent()), false);
    assert.equal(part.endToolbarDrag(dragEvent()), false);
    assert.equal(part.cancelToolbarDrag(dragEvent()), false);

    assert.equal(part.startToolbarDrag(dragEvent()), true);
    assert.equal(part.startToolbarDrag(dragEvent()), false);
    assert.equal(part.cancelToolbarDrag(dragEvent()), 'reset');
  });

  it('releases drags without a toolbar element', () => {
    const part = createToolbarPart({ browser: fakeBrowser() });
    part.startToolbarDrag(dragEvent());
    part.moveToolbarDrag(dragEvent({ clientX: 50, clientY: 50 }));
    assert.equal(part.endToolbarDrag({ pointerId: 7 }), 'top-left');
  });

  it('suppresses clicks without a scheduler', () => {
    const part = createToolbarPart({
      browser: fakeBrowser({ schedule: null }),
    });
    assert.equal(part.suppressToolbarClick(), true);
    assert.equal(part.toolbarClickTimer, null);
    assert.equal(part.consumeToolbarClick({}), true);
  });
});

describe('toolbar shell composition', () => {
  it('registers the toolbar part in createShell', () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    assert.equal(shell.toolbarPlacement, 'bottom');
    assert.equal(typeof shell.startToolbarDrag, 'function');
    assert.equal(typeof shell.pinToolbar, 'function');
  });
});
