import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  applyListFilter,
  currentRequestId,
  filterKeyOf,
  fitPopupInViewport,
  isEmbeddedFrame,
  setHidden,
} from '../../../webroot/js/shell/boot/core.js';
import { FakeElement } from '../boot-fake-dom.js';

function filterable(rows, { filter = 'all', search = '' } = {}) {
  const root = new FakeElement('div', { 'data-dk-active-filter': filter });
  for (const [group, text] of rows) {
    root.appendChild(
      new FakeElement('button', {
        'data-dk-search-text': text,
        'data-dk-filter-group': group,
      }),
    );
  }
  const input = new FakeElement('input', { 'data-dk-search': 'x' });
  input.value = search;
  root.appendChild(input);
  root.appendChild(
    new FakeElement('div', { 'data-dk-list-empty': '', hidden: '' }),
  );
  root.appendChild(
    new FakeElement('span', { 'data-dk-visible-count': '', hidden: '' }),
  );

  return root;
}

describe('core list filter edges', () => {
  it('resolves a null container to the default filter', () => {
    assert.equal(filterKeyOf(null), 'all');
  });

  it('ignores a null container', () => {
    assert.doesNotThrow(() => applyListFilter(null));
  });

  it('shows the empty note when nothing matches', () => {
    const root = filterable(
      [
        ['a', 'alpha'],
        ['a', 'beta'],
      ],
      { search: 'nothing-matches' },
    );
    applyListFilter(root);
    const empty = root.querySelector('[data-dk-list-empty]');
    const count = root.querySelector('[data-dk-visible-count]');

    assert.equal(empty.hasAttribute('hidden'), false);
    assert.equal(count.textContent, '0 shown');
  });

  it('resets the counter when everything matches', () => {
    const root = filterable([
      ['a', 'alpha'],
      ['a', 'beta'],
    ]);
    applyListFilter(root);
    const empty = root.querySelector('[data-dk-list-empty]');
    const count = root.querySelector('[data-dk-visible-count]');

    assert.equal(empty.hasAttribute('hidden'), true);
    assert.equal(count.textContent, '');
    assert.equal(count.hasAttribute('hidden'), true);
  });

  it('hides elements safely', () => {
    assert.doesNotThrow(() => setHidden(null, true));
    assert.doesNotThrow(() => setHidden(undefined, false));
    const el = new FakeElement('div');
    setHidden(el, true);

    assert.equal(el.hasAttribute('hidden'), true);
    setHidden(el, false);

    assert.equal(el.hasAttribute('hidden'), false);
  });
});

describe('core frame helpers', () => {
  let realWindow;

  beforeEach(() => {
    realWindow = globalThis.window;
  });

  afterEach(() => {
    if (realWindow === undefined) {
      delete globalThis.window;
    } else {
      globalThis.window = realWindow;
    }
  });

  it('reports embedded frames', () => {
    const self = {};
    globalThis.window = { self, top: {} };

    assert.equal(isEmbeddedFrame(), true);

    globalThis.window = { self, top: self };

    assert.equal(isEmbeddedFrame(), false);
  });

  it('tolerates throwing window access', () => {
    globalThis.window = {};
    Object.defineProperty(globalThis.window, 'self', {
      get() {
        throw new Error('gone');
      },
    });

    assert.equal(isEmbeddedFrame(), false);
  });

  it('ignores popups without geometry', () => {
    globalThis.window = { innerWidth: 800 };

    assert.doesNotThrow(() => fitPopupInViewport(null));
    assert.doesNotThrow(() => fitPopupInViewport({}));
    assert.doesNotThrow(() =>
      fitPopupInViewport({
        getBoundingClientRect: () => {
          throw new Error('gone');
        },
      }),
    );
    assert.doesNotThrow(() =>
      fitPopupInViewport({ getBoundingClientRect: () => null }),
    );
  });

  it('nudges popups into the viewport', () => {
    globalThis.window = { innerWidth: 800 };
    const left = {
      getBoundingClientRect: () => ({ left: 5, right: 100 }),
      style: {},
    };
    fitPopupInViewport(left);

    assert.equal(left.style.transform, 'translateX(7px)');

    const right = {
      getBoundingClientRect: () => ({ left: 100, right: 900 }),
      style: {},
    };
    fitPopupInViewport(right);

    assert.equal(right.style.transform, 'translateX(-88px)');

    const snug = {
      getBoundingClientRect: () => ({ left: 50, right: 200 }),
      style: {},
    };
    fitPopupInViewport(snug);

    assert.equal(snug.style.transform, '');
  });
});

describe('core request id resolution', () => {
  it('prefers the root attribute', () => {
    const root = new FakeElement('div', { 'data-dk-request-id': 'r1' });

    assert.equal(currentRequestId({ root }), 'r1');
    assert.equal(currentRequestId({ root: new FakeElement('div') }), null);
  });

  it('falls back through the shell', () => {
    assert.equal(currentRequestId({ shell: { currentProfileId: 'p1' } }), 'p1');
    assert.equal(currentRequestId({ shell: { currentRequestId: 'r2' } }), 'r2');
    assert.equal(currentRequestId({ shell: { summary: { id: 'r3' } } }), 'r3');
  });

  it('falls back to the summary', () => {
    assert.equal(currentRequestId({ summary: { id: 'r4' } }), 'r4');
    assert.equal(currentRequestId({}), null);
    assert.equal(currentRequestId(), null);
  });
});
