import { describe, it, before, after } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = fileURLToPath(new URL('.', import.meta.url));
const pluginRoot = path.resolve(here, '..', '..');

function installHost() {
  const posted = [];
  const docListeners = {};
  const winListeners = {};
  const store = { v: null, throwGet: false, throwSet: false };
  const attrs = {
    'data-id': 'req-1',
    'data-url': 'http://localhost/',
    'data-mini-position': 'bottom-right',
  };
  const script = {
    getAttribute: (name) => attrs[name] ?? null,
  };
  const iframe = {
    style: {},
    width: 0,
    height: 0,
    contentWindow: {
      postMessage: (message) => posted.push(message),
    },
  };
  const appended = [];
  const body = {
    appendChild: (element) => appended.push(element),
    style: {},
  };
  const opened = [];
  const sent = [];
  const fakeDocument = {
    readyState: 'complete',
    getElementById: (id) => (id === '__debug_bar_script' ? script : null),
    addEventListener: (type, fn) => {
      (docListeners[type] ??= []).push(fn);
    },
    createElement: () => iframe,
    body,
  };
  let fetchMode = 'id';
  const fakeWindow = {
    location: { origin: 'http://localhost' },
    localStorage: {
      getItem: () => {
        if (store.throwGet) {
          throw new Error('denied');
        }

        return store.v;
      },
      setItem: (key, value) => {
        if (store.throwSet) {
          throw new Error('denied');
        }
        store.v = String(value);
      },
      removeItem: () => {
        store.v = null;
      },
    },
    addEventListener: (type, fn) => {
      (winListeners[type] ??= []).push(fn);
    },
    XMLHttpRequest: {
      prototype: {
        open(...args) {
          opened.push(args);
        },
        send(...args) {
          sent.push(args);
        },
      },
    },
    fetch: async (input, init = {}) => {
      if (fetchMode === 'throw') {
        throw new Error('down');
      }
      const debug = fetchMode === 'id' ? 'fid-1' : null;

      return {
        status: 201,
        headers: {
          get: (name) => {
            if (name === 'X-DEBUGBAR-ID') return debug;
            if (name === 'Content-Type') return 'application/json';

            return null;
          },
        },
      };
    },
  };
  fakeWindow.self = fakeWindow;
  fakeWindow.top = fakeWindow;
  fakeWindow.window = fakeWindow;
  globalThis.window = fakeWindow;
  globalThis.document = fakeDocument;

  return {
    posted,
    docListeners,
    winListeners,
    store,
    attrs,
    iframe,
    appended,
    body,
    opened,
    sent,
    fakeWindow,
    setFetchMode: (mode) => {
      fetchMode = mode;
    },
  };
}

function cleanupHostGlobals() {
  delete globalThis.window;
  delete globalThis.document;
}

function message(host, data, overrides = {}) {
  const { winListeners, iframe, fakeWindow } = host;
  for (const fn of winListeners.message ?? []) {
    fn({
      origin: 'http://localhost',
      source: iframe.contentWindow,
      data,
      ...overrides,
    });
  }
}

describe('host mini frame', () => {
  let host;

  before(async () => {
    cleanupHostGlobals();
    host = installHost();
    const url = pathToFileURL(
      path.join(pluginRoot, 'webroot', 'js', 'inject-iframe.js'),
    ).href;
    await import(url);
  });

  after(() => {
    cleanupHostGlobals();
  });

  it('boots the mini frame at the server position', () => {
    assert.equal(host.appended.length, 1);
    assert.equal(host.iframe.width, 40);
    assert.equal(host.iframe.height, 40);
    assert.equal(host.iframe.style.bottom, '0');
    assert.equal(host.iframe.style.right, '0');
  });

  it('ignores messages from foreign origins, sources and shapes', () => {
    const before = host.posted.length;
    message(host, 'mini-position$$top-left', { origin: 'http://evil/' });
    message(host, 'mini-position$$top-left', { source: null });
    message(host, 'hello');
    assert.equal(host.posted.length, before);
    assert.equal(host.iframe.style.bottom, '0');
  });

  it('pins positions from the shell and applies them', () => {
    message(host, 'mini-position$$middle-right');
    assert.equal(
      host.store.v,
      JSON.stringify({ pos: 'middle-right', srv: 'bottom-right' }),
    );
    assert.equal(host.iframe.style.top, '50%');
    assert.equal(host.iframe.style.right, '0');
    assert.equal(host.iframe.style.transform, 'translateY(-50%)');

    for (const [position, probe] of [
      ['top-left', ['top', 'left']],
      ['top-right', ['top', 'right']],
      ['bottom-left', ['bottom', 'left']],
    ]) {
      message(host, `mini-position$$${position}`);
      const [vertical, horizontal] = probe;
      assert.equal(host.iframe.style[vertical], '0');
      assert.equal(host.iframe.style[horizontal], '0');
    }
    message(host, 'mini-position$$middle-left');
    assert.equal(host.iframe.style.top, '50%');
    assert.equal(host.iframe.style.left, '0');
  });

  it('drops the pin when the server value changes', () => {
    host.fakeWindow.debugBarMiniPosition = 'top-left';
    message(host, 'collapse');
    assert.equal(host.store.v, null);
    assert.equal(host.iframe.style.top, '0');
    assert.equal(host.iframe.style.left, '0');
  });

  it('treats legacy plain-string pins as server defaults', () => {
    host.store.v = 'middle-left';
    message(host, 'collapse');
    assert.equal(host.iframe.style.top, '0');
    assert.equal(host.iframe.style.left, '0');
  });

  it('ignores stored pins with unknown positions', () => {
    host.store.v = JSON.stringify({ pos: 'nope', srv: 'top-left' });
    message(host, 'collapse');
    assert.equal(host.iframe.style.top, '0');
  });

  it('ignores stored pins without a matching server value', () => {
    host.store.v = JSON.stringify({ pos: 'middle-left' });
    message(host, 'collapse');
    assert.equal(host.store.v, null);
  });

  it('survives unreadable storage', () => {
    host.store.throwGet = true;
    message(host, 'collapse');
    assert.equal(host.iframe.style.top, '0');
    host.store.throwGet = false;
  });

  it('treats empty and non-string storage as missing', () => {
    host.store.v = '';
    message(host, 'collapse');
    assert.equal(host.iframe.style.top, '0');
    host.store.v = 42;
    message(host, 'collapse');
    assert.equal(host.iframe.style.top, '0');
    host.store.v = null;
  });

  it('still applies the server default when persisting fails', () => {
    host.store.throwSet = true;
    message(host, 'mini-position$$bottom-left');
    assert.equal(host.iframe.style.top, '0');
    assert.equal(host.iframe.style.left, '0');
    host.store.throwSet = false;
  });

  it('resets to the server default on demand', () => {
    message(host, 'mini-position$$bottom-right');
    assert.notEqual(host.store.v, null);
    message(host, 'mini-position$$server-default');
    assert.equal(host.store.v, null);
    assert.equal(host.iframe.style.top, '0');
    assert.equal(host.iframe.style.left, '0');
  });

  it('resets through the setItem fallback without removeItem', () => {
    const mainStorage = host.fakeWindow.localStorage;
    host.fakeWindow.localStorage = {
      getItem: () => host.store.v,
      setItem: (key, value) => {
        host.store.v = String(value);
      },
    };
    try {
      message(host, 'mini-position$$middle-left');
      assert.notEqual(host.store.v, null);
      message(host, 'mini-position$$server-default');
      assert.equal(host.store.v, '');
      assert.equal(host.iframe.style.top, '0');
      assert.equal(host.iframe.style.left, '0');
    } finally {
      host.fakeWindow.localStorage = mainStorage;
      host.store.v = null;
    }
  });

  it('tolerates a throwing removeItem on reset', () => {
    const mainStorage = host.fakeWindow.localStorage;
    host.fakeWindow.localStorage = {
      getItem: () => host.store.v,
      setItem: (key, value) => {
        host.store.v = String(value);
      },
      removeItem: () => {
        throw new Error('denied');
      },
    };
    try {
      message(host, 'mini-position$$top-right');
      message(host, 'mini-position$$server-default');
      assert.equal(host.iframe.style.top, '0');
      assert.equal(host.iframe.style.right, '0');
    } finally {
      host.fakeWindow.localStorage = mainStorage;
      host.store.v = null;
    }
  });

  it('drives the frame through collapse, toolbar, picker, expand and error', () => {
    message(host, 'toolbar');
    assert.equal(host.iframe.width, '100%');
    assert.equal(host.iframe.height, 40);

    message(host, 'picker');
    assert.equal(host.iframe.height, 320);

    message(host, 'expand');
    assert.equal(host.iframe.width, '100%');
    assert.equal(host.iframe.height, '100%');
    assert.equal(host.body.style.overflow, 'hidden');

    message(host, 'error');
    assert.equal(host.iframe.width, '40%');
    assert.equal(host.iframe.height, '40%');

    message(host, 'collapse');
    assert.equal(host.iframe.width, 40);
    assert.equal(host.iframe.height, 40);
  });

  it('persists pins without applying them outside mini mode', () => {
    message(host, 'expand');
    message(host, 'mini-position$$top-right');
    assert.equal(
      host.store.v,
      JSON.stringify({ pos: 'top-right', srv: 'top-left' }),
    );
    assert.equal(host.iframe.width, '100%');
    assert.equal(host.iframe.height, '100%');
    assert.equal(host.body.style.overflow, 'hidden');

    message(host, 'collapse');
    assert.equal(host.iframe.style.top, '0');
    assert.equal(host.iframe.style.right, '0');
    host.store.v = null;
  });

  it('falls back to bottom-right for unknown server values', () => {
    host.fakeWindow.debugBarMiniPosition = 'nope';
    message(host, 'collapse');
    assert.equal(host.iframe.style.bottom, '0');
    assert.equal(host.iframe.style.right, '0');
    host.fakeWindow.debugBarMiniPosition = 'top-left';
  });

  it('proxies XHR open arguments', () => {
    const xhr = {};
    host.fakeWindow.XMLHttpRequest.prototype.open.call(xhr, 'GET', '/x');
    assert.deepEqual(xhr._arguments, ['GET', '/x']);
    assert.deepEqual(host.opened, [['GET', '/x']]);
  });

  it('notices completed ajax with a debug id', () => {
    const seen = [];
    const xhr = {
      readyState: 4,
      status: 200,
      _arguments: ['POST', '/api'],
      getResponseHeader: (name) => {
        if (name === 'X-DEBUGBAR-ID') return 'rid-1';
        if (name === 'Content-Type') return 'application/json';

        return null;
      },
      onreadystatechange: () => seen.push('orig'),
    };
    const before = host.posted.length;
    host.fakeWindow.XMLHttpRequest.prototype.send.call(xhr);
    xhr.onreadystatechange();
    assert.deepEqual(seen, ['orig']);
    assert.equal(host.posted.length, before + 1);
    assert.match(host.posted[before], /"requestId":"rid-1"/);
  });

  it('leaves quiet ajax alone but keeps the original handler', () => {
    const seen = [];
    const xhr = {
      readyState: 3,
      onreadystatechange: () => seen.push('orig'),
    };
    const before = host.posted.length;
    host.fakeWindow.XMLHttpRequest.prototype.send.call(xhr);
    xhr.onreadystatechange();
    assert.deepEqual(seen, ['orig']);
    assert.equal(host.posted.length, before);
  });

  it('returns false without an original handler', () => {
    const xhr = { readyState: 0 };
    host.fakeWindow.XMLHttpRequest.prototype.send.call(xhr);
    assert.equal(xhr.onreadystatechange(), false);
  });

  it('notices fetch responses carrying a debug id', async () => {
    host.setFetchMode('id');
    const before = host.posted.length;
    const response = await host.fakeWindow.fetch('/api', { method: 'POST' });
    assert.equal(response.status, 201);
    assert.equal(host.posted.length, before + 1);
    assert.match(host.posted[before], /"requestId":"fid-1"/);
  });

  it('ignores fetch responses without a debug id', async () => {
    host.setFetchMode('noid');
    const before = host.posted.length;
    await host.fakeWindow.fetch({ url: '/obj' });
    assert.equal(host.posted.length, before);
    host.setFetchMode('id');
  });

  it('reports failed fetches and rethrows', async () => {
    host.setFetchMode('throw');
    const before = host.posted.length;
    await assert.rejects(host.fakeWindow.fetch('/api'), /down/);
    assert.equal(host.posted.length, before + 1);
    assert.match(host.posted[before], /"error":"down"/);
    host.setFetchMode('id');
  });

  it('ignores keydown noise on the host page', () => {
    const before = host.posted.length;
    const throwingEvent = {};
    Object.defineProperty(throwingEvent, 'defaultPrevented', {
      get: () => {
        throw new Error('denied');
      },
    });
    for (const fn of host.docListeners.keydown ?? []) {
      fn(null);
      fn({ defaultPrevented: true });
      fn(throwingEvent);
      fn({
        defaultPrevented: false,
        shiftKey: false,
        ctrlKey: true,
        altKey: true,
        code: undefined,
        key: undefined,
        target: { closest: () => null },
      });
      fn({
        defaultPrevented: false,
        shiftKey: false,
        ctrlKey: true,
        altKey: true,
        code: 'KeyP',
        key: 'p',
        target: null,
        preventDefault: () => {
          throw new Error('denied');
        },
      });
    }
    assert.equal(host.posted.length, before + 1);
  });

  it('tolerates a throwing postMessage target', () => {
    const before = host.posted.length;
    const target = host.iframe.contentWindow;
    target.postMessage = () => {
      throw new Error('denied');
    };
    try {
      for (const fn of host.docListeners.keydown ?? []) {
        fn({
          defaultPrevented: false,
          shiftKey: false,
          ctrlKey: true,
          altKey: true,
          code: 'KeyP',
          key: 'p',
          target: { closest: () => null },
          preventDefault: () => {},
        });
      }
    } finally {
      target.postMessage = (message) => host.posted.push(message);
    }
    assert.equal(host.posted.length, before);
  });
});
