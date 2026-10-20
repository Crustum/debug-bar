import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { FakeElement, FakeNode } from '../boot-fake-dom.js';

export const here = path.dirname(fileURLToPath(import.meta.url));
export const pluginRoot = path.resolve(here, '..', '..', '..');

export function el(tag, attrs = {}, children = []) {
  return new FakeElement(tag, attrs, children);
}

export function text(tag, attrs, value) {
  const node = el(tag, attrs);
  node.textContent = value;

  return node;
}

export function navRow(key, favorite) {
  const item = el('button', {
    'data-dk-inspector-nav-item': key,
    'aria-current': 'false',
    'aria-label': key,
  });
  const label = el('span', { class: 'dk-nav-label' });
  label.textContent = key;
  item.appendChild(label);
  const star = el('button', { 'data-dk-inspector-favorite': key });
  const row = el('div', { 'data-dk-nav-row': key }, [item, star]);
  if (favorite) {
    star.setAttribute('aria-pressed', 'true');
  }

  return { row, item, star };
}

export function buildRoot() {
  const summary = text(
    'script',
    { 'data-dk-summary': '' },
    JSON.stringify({
      id: 'req-1',
      max_requests: 5,
      inspectors: [
        { key: 'request', label: 'Request', description: 'Req desc' },
        { key: 'cache', label: 'Cache' },
      ],
    }),
  );
  const recent = text('script', { 'data-dk-recent': '' }, '[]');
  const toolbar = el('div', { 'data-dk-toolbar-shell': '' });
  const inspector = el('div', { 'data-dk-inspector': '' });
  const dialog = el('div', { id: 'debugbar-inspector' });
  const mini = el('button', { 'data-dk-mini-toggle': '' });

  const requestRow = navRow('request', false);
  const cacheRow = navRow('cache', false);
  const favList = el('div', { 'data-dk-nav-list': 'favorites' }, [
    cacheRow.row,
  ]);
  const insList = el('div', { 'data-dk-nav-list': 'inspectors' }, [
    requestRow.row,
  ]);
  const favGroup = el('div', { 'data-dk-nav-group': 'favorites' }, [favList]);
  const insGroup = el('div', { 'data-dk-nav-group': 'inspectors' }, [insList]);

  const heading = el('h2', { 'data-dk-inspector-heading': '' });
  const desc = el('p', { 'data-dk-inspector-description': '' });
  const loadingLabel = el('span', { 'data-dk-inspector-loading-label': '' });
  const content = el('div', { 'data-dk-inspector-content': '' });

  const expand = el('button', { 'data-dk-window': 'expand' });
  const shrink = el('button', { 'data-dk-window': 'shrink' });
  const hide = el('button', { 'data-dk-window': 'hide' });
  const backdrop = el('div', { 'data-dk-backdrop': '' });
  const openBtn = el('button', { 'data-dk-open-inspector': 'cache' });
  const retry = el('button', { 'data-dk-inspector-retry': '' });
  const reload = el('button', { 'data-dk-inspector-reload': '' });
  const activityRetry = el('button', { 'data-dk-activity-retry': '' });
  const paletteBtn = el('button', { 'data-dk-open-palette': '' });
  const paletteSearch = el('input', { 'data-dk-palette-search': '' });
  paletteSearch.value = '';
  const paletteList = el('div', { 'data-dk-palette-list': '' });
  const palette = el('div', { 'data-dk-palette': '', hidden: '' }, [
    paletteSearch,
    paletteList,
  ]);

  const copyLabel = el('span', { 'data-dk-copy-label': '' });
  copyLabel.textContent = 'Copy id';
  const copyBtn = el(
    'button',
    { 'data-dk-copy': '', 'data-dk-copy-value': 'req-1' },
    [copyLabel],
  );
  const copyAllLabel = el('span', { 'data-dk-copy-label': '' });
  copyAllLabel.textContent = 'Copy all';
  const copyValue = el('span', { 'data-dk-copy-value': 'a\nb' });
  const copyScope = el('div', { 'data-dk-copy-scope': '' }, [copyValue]);
  const copyAllBtn = el('button', { 'data-dk-copy-all': '' }, [copyAllLabel]);

  const tabBtn = el('button', {
    'data-dk-tab': 'http:http-1:overview',
    'aria-selected': 'false',
  });
  const tabPanel = el('div', { 'data-dk-tab-panel': 'http:http-1:overview' });
  const tabPanelHidden = el('div', {
    'data-dk-tab-panel': 'http:http-1:explain',
    hidden: '',
  });
  const detailTabs = el('div', { 'data-dk-detail-tabs': '' }, [tabBtn]);

  const filterRow = el('button', {
    'data-dk-filter-group': 'sent',
    'data-dk-filter-attention': 'false',
    'data-dk-search-text': 'hello world',
  });
  const filterTab = el('button', {
    'data-dk-filter': 'sent',
    'aria-selected': 'false',
  });
  const filterTabs = el('div', { 'data-dk-filter-tabs': '' }, [filterTab]);
  const searchInput = el('input', { 'data-dk-search': 'cache' });
  searchInput.value = '';
  const select = el('select', { 'data-dk-select': 'cache-filter' });
  select.value = 'sent';
  const filterScope = el('div', { 'data-dk-active-filter': 'all' }, [
    filterRow,
    filterTab,
    filterTabs,
    searchInput,
    select,
  ]);

  const treeGroup = el('details', { 'data-dk-tree-group': '' });
  const treeBtn = el('button', { 'data-dk-tree-expand': '' });
  const treeScope = el('div', { 'data-dk-tree-scope': '' }, [
    treeGroup,
    treeBtn,
  ]);

  const backBtn = el('button', { 'data-dk-workspace-back': '' });

  const httpRow = el('button', {
    'data-dk-http-entry': '',
    'data-dk-http-key': 'http-1',
    'aria-pressed': 'false',
  });
  const httpDetail = el('section', { 'data-dk-http-detail': 'http-1' });
  httpDetail.setAttribute('hidden', '');
  const httpPanel = el('div', { 'data-dk-http-panel': '' }, [
    httpRow,
    httpDetail,
  ]);
  const inspectorPanel = el('div', { 'data-dk-inspector-panel': 'request' });

  const root = el('div', { id: 'debugbar' }, [
    summary,
    recent,
    toolbar,
    inspector,
    dialog,
    mini,
    favGroup,
    insGroup,
    heading,
    desc,
    loadingLabel,
    content,
    expand,
    shrink,
    hide,
    backdrop,
    openBtn,
    retry,
    reload,
    activityRetry,
    paletteBtn,
    palette,
    copyBtn,
    copyScope,
    copyAllBtn,
    detailTabs,
    tabPanel,
    tabPanelHidden,
    filterScope,
    treeScope,
    backBtn,
    httpPanel,
    inspectorPanel,
  ]);

  return {
    root,
    toolbar,
    inspector,
    dialog,
    mini,
    requestRow,
    cacheRow,
    expand,
    shrink,
    hide,
    backdrop,
    openBtn,
    retry,
    reload,
    activityRetry,
    paletteBtn,
    palette,
    paletteSearch,
    copyBtn,
    copyLabel,
    copyAllBtn,
    copyAllLabel,
    tabBtn,
    tabPanel,
    tabPanelHidden,
    filterTab,
    filterRow,
    searchInput,
    select,
    treeGroup,
    treeBtn,
    backBtn,
    httpRow,
    httpDetail,
    heading,
    desc,
    content,
  };
}

export function installGlobals(root, { top = true, posted = [] } = {}) {
  const listeners = { document: {}, window: {} };
  const reloaded = [];
  const timers = [];
  const fakeWindow = {
    location: {
      origin: 'http://localhost',
      href: 'http://localhost/',
      reload: () => reloaded.push(true),
    },
    navigator: {
      clipboard: {
        writeText: async () => true,
      },
    },
    requestAnimationFrame: (fn) => {
      fn();

      return 1;
    },
    cancelAnimationFrame: () => {},
    setTimeout: (fn) => {
      timers.push(fn);

      return timers.length;
    },
    clearTimeout: () => {},
    matchMedia: () => ({ matches: false }),
    parent: { postMessage: (message) => posted.push(message) },
    addEventListener: (type, fn) => {
      (listeners.window[type] ??= []).push(fn);
    },
  };
  fakeWindow.self = top ? fakeWindow : {};
  fakeWindow.window = fakeWindow;
  fakeWindow.top = top ? fakeWindow : {};
  const fakeDocument = {
    cookie: '',
    getElementById: (id) => (id === 'debugbar' ? root : null),
    addEventListener: (type, fn) => {
      (listeners.document[type] ??= []).push(fn);
    },
    createElement: (tag) => el(tag),
    body: el('body'),
    activeElement: null,
  };
  globalThis.window = fakeWindow;
  globalThis.document = fakeDocument;
  globalThis.Element = FakeNode;

  return { listeners, reloaded, timers, posted, fakeWindow, fakeDocument };
}

export function cleanupGlobals() {
  delete globalThis.window;
  delete globalThis.document;
  delete globalThis.Element;
}

export function bootUrl() {
  return pathToFileURL(
    path.join(pluginRoot, 'webroot', 'js', 'shell', 'boot', 'boot.js'),
  ).href;
}

export function keydown(listeners, target, init) {
  for (const fn of listeners.document.keydown ?? []) {
    fn({ target, preventDefault: () => {}, ...init });
  }
}
