import { describe, it, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { handleRequestLinkClick } from '../../../webroot/js/shell/boot/picker.js';
import { FakeElement } from '../boot-fake-dom.js';

function link(url = '/queue/debug/related?key=k-1') {
  return new FakeElement('button', { 'data-dk-request-link': url });
}

function setup(
  selectImpl,
  payload = { request_id: 'req-9', inspector: 'queue' },
) {
  const calls = {
    select: [],
    swapped: [],
    refreshed: 0,
    synced: 0,
    opened: [],
  };
  const root = new FakeElement('div');
  const ctx = {
    root,
    shell: { selectRequest: selectImpl(calls) },
    swapRequestPanels: (id) => calls.swapped.push(id),
    openInspector: (key) => calls.opened.push(key),
    state: {},
  };
  globalThis.fetch = async () => ({
    ok: true,
    json: async () => payload,
  });

  return { calls, ctx };
}

async function settle() {
  await new Promise((resolve) => setTimeout(resolve, 10));
}

describe('boot request link', () => {
  afterEach(() => {
    delete globalThis.fetch;
  });

  it('ignores targets without a lookup URL', () => {
    const ctx = { root: new FakeElement('div'), shell: {} };

    assert.equal(handleRequestLinkClick(new FakeElement('button'), ctx), false);
  });

  it('ignores empty URLs without fetching', () => {
    let fetched = 0;
    globalThis.fetch = async () => {
      fetched += 1;

      return { ok: false };
    };
    const ctx = { root: new FakeElement('div'), shell: {} };

    assert.equal(handleRequestLinkClick(link(''), ctx), true);
    assert.equal(fetched, 0);
  });

  it('switches to the resolved request with its inspector', async () => {
    const { calls, ctx } = setup((calls) => async (id, options) => {
      calls.select.push([id, options]);

      return { id };
    });
    const target = link();

    assert.equal(handleRequestLinkClick(target, ctx), true);
    assert.equal(target.getAttribute('aria-busy'), 'true');
    await settle();

    assert.deepEqual(calls.select, [['req-9', { inspector: 'queue' }]]);
    assert.deepEqual(calls.swapped, ['req-9']);
    assert.equal(ctx.state.swapKeepKey, 'queue');
    assert.equal(target.hasAttribute('aria-busy'), false);
  });

  it('supports synchronous select results', async () => {
    const { calls, ctx } = setup((calls) => (id) => {
      calls.select.push([id, {}]);

      return { id, same: true };
    });

    handleRequestLinkClick(link(), ctx);
    await settle();

    assert.deepEqual(calls.select, [['req-9', {}]]);
    assert.deepEqual(calls.opened, ['request']);
    assert.deepEqual(calls.swapped, []);
  });

  it('stays put on unknown ids and lookup failures', async () => {
    const { calls, ctx } = setup(
      (calls) => async (id) => {
        calls.select.push(id);

        return { id };
      },
      { request_id: '' },
    );
    const target = link();

    handleRequestLinkClick(target, ctx);
    await settle();

    assert.deepEqual(calls.select, []);
    assert.equal(target.hasAttribute('aria-busy'), false);

    globalThis.fetch = async () => ({ ok: false });
    handleRequestLinkClick(link(), ctx);
    await settle();

    assert.deepEqual(calls.select, []);
  });
});
