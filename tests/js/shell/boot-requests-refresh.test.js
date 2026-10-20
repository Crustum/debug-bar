import { describe, it, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { handleRequestsRefreshClick } from '../../../webroot/js/shell/boot/picker.js';
import { FakeElement } from '../boot-fake-dom.js';

function option(id) {
  const row = new FakeElement('li', {
    role: 'option',
    'data-dk-request-option': id,
    'aria-selected': 'false',
  });
  row.appendChild(
    new FakeElement('span', { 'data-dk-request-unread': '', hidden: '' }),
  );

  return row;
}

function refreshButton() {
  return new FakeElement('button', { 'data-dk-requests-refresh': '' });
}

function setup() {
  const calls = { loaded: 0 };
  const root = new FakeElement('div');
  const picker = new FakeElement('ul', { 'data-dk-request-picker': 'toolbar' });
  picker.appendChild(option('req-1'));
  root.appendChild(picker);
  const badge = new FakeElement('span', {
    'data-dk-request-badge': '',
    hidden: '',
  });
  root.appendChild(badge);
  const target = refreshButton();
  root.appendChild(target);
  const shell = {
    currentRequestId: 'req-1',
    currentRequest: { method: 'GET', url: '/one' },
    recentRequests: [
      { id: 'req-1', method: 'GET', url: '/one', status_code: 200 },
    ],
    viewedRequestIds: ['req-1'],
    requestIsUnread: (entry) => entry.id !== 'req-1',
    unreadRequestCount: 0,
    loadRecentRequests: async () => {
      calls.loaded += 1;
      shell.recentRequests = [
        { id: 'req-1', method: 'GET', url: '/one', status_code: 200 },
        {
          id: 'req-2',
          method: 'JOB',
          url: 'DebugBarProbeJob',
          status_code: 200,
        },
      ];
      shell.unreadRequestCount = 1;

      return shell.recentRequests;
    },
  };
  const ctx = { root, shell };
  globalThis.document = {
    createElement: (tag) => new FakeElement(tag),
  };

  return { calls, ctx, picker, target };
}

async function settle() {
  await new Promise((resolve) => setTimeout(resolve, 10));
}

describe('boot requests refresh', () => {
  afterEach(() => {
    delete globalThis.document;
  });

  it('ignores targets without the refresh hook', () => {
    const ctx = { root: new FakeElement('div'), shell: {} };

    assert.equal(
      handleRequestsRefreshClick(new FakeElement('button'), ctx),
      false,
    );
  });

  it('reloads options in place and keeps the refresh row last', async () => {
    const { calls, ctx, picker, target } = setup();

    assert.equal(handleRequestsRefreshClick(target, ctx), true);
    assert.equal(target.getAttribute('aria-busy'), 'true');
    await settle();

    assert.equal(calls.loaded, 1);
    const options = picker.querySelectorAll('[data-dk-request-option]');
    assert.equal(options.length, 2);
    assert.equal(options[1].getAttribute('data-dk-request-option'), 'req-2');
    const dots = picker.querySelectorAll('[data-dk-request-unread]');
    assert.equal(dots[0].hasAttribute('hidden'), true);
    assert.equal(dots[1].hasAttribute('hidden'), false);
    assert.equal(target.hasAttribute('aria-busy'), false);
  });

  it('stays put when the shell cannot reload', async () => {
    const ctx = { root: new FakeElement('div'), shell: {} };
    const target = new FakeElement('button', {
      'data-dk-requests-refresh': '',
    });

    assert.equal(handleRequestsRefreshClick(target, ctx), true);
    await settle();

    assert.equal(target.hasAttribute('aria-busy'), false);
  });
});
