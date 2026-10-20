import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
  NAV_GROUPS,
  navRowKey,
  collectNavRows,
  renderNavGroups,
  syncNavCounts,
} from '../../../webroot/js/shell/nav-groups.js';

function matchSelector(node, selector) {
  let attr = selector.match(/^\[([a-z-]+)(?:="([^"]*)")?\]$/);
  if (attr) {
    const value = node.getAttribute(attr[1]);

    return attr[2] === undefined ? value !== null : value === attr[2];
  }
  const cls = selector.match(/^\.([a-z-]+)$/);
  if (cls) return (node.classes ?? []).includes(cls[1]);

  return false;
}

function fakeEl(attrs = {}, classes = []) {
  const store = { ...attrs };
  const el = {
    children: [],
    parent: null,
    classes: [...classes],
    textContent: '',
    hidden: false,
    removed: false,
    getAttribute: (name) => (name in store ? store[name] : null),
    setAttribute: (name, value) => {
      store[name] = String(value);
      if (name === 'hidden') el.hidden = true;
    },
    removeAttribute: (name) => {
      delete store[name];
      if (name === 'hidden') el.hidden = false;
    },
    appendChild: (child) => {
      if (child.parent) {
        child.parent.children = child.parent.children.filter(
          (node) => node !== child,
        );
      }
      child.parent = el;
      el.children.push(child);

      return child;
    },
    remove: () => {
      el.removed = true;
      if (el.parent)
        el.parent.children = el.parent.children.filter((node) => node !== el);
    },
    querySelector: (selector) => fakeRoot(el).querySelector(selector),
    querySelectorAll: (selector) => fakeRoot(el).querySelectorAll(selector),
  };

  return el;
}

function fakeRoot(top) {
  function find(selector) {
    const out = [];
    const visit = (node) => {
      for (const child of node.children ?? []) {
        if (matchSelector(child, selector)) out.push(child);
        visit(child);
      }
    };
    visit(top);

    return out;
  }

  return {
    querySelector: (selector) => find(selector)[0] ?? null,
    querySelectorAll: (selector) => find(selector),
  };
}

function navRow(key, label, count = null) {
  const row = fakeEl({ 'data-dk-nav-row': key });
  const item = fakeEl({
    'data-dk-inspector-nav-item': key,
    'aria-label': label,
  });
  const side = fakeEl({}, ['dk-nav-side']);
  if (count !== null) {
    const badge = fakeEl({ 'data-dk-inspector-count': '' }, ['dk-nav-count']);
    badge.textContent = String(count);
    side.appendChild(badge);
  }
  item.appendChild(side);
  const star = fakeEl({
    'data-dk-inspector-favorite': key,
    'aria-pressed': 'false',
  });
  const outline = fakeEl({}, ['dk-nav-star-outline']);
  const filled = fakeEl({}, ['dk-nav-star-filled']);
  filled.setAttribute('hidden', '');
  star.appendChild(outline);
  star.appendChild(filled);
  row.appendChild(item);
  row.appendChild(star);

  return row;
}

function sidebar(keys = ['request', 'sqllog', 'timer']) {
  const root = fakeEl({ id: 'debugbar' });
  const favGroup = fakeEl({ 'data-dk-nav-group': 'favorites' });
  favGroup.setAttribute('hidden', '');
  const favList = fakeEl({ 'data-dk-nav-list': 'favorites' });
  const allGroup = fakeEl({ 'data-dk-nav-group': 'inspectors' });
  const allList = fakeEl({ 'data-dk-nav-list': 'inspectors' });
  for (const key of keys)
    allList.appendChild(navRow(key, key, key === 'sqllog' ? 5 : null));
  favGroup.appendChild(favList);
  allGroup.appendChild(allList);
  root.appendChild(favGroup);
  root.appendChild(allGroup);

  return { root, favGroup, favList, allList };
}

function fakeShell(favorites = []) {
  const order = ['request', 'sqllog', 'timer'];
  const entries = order.map((key) => ({ key, label: key, active: true }));

  return {
    navigationInspectors: (favorite) =>
      entries.filter((entry) => favorites.includes(entry.key) === favorite),
    isFavorite: (key) => favorites.includes(key),
  };
}

describe('nav groups', () => {
  it('exposes both group names', () => {
    assert.deepEqual(NAV_GROUPS, ['favorites', 'inspectors']);
  });

  it('keys rows by their nav item', () => {
    const { root } = sidebar(['sqllog']);
    const row = root.querySelector('[data-dk-nav-row]');
    assert.equal(navRowKey(row), 'sqllog');
    assert.equal(navRowKey(null), null);
    assert.deepEqual(Object.keys(collectNavRows(root)), ['sqllog']);
  });

  it('moves starred rows into favorites with star states', () => {
    const { root, favGroup, favList, allList } = sidebar();
    const result = renderNavGroups(root, fakeShell(['sqllog']));

    assert.deepEqual(result, { favorites: 1, inspectors: 2 });
    assert.equal(favGroup.hidden, false);
    assert.equal(favList.children.length, 1);
    assert.equal(allList.children.length, 2);
    assert.equal(navRowKey(favList.children[0]), 'sqllog');

    const star = favList.children[0].querySelector(
      '[data-dk-inspector-favorite]',
    );
    assert.equal(star.getAttribute('aria-pressed'), 'true');
    assert.equal(
      star.getAttribute('aria-label'),
      'Remove sqllog from favorites',
    );
    assert.equal(
      star.querySelector('.dk-nav-star-filled').getAttribute('hidden'),
      null,
    );
    assert.notEqual(
      star.querySelector('.dk-nav-star-outline').getAttribute('hidden'),
      null,
    );
  });

  it('hides the favorites group when empty', () => {
    const { root, favGroup, allList } = sidebar();
    const result = renderNavGroups(root, fakeShell([]));

    assert.deepEqual(result, { favorites: 0, inspectors: 3 });
    assert.equal(favGroup.hidden, true);
    assert.equal(allList.children.length, 3);
  });

  it('tolerates missing lists and shell', () => {
    assert.deepEqual(renderNavGroups(fakeEl(), fakeShell(['sqllog'])), {
      favorites: 0,
      inspectors: 0,
    });
    const { root } = sidebar();
    assert.deepEqual(renderNavGroups(root, null), {
      favorites: 0,
      inspectors: 0,
    });
  });
});

describe('nav counts', () => {
  it('updates, removes and creates badges', () => {
    const { root } = sidebar(['sqllog', 'timer']);
    const created = [];
    const updated = syncNavCounts(
      root,
      [
        { key: 'sqllog', count: 9 },
        { key: 'timer', count: 4 },
        { key: 'missing', count: 1 },
      ],
      (tag) => {
        const el = fakeEl();
        el.tag = tag;
        created.push(el);

        return el;
      },
    );

    assert.equal(updated, 2);
    const sqlRow = root.querySelector('[data-dk-nav-row="sqllog"]');
    assert.equal(
      sqlRow.querySelector('[data-dk-inspector-count]').textContent,
      '9',
    );
    const timerRow = root.querySelector('[data-dk-nav-row="timer"]');
    assert.equal(
      timerRow.querySelector('[data-dk-inspector-count]').textContent,
      '4',
    );
    assert.equal(created.length, 1);

    const removed = syncNavCounts(root, [{ key: 'sqllog', count: null }]);
    assert.equal(removed, 1);
    assert.equal(sqlRow.querySelector('[data-dk-inspector-count]'), null);
  });

  it('ignores bad payloads', () => {
    const { root } = sidebar();
    assert.equal(syncNavCounts(root, null), 0);
    assert.equal(syncNavCounts(root, 'nope'), 0);
    assert.equal(syncNavCounts(null, [{ key: 'sqllog', count: 1 }]), 0);
  });
});
