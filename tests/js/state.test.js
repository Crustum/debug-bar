import { describe, it, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  createShell,
  SHELL_EVENTS,
  SHELL_ROOT_ID,
} from '../../webroot/js/state.js';

const globalsToRestore = [];

function setGlobal(name, value) {
  const had = Object.hasOwn(globalThis, name);
  globalsToRestore.push([name, had ? globalThis[name] : undefined, had]);
  if (value === undefined) {
    delete globalThis[name];
  } else {
    globalThis[name] = value;
  }
}

afterEach(() => {
  while (globalsToRestore.length > 0) {
    const [name, value, had] = globalsToRestore.pop();
    if (had) {
      globalThis[name] = value;
    } else {
      delete globalThis[name];
    }
  }
  delete globalThis.document;
});

describe('createShell composition', () => {
  it('builds a minimal store without parts', () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    assert.equal(shell.summary.id, 'req-1');
    assert.equal(shell.currentProfileId, 'req-1');
    assert.deepEqual(shell.noticedProfileIds, []);
    assert.deepEqual(shell.events, SHELL_EVENTS);
    assert.equal(typeof shell.emit, 'function');
    assert.equal(typeof shell.on, 'function');
    assert.equal(shell.registry.size, 0);
  });

  it('tolerates missing summary and defaults', () => {
    const shell = createShell(null);
    assert.deepEqual(shell.summary, {});
    assert.equal(shell.currentProfileId, null);

    const empty = createShell();
    assert.equal(empty.currentProfileId, null);
  });

  it('merges part states with later parts winning', () => {
    const shell = createShell(
      { id: 'req-1' },
      {
        fetchClient: async () => null,
        parts: [
          { toolbarPlacement: 'top' },
          { toolbarPlacement: 'bottom', extra: 1 },
        ],
      },
    );
    assert.equal(shell.toolbarPlacement, 'bottom');
    assert.equal(shell.extra, 1);
    assert.equal(shell.currentProfileId, 'req-1');
  });

  it('accepts a custom registry and runtime', () => {
    const registry = { size: 7, create: () => 'panel' };
    const runtime = { now: () => 42 };
    const shell = createShell(
      { id: 'req-1' },
      { registry, runtime, fetchClient: async () => null },
    );
    assert.equal(shell.registry, registry);
    assert.equal(shell.browser, runtime);
    assert.equal(shell.createPanel('queries'), 'panel');
  });

  it('restores noticed ids from the summary', () => {
    const shell = createShell(
      { id: 'req-1', noticedIds: ['req-0'] },
      { fetchClient: async () => null },
    );
    assert.deepEqual(shell.noticedProfileIds, ['req-0']);
  });

  it('seeds the request switcher from options.recentRequests', () => {
    const recent = [
      {
        id: 'req-2',
        url: '/two',
        method: 'GET',
        status_code: 200,
        requested_at: '',
      },
      {
        id: 'req-3',
        url: '/three',
        method: 'POST',
        status_code: 500,
        requested_at: '',
      },
    ];
    const shell = createShell(
      { id: 'req-1', url: '/one' },
      {
        fetchClient: async () => null,
        recentRequests: recent,
        runtime: { activeElement: () => null },
      },
    );
    assert.equal(shell.hasOtherRequests, true);
    assert.equal(shell.laterRequestCount, 2);
    assert.equal(shell.openRequestPicker('toolbar'), 'toolbar');
    shell.closeRequestPicker(false);
  });

  it('falls back to summary.recent when no option is given', () => {
    const shell = createShell(
      { id: 'req-1', recent: [{ id: 'req-2', url: '/two' }] },
      { fetchClient: async () => null },
    );
    assert.equal(shell.hasOtherRequests, true);
  });

  it('keeps a single-request shell without a picker', () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    assert.equal(shell.hasOtherRequests, false);
    assert.equal(shell.openRequestPicker('toolbar'), null);
  });
});

describe('shell event bus', () => {
  it('delivers CustomEvent detail on the internal bus', () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    const received = [];
    const off = shell.on('debugbar:profile-switched', (detail) =>
      received.push(detail),
    );

    assert.equal(
      shell.emit('debugbar:profile-switched', { profileId: 'req-1' }),
      true,
    );
    assert.deepEqual(received, [{ profileId: 'req-1' }]);

    off();
    shell.emit('debugbar:profile-switched', { profileId: 'req-2' });
    assert.equal(received.length, 1);
  });

  it('forwards events to the #debugbar root element', () => {
    const root = new EventTarget();
    const seen = [];
    root.addEventListener('debugbar:content-updated', (event) =>
      seen.push(event.detail),
    );
    setGlobal('document', {
      getElementById: (id) => (id === SHELL_ROOT_ID ? root : null),
    });

    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    shell.emit('debugbar:content-updated', { profileId: 'req-1' });
    assert.deepEqual(seen, [{ profileId: 'req-1' }]);
    assert.ok(seen.length === 1);
  });

  it('emits without a document or bus listener target', () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    setGlobal('document', {});
    assert.equal(shell.emit('debugbar:content-updated', {}), true);

    setGlobal('EventTarget', undefined);
    const bare = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    delete globalThis.document;
    assert.equal(bare.emit('debugbar:content-updated', {}), false);
    const off = bare.on('debugbar:content-updated', () => {});
    assert.equal(typeof off, 'function');
    off();
  });

  it('reports undelivered events without CustomEvent support', () => {
    setGlobal('CustomEvent', undefined);
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    assert.equal(shell.emit('debugbar:content-updated', {}), false);
  });
});

describe('shell profile methods', () => {
  const track = (shell) => {
    const received = {};
    for (const name of Object.values(SHELL_EVENTS)) {
      shell.on(name, (detail) => {
        received[name] = detail;
      });
    }

    return received;
  };

  it('switchProfile updates the current profile and announces it', () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    const received = track(shell);

    assert.equal(shell.switchProfile('req-2'), 'req-2');
    assert.equal(shell.currentProfileId, 'req-2');
    assert.deepEqual(received[SHELL_EVENTS.profileSwitched], {
      profileId: 'req-2',
    });

    assert.equal(shell.switchProfile('req-3', { id: 'req-3' }), 'req-3');
    assert.deepEqual(received[SHELL_EVENTS.profileSwitched], {
      profileId: 'req-3',
      profile: { id: 'req-3' },
    });

    assert.equal(shell.switchProfile(null), 'req-3');
    assert.equal(shell.switchProfile(undefined), 'req-3');
  });

  it('noticeProfile tracks ids and announces them', () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    const received = track(shell);

    assert.equal(shell.noticeProfile(null), null);
    assert.equal(shell.noticeProfile(undefined), null);
    assert.equal(shell.noticeProfile('req-2'), 'req-2');
    assert.deepEqual(shell.noticedProfileIds, ['req-2']);
    assert.deepEqual(received[SHELL_EVENTS.profileNoticed], {
      profileId: 'req-2',
    });

    shell.noticeProfile('req-3', { transport: 'fetch' });
    assert.deepEqual(received[SHELL_EVENTS.profileNoticed], {
      profileId: 'req-3',
      meta: { transport: 'fetch' },
    });
  });

  it('refreshProfile announces the current profile', () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    const received = track(shell);

    assert.deepEqual(shell.refreshProfile(), { profileId: 'req-1' });
    assert.deepEqual(received[SHELL_EVENTS.profileRefreshed], {
      profileId: 'req-1',
    });

    assert.deepEqual(shell.refreshProfile({ manual: true }), {
      profileId: 'req-1',
      detail: { manual: true },
    });
  });

  it('announces inspector loads and content updates', () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    const received = track(shell);

    assert.deepEqual(shell.markInspectorLoaded('queries'), {
      key: 'queries',
      profileId: 'req-1',
    });
    assert.deepEqual(shell.markInspectorLoaded('queries', { cached: true }), {
      key: 'queries',
      profileId: 'req-1',
      detail: { cached: true },
    });
    assert.deepEqual(received[SHELL_EVENTS.inspectorLoaded], {
      key: 'queries',
      profileId: 'req-1',
      detail: { cached: true },
    });

    assert.deepEqual(shell.markContentUpdated(), { profileId: 'req-1' });
    assert.deepEqual(shell.markContentUpdated({ counts: 2 }), {
      counts: 2,
      profileId: 'req-1',
    });
    assert.deepEqual(shell.markContentUpdated('nope'), { profileId: 'req-1' });
    assert.deepEqual(shell.markContentUpdated({ profileId: 'req-9' }), {
      profileId: 'req-9',
    });
  });

  it('discoverProfile validates ids before announcing', () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    const received = track(shell);

    assert.equal(shell.discoverProfile(''), null);
    assert.equal(shell.discoverProfile(42), null);
    assert.deepEqual(shell.discoverProfile('req-2'), { profileId: 'req-2' });
    assert.deepEqual(shell.discoverProfile('req-3', { transport: 'xhr' }), {
      profileId: 'req-3',
      transport: 'xhr',
    });
    assert.deepEqual(shell.discoverProfile('req-4', 'nope'), {
      profileId: 'req-4',
    });
    assert.deepEqual(received[SHELL_EVENTS.profileDiscovered], {
      profileId: 'req-4',
    });
  });
});

describe('shell fetch', () => {
  it('fetchJson parses JSON responses through the injected client', async () => {
    const calls = [];
    const shell = createShell(
      { id: 'req-1' },
      {
        fetchClient: async (url, init) => {
          calls.push([url, init]);

          return { json: async () => ({ ok: true }) };
        },
      },
    );
    assert.deepEqual(await shell.fetchJson('/panels/req-1.json'), { ok: true });
    assert.deepEqual(calls, [['/panels/req-1.json', undefined]]);
  });

  it('fetchJson passes through non-JSON responses', async () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => 'raw' },
    );
    assert.equal(await shell.fetchJson('/panels/req-1'), 'raw');

    const nullish = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    assert.equal(await nullish.fetchJson('/panels/req-1'), null);
  });

  it('fetchJson throws without a fetch client', async () => {
    setGlobal('fetch', undefined);
    const shell = createShell({ id: 'req-1' });
    await assert.rejects(
      () => shell.fetchJson('/panels/req-1'),
      /no fetch client/,
    );
  });

  it('createPanel uses the current profile by default', () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    const seen = [];
    shell.registry.register('queries', (context) => {
      seen.push(context.profileId);

      return {};
    });
    const panel = shell.createPanel('queries');
    assert.equal(panel.profileId, 'req-1');

    const explicit = shell.createPanel('queries', { profileId: 'req-2' });
    assert.equal(explicit.profileId, 'req-2');
    assert.deepEqual(seen, ['req-1', 'req-2']);
  });
});
