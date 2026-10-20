import { describe, it, before, after } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = fileURLToPath(new URL('.', import.meta.url));
const pluginRoot = path.resolve(here, '..', '..');

describe('host boot without an id', () => {
  let host;

  before(async () => {
    delete globalThis.window;
    delete globalThis.document;
    delete globalThis.Turbo;
    const posted = [];
    const docListeners = {};
    const created = [];
    const fakeDocument = {
      readyState: 'loading',
      getElementById: () => null,
      addEventListener: (type, fn) => {
        (docListeners[type] ??= []).push(fn);
      },
      createElement: (tag) => {
        created.push(tag);

        return { style: {} };
      },
      body: { appendChild: () => {}, style: {} },
    };
    const fakeWindow = {
      location: { origin: 'http://localhost' },
      localStorage: {
        getItem: () => null,
        setItem: () => {},
        removeItem: () => {},
      },
      addEventListener: () => {},
      XMLHttpRequest: { prototype: { open() {}, send() {} } },
    };
    fakeWindow.self = fakeWindow;
    fakeWindow.top = fakeWindow;
    fakeWindow.window = fakeWindow;
    globalThis.window = fakeWindow;
    globalThis.document = fakeDocument;
    globalThis.Turbo = {};
    const url = pathToFileURL(
      path.join(pluginRoot, 'webroot', 'js', 'inject-iframe.js'),
    ).href;
    await import(url);
    host = { posted, docListeners, created, fakeWindow };
  });

  after(() => {
    delete globalThis.window;
    delete globalThis.document;
    delete globalThis.Turbo;
  });

  it('waits for turbo load and creates no frame without an id', () => {
    assert.ok(Array.isArray(host.docListeners.keydown));
    assert.ok(Array.isArray(host.docListeners['turbo:load']));
    assert.equal(host.docListeners['DOMContentLoaded'], undefined);
    host.docListeners['turbo:load'][0]();
    assert.deepEqual(host.created, []);
  });

  it('ignores keydowns while the frame is missing', () => {
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
  });

  it('keeps proxied ajax quiet without a frame', () => {
    const seen = [];
    const xhr = {
      readyState: 4,
      status: 200,
      _arguments: ['GET', '/x'],
      getResponseHeader: (name) => (name === 'X-DEBUGBAR-ID' ? 'rid-9' : null),
      onreadystatechange: () => seen.push('orig'),
    };
    host.fakeWindow.XMLHttpRequest.prototype.send.call(xhr);
    xhr.onreadystatechange();
    assert.deepEqual(seen, ['orig']);
  });
});
