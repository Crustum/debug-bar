import { describe, it, before } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { FakeElement, FakeNode } from '../boot-fake-dom.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(here, '..', '..', '..');

function el(tag, attrs = {}, children = []) {
  return new FakeElement(tag, attrs, children);
}

// nav row: <div data-dk-nav-row="key"><button nav-item><button star>
function navRow(key) {
  const item = el('button', { 'data-dk-inspector-nav-item': key });
  const star = el('button', { 'data-dk-inspector-favorite': '' });
  const row = el('div', { 'data-dk-nav-row': key }, [item, star]);

  return { row, item, star };
}

function buildRoot() {
  const summary = el('script', { 'data-dk-summary': '' });
  summary.textContent = JSON.stringify({
    id: 'req-1',
    inspectors: [
      { key: 'request', label: 'Request' },
      { key: 'queries', label: 'Queries' },
      { key: 'cache', label: 'Cache' },
      { key: 'models', label: 'Models' },
    ],
  });
  const recent = el('script', { 'data-dk-recent': '' });
  recent.textContent = '[]';
  const favList = el('div', { 'data-dk-nav-list': 'favorites' });
  const insList = el('div', { 'data-dk-nav-list': 'inspectors' });
  const favGroup = el('div', { 'data-dk-nav-group': 'favorites' }, [favList]);
  const insGroup = el('div', { 'data-dk-nav-group': 'inspectors' }, [insList]);
  const byKey = {};
  for (const key of ['request', 'queries', 'cache', 'models']) {
    const built = navRow(key);
    byKey[key] = built;
    insList.appendChild(built.row);
  }
  const root = el('div', { id: 'debugbar' }, [
    summary,
    recent,
    el('div', { 'data-dk-toolbar-shell': '' }),
    el('div', { 'data-dk-inspector': '' }),
    el('div', { id: 'debugbar-inspector' }),
    el('button', { 'data-dk-mini-toggle': '' }),
    favGroup,
    insGroup,
  ]);

  return { root, byKey, insList };
}

function orderOf(insList) {
  return insList.children.map((row) =>
    row
      .querySelector('[data-dk-inspector-nav-item]')
      .getAttribute('data-dk-inspector-nav-item'),
  );
}

function bootFileUrl() {
  return pathToFileURL(
    path.join(pluginRoot, 'webroot', 'js', 'shell', 'boot', 'boot.js'),
  ).href;
}

describe('nav reorder keeps working without re-clicking', () => {
  let keydown;
  let root;
  let byKey;
  let insList;

  before(async () => {
    const built = buildRoot();
    root = built.root;
    byKey = built.byKey;
    insList = built.insList;
    const listeners = {};
    const fakeWindow = {
      location: { origin: 'http://localhost', href: 'http://localhost/' },
      addEventListener: (type, fn) => {
        (listeners[type] ??= []).push(fn);
      },
      top: {},
      parent: {},
    };
    fakeWindow.self = fakeWindow;
    fakeWindow.window = fakeWindow;
    globalThis.window = fakeWindow;
    globalThis.document = {
      cookie: '',
      getElementById: (id) => (id === 'debugbar' ? root : null),
      addEventListener: (type, fn) => {
        (listeners[type] ??= []).push(fn);
      },
      createElement: (tag) => el(tag),
      body: el('body'),
    };
    globalThis.Element = FakeNode;
    await import(bootFileUrl());
    keydown = (target, key) => {
      const event = { key, shiftKey: true, target, preventDefault: () => {} };
      for (const fn of listeners.keydown ?? []) fn(event);
    };
  });

  it('reorders twice in a row from the star button', () => {
    // shell order starts label-sorted: cache, models, queries, request
    assert.deepEqual(orderOf(insList), [
      'cache',
      'models',
      'queries',
      'request',
    ]);

    // focus sits on the star button after starring; the row still reorders
    keydown(byKey.queries.star, 'ArrowUp');
    assert.deepEqual(orderOf(insList), [
      'cache',
      'queries',
      'models',
      'request',
    ]);

    // no re-click: the second press moves the same row again
    keydown(byKey.queries.star, 'ArrowUp');
    assert.deepEqual(orderOf(insList), [
      'queries',
      'cache',
      'models',
      'request',
    ]);
  });

  it('restores focus to the moved row control', () => {
    const seen = [];
    byKey.cache.star.focus = () => {
      seen.push('cache-star');
    };
    keydown(byKey.cache.star, 'ArrowUp');
    assert.deepEqual(orderOf(insList), [
      'cache',
      'queries',
      'models',
      'request',
    ]);
    assert.deepEqual(seen, ['cache-star']);
  });
  it('reorders from the nav item itself', () => {
    keydown(byKey.models.item, 'ArrowDown');
    assert.deepEqual(orderOf(insList), [
      'cache',
      'queries',
      'request',
      'models',
    ]);
  });
});
