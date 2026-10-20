export function syncAnchorPreview(ctx) {
  let dragging = false;
  let target = null;
  let preferred = null;
  let rebasing = false;
  let snapping = false;
  let offsetX = 0;
  let offsetY = 0;
  try {
    dragging = ctx.shell.toolbarDragging === true;
    target = ctx.shell.toolbarDragTarget || null;
    preferred = ctx.shell.toolbarPreferredPlacement || null;
    rebasing = ctx.shell.toolbarRebasing === true;
    snapping = ctx.shell.toolbarSnapping === true;
    offsetX = ctx.shell.toolbarDragOffsetX || 0;
    offsetY = ctx.shell.toolbarDragOffsetY || 0;
  } catch (error) {
    dragging = false;
    target = null;
  }
  if (ctx.toolbar) {
    ctx.toolbar.setAttribute('data-dk-dragging', dragging ? 'true' : 'false');
    ctx.toolbar.setAttribute('data-dk-drag-target', target || '');
    if (preferred) {
      ctx.toolbar.setAttribute('data-dk-preferred', preferred);
    }
    ctx.toolbar.setAttribute('data-dk-rebasing', rebasing ? 'true' : 'false');
    ctx.toolbar.setAttribute('data-dk-snapping', snapping ? 'true' : 'false');
    try {
      ctx.toolbar.style.setProperty('--dk-toolbar-drag-x', offsetX + 'px');
      ctx.toolbar.style.setProperty('--dk-toolbar-drag-y', offsetY + 'px');
    } catch (error) {}
  }
  ctx.root
    .querySelectorAll('[data-dk-anchor-preview]')
    .forEach(function (frame) {
      const show =
        dragging && frame.getAttribute('data-dk-anchor-preview') === target;
      if (show) {
        frame.removeAttribute('hidden');
      } else {
        frame.setAttribute('hidden', '');
      }
    });
}

export function initDrag(ctx) {
  if (!ctx.toolbar) {
    return;
  }
  ctx.toolbar.addEventListener('pointerdown', function (event) {
    let started = false;
    try {
      started = ctx.shell.startToolbarDrag(event);
    } catch (error) {
      started = false;
    }
    if (started) {
      syncAnchorPreview(ctx);
    }
  });
  ctx.toolbar.addEventListener('pointermove', function (event) {
    let moved = false;
    try {
      moved = ctx.shell.moveToolbarDrag(event);
    } catch (error) {
      moved = false;
    }
    if (moved || ctx.shell.toolbarDragging === true) {
      syncAnchorPreview(ctx);
    }
  });
  ['pointerup', 'pointercancel'].forEach(function (type) {
    ctx.toolbar.addEventListener(type, function (event) {
      try {
        if (type === 'pointerup') {
          ctx.shell.endToolbarDrag(event);
        } else {
          ctx.shell.cancelToolbarDrag(event);
        }
      } catch (error) {}
      syncAnchorPreview(ctx);
      syncToolbarPlacement(ctx);
    });
  });
  ctx.toolbar.addEventListener(
    'click',
    function (event) {
      let consumed = false;
      try {
        consumed = ctx.shell.consumeToolbarClick
          ? ctx.shell.consumeToolbarClick(event) === true
          : ctx.shell.toolbarSuppressClick === true;
      } catch (error) {
        consumed = false;
      }
      if (consumed) {
        event.preventDefault();
        event.stopPropagation();
      }
    },
    true,
  );
  ctx.toolbar.addEventListener('transitionend', function (event) {
    if (event && event.propertyName && event.propertyName !== 'transform') {
      return;
    }
    try {
      if (ctx.shell.finishToolbarSnap) {
        ctx.shell.finishToolbarSnap();
      }
    } catch (error) {}
    syncAnchorPreview(ctx);
    if (typeof ctx.syncToolbarForm === 'function') {
      ctx.syncToolbarForm();
    }
  });
}

/**
 * Mirrors the live toolbar placement onto the shell element.
 *
 * The frame CSS pins pickers upward for bottom docks and downward for
 * top docks, so the attribute must track drags, palette pins and the
 * restored placement — not just the server-rendered default.
 *
 * @param {object} ctx Shell context.
 * @return void
 */
export function syncToolbarPlacement(ctx) {
  const shell =
    (ctx && ctx.toolbar) ||
    (ctx && ctx.root
      ? ctx.root.querySelector('[data-dk-toolbar-shell]')
      : null);
  if (!shell || typeof shell.setAttribute !== 'function') {
    return;
  }
  let placement = 'bottom';
  try {
    if (
      ctx.shell &&
      typeof ctx.shell.toolbarPlacement === 'string' &&
      ctx.shell.toolbarPlacement !== ''
    ) {
      placement = ctx.shell.toolbarPlacement;
    }
  } catch (error) {}
  shell.setAttribute('data-dk-placement', placement);
}

export function syncToolbarForm(ctx) {
  syncToolbarPlacement(ctx);
  let corner = false;
  try {
    corner = ctx.shell.toolbarIsCorner === true;
  } catch (error) {
    corner = false;
  }
  const center = ctx.root.querySelector('[data-dk-center-toolbar]');
  const pill = ctx.root.querySelector('[data-dk-corner-toolbar]');
  if (center) {
    if (corner) {
      center.setAttribute('hidden', '');
    } else {
      center.removeAttribute('hidden');
    }
  }
  if (pill) {
    if (corner) {
      pill.removeAttribute('hidden');
    } else {
      pill.setAttribute('hidden', '');
    }
  }
}
