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

function el(tag, attrs = {}, children = []) {
  return new FakeElement(tag, attrs, children);
}

function text(tag, attrs, value) {
  const node = el(tag, attrs);
  node.textContent = value;

  return node;
}

describe('boot collapse cycle', () => {
  let DebugBar;
  let root;
  let mini;
  let shell;
  let inspector;
  let expandBtn;
  let closeBtn;
  let shrinkBtn;
  let posted;

  before(async () => {
    DebugBar = loadDist();
    root = el('div', { id: 'debugbar', 'data-dk-frame': 'full' });
    const summary = text(
      'script',
      { 'data-dk-summary': '' },
      JSON.stringify({ id: 'req-1' }),
    );
    const recent = text('script', { 'data-dk-recent': '' }, '[]');
    mini = el('button', { 'data-dk-mini-toggle': '' });
    shell = el('div', { 'data-dk-toolbar-shell': '' });
    inspector = el('div', { 'data-dk-inspector': '', hidden: '' });
    expandBtn = el('button', { 'data-dk-window': 'expand' });
    closeBtn = el('button', { 'data-dk-window': 'close' });
    shrinkBtn = el('button', { 'data-dk-window': 'shrink' });
    shell.appendChild(expandBtn);
    shell.appendChild(shrinkBtn);
    shell.appendChild(closeBtn);
    root.appendChild(summary);
    root.appendChild(recent);
    root.appendChild(mini);
    root.appendChild(shell);
    root.appendChild(inspector);
    posted = [];
    const fakeWindow = {
      DebugBar,
      location: { origin: 'http://localhost', href: 'http://localhost/' },
      addEventListener: () => {},
      setTimeout: (...args) => setTimeout(...args),
      clearTimeout: (...args) => clearTimeout(...args),
    };
    fakeWindow.self = {};
    fakeWindow.top = {};
    fakeWindow.parent = {
      postMessage: (message) => posted.push(message),
    };
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
    await import(
      pathToFileURL(
        path.join(pluginRoot, 'webroot', 'js', 'shell', 'boot', 'boot.js'),
      ).href
    );
  });

  it('expand shows only the inspector, collapse returns to mini only', () => {
    expandBtn.click();
    assert.equal(mini.hasAttribute('hidden'), true);
    assert.equal(shell.hasAttribute('hidden'), true);
    assert.equal(inspector.hasAttribute('hidden'), false);

    closeBtn.click();
    assert.equal(mini.hasAttribute('hidden'), false);
    assert.equal(shell.hasAttribute('hidden'), true);
    assert.equal(inspector.hasAttribute('hidden'), true);
    assert.deepEqual(posted.slice(-1), ['collapse']);
  });

  it('shrink hides the inspector but keeps the bottom bar', () => {
    expandBtn.click();
    assert.equal(inspector.hasAttribute('hidden'), false);

    shrinkBtn.click();
    assert.equal(mini.hasAttribute('hidden'), true);
    assert.equal(shell.hasAttribute('hidden'), false);
    assert.equal(inspector.hasAttribute('hidden'), true);
    assert.deepEqual(posted.slice(-1), ['toolbar']);
  });
});
