import { describe, it, before, after } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(here, '..', '..');

function installHostGlobals() {
  const posted = [];
  const docListeners = {};
  const winListeners = {};
  const script = {
    getAttribute: (name) => {
      if (name === 'data-id') return 'req-1';
      if (name === 'data-url') return 'http://localhost/';
      if (name === 'data-mini-position') return 'bottom-right';

      return null;
    },
  };
  const iframe = {
    style: {},
    width: 0,
    height: 0,
    contentWindow: {
      postMessage: (message) => posted.push(message),
    },
  };
  const fakeDocument = {
    readyState: 'complete',
    getElementById: (id) => (id === '__debug_bar_script' ? script : null),
    addEventListener: (type, fn) => {
      (docListeners[type] ??= []).push(fn);
    },
    createElement: () => iframe,
    body: {
      appendChild: () => {},
      style: {},
    },
  };
  const fakeWindow = {
    location: { origin: 'http://localhost' },
    localStorage: {
      getItem: () => null,
      setItem: () => {},
      removeItem: () => {},
    },
    addEventListener: (type, fn) => {
      (winListeners[type] ??= []).push(fn);
    },
    XMLHttpRequest: { prototype: { open() {}, send() {} } },
  };
  fakeWindow.self = fakeWindow;
  fakeWindow.top = fakeWindow;
  fakeWindow.window = fakeWindow;
  globalThis.window = fakeWindow;
  globalThis.document = fakeDocument;

  return { posted, docListeners, winListeners, iframe, fakeWindow };
}

function cleanupHostGlobals() {
  delete globalThis.window;
  delete globalThis.document;
}

function keyEvent(overrides = {}) {
  return {
    defaultPrevented: false,
    shiftKey: false,
    ctrlKey: true,
    altKey: true,
    metaKey: false,
    code: 'KeyP',
    key: 'p',
    target: { closest: () => null },
    preventDefault: () => {},
    ...overrides,
  };
}

describe('host palette hotkey', () => {
  let posted;
  let docListeners;

  before(async () => {
    cleanupHostGlobals();
    ({ posted, docListeners } = installHostGlobals());
    const url = pathToFileURL(
      path.join(pluginRoot, 'webroot', 'js', 'inject-iframe.js'),
    ).href;
    await import(url);
  });

  after(() => {
    cleanupHostGlobals();
  });

  it('forwards Ctrl+Alt+P from the host page to the shell frame', () => {
    assert.ok(
      Array.isArray(docListeners.keydown) && docListeners.keydown.length === 1,
    );
    docListeners.keydown[0](keyEvent());
    assert.deepEqual(posted, ['open-palette']);
  });

  it('forwards the Meta variant and ignores other keys', () => {
    docListeners.keydown[0](keyEvent({ ctrlKey: false, metaKey: true }));
    assert.deepEqual(posted, ['open-palette', 'open-palette']);

    docListeners.keydown[0](keyEvent({ code: 'KeyQ', key: 'q' }));
    docListeners.keydown[0](keyEvent({ shiftKey: true }));
    docListeners.keydown[0](keyEvent({ altKey: false }));
    assert.deepEqual(posted, ['open-palette', 'open-palette']);
  });

  it('stays quiet while typing in editable fields', () => {
    docListeners.keydown[0](keyEvent({ target: { closest: () => ({}) } }));
    assert.deepEqual(posted, ['open-palette', 'open-palette']);
  });
});
