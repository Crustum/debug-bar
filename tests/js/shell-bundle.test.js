import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { FakeElement, FakeNode } from './boot-fake-dom.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(here, '..', '..');

function shellBundleUrl() {
  return pathToFileURL(path.join(pluginRoot, 'webroot', 'dist', 'shell.js'))
    .href;
}

function el(tag, attrs = {}, children = []) {
  return new FakeElement(tag, attrs, children);
}

function text(tag, attrs, value) {
  const node = el(tag, attrs);
  node.textContent = value;

  return node;
}

function buildRoot() {
  const summary = text(
    'script',
    { 'data-dk-summary': '' },
    JSON.stringify({ id: 'req-1' }),
  );
  const recent = text('script', { 'data-dk-recent': '' }, '[]');
  const row1 = el('button', {
    'data-dk-http-entry': '',
    'data-dk-http-key': 'http-1',
    'aria-pressed': 'true',
  });
  const detail1 = el('section', { 'data-dk-http-detail': 'http-1' });
  const panel = el('div', { 'data-dk-http-panel': '' }, [row1, detail1]);
  const root = el('div', { id: 'debugbar' }, [
    summary,
    recent,
    el('div', { 'data-dk-toolbar-shell': '' }),
    el('div', { 'data-dk-inspector': '' }),
    el('div', { id: 'debugbar-inspector' }),
    el('button', { 'data-dk-mini-toggle': '' }),
    panel,
  ]);

  return { root, row1, detail1 };
}

describe('packed shell bundle', () => {
  it('boots the shell from dist without raw modules', async () => {
    const { root } = buildRoot();
    const fakeWindow = {
      location: { origin: 'http://localhost', href: 'http://localhost/' },
      addEventListener: () => {},
      top: {},
      parent: {},
    };
    fakeWindow.self = fakeWindow;
    fakeWindow.window = fakeWindow;
    const fakeDocument = {
      cookie: '',
      getElementById: (id) => (id === 'debugbar' ? root : null),
      addEventListener: () => {},
      createElement: (tag) => el(tag),
      body: el('body'),
    };
    globalThis.window = fakeWindow;
    globalThis.document = fakeDocument;
    globalThis.Element = FakeNode;

    try {
      await import(shellBundleUrl());
    } finally {
      delete globalThis.window;
      delete globalThis.document;
      delete globalThis.Element;
    }

    assert.ok(root.__dkShell, 'packed bundle wires the shell onto the root');
  });
});
