import { describe, it, after } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = fileURLToPath(new URL('.', import.meta.url));
const pluginRoot = path.resolve(here, '..', '..');

describe('host without event support', () => {
  after(() => {
    delete globalThis.window;
    delete globalThis.document;
  });

  it('throws a clear error', async () => {
    delete globalThis.window;
    delete globalThis.document;
    const fakeWindow = {
      location: { origin: 'http://localhost' },
    };
    fakeWindow.self = fakeWindow;
    fakeWindow.top = fakeWindow;
    fakeWindow.window = fakeWindow;
    globalThis.window = fakeWindow;
    globalThis.document = {
      getElementById: () => null,
    };
    const url = pathToFileURL(
      path.join(pluginRoot, 'webroot', 'js', 'inject-iframe.js'),
    ).href;
    await assert.rejects(import(url), /addEventListener/);
  });
});
