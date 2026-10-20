import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
  initDrag,
  syncAnchorPreview,
  syncToolbarForm,
  syncToolbarPlacement,
} from '../../../webroot/js/shell/boot/drag.js';
import { FakeElement } from '../boot-fake-dom.js';

function toolbarCtx(shell = {}) {
  const root = new FakeElement('div');
  const toolbar = new FakeElement('div', { 'data-dk-toolbar-shell': '' });
  root.appendChild(toolbar);

  return { root, toolbar, shell };
}

describe('toolbar drag preview', () => {
  it('mirrors drag state onto the toolbar', () => {
    const ctx = toolbarCtx({
      toolbarDragging: true,
      toolbarDragTarget: 'top',
      toolbarPreferredPlacement: 'top',
      toolbarRebasing: false,
      toolbarSnapping: true,
      toolbarDragOffsetX: 3,
      toolbarDragOffsetY: 4,
    });
    const frame = new FakeElement('div', {
      'data-dk-anchor-preview': 'top',
    });
    frame.setAttribute('hidden', '');
    const other = new FakeElement('div', {
      'data-dk-anchor-preview': 'bottom',
    });
    ctx.root.appendChild(frame);
    ctx.root.appendChild(other);
    syncAnchorPreview(ctx);

    assert.equal(ctx.toolbar.getAttribute('data-dk-dragging'), 'true');
    assert.equal(frame.hasAttribute('hidden'), false);
    assert.equal(other.hasAttribute('hidden'), true);
  });

  it('syncs live drag values without previews', () => {
    const ctx = toolbarCtx({
      toolbarDragging: true,
      toolbarDragTarget: 'top',
      toolbarPreferredPlacement: 'top',
      toolbarRebasing: false,
      toolbarSnapping: true,
      toolbarDragOffsetX: 3,
      toolbarDragOffsetY: 4,
    });
    syncAnchorPreview(ctx);

    assert.equal(ctx.toolbar.getAttribute('data-dk-dragging'), 'true');
    assert.equal(ctx.toolbar.getAttribute('data-dk-drag-target'), 'top');
    assert.equal(ctx.toolbar.getAttribute('data-dk-preferred'), 'top');
    assert.equal(ctx.toolbar.style.props['--dk-toolbar-drag-x'], '3px');
  });

  it('tolerates shells that throw', () => {
    const ctx = toolbarCtx({
      get toolbarDragging() {
        throw new Error('gone');
      },
    });
    syncAnchorPreview(ctx);

    assert.equal(ctx.toolbar.getAttribute('data-dk-dragging'), 'false');
  });

  it('works without a toolbar element', () => {
    const root = new FakeElement('div');

    assert.doesNotThrow(() =>
      syncAnchorPreview({ root, toolbar: null, shell: {} }),
    );
  });
});

describe('toolbar drag listeners', () => {
  it('ignores a missing toolbar', () => {
    assert.doesNotThrow(() =>
      initDrag({ root: new FakeElement('div'), shell: {} }),
    );
  });

  function draggingCtx(shell) {
    const ctx = toolbarCtx(shell);

    initDrag(ctx);

    return ctx;
  }

  it('starts drags on pointerdown', () => {
    let started = 0;
    const ctx = draggingCtx({
      startToolbarDrag: () => {
        started += 1;
        return true;
      },
    });
    ctx.toolbar.dispatch('pointerdown', new FakeElement('div'));

    assert.equal(started, 1);
  });

  it('ignores failed drag starts', () => {
    const ctx = draggingCtx({
      startToolbarDrag: () => {
        throw new Error('nope');
      },
    });

    assert.doesNotThrow(() =>
      ctx.toolbar.dispatch('pointerdown', new FakeElement('div')),
    );
  });

  it('syncs previews while moving', () => {
    const ctx = draggingCtx({
      moveToolbarDrag: () => true,
      toolbarDragging: false,
    });
    ctx.toolbar.dispatch('pointermove', new FakeElement('div'));

    assert.equal(ctx.toolbar.getAttribute('data-dk-dragging'), 'false');
  });

  it('syncs previews for active drags without move events', () => {
    const ctx = draggingCtx({
      moveToolbarDrag: () => false,
      toolbarDragging: true,
    });
    ctx.toolbar.dispatch('pointermove', new FakeElement('div'));

    assert.equal(ctx.toolbar.getAttribute('data-dk-dragging'), 'true');
  });

  it('ends and cancels drags', () => {
    const calls = [];
    const ctx = draggingCtx({
      endToolbarDrag: () => calls.push('end'),
      cancelToolbarDrag: () => calls.push('cancel'),
    });
    ctx.toolbar.listeners.pointerup[0]({});
    ctx.toolbar.listeners.pointercancel[0]({});

    assert.deepEqual(calls, ['end', 'cancel']);
  });

  it('tolerates failing drag ends', () => {
    const ctx = draggingCtx({
      endToolbarDrag: () => {
        throw new Error('nope');
      },
      cancelToolbarDrag: () => {
        throw new Error('nope');
      },
    });

    assert.doesNotThrow(() => ctx.toolbar.listeners.pointerup[0]({}));
    assert.doesNotThrow(() => ctx.toolbar.listeners.pointercancel[0]({}));
  });

  it('consumes clicks through the shell', () => {
    const ctx = draggingCtx({ consumeToolbarClick: () => true });
    let prevented = 0;
    let stopped = 0;
    ctx.toolbar.listeners.click[0]({
      preventDefault: () => {
        prevented += 1;
      },
      stopPropagation: () => {
        stopped += 1;
      },
    });

    assert.equal(prevented, 1);
    assert.equal(stopped, 1);
  });

  it('consumes clicks through the suppress flag', () => {
    const ctx = draggingCtx({ toolbarSuppressClick: true });
    let prevented = 0;
    ctx.toolbar.listeners.click[0]({
      preventDefault: () => {
        prevented += 1;
      },
      stopPropagation: () => {},
    });

    assert.equal(prevented, 1);
  });

  it('tolerates failing click consumers', () => {
    const ctx = draggingCtx({
      consumeToolbarClick: () => {
        throw new Error('nope');
      },
    });

    assert.doesNotThrow(() =>
      ctx.toolbar.listeners.click[0]({
        preventDefault: () => {},
        stopPropagation: () => {},
      }),
    );
  });

  it('ignores unconsumed clicks', () => {
    const ctx = draggingCtx({});
    let prevented = 0;
    ctx.toolbar.listeners.click[0]({
      preventDefault: () => {
        prevented += 1;
      },
      stopPropagation: () => {},
    });

    assert.equal(prevented, 0);
  });

  it('finishes snaps on transform transitions', () => {
    let finished = 0;
    let synced = 0;
    const ctx = draggingCtx({
      finishToolbarSnap: () => {
        finished += 1;
      },
    });
    ctx.syncToolbarForm = () => {
      synced += 1;
    };
    ctx.toolbar.listeners.transitionend[0]({ propertyName: 'transform' });

    assert.equal(finished, 1);
    assert.equal(synced, 1);
  });

  it('ignores unrelated transitions', () => {
    const ctx = draggingCtx({});
    let synced = 0;
    ctx.syncToolbarForm = () => {
      synced += 1;
    };
    ctx.toolbar.listeners.transitionend[0]({ propertyName: 'opacity' });

    assert.equal(synced, 0);
  });

  it('tolerates missing snap hooks', () => {
    const ctx = draggingCtx({});
    delete ctx.syncToolbarForm;

    assert.doesNotThrow(() => ctx.toolbar.listeners.transitionend[0]({}));
  });
});

describe('toolbar placement sync', () => {
  it('reads placement from the shell', () => {
    const ctx = toolbarCtx({ toolbarPlacement: 'top' });
    syncToolbarPlacement(ctx);

    assert.equal(ctx.toolbar.getAttribute('data-dk-placement'), 'top');
  });

  it('falls back to the root toolbar shell', () => {
    const root = new FakeElement('div');
    const toolbar = new FakeElement('div', { 'data-dk-toolbar-shell': '' });
    root.appendChild(toolbar);
    syncToolbarPlacement({ root, shell: {} });

    assert.equal(toolbar.getAttribute('data-dk-placement'), 'bottom');
  });

  it('ignores missing shells', () => {
    assert.doesNotThrow(() => syncToolbarPlacement({}));
    assert.doesNotThrow(() =>
      syncToolbarPlacement({ root: new FakeElement('div'), shell: null }),
    );
  });

  it('tolerates shells that throw', () => {
    const ctx = toolbarCtx({});
    Object.defineProperty(ctx.shell, 'toolbarPlacement', {
      get() {
        throw new Error('gone');
      },
    });
    syncToolbarPlacement(ctx);

    assert.equal(ctx.toolbar.getAttribute('data-dk-placement'), 'bottom');
  });

  it('syncs the corner and center toolbars', () => {
    const root = new FakeElement('div');
    const center = new FakeElement('div', { 'data-dk-center-toolbar': '' });
    const pill = new FakeElement('div', {
      'data-dk-corner-toolbar': '',
      hidden: '',
    });
    root.appendChild(center);
    root.appendChild(pill);
    syncToolbarForm({ root, shell: { toolbarIsCorner: true } });

    assert.equal(center.hasAttribute('hidden'), true);
    assert.equal(pill.hasAttribute('hidden'), false);
  });

  it('shows the center toolbar outside corners', () => {
    const root = new FakeElement('div');
    const center = new FakeElement('div', {
      'data-dk-center-toolbar': '',
      hidden: '',
    });
    const pill = new FakeElement('div', { 'data-dk-corner-toolbar': '' });
    root.appendChild(center);
    root.appendChild(pill);
    syncToolbarForm({ root, shell: {} });

    assert.equal(center.hasAttribute('hidden'), false);
    assert.equal(pill.hasAttribute('hidden'), true);
  });

  it('tolerates throwing corner flags', () => {
    const root = new FakeElement('div');
    const shell = {};
    Object.defineProperty(shell, 'toolbarIsCorner', {
      get() {
        throw new Error('gone');
      },
    });

    assert.doesNotThrow(() => syncToolbarForm({ root, shell }));
  });
});
