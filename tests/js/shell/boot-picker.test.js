import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  closeAllPickers,
  handlePickerClick,
  handlePickerKey,
  handleRequestLinkClick,
  handleRequestsRefreshClick,
  pickers,
  refreshRequestOptions,
  refreshRequestPickers,
  shrinkAfterPicker,
  syncTriggerLabels,
  togglePicker,
} from '../../../webroot/js/shell/boot/picker.js';
import { FakeElement } from '../boot-fake-dom.js';

globalThis.Element ??= FakeElement;

function stubDocument() {
  const realDocument = globalThis.document;
  globalThis.document = {
    createElement: (tag) => new FakeElement(tag),
  };

  return function restore() {
    if (realDocument === undefined) {
      delete globalThis.document;
    } else {
      globalThis.document = realDocument;
    }
  };
}

function stubWindow(embedded = false) {
  const realWindow = globalThis.window;
  const self = {};
  globalThis.window = {
    innerWidth: 800,
    location: { assign: () => {}, reload: () => {} },
    self,
    top: embedded ? {} : self,
    addEventListener: () => {},
  };

  return function restore() {
    if (realWindow === undefined) {
      delete globalThis.window;
    } else {
      globalThis.window = realWindow;
    }
  };
}

function stubFetch(handler) {
  const calls = [];
  const realFetch = globalThis.fetch;
  globalThis.fetch = (url, init) => {
    calls.push({ url, init });

    return handler(url, init);
  };

  return {
    calls,
    restore() {
      if (realFetch === undefined) {
        delete globalThis.fetch;
      } else {
        globalThis.fetch = realFetch;
      }
    },
  };
}

async function flush(times = 8) {
  for (let i = 0; i < times; i += 1) {
    await new Promise((resolve) => setImmediate(resolve));
  }
}

function shellCtx(extra = {}) {
  const root = new FakeElement('div');
  const shell = {
    closeRequestPicker: () => {},
    closeThemeMenu: () => {},
  };
  const frames = [];
  const ctx = {
    root,
    shell,
    inspector: null,
    setFrame: (mode) => frames.push(mode),
    state: {},
    openInspector: () => {},
    swapRequestPanels: () => {},
    ...extra,
  };

  return { ctx, root, shell, frames };
}

function trigger(scope = 'toolbar') {
  return new FakeElement('button', {
    'data-dk-request-trigger': scope,
    'aria-expanded': 'false',
  });
}

function pickerList(scope = 'toolbar') {
  return new FakeElement('ul', {
    'data-dk-request-picker': scope,
    hidden: '',
  });
}

function dropupSetup(embedded) {
  const root = new FakeElement('div', { 'data-dk-frame': 'bar' });
  const item = trigger();
  const list = pickerList();
  root.appendChild(item);
  root.appendChild(list);
  const posted = [];
  const self = {};
  const fakeWindow = {
    self: embedded ? self : {},
    top: embedded ? {} : null,
    parent: {
      postMessage: (message) => posted.push(message),
    },
    location: { origin: 'http://localhost' },
  };
  if (!embedded) {
    fakeWindow.self = fakeWindow.top = {};
  }
  const frames = [];
  const shell = {
    recentRequests: [],
    currentRequestId: 'req-1',
    toggleRequestPicker: () => {},
    closeRequestPicker: () => {},
    closeThemeMenu: () => {},
  };
  const ctx = {
    root,
    shell,
    inspector: null,
    setFrame: (mode) => {
      frames.push(mode);
      root.setAttribute('data-dk-frame', mode);
    },
  };
  globalThis.window = fakeWindow;

  return { root, item, list, posted, frames, ctx };
}

describe('boot picker dropup protocol', () => {
  afterEach(() => {
    delete globalThis.window;
  });

  it('opens the picker in place with a picker message when embedded', () => {
    const { item, list, posted, frames, ctx } = dropupSetup(true);
    const handled = handlePickerClick(item, ctx);

    assert.equal(handled, true);
    assert.equal(list.hasAttribute('hidden'), false);
    assert.deepEqual(frames, ['picker']);
    assert.deepEqual(posted, ['picker']);
  });

  it('leaves the frame alone on a full page', () => {
    const { item, list, posted, frames, ctx } = dropupSetup(false);
    const handled = handlePickerClick(item, ctx);

    assert.equal(handled, true);
    assert.equal(list.hasAttribute('hidden'), false);
    assert.deepEqual(frames, []);
    assert.deepEqual(posted, []);
  });
});

describe('picker open and close', () => {
  it('lists every picker in the root', () => {
    const { ctx, root } = shellCtx();
    const request = new FakeElement('div', {
      'data-dk-request-picker': 'toolbar',
    });
    const theme = new FakeElement('div', {
      'data-dk-theme-options': 'toolbar',
    });
    const mobile = new FakeElement('div', { 'data-dk-mobile-popover': 'm' });
    root.appendChild(request);
    root.appendChild(theme);
    root.appendChild(mobile);

    assert.equal(pickers(ctx).length, 3);
  });

  it('closes pickers and resets triggers', () => {
    const { ctx, root } = shellCtx();
    const picker = new FakeElement('div', {
      'data-dk-request-picker': 'toolbar',
    });
    picker.removeAttribute('hidden');
    const trigger = new FakeElement('button', {
      'data-dk-request-trigger': 'toolbar',
      'aria-expanded': 'true',
    });
    root.appendChild(picker);
    root.appendChild(trigger);
    closeAllPickers(ctx);

    assert.equal(picker.hasAttribute('hidden'), true);
    assert.equal(trigger.getAttribute('aria-expanded'), 'false');
  });

  it('returns to the inspector frame when visible', () => {
    const { ctx, root, frames } = shellCtx();
    const inspector = new FakeElement('div', { 'data-dk-inspector': '' });
    root.appendChild(inspector);
    ctx.inspector = inspector;
    root.setAttribute('data-dk-frame', 'picker');
    closeAllPickers(ctx);

    assert.deepEqual(frames, ['inspector']);
  });

  it('returns to the bar frame otherwise', () => {
    const { ctx, root, frames } = shellCtx();
    const inspector = new FakeElement('div', { 'data-dk-inspector': '' });
    inspector.setAttribute('hidden', '');
    root.appendChild(inspector);
    ctx.inspector = inspector;
    root.setAttribute('data-dk-frame', 'picker');
    closeAllPickers(ctx);

    assert.deepEqual(frames, ['bar']);
  });

  it('keeps focus when requested', () => {
    const { ctx, root } = shellCtx();
    const picker = new FakeElement('div', {
      'data-dk-request-picker': 'toolbar',
    });
    root.appendChild(picker);

    assert.doesNotThrow(() => closeAllPickers(ctx, true));
  });

  it('tolerates shells that throw on close', () => {
    const { ctx, root } = shellCtx();
    ctx.shell.closeRequestPicker = () => {
      throw new Error('gone');
    };
    ctx.shell.closeThemeMenu = () => {
      throw new Error('gone');
    };
    root.appendChild(new FakeElement('div', { 'data-dk-request-picker': 't' }));

    assert.doesNotThrow(() => closeAllPickers(ctx, true));
  });

  it('toggles pickers open and shut', () => {
    const { ctx, root } = shellCtx();
    const trigger = new FakeElement('button', {
      'data-dk-request-trigger': 'toolbar',
    });
    const picker = new FakeElement('div', {
      'data-dk-request-picker': 'toolbar',
    });
    picker.setAttribute('hidden', '');
    root.appendChild(trigger);
    root.appendChild(picker);
    togglePicker(ctx, trigger, picker);

    assert.equal(picker.hasAttribute('hidden'), false);
    assert.equal(trigger.getAttribute('aria-expanded'), 'true');

    togglePicker(ctx, trigger, picker);

    assert.equal(picker.hasAttribute('hidden'), true);
  });

  it('shrinks toolbar scopes back to the frame', () => {
    const { ctx, frames } = shellCtx();
    shrinkAfterPicker(ctx, 'toolbar');

    assert.deepEqual(frames, ['bar']);
  });

  it('shrinks to the inspector when visible', () => {
    const { ctx, root, frames } = shellCtx();
    const inspector = new FakeElement('div', { 'data-dk-inspector': '' });
    root.appendChild(inspector);
    ctx.inspector = inspector;
    shrinkAfterPicker(ctx, 'corner');

    assert.deepEqual(frames, ['inspector']);
  });

  it('ignores non-toolbar scopes', () => {
    const { ctx } = shellCtx();

    assert.doesNotThrow(() => shrinkAfterPicker(ctx, 'header'));
  });
});

describe('picker request triggers', () => {
  let restoreWindow;

  beforeEach(() => {
    restoreWindow = stubWindow(false);
  });

  afterEach(() => {
    restoreWindow();
  });

  function triggerCtx() {
    const { ctx, root } = shellCtx();
    const trigger = new FakeElement('button', {
      'data-dk-request-trigger': 'toolbar',
      'aria-expanded': 'false',
    });
    const picker = new FakeElement('div', {
      'data-dk-request-picker': 'toolbar',
    });
    picker.setAttribute('hidden', '');
    root.appendChild(trigger);
    root.appendChild(picker);

    return { ctx, root, trigger, picker };
  }

  it('opens the picker through the shell', () => {
    const { ctx, trigger, picker } = triggerCtx();
    const toggles = [];
    ctx.shell.toggleRequestPicker = (scope) => toggles.push(scope);
    handlePickerClick(trigger, ctx);

    assert.equal(picker.hasAttribute('hidden'), false);
    assert.equal(trigger.getAttribute('aria-expanded'), 'true');
    assert.deepEqual(toggles, ['toolbar']);
  });

  it('opens through the legacy hook', () => {
    const { ctx, trigger, picker } = triggerCtx();
    const opens = [];
    ctx.shell.openRequestPicker = (scope) => opens.push(scope);
    handlePickerClick(trigger, ctx);

    assert.deepEqual(opens, ['toolbar']);
  });

  it('tolerates shells that throw on toggle', () => {
    const { ctx, trigger, picker } = triggerCtx();
    ctx.shell.toggleRequestPicker = () => {
      throw new Error('gone');
    };
    handlePickerClick(trigger, ctx);

    assert.equal(picker.hasAttribute('hidden'), false);
  });

  it('shrinks when the picker is already open', () => {
    const { ctx, trigger, picker } = triggerCtx();
    picker.removeAttribute('hidden');
    trigger.setAttribute('aria-expanded', 'true');
    handlePickerClick(trigger, ctx);

    assert.equal(picker.hasAttribute('hidden'), true);
  });

  it('ignores triggers without a picker', () => {
    const { ctx } = shellCtx();
    const trigger = new FakeElement('button', {
      'data-dk-request-trigger': 'ghost',
    });

    assert.equal(handlePickerClick(trigger, ctx), true);
  });

  it('selects requests synchronously', () => {
    const { ctx, root } = shellCtx();
    const option = new FakeElement('button', {
      'data-dk-request-option': 'r1',
    });
    root.appendChild(option);
    const selected = [];
    ctx.shell.selectRequest = (id) => {
      selected.push(id);
      return { id };
    };
    ctx.swapRequestPanels = (id) => selected.push('panels:' + id);
    handlePickerClick(option, ctx);

    assert.deepEqual(selected, ['r1', 'panels:r1']);
  });

  it('selects requests through promises', async () => {
    const { ctx, root } = shellCtx();
    const option = new FakeElement('button', {
      'data-dk-request-option': 'r2',
    });
    root.appendChild(option);
    ctx.shell.selectRequest = () => Promise.resolve({ same: true });
    const opened = [];
    ctx.openInspector = (key) => opened.push(key);
    handlePickerClick(option, ctx);
    await flush(4);

    assert.deepEqual(opened, ['request']);
  });

  it('reads the inspector keep key from the shell', () => {
    const { ctx, root } = shellCtx();
    const option = new FakeElement('button', {
      'data-dk-request-option': 'r3',
    });
    root.appendChild(option);
    ctx.shell.selected = 'sql';
    const seen = [];
    ctx.shell.selectRequest = (id, options) => {
      seen.push([id, options]);

      return null;
    };
    handlePickerClick(option, ctx);

    assert.deepEqual(seen, [['r3', { inspector: 'sql' }]]);
    assert.equal(ctx.state.swapKeepKey, 'sql');
  });

  it('tolerates shells that throw on select', () => {
    const { ctx, root } = shellCtx();
    const option = new FakeElement('button', {
      'data-dk-request-option': 'r4',
    });
    root.appendChild(option);
    ctx.shell.selectRequest = () => {
      throw new Error('gone');
    };

    assert.equal(handlePickerClick(option, ctx), true);
  });
});

describe('picker theme and mobile menus', () => {
  let restoreWindow;

  beforeEach(() => {
    restoreWindow = stubWindow(true);
  });

  afterEach(() => {
    restoreWindow();
  });

  it('opens theme menus in embedded frames', () => {
    const { ctx, root } = shellCtx();
    root.setAttribute('data-dk-frame', 'bar');
    const trigger = new FakeElement('button', {
      'data-dk-theme-trigger': 'toolbar',
    });
    const menu = new FakeElement('div', { 'data-dk-theme-options': 'toolbar' });
    menu.setAttribute('hidden', '');
    root.appendChild(trigger);
    root.appendChild(menu);
    const frames = [];
    ctx.setFrame = (mode) => frames.push(mode);
    handlePickerClick(trigger, ctx);

    assert.equal(menu.hasAttribute('hidden'), false);
    assert.deepEqual(frames, ['picker']);
  });

  it('applies theme options', () => {
    const { ctx } = shellCtx();
    const applied = [];
    ctx.shell.setTheme = (value) => applied.push(['set', value]);
    ctx.shell.applyTheme = () => applied.push(['apply']);
    const option = new FakeElement('button', {
      'data-dk-theme-option': 'dark',
    });

    assert.equal(handlePickerClick(option, ctx), true);
    assert.deepEqual(applied, [['set', 'dark'], ['apply']]);
  });

  it('tolerates theme shells that throw', () => {
    const { ctx } = shellCtx();
    ctx.shell.setTheme = () => {
      throw new Error('gone');
    };
    const option = new FakeElement('button', {
      'data-dk-theme-option': 'dark',
    });

    assert.equal(handlePickerClick(option, ctx), true);
  });

  it('opens mobile popovers', () => {
    const { ctx, root } = shellCtx();
    const trigger = new FakeElement('button', {
      'data-dk-mobile-trigger': 'm',
    });
    const popover = new FakeElement('div', { 'data-dk-mobile-popover': 'm' });
    popover.setAttribute('hidden', '');
    root.appendChild(trigger);
    root.appendChild(popover);

    assert.equal(handlePickerClick(trigger, ctx), true);
    assert.equal(popover.hasAttribute('hidden'), false);
  });

  it('ignores missing popovers and clicks', () => {
    const { ctx } = shellCtx();
    const trigger = new FakeElement('button', {
      'data-dk-mobile-trigger': 'ghost',
    });

    assert.equal(handlePickerClick(trigger, ctx), true);
    assert.equal(handlePickerClick(new FakeElement('button'), ctx), false);
  });
});

describe('picker keyboard navigation', () => {
  function keyCtx() {
    const { ctx, root } = shellCtx();
    const picker = new FakeElement('div', { 'data-dk-request-picker': 't' });
    const first = new FakeElement('button', {
      'data-dk-request-option': 'r1',
    });
    const second = new FakeElement('button', {
      'data-dk-request-option': 'r2',
    });
    picker.appendChild(first);
    picker.appendChild(second);
    root.appendChild(picker);

    return { ctx, root, picker, first, second };
  }

  function keyEvent(key, target, extra = {}) {
    return {
      key,
      target,
      preventDefault: () => {},
      shiftKey: false,
      ctrlKey: false,
      metaKey: false,
      altKey: false,
      ...extra,
    };
  }

  it('rejects other keys and modifiers', () => {
    const { ctx, first } = keyCtx();

    assert.equal(handlePickerKey(keyEvent('x', first), ctx), false);
    assert.equal(
      handlePickerKey(keyEvent('ArrowDown', first, { shiftKey: true }), ctx),
      false,
    );
    assert.equal(handlePickerKey(null, ctx), false);
  });

  it('walks options with arrows', () => {
    const { ctx, first, second } = keyCtx();
    let focused = null;
    first.focus = () => {
      focused = 'first';
    };
    second.focus = () => {
      focused = 'second';
    };

    assert.equal(handlePickerKey(keyEvent('ArrowDown', first), ctx), true);
    assert.equal(focused, 'second');
    assert.equal(ctx.shell.requestPickerIndex, 1);

    assert.equal(handlePickerKey(keyEvent('ArrowUp', first), ctx), true);
    assert.equal(focused, 'second');
  });

  it('jumps with home and end', () => {
    const { ctx, first, second } = keyCtx();
    let focused = null;
    first.focus = () => {
      focused = 'first';
    };
    second.focus = () => {
      focused = 'second';
    };

    assert.equal(handlePickerKey(keyEvent('End', first), ctx), true);
    assert.equal(focused, 'second');
    assert.equal(handlePickerKey(keyEvent('Home', second), ctx), true);
    assert.equal(focused, 'first');
  });

  it('activates options with enter and space', () => {
    const { ctx, root, first } = keyCtx();
    const selected = [];
    ctx.shell.selectRequest = (id) => {
      selected.push(id);
      return { id };
    };
    ctx.swapRequestPanels = () => {};

    assert.equal(handlePickerKey(keyEvent('Enter', first), ctx), true);
    assert.deepEqual(selected, ['r1']);
    assert.equal(handlePickerKey(keyEvent(' ', root), ctx), false);
  });

  it('ignores hidden and empty pickers', () => {
    const { ctx, picker, first } = keyCtx();
    picker.setAttribute('hidden', '');

    assert.equal(handlePickerKey(keyEvent('ArrowDown', first), ctx), false);

    picker.removeAttribute('hidden');
    picker.removeChild(first);
    picker.removeChild(picker.children[0]);

    assert.equal(handlePickerKey(keyEvent('ArrowDown', first), ctx), false);
  });

  it('tolerates targets that throw on closest', () => {
    const { ctx } = keyCtx();
    const target = {
      closest: () => {
        throw new Error('gone');
      },
    };

    assert.equal(handlePickerKey(keyEvent('ArrowDown', target), ctx), false);
  });

  it('tolerates options without focus', () => {
    const { ctx, first } = keyCtx();
    delete first.focus;

    assert.equal(handlePickerKey(keyEvent('ArrowDown', first), ctx), true);
  });

  it('jumps from triggers into the list', () => {
    const { ctx, root, first, second } = keyCtx();
    const trigger = new FakeElement('button', {
      'data-dk-request-trigger': 't',
    });
    root.appendChild(trigger);
    let focused = null;
    first.focus = () => {
      focused = 'first';
    };
    second.focus = () => {
      focused = 'second';
    };

    assert.equal(handlePickerKey(keyEvent('ArrowDown', trigger), ctx), true);
    assert.equal(focused, 'first');

    assert.equal(
      handlePickerKey(
        keyEvent(
          'ArrowUp',
          new FakeElement('button', {
            'data-dk-request-trigger': 'ghost',
          }),
        ),
        ctx,
      ),
      false,
    );
  });

  it('ignores triggers with hidden or empty pickers', () => {
    const { ctx, root, picker } = keyCtx();
    const trigger = new FakeElement('button', {
      'data-dk-request-trigger': 't',
    });
    root.appendChild(trigger);
    picker.setAttribute('hidden', '');

    assert.equal(handlePickerKey(keyEvent('ArrowDown', trigger), ctx), false);

    picker.removeAttribute('hidden');
    picker.removeChild(picker.children[0]);
    picker.removeChild(picker.children[0]);

    assert.equal(handlePickerKey(keyEvent('ArrowDown', trigger), ctx), false);
  });
});

describe('picker recent refresh', () => {
  let fetchStub;
  let restoreWindow;

  beforeEach(() => {
    restoreWindow = stubWindow(false);
    fetchStub = null;
  });

  afterEach(() => {
    fetchStub?.restore();
    restoreWindow();
  });

  it('ignores unrelated clicks', () => {
    assert.equal(
      handleRequestsRefreshClick(new FakeElement('button'), { root: null }),
      false,
    );
  });

  it('reports missing loaders', () => {
    const target = new FakeElement('button', {
      'data-dk-requests-refresh': '',
    });

    assert.equal(handleRequestsRefreshClick(target, { shell: {} }), true);
  });

  it('reloads through promise loaders', async () => {
    const { ctx, root } = shellCtx();
    ctx.shell.loadRecentRequests = () => Promise.resolve([{ id: 'r1' }]);
    ctx.shell.currentRequestId = null;
    ctx.shell.recentRequests = [{ id: 'r1', status_code: 200 }];
    const target = new FakeElement('button', {
      'data-dk-requests-refresh': '',
    });
    root.appendChild(target);
    handleRequestsRefreshClick(target, ctx);
    await flush(4);

    assert.equal(target.hasAttribute('aria-busy'), false);
  });

  it('reloads through sync loaders', () => {
    const { ctx, root } = shellCtx();
    ctx.shell.loadRecentRequests = () => [{ id: 'r1' }];
    const target = new FakeElement('button', {
      'data-dk-requests-refresh': '',
    });
    root.appendChild(target);
    handleRequestsRefreshClick(target, ctx);

    assert.equal(target.hasAttribute('aria-busy'), false);
  });

  it('refits open pickers after reload', () => {
    const restore = stubDocument();
    const { ctx, root } = shellCtx();
    ctx.shell.loadRecentRequests = () => [];
    ctx.shell.currentRequestId = null;
    const picker = new FakeElement('div', { 'data-dk-request-picker': 't' });
    root.appendChild(picker);
    const target = new FakeElement('button', {
      'data-dk-requests-refresh': '',
    });
    root.appendChild(target);
    handleRequestsRefreshClick(target, ctx);
    restore();

    assert.equal(target.hasAttribute('aria-busy'), false);
  });
});

describe('picker request links', () => {
  let fetchStub;

  beforeEach(() => {
    fetchStub = null;
  });

  afterEach(() => {
    fetchStub?.restore();
  });

  it('ignores elements without a link', () => {
    assert.equal(handleRequestLinkClick(new FakeElement('button'), {}), false);
  });

  it('ignores empty links and missing selectors', () => {
    const target = new FakeElement('button', { 'data-dk-request-link': '' });

    assert.equal(handleRequestLinkClick(target, {}), true);
    assert.equal(
      handleRequestLinkClick(
        new FakeElement('button', { 'data-dk-request-link': '/x' }),
        { shell: {} },
      ),
      true,
    );
  });

  it('switches to linked requests', async () => {
    const { ctx } = shellCtx();
    const switched = [];
    ctx.shell.selectRequest = () => ({ id: 'r5' });
    ctx.swapRequestPanels = (id) => switched.push(id);
    const target = new FakeElement('button', {
      'data-dk-request-link': '/debug-bar/requests/link/r5',
    });
    fetchStub = stubFetch(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ request_id: 'r5' }),
      }),
    );
    handleRequestLinkClick(target, ctx);
    await flush(4);

    assert.deepEqual(switched, ['r5']);
    assert.equal(target.hasAttribute('aria-busy'), false);
  });

  it('opens the inspector for same requests', async () => {
    const { ctx } = shellCtx();
    const opened = [];
    ctx.openInspector = (key) => opened.push(key);
    ctx.shell.selectRequest = () => Promise.resolve({ same: true });
    const target = new FakeElement('button', {
      'data-dk-request-link': '/debug-bar/requests/link/r5',
    });
    fetchStub = stubFetch(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ request_id: 'r5' }),
      }),
    );
    handleRequestLinkClick(target, ctx);
    await flush(4);

    assert.deepEqual(opened, ['request']);
  });

  it('keeps inspector context from the payload', async () => {
    const { ctx } = shellCtx();
    const seen = [];
    ctx.shell.selectRequest = (id, options) => {
      seen.push([id, options]);

      return null;
    };
    const target = new FakeElement('button', {
      'data-dk-request-link': '/debug-bar/requests/link/r5',
    });
    fetchStub = stubFetch(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ request_id: 'r5', inspector: 'sql' }),
      }),
    );
    handleRequestLinkClick(target, ctx);
    await flush(4);

    assert.deepEqual(seen, [['r5', { inspector: 'sql' }]]);
    assert.equal(ctx.state.swapKeepKey, 'sql');
  });

  it('ignores payloads without an id', async () => {
    const { ctx } = shellCtx();
    const target = new FakeElement('button', {
      'data-dk-request-link': '/debug-bar/requests/link/r5',
    });
    fetchStub = stubFetch(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ nope: true }),
      }),
    );
    handleRequestLinkClick(target, ctx);
    await flush(4);

    assert.equal(target.hasAttribute('aria-busy'), false);
  });

  it('reports lookup failures', async () => {
    const { ctx } = shellCtx();
    const target = new FakeElement('button', {
      'data-dk-request-link': '/debug-bar/requests/link/r5',
    });
    fetchStub = stubFetch(() => Promise.resolve({ ok: false }));
    handleRequestLinkClick(target, ctx);
    await flush(4);

    assert.equal(target.hasAttribute('aria-busy'), false);
  });
});

describe('picker label and option sync', () => {
  let restoreDocument;

  beforeEach(() => {
    restoreDocument = stubDocument();
  });

  afterEach(() => {
    restoreDocument();
  });

  it('syncs the current request labels', () => {
    const { ctx, root } = shellCtx();
    ctx.shell.currentRequest = {
      method: 'POST',
      url: '/save',
      status_code: 201,
      response_size: '1KB',
    };
    const method = new FakeElement('span', {
      'data-dk-current-request-method': '',
    });
    const url = new FakeElement('span', {
      'data-dk-current-request-url': '',
    });
    const status = new FakeElement('span', {
      'data-dk-current-request-status': '',
    });
    const size = new FakeElement('span', {
      'data-dk-current-request-size': '',
    });
    root.appendChild(method);
    root.appendChild(url);
    root.appendChild(status);
    root.appendChild(size);
    syncTriggerLabels(ctx);

    assert.equal(method.textContent, 'POST');
    assert.equal(url.textContent, '/save');
    assert.equal(status.textContent, '201');
    assert.equal(size.textContent, '1KB');
  });

  it('hides missing sizes and tolerates throwing shells', () => {
    const { ctx, root } = shellCtx();
    const size = new FakeElement('span', {
      'data-dk-current-request-size': '',
    });
    root.appendChild(size);
    syncTriggerLabels(ctx);

    assert.equal(size.textContent, '');
    assert.equal(size.hasAttribute('hidden'), true);

    Object.defineProperty(ctx.shell, 'currentRequest', {
      get() {
        throw new Error('gone');
      },
    });
    syncTriggerLabels(ctx);

    assert.equal(size.hasAttribute('hidden'), true);
  });

  it('marks the current request option', () => {
    const { ctx, root } = shellCtx();
    ctx.shell.currentRequestId = 'r1';
    ctx.shell.unreadRequestCount = 12;
    const option = new FakeElement('button', {
      'data-dk-request-option': 'r1',
    });
    root.appendChild(option);
    const badge = new FakeElement('span', { 'data-dk-request-badge': '' });
    badge.setAttribute('hidden', '');
    root.appendChild(badge);
    refreshRequestOptions(ctx);

    assert.equal(option.getAttribute('aria-selected'), 'true');
    assert.equal(badge.textContent, '9+');
    assert.equal(badge.hasAttribute('hidden'), false);
  });

  it('clears badges without unread requests', () => {
    const { ctx, root } = shellCtx();
    const badge = new FakeElement('span', { 'data-dk-request-badge': '' });
    root.appendChild(badge);
    refreshRequestOptions(ctx);

    assert.equal(badge.textContent, '');
    assert.equal(badge.hasAttribute('hidden'), true);
  });

  it('tolerates shells that throw on counts', () => {
    const { ctx, root } = shellCtx();
    const badge = new FakeElement('span', { 'data-dk-request-badge': '' });
    root.appendChild(badge);
    Object.defineProperty(ctx.shell, 'unreadRequestCount', {
      get() {
        throw new Error('gone');
      },
    });
    refreshRequestOptions(ctx);

    assert.equal(badge.textContent, '');
  });

  it('rebuilds picker options with unread states', () => {
    const { ctx, root } = shellCtx();
    ctx.shell.currentRequestId = 'r1';
    ctx.shell.recentRequests = [
      {
        id: 'r1',
        method: 'GET',
        url: '/',
        status_code: 200,
        response_size: '2KB',
      },
      { id: 'r2', method: 'CLI', url: 'job', status_code: 500 },
      null,
    ];
    ctx.shell.requestIsUnread = () => true;
    const picker = new FakeElement('div', { 'data-dk-request-picker': 't' });
    root.appendChild(picker);
    refreshRequestPickers(ctx);

    assert.equal(picker.children.length, 2);
  });

  it('tolerates shells that throw on entries', () => {
    const { ctx, root } = shellCtx();
    Object.defineProperty(ctx.shell, 'recentRequests', {
      get() {
        throw new Error('gone');
      },
    });
    const picker = new FakeElement('div', { 'data-dk-request-picker': 't' });
    root.appendChild(picker);
    refreshRequestPickers(ctx);

    assert.equal(picker.children.length, 0);
  });
});
