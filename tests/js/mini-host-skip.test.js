import { describe, it, before, after } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = fileURLToPath(new URL('.', import.meta.url));
const pluginRoot = path.resolve(here, '..', '..');

describe('host boot guard', () => {
  let docListeners;
  let winListeners;
  let created;

  before(async () => {
    delete globalThis.window;
    delete globalThis.document;
    docListeners = {};
    winListeners = {};
    created = [];
    const fakeDocument = {
      readyState: 'complete',
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
      addEventListener: (type, fn) => {
        (winListeners[type] ??= []).push(fn);
      },
      debugKitListenersApplied: true,
    };
    fakeWindow.self = fakeWindow;
    fakeWindow.top = fakeWindow;
    fakeWindow.window = fakeWindow;
    globalThis.window = fakeWindow;
    globalThis.document = fakeDocument;
    const url = pathToFileURL(
      path.join(pluginRoot, 'webroot', 'js', 'inject-iframe.js'),
    ).href;
    await import(url);
  });

  after(() => {
    delete globalThis.window;
    delete globalThis.document;
  });

  it('registers the hotkey but skips a second boot', () => {
    assert.ok(Array.isArray(docListeners.keydown));
    assert.deepEqual(created, []);
    assert.equal(winListeners.message, undefined);
  });
});
