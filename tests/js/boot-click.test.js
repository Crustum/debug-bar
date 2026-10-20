import { describe, it, before } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { FakeElement, FakeNode } from './boot-fake-dom.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(here, '..', '..');

function loadDist() {
  const src = fs.readFileSync(
    path.join(pluginRoot, 'webroot', 'dist', 'debugbar.js'),
    'utf8',
  );
  const factory = new Function(
    `${src}; return typeof DebugBar !== 'undefined' ? DebugBar : null;`,
  );
  const namespace = factory();
  assert.ok(
    namespace && typeof namespace.createShell === 'function',
    'dist bundle exposes DebugBar',
  );

  return namespace;
}

function bootFileUrl() {
  return pathToFileURL(
    path.join(pluginRoot, 'webroot', 'js', 'shell', 'boot', 'boot.js'),
  ).href;
}

function assertBootEntry() {
  const src = fs.readFileSync(
    path.join(pluginRoot, 'webroot', 'js', 'shell', 'boot', 'boot.js'),
    'utf8',
  );
  assert.ok(
    src.includes("getElementById('debugbar')"),
    'boot entry found in shell/boot/boot.js',
  );
  assert.ok(
    src.includes("from './bridges.js'"),
    'boot entry wires bridge handlers',
  );
}

function el(tag, attrs = {}, children = []) {
  return new FakeElement(tag, attrs, children);
}

function text(tag, attrs, value) {
  const node = el(tag, attrs);
  node.textContent = value;

  return node;
}

// Builds the smallest tree the boot script touches on load: summary
// payloads, toolbar shell bits and one HTTP panel with two rows.
function buildRoot() {
  const summary = text(
    'script',
    { 'data-dk-summary': '' },
    JSON.stringify({ id: 'req-1' }),
  );
  const recent = text('script', { 'data-dk-recent': '' }, '[]');
  const row1 = el('button', {
    class: 'dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60',
    'data-dk-http-entry': '',
    'data-dk-http-key': 'http-1',
    'aria-pressed': 'true',
  });
  const row2 = el('button', {
    class: 'dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60',
    'data-dk-http-entry': '',
    'data-dk-http-key': 'http-2',
    'aria-pressed': 'false',
  });
  const detail1 = el('section', { 'data-dk-http-detail': 'http-1' });
  const detail2 = el('section', { 'data-dk-http-detail': 'http-2' });
  detail2.setAttribute('hidden', '');
  const panel = el('div', { 'data-dk-http-panel': '' }, [
    row1,
    row2,
    detail1,
    detail2,
  ]);
  const root = el('div', { id: 'debugbar' }, [
    summary,
    recent,
    el('div', { 'data-dk-toolbar-shell': '' }),
    el('div', { 'data-dk-inspector': '' }),
    el('div', { id: 'debugbar-inspector' }),
    el('button', { 'data-dk-mini-toggle': '' }),
    panel,
  ]);

  return { root, row1, row2, detail1, detail2 };
}

describe('requests boot clicks', () => {
  let DebugBar;

  before(() => {
    DebugBar = loadDist();
    assertBootEntry();
  });

  it('moves http selection and highlight classes on row click', async () => {
    const { root, row1, row2, detail1, detail2 } = buildRoot();
    const listeners = {};
    const fakeWindow = {
      DebugBar,
      location: { origin: 'http://localhost', href: 'http://localhost/' },
      addEventListener: (type, fn) => {
        (listeners[type] ??= []).push(fn);
      },
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
    await import(bootFileUrl());

    row2.click();
    assert.equal(row2.getAttribute('aria-pressed'), 'true');
    assert.equal(row1.getAttribute('aria-pressed'), 'false');
    assert.equal(detail2.hasAttribute('hidden'), false);
    assert.equal(detail1.hasAttribute('hidden'), true);
    assert.equal(row2.classList.contains('dk:bg-indigo-50/65'), true);
    assert.equal(row1.classList.contains('dk:bg-indigo-50/65'), false);

    delete globalThis.window;
    delete globalThis.document;
    delete globalThis.Element;
  });
});
