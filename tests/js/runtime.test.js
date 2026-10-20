import { describe, it, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  DK_TOOLBAR_PLACEMENTS,
  composeState,
  decodeInspectorPayload,
  defaultRuntime,
  dkToolbarHorizontal,
  dkToolbarVertical,
  readInspectorPayload,
  resolveToolbarPlacement,
  writeTextToClipboard,
} from '../../webroot/js/runtime.js';

const savedGlobals = [];

function setGlobal(name, value) {
  savedGlobals.push([
    name,
    Object.hasOwn(globalThis, name) ? globalThis[name] : undefined,
    Object.hasOwn(globalThis, name),
  ]);
  globalThis[name] = value;
}

function deleteGlobal(name) {
  savedGlobals.push([
    name,
    Object.hasOwn(globalThis, name) ? globalThis[name] : undefined,
    Object.hasOwn(globalThis, name),
  ]);
  delete globalThis[name];
}

afterEach(() => {
  while (savedGlobals.length > 0) {
    const [name, value, had] = savedGlobals.pop();
    if (had) {
      globalThis[name] = value;
    } else {
      delete globalThis[name];
    }
  }
});

function installWindow(overrides = {}) {
  const listeners = {};
  const rafQueue = [];
  const timeouts = [];
  const win = {
    innerWidth: 1280,
    innerHeight: 800,
    localStorage: (() => {
      const store = new Map();

      return {
        getItem: (key) => (store.has(key) ? store.get(key) : null),
        setItem: (key, value) => store.set(key, String(value)),
      };
    })(),
    matchMedia: (query) => ({
      matches: query === '(prefers-color-scheme: dark)',
      media: query,
    }),
    requestAnimationFrame: (callback) => {
      rafQueue.push(callback);

      return rafQueue.length;
    },
    cancelAnimationFrame: (id) => {
      timeouts.push(['cancel', id]);
      rafQueue.splice(id - 1, 1);
    },
    setTimeout: (callback, delay) => {
      timeouts.push(['set', delay]);

      return timeouts.length;
    },
    clearTimeout: (id) => {
      timeouts.push(['clear', id]);
    },
    getComputedStyle: () => ({
      paddingRight: '',
      display: 'block',
      visibility: 'visible',
    }),
    addEventListener: (type, callback, options) => {
      listeners[type] = listeners[type] ?? [];
      listeners[type].push([callback, options]);
    },
    removeEventListener: (type, callback) => {
      listeners[type] = (listeners[type] ?? []).filter(
        ([current]) => current !== callback,
      );
    },
    navigator: {},
    IntersectionObserver: undefined,
    ...overrides,
  };
  setGlobal('window', win);

  return { win, listeners, rafQueue, timeouts };
}

function flushRaf(rafQueue) {
  while (rafQueue.length > 0) {
    const callback = rafQueue.shift();
    callback();
  }
}

function installDocument(overrides = {}) {
  const doc = {
    activeElement: null,
    body: null,
    documentElement: { clientWidth: 1280 },
    createElement: () => null,
    querySelectorAll: () => [],
    ...overrides,
  };
  setGlobal('document', doc);

  return doc;
}

function textareaFake() {
  return {
    value: '',
    focuses: 0,
    selects: 0,
    removed: false,
    style: { setProperty: () => {} },
    focus: function focus() {
      this.focuses += 1;
    },
    select: function select() {
      this.selects += 1;
    },
    remove: function remove() {
      this.removed = true;
    },
  };
}

describe('composeState', () => {
  it('merges parts with later parts winning and getters intact', () => {
    const first = {
      x: 1,
      get doubled() {
        return this.x * 2;
      },
    };
    const second = { x: 5, y: 2 };
    const merged = composeState(first, second);
    assert.equal(merged.x, 5);
    assert.equal(merged.y, 2);
    assert.equal(merged.doubled, 10);
  });

  it('returns a fresh object', () => {
    const first = { x: 1 };
    const merged = composeState(first);
    merged.x = 100;
    assert.equal(first.x, 1);
  });
});

describe('decodeInspectorPayload', () => {
  const payload = { queries: [{ sql: 'SELECT 1', took: 2 }], note: 'héllo' };
  const encoded = Buffer.from(JSON.stringify(payload), 'utf8').toString(
    'base64',
  );

  it('decodes base64 JSON payloads', () => {
    assert.deepEqual(decodeInspectorPayload(encoded), payload);
    assert.deepEqual(decodeInspectorPayload(`  ${encoded} `), payload);
  });

  it('returns null for blank or non-string input', () => {
    assert.equal(decodeInspectorPayload(''), null);
    assert.equal(decodeInspectorPayload('   '), null);
    assert.equal(decodeInspectorPayload(null), null);
    assert.equal(decodeInspectorPayload(42), null);
  });

  it('throws on corrupt input', () => {
    assert.throws(() => decodeInspectorPayload('!!!not-base64!!!'));
  });
});

describe('readInspectorPayload', () => {
  const payload = { rows: [1, 2] };
  const encoded = Buffer.from(JSON.stringify(payload), 'utf8').toString(
    'base64',
  );

  it('reads payloads through a root and selector', () => {
    const root = {
      querySelector: (selector) =>
        selector === '[data-dk-rows]' ? { textContent: encoded } : null,
    };
    assert.deepEqual(readInspectorPayload(root, '[data-dk-rows]'), payload);
  });

  it('returns null when nothing is found', () => {
    assert.equal(readInspectorPayload(null, '[data-dk-rows]'), null);
    assert.equal(readInspectorPayload({}, '[data-dk-rows]'), null);
    assert.equal(
      readInspectorPayload({ querySelector: () => null }, '[data-dk-rows]'),
      null,
    );
    assert.equal(
      readInspectorPayload(
        { querySelector: () => ({ textContent: '  ' }) },
        '[data-dk-rows]',
      ),
      null,
    );
  });
});

describe('toolbar placement helpers', () => {
  it('lists the six placements', () => {
    assert.deepEqual(DK_TOOLBAR_PLACEMENTS, [
      'top-left',
      'top',
      'top-right',
      'bottom-left',
      'bottom',
      'bottom-right',
    ]);
  });

  it('resolves horizontal placement', () => {
    assert.equal(dkToolbarHorizontal('top-left'), 'left');
    assert.equal(dkToolbarHorizontal('bottom-right'), 'right');
    assert.equal(dkToolbarHorizontal('top'), 'center');
    assert.equal(dkToolbarHorizontal('bottom'), 'center');
    assert.equal(dkToolbarHorizontal(undefined), 'center');
    assert.equal(dkToolbarHorizontal(42), 'center');
  });

  it('resolves vertical placement', () => {
    assert.equal(dkToolbarVertical('top-left'), 'top');
    assert.equal(dkToolbarVertical('top'), 'top');
    assert.equal(dkToolbarVertical('bottom'), 'bottom');
    assert.equal(dkToolbarVertical('bottom-right'), 'bottom');
    assert.equal(dkToolbarVertical(undefined), 'bottom');
  });
});

describe('resolveToolbarPlacement', () => {
  const base = {
    toolbarWidth: 196,
    toolbarHeight: 56,
    viewportWidth: 1280,
    viewportHeight: 800,
    dialogs: [],
  };

  it('keeps the preferred placement without dialogs', () => {
    assert.equal(
      resolveToolbarPlacement({ ...base, preferred: 'top-left' }),
      'top-left',
    );
    assert.equal(
      resolveToolbarPlacement({ ...base, preferred: 'bottom' }),
      'bottom',
    );
    assert.equal(resolveToolbarPlacement(), 'bottom');
  });

  it('falls back to bottom for unknown preferences', () => {
    assert.equal(
      resolveToolbarPlacement({ ...base, preferred: 'nope' }),
      'bottom',
    );
  });

  it('flips to the side with less dialog overlap', () => {
    const blockingBottom = {
      ...base,
      toolbarWidth: 200,
      toolbarHeight: 50,
      viewportWidth: 1000,
      viewportHeight: 800,
      preferred: 'bottom-right',
      dialogs: [{ left: 700, right: 1000, top: 700, bottom: 800 }],
    };
    assert.equal(resolveToolbarPlacement(blockingBottom), 'top-right');

    const blockingTop = {
      ...blockingBottom,
      preferred: 'top',
      dialogs: [{ left: 0, right: 1000, top: 0, bottom: 100 }],
    };
    assert.equal(resolveToolbarPlacement(blockingTop), 'bottom');

    const blockingBoth = {
      ...blockingBottom,
      preferred: 'top-right',
      dialogs: [
        { left: 700, right: 1000, top: 0, bottom: 100 },
        { left: 700, right: 1000, top: 700, bottom: 800 },
      ],
    };
    assert.equal(resolveToolbarPlacement(blockingBoth), 'top-right');
  });

  it('clamps the toolbar box to narrow viewports', () => {
    assert.equal(
      resolveToolbarPlacement({
        preferred: 'bottom',
        toolbarWidth: 200,
        toolbarHeight: 50,
        viewportWidth: 100,
        viewportHeight: 800,
        dialogs: [],
      }),
      'bottom',
    );
    assert.equal(
      resolveToolbarPlacement({
        preferred: 'bottom-right',
        toolbarWidth: 200,
        toolbarHeight: 50,
        viewportWidth: 100,
        viewportHeight: 800,
        dialogs: [],
      }),
      'bottom-right',
    );
  });
});

describe('defaultRuntime basics', () => {
  it('reads storage, media, viewport and time through the window', () => {
    installWindow();
    installDocument();
    const runtime = defaultRuntime();

    runtime.storage.setItem('debugbar.theme', 'dark');
    assert.equal(runtime.storage.getItem('debugbar.theme'), 'dark');
    assert.deepEqual(runtime.matchMedia('(prefers-color-scheme: dark)'), {
      matches: true,
      media: '(prefers-color-scheme: dark)',
    });
    assert.equal(runtime.viewportWidth(), 1280);
    assert.equal(runtime.viewportHeight(), 800);
    assert.equal(typeof runtime.now(), 'number');
  });

  it('falls back to memory storage when localStorage throws', () => {
    installWindow({
      localStorage: {
        getItem: () => {
          throw new Error('blocked');
        },
        setItem: () => {
          throw new Error('blocked');
        },
      },
    });
    installDocument();
    const runtime = defaultRuntime();

    runtime.storage.setItem('debugbar.theme', 'dark');
    assert.equal(runtime.storage.getItem('debugbar.theme'), 'dark');
  });

  it('reads DOM helpers through the document', () => {
    installWindow();
    installDocument({
      activeElement: 'focused',
      querySelectorAll: (selector) => [`match:${selector}`],
    });
    const runtime = defaultRuntime();
    assert.equal(runtime.activeElement(), 'focused');
    assert.deepEqual(runtime.queryAll('[data-dk-x]'), ['match:[data-dk-x]']);
  });

  it('schedules single frames and timers', () => {
    const { rafQueue, timeouts } = installWindow();
    installDocument();
    const runtime = defaultRuntime();

    let frames = 0;
    runtime.nextFrame(() => {
      frames += 1;
    });
    flushRaf(rafQueue);
    assert.equal(frames, 1);

    const timer = runtime.schedule(() => {}, 200);
    runtime.cancelSchedule(timer);
    assert.deepEqual(timeouts, [
      ['set', 200],
      ['clear', 1],
    ]);
  });

  it('runs afterPaint callbacks after two frames', () => {
    const { rafQueue } = installWindow();
    installDocument();
    const runtime = defaultRuntime();

    let calls = 0;
    runtime.afterPaint(() => {
      calls += 1;
    });
    assert.equal(rafQueue.length, 1);
    flushRaf([rafQueue.shift()]);
    assert.equal(calls, 0);
    flushRaf(rafQueue);
    assert.equal(calls, 1);
  });

  it('accepts overrides', () => {
    installWindow();
    installDocument();
    const runtime = defaultRuntime({ now: () => 1 });
    assert.equal(runtime.now(), 1);
  });
});

describe('writeTextToClipboard', () => {
  it('uses the async clipboard API when available', async () => {
    let seen = null;
    installWindow({
      navigator: {
        clipboard: {
          writeText: async (value) => {
            seen = value;
          },
        },
      },
    });
    installDocument();
    assert.equal(await writeTextToClipboard('copy me'), true);
    assert.equal(seen, 'copy me');
  });

  it('uses the textarea path when the async copy fails', async () => {
    const element = textareaFake();
    installWindow({
      navigator: {
        clipboard: {
          writeText: async () => {
            throw new Error('denied');
          },
        },
      },
    });
    installDocument({
      body: { append: () => {} },
      createElement: () => element,
      execCommand: () => true,
    });
    assert.equal(await writeTextToClipboard('fallback'), true);
    assert.equal(element.value, 'fallback');
    assert.equal(element.selects, 1);
    assert.equal(element.removed, true);
  });

  it('uses the textarea path without clipboard support', async () => {
    const element = textareaFake();
    installWindow({ navigator: {} });
    installDocument({
      body: { append: () => {} },
      createElement: () => element,
      execCommand: () => true,
    });
    assert.equal(await writeTextToClipboard('plain'), true);
  });

  it('uses the textarea path without a navigator', async () => {
    const element = textareaFake();
    const { win } = installWindow();
    delete win.navigator;
    installDocument({
      body: { append: () => {} },
      createElement: () => element,
      execCommand: () => true,
    });
    assert.equal(await writeTextToClipboard('plain'), true);
  });

  it('reports failure when the copy command fails', async () => {
    installWindow();
    installDocument({
      body: { append: () => {} },
      createElement: () => textareaFake(),
      execCommand: () => false,
    });
    assert.equal(await writeTextToClipboard('no'), false);
  });

  it('reports failure without an execCommand implementation', async () => {
    installWindow();
    installDocument({
      body: { append: () => {} },
      createElement: () => textareaFake(),
      execCommand: undefined,
    });
    assert.equal(await writeTextToClipboard('no'), false);
  });

  it('reports failure when the copy command throws', async () => {
    installWindow();
    installDocument({
      body: { append: () => {} },
      createElement: () => textareaFake(),
      execCommand: () => {
        throw new Error('blocked');
      },
    });
    assert.equal(await writeTextToClipboard('no'), false);
  });

  it('restores focus and selection after copying', async () => {
    const element = textareaFake();
    const added = [];
    const focused = [];
    installWindow();
    installDocument({
      activeElement: { focus: (options) => focused.push(options) },
      body: { append: () => {} },
      createElement: () => element,
      execCommand: () => true,
      getSelection: () => ({
        rangeCount: 1,
        getRangeAt: () => 'range-0',
        removeAllRanges: () => {},
        addRange: (range) => added.push(range),
      }),
    });
    assert.equal(await writeTextToClipboard('restore'), true);
    assert.deepEqual(focused, [{ preventScroll: true }]);
    assert.deepEqual(added, ['range-0']);
  });

  it('tolerates missing focus and selection implementations', async () => {
    installWindow();
    installDocument({
      activeElement: {},
      body: { append: () => {} },
      createElement: () => textareaFake(),
      execCommand: () => true,
      getSelection: undefined,
    });
    assert.equal(await writeTextToClipboard('tolerant'), true);
  });

  it('tolerates selection objects without ranges', async () => {
    installWindow();
    installDocument({
      activeElement: null,
      body: { append: () => {} },
      createElement: () => textareaFake(),
      execCommand: () => true,
      getSelection: () => ({}),
    });
    assert.equal(await writeTextToClipboard('tolerant'), true);
  });

  it('tolerates selection restore failures', async () => {
    installWindow();
    installDocument({
      activeElement: null,
      body: { append: () => {} },
      createElement: () => textareaFake(),
      execCommand: () => true,
      getSelection: () => ({
        rangeCount: 0,
        removeAllRanges: () => {
          throw new Error('gone');
        },
        addRange: () => {},
      }),
    });
    assert.equal(await writeTextToClipboard('tolerant'), true);
  });

  it('reports failure without a document', async () => {
    installWindow({ navigator: {} });
    deleteGlobal('document');
    assert.equal(await writeTextToClipboard('nowhere'), false);
  });

  it('reports failure without a body', async () => {
    installWindow();
    installDocument({ body: null });
    assert.equal(await writeTextToClipboard('nowhere'), false);
  });

  it('copies without a window when the document suffices', async () => {
    deleteGlobal('window');
    installDocument({
      body: { append: () => {} },
      createElement: () => textareaFake(),
      execCommand: () => true,
    });
    assert.equal(await writeTextToClipboard('nowhere'), true);
  });
});

describe('lockHost and unlockHost', () => {
  function installHost({ clientWidth = 1200, paddingRight = '' } = {}) {
    class HostElement {}
    setGlobal('HTMLElement', HostElement);

    const makeElement = (
      tag,
      { containsRoot = false, scriptLike = false } = {},
    ) => {
      const element = new HostElement();
      element.tag = tag;
      element.inert = false;
      element.contains = (other) =>
        containsRoot && other && other.tag === 'debugbar-root';
      element.matches = (selector) =>
        selector === 'script, style, link' && (scriptLike || tag === 'script');

      return element;
    };

    const root = makeElement('debugbar-root');
    const sibling = makeElement('div');
    const script = makeElement('script', { scriptLike: true });
    const container = makeElement('div', { containsRoot: true });
    const textNode = { contains: () => false };
    const body = {
      style: { overflow: '', paddingRight },
      children: [root, sibling, script, container, textNode],
    };
    installWindow({
      innerWidth: 1280,
      getComputedStyle: (target) => ({
        paddingRight: target.style.paddingRight || '0',
      }),
    });
    installDocument({ body, documentElement: { clientWidth } });

    return { root, sibling, script, container, textNode, body };
  }

  it('locks siblings while skipping the root, containers and helpers', () => {
    const { root, sibling, script, container, textNode, body } = installHost();
    const runtime = defaultRuntime();

    runtime.lockHost(root);
    assert.equal(sibling.inert, true);
    assert.equal(script.inert, false);
    assert.equal(container.inert, false);
    assert.equal(textNode.inert, undefined);
    assert.equal(body.style.overflow, 'hidden');
    assert.equal(body.style.paddingRight, '80px');

    runtime.lockHost(root);
    assert.equal(body.style.paddingRight, '80px');
  });

  it('adds existing padding to the scrollbar compensation', () => {
    const { root, body } = installHost({ paddingRight: '8px' });
    defaultRuntime().lockHost(root);
    assert.equal(body.style.paddingRight, '88px');
  });

  it('skips compensation without a scrollbar', () => {
    const { root, body } = installHost({ clientWidth: 1280 });
    defaultRuntime().lockHost(root);
    assert.equal(body.style.paddingRight, '');
  });

  it('ignores missing roots', () => {
    const runtime = defaultRuntime();
    runtime.lockHost(null);
    runtime.lockHost(undefined);
  });

  it('restores the host on unlock', () => {
    const { root, sibling, body } = installHost();
    const runtime = defaultRuntime();

    runtime.lockHost(root);
    runtime.unlockHost(root);
    assert.equal(sibling.inert, false);
    assert.equal(body.style.overflow, '');
    assert.equal(body.style.paddingRight, '');
    assert.equal(root.__dkHostLock, undefined);

    runtime.unlockHost(root);
    runtime.unlockHost(null);
  });
});

describe('runtime toolbarPlacement', () => {
  function installPlacement({
    dialogs = [],
    styles = new Map(),
    preferredWidth = 200,
  } = {}) {
    const toolbar = {
      getBoundingClientRect: () => ({ width: preferredWidth, height: 50 }),
    };
    const internal = { id: 'internal' };
    const root = {
      querySelector: (selector) =>
        selector === '[data-dk-toolbar-shell]' ? toolbar : null,
      contains: (element) => element === internal,
    };
    installWindow({
      innerWidth: 1000,
      innerHeight: 800,
      getComputedStyle: (element) =>
        styles.get(element) ?? { display: 'block', visibility: 'visible' },
    });
    installDocument({ querySelectorAll: () => dialogs });

    return { root, internal, toolbar };
  }

  const box = (overrides = {}) => ({ width: 100, height: 100, ...overrides });

  it('falls back to bottom without a toolbar', () => {
    installWindow();
    installDocument();
    const runtime = defaultRuntime();
    assert.equal(runtime.toolbarPlacement(null), 'bottom');
    assert.equal(runtime.toolbarPlacement({}), 'bottom');
    assert.equal(
      runtime.toolbarPlacement({ querySelector: () => null }),
      'bottom',
    );
  });

  it('uses the default preference', () => {
    installPlacement();
    assert.equal(
      defaultRuntime().toolbarPlacement({ querySelector: () => null }),
      'bottom',
    );
    const { root } = installPlacement();
    assert.equal(defaultRuntime().toolbarPlacement(root), 'bottom');
  });

  it('filters host dialogs before measuring overlap', () => {
    const internal = { id: 'internal' };
    const zero = {
      id: 'zero',
      getBoundingClientRect: () => box({ width: 0, height: 50 }),
    };
    const hidden = { id: 'hidden', getBoundingClientRect: () => box() };
    const invisible = { id: 'invisible', getBoundingClientRect: () => box() };
    const external = {
      id: 'external',
      getBoundingClientRect: () => ({
        left: 700,
        right: 1000,
        top: 700,
        bottom: 800,
        width: 300,
        height: 100,
      }),
    };
    internal.getBoundingClientRect = () => box();
    const styles = new Map([
      [internal, { display: 'block', visibility: 'visible' }],
      [zero, { display: 'block', visibility: 'visible' }],
      [hidden, { display: 'none', visibility: 'visible' }],
      [invisible, { display: 'block', visibility: 'hidden' }],
      [external, { display: 'block', visibility: 'visible' }],
    ]);
    const { root } = installPlacement({
      dialogs: [internal, zero, hidden, invisible, external],
      styles,
    });
    root.contains = (element) => element === internal;

    assert.equal(
      defaultRuntime().toolbarPlacement(root, 'bottom-right'),
      'top-right',
    );
  });
});

describe('watchHostDialogs', () => {
  function installObserver() {
    let callback = null;
    let disconnected = 0;
    setGlobal(
      'MutationObserver',
      class {
        constructor(current) {
          callback = current;
        }

        observe() {}

        disconnect() {
          disconnected += 1;
        }
      },
    );
    const { listeners, rafQueue, timeouts } = installWindow();
    installDocument({ body: {} });

    return {
      fire: () => callback(),
      disconnected: () => disconnected,
      listeners,
      rafQueue,
      timeouts,
    };
  }

  it('debounces dialog changes through a frame', () => {
    const host = installObserver();
    const runtime = defaultRuntime();
    let calls = 0;
    const stop = runtime.watchHostDialogs({}, () => {
      calls += 1;
    });

    host.fire();
    host.fire();
    assert.equal(calls, 0);
    flushRaf(host.rafQueue);
    assert.equal(calls, 1);

    host.fire();
    flushRaf(host.rafQueue);
    assert.equal(calls, 2);
    stop();
    assert.equal(host.disconnected(), 1);
  });

  it('listens to resize and scroll with capture', () => {
    const host = installObserver();
    const stop = defaultRuntime().watchHostDialogs({}, () => {});
    assert.equal(host.listeners.resize.length, 1);
    assert.deepEqual(host.listeners.scroll[0][1], true);
    stop();
    assert.equal(host.listeners.resize.length, 0);
    assert.equal(host.listeners.scroll.length, 0);
  });

  it('cancels a pending frame on stop', () => {
    const host = installObserver();
    const stop = defaultRuntime().watchHostDialogs({}, () => {});
    host.fire();
    stop();
    assert.ok(host.timeouts.some(([action]) => action === 'cancel'));
  });
});

describe('observeNearEnd', () => {
  it('rejects invalid targets', () => {
    installWindow();
    installDocument();
    const runtime = defaultRuntime();
    const callback = () => {};
    assert.equal(runtime.observeNearEnd(null, {}, callback), null);
    assert.equal(runtime.observeNearEnd({}, null, callback), null);
    assert.equal(runtime.observeNearEnd({}, {}, 'nope'), null);
  });

  it('observes through IntersectionObserver with a 160px margin', () => {
    let callback = null;
    let options = null;
    let observed = null;
    let disconnected = 0;
    installWindow({
      IntersectionObserver: class {
        constructor(current, currentOptions) {
          callback = current;
          options = currentOptions;
        }

        observe(target) {
          observed = target;
        }

        disconnect() {
          disconnected += 1;
        }
      },
    });
    installDocument();
    const runtime = defaultRuntime();

    let calls = 0;
    const target = { id: 'sentinel' };
    const stop = runtime.observeNearEnd(target, { id: 'owner' }, () => {
      calls += 1;
    });
    assert.equal(observed, target);
    assert.deepEqual(options, {
      root: { id: 'owner' },
      rootMargin: '0px 0px 160px 0px',
      threshold: 0,
    });

    callback([{ isIntersecting: false }]);
    assert.equal(calls, 0);
    callback([{ isIntersecting: false }, { isIntersecting: true }]);
    assert.equal(calls, 1);

    stop();
    assert.equal(disconnected, 1);
  });

  it('falls back to scroll math without IntersectionObserver', () => {
    const { listeners, rafQueue } = installWindow({
      IntersectionObserver: undefined,
    });
    installDocument();
    const runtime = defaultRuntime();

    const owner = {
      isConnected: true,
      scrolled: [],
      resized: [],
      getBoundingClientRect: () => ({ top: 0, bottom: 600 }),
      addEventListener: function add(type, callback) {
        if (type === 'scroll') this.scrolled.push(callback);
      },
      removeEventListener: function remove(type, callback) {
        if (type === 'scroll')
          this.scrolled = this.scrolled.filter(
            (current) => current !== callback,
          );
      },
    };
    const target = {
      isConnected: true,
      getBoundingClientRect: () => ({ top: 500, bottom: 520 }),
    };

    let calls = 0;
    const stop = runtime.observeNearEnd(target, owner, () => {
      calls += 1;
    });
    assert.equal(owner.scrolled.length, 1);
    assert.equal(listeners.resize.length, 1);

    owner.scrolled[0]();
    owner.scrolled[0]();
    flushRaf(rafQueue);
    assert.equal(calls, 1);

    stop();
    assert.equal(owner.scrolled.length, 0);
    assert.equal(listeners.resize.length, 0);
  });

  it('ignores out-of-range targets in the fallback', () => {
    const { rafQueue } = installWindow({ IntersectionObserver: undefined });
    installDocument();
    const runtime = defaultRuntime();
    const owner = {
      isConnected: true,
      getBoundingClientRect: () => ({ top: 0, bottom: 600 }),
      addEventListener: () => {},
      removeEventListener: () => {},
    };

    const below = {
      isConnected: true,
      getBoundingClientRect: () => ({ top: 800, bottom: 820 }),
    };
    let calls = 0;
    runtime.observeNearEnd(below, owner, () => {
      calls += 1;
    });
    flushRaf(rafQueue);
    assert.equal(calls, 0);
  });

  it('flushes the initial fallback check', () => {
    const { rafQueue } = installWindow({ IntersectionObserver: undefined });
    installDocument();
    const runtime = defaultRuntime();
    const owner = {
      isConnected: true,
      getBoundingClientRect: () => ({ top: 0, bottom: 600 }),
      addEventListener: () => {},
      removeEventListener: () => {},
    };
    const above = {
      isConnected: true,
      getBoundingClientRect: () => ({ top: -200, bottom: -10 }),
    };
    let calls = 0;
    const stop = runtime.observeNearEnd(above, owner, () => {
      calls += 1;
    });
    flushRaf(rafQueue);
    assert.equal(calls, 0);
    stop();
  });

  it('skips detached targets in the fallback', () => {
    const { rafQueue } = installWindow({ IntersectionObserver: undefined });
    installDocument();
    const runtime = defaultRuntime();
    const owner = {
      isConnected: true,
      getBoundingClientRect: () => ({ top: 0, bottom: 600 }),
      addEventListener: () => {},
      removeEventListener: () => {},
    };

    let calls = 0;
    const detached = {
      isConnected: false,
      getBoundingClientRect: () => ({ top: 1, bottom: 2 }),
    };
    const stopDetached = runtime.observeNearEnd(detached, owner, () => {
      calls += 1;
    });
    flushRaf(rafQueue);
    assert.equal(calls, 0);
    stopDetached();

    const ownerGone = { ...owner, isConnected: false };
    const stopOwner = runtime.observeNearEnd(
      {
        isConnected: true,
        getBoundingClientRect: () => ({ top: 1, bottom: 2 }),
      },
      ownerGone,
      () => {
        calls += 1;
      },
    );
    stopOwner();
    assert.equal(calls, 0);
  });

  it('cancels a pending fallback frame on stop', () => {
    const { timeouts } = installWindow({ IntersectionObserver: undefined });
    installDocument();
    const runtime = defaultRuntime();
    const owner = {
      isConnected: true,
      getBoundingClientRect: () => ({ top: 0, bottom: 600 }),
      addEventListener: () => {},
      removeEventListener: () => {},
    };
    const target = {
      isConnected: true,
      getBoundingClientRect: () => ({ top: 1, bottom: 2 }),
    };
    const stop = runtime.observeNearEnd(target, owner, () => {});
    stop();
    assert.ok(timeouts.some(([action]) => action === 'cancel'));
  });
});
