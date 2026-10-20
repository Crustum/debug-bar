import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { handleCacheClearClick } from '../../../webroot/js/shell/panels/cache.js';
import { FakeElement } from '../boot-fake-dom.js';

class RowButton extends FakeElement {
  constructor(attrs, row) {
    super('button', attrs);
    this.row = row;
  }

  closest(selector) {
    if (selector === 'li' && this.row) {
      return this.row;
    }

    return super.closest(selector);
  }
}

function cacheRow(extra = {}) {
  const row = new FakeElement('li');
  const result = new FakeElement('p', {
    'data-dk-cache-clear-result': '',
    hidden: '',
  });
  const button = new RowButton(
    {
      'data-dk-cache-clear': '',
      'data-dk-cache-clear-name': 'default',
      'data-dk-cache-clear-url': '/debug-bar/cache/clear',
      ...extra,
    },
    row,
  );
  row.appendChild(button);
  row.appendChild(result);

  return { row, button, result };
}

function stubFetch(handler) {
  const calls = [];
  const realFetch = globalThis.fetch;
  globalThis.fetch = (url, options) => {
    calls.push({ url, options });

    return handler(url, options);
  };

  return {
    calls,
    restore() {
      if (realFetch === undefined) {
        delete globalThis.fetch;
      } else {
        globalThis.fetch = realFetch;
      }
    },
  };
}

function stubWindow() {
  const timers = [];
  const realWindow = globalThis.window;
  globalThis.window = {
    setTimeout: (fn) => {
      timers.push(fn);

      return timers.length;
    },
    clearTimeout: () => {},
  };

  return {
    timers,
    restore() {
      if (realWindow === undefined) {
        delete globalThis.window;
      } else {
        globalThis.window = realWindow;
      }
    },
  };
}

async function flush(times = 6) {
  for (let i = 0; i < times; i += 1) {
    await new Promise((resolve) => setImmediate(resolve));
  }
}

describe('cache clear action', () => {
  let fetchStub;
  let windowStub;

  beforeEach(() => {
    windowStub = stubWindow();
  });

  afterEach(() => {
    fetchStub?.restore();
    fetchStub = null;
    windowStub.restore();
  });

  it('ignores unrelated clicks', () => {
    assert.equal(handleCacheClearClick(new FakeElement('button')), false);
  });

  it('clears one engine and reports the payload message', async () => {
    const { button, result } = cacheRow();
    fetchStub = stubFetch(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ message: 'Gone.' }),
      }),
    );

    assert.equal(handleCacheClearClick(button), true);
    assert.equal(button.hasAttribute('disabled'), true);
    assert.equal(result.textContent, 'Clearing default…');
    await flush();

    assert.equal(result.textContent, 'Gone.');
    assert.equal(button.hasAttribute('disabled'), false);
    assert.equal(fetchStub.calls.length, 1);
    assert.equal(
      fetchStub.calls[0].options.body,
      'name=' + encodeURIComponent('default'),
    );
  });

  it('clears every engine with an empty body', async () => {
    const { button, result } = cacheRow({
      'data-dk-cache-clear-all': '',
      'data-dk-cache-clear-all-url': '/debug-bar/cache/clear-all',
    });
    delete button.attrs['data-dk-cache-clear'];
    fetchStub = stubFetch(() =>
      Promise.resolve({ ok: true, json: () => Promise.resolve({}) }),
    );
    handleCacheClearClick(button);
    await flush();

    assert.equal(result.textContent, 'Cache cleared.');
    assert.equal(fetchStub.calls[0].url, '/debug-bar/cache/clear-all');
    assert.equal(fetchStub.calls[0].options.body, '');
  });

  it('reports failures without throwing', async () => {
    const { button, result } = cacheRow();
    fetchStub = stubFetch(() => Promise.reject(new Error('offline')));
    handleCacheClearClick(button);
    await flush();

    assert.equal(result.textContent, 'Could not clear default.');
    assert.equal(button.hasAttribute('disabled'), false);
  });

  it('reports failed responses without throwing', async () => {
    const { button, result } = cacheRow();
    fetchStub = stubFetch(() => Promise.resolve({ ok: false }));
    handleCacheClearClick(button);
    await flush();

    assert.equal(result.textContent, 'Could not clear default.');
  });

  it('reports failed clear-all responses', async () => {
    const { button, result } = cacheRow({
      'data-dk-cache-clear-all': '',
      'data-dk-cache-clear-all-url': '/debug-bar/cache/clear-all',
    });
    delete button.attrs['data-dk-cache-clear'];
    fetchStub = stubFetch(() => Promise.resolve({ ok: false }));
    handleCacheClearClick(button);
    await flush();

    assert.equal(result.textContent, 'Could not clear caches.');
  });

  it('hides the result note after the delay', async () => {
    const { button, result } = cacheRow();
    fetchStub = stubFetch(() =>
      Promise.resolve({ ok: true, json: () => Promise.resolve({}) }),
    );
    handleCacheClearClick(button);
    await flush();

    assert.equal(result.hasAttribute('hidden'), false);
    assert.equal(windowStub.timers.length, 2);
    windowStub.timers[windowStub.timers.length - 1]();

    assert.equal(result.textContent, '');
    assert.equal(result.hasAttribute('hidden'), true);
  });

  it('works without a result note in the row', async () => {
    const button = new RowButton(
      {
        'data-dk-cache-clear': '',
        'data-dk-cache-clear-name': 'default',
        'data-dk-cache-clear-url': '/debug-bar/cache/clear',
      },
      new FakeElement('li'),
    );
    fetchStub = stubFetch(() =>
      Promise.resolve({ ok: true, json: () => Promise.resolve({}) }),
    );

    assert.equal(handleCacheClearClick(button), true);
    await flush();

    assert.equal(button.hasAttribute('disabled'), false);
  });

  it('flashes the button icon and clears pending flashes', async () => {
    const { button } = cacheRow();
    button.appendChild(new FakeElement('svg'));
    fetchStub = stubFetch(() =>
      Promise.resolve({ ok: true, json: () => Promise.resolve({}) }),
    );
    handleCacheClearClick(button);
    handleCacheClearClick(button);
    await flush();

    assert.equal(button.hasAttribute('disabled'), false);
    const icon = button.querySelector('svg');
    assert.equal(icon.classList.contains('dk-cache-flash'), true);
    windowStub.timers.forEach((timer) => timer());
    assert.equal(icon.classList.contains('dk-cache-flash'), false);
  });

  it('tolerates icon classList failures and missing timers', async () => {
    const { button } = cacheRow();
    const broken = new FakeElement('svg');
    broken.classList = {
      add: () => {
        throw new Error('nope');
      },
      remove: () => {},
    };
    button.appendChild(broken);
    const realWindow = globalThis.window;
    globalThis.window = {};
    fetchStub = stubFetch(() =>
      Promise.resolve({ ok: true, json: () => Promise.resolve({}) }),
    );
    handleCacheClearClick(button);
    await flush();
    globalThis.window = realWindow;

    assert.equal(button.hasAttribute('disabled'), false);
  });
});
