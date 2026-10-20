import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { createShell } from '../../../webroot/js/state.js';
import {
  createDiscoveryPart,
  installRequestDiscovery,
  isProfileId,
  discoveryEligible,
  responseHeaderId,
  announceDiscoveredProfile,
  DK_PROFILE_HEADER,
  DK_OWN_PATH_PREFIX,
} from '../../../webroot/js/shell/discovery.js';

const LOCATION = { href: 'https://app.test/page', origin: 'https://app.test' };

function fakeRuntime(overrides = {}) {
  return {
    location: { ...LOCATION },
    setTimeout: (callback) => callback(),
    ...overrides,
  };
}

function jsonResponse(url, profileId) {
  return {
    url,
    headers: { get: (name) => (name === DK_PROFILE_HEADER ? profileId : null) },
  };
}

describe('discovery helpers', () => {
  it('exposes the header and own-path constants', () => {
    assert.equal(DK_PROFILE_HEADER, 'X-DEBUGBAR-ID');
    assert.equal(DK_OWN_PATH_PREFIX, '/debug-bar/');
  });

  it('validates profile ids', () => {
    assert.equal(isProfileId('req-1'), true);
    assert.equal(isProfileId('  req-1  '), true);
    assert.equal(isProfileId(''), false);
    assert.equal(isProfileId('   '), false);
    assert.equal(isProfileId(null), false);
    assert.equal(isProfileId(42), false);
    assert.equal(isProfileId('x'.repeat(257)), false);
  });

  it('accepts same-origin app requests only', () => {
    assert.equal(discoveryEligible('/api/data', LOCATION), true);
    assert.equal(
      discoveryEligible('https://app.test/api/data', LOCATION),
      true,
    );
    assert.equal(
      discoveryEligible(new URL('https://app.test/api'), LOCATION),
      true,
    );
    assert.equal(discoveryEligible({ url: '/api/data' }, LOCATION), true);
    assert.equal(discoveryEligible('https://cdn.test/lib.js', LOCATION), false);
    assert.equal(
      discoveryEligible('/debug-bar/requests/recent.json', LOCATION),
      false,
    );
    assert.equal(
      discoveryEligible(
        'https://app.test/debug-bar/toolbar/req-1.json',
        LOCATION,
      ),
      false,
    );
    assert.equal(discoveryEligible('/api/data', null), false);
    assert.equal(
      discoveryEligible('https://app.test:99999/api', LOCATION),
      false,
    );
    assert.equal(discoveryEligible(null, LOCATION), false);
  });

  it('reads the id header in every shape', () => {
    assert.equal(responseHeaderId({ get: () => 'req-1' }), 'req-1');
    assert.equal(
      responseHeaderId({
        get: () => {
          throw new Error('denied');
        },
      }),
      null,
    );
    assert.equal(
      responseHeaderId([
        ['Content-Type', 'text/html'],
        ['x-debugbar-id', 'req-2'],
      ]),
      'req-2',
    );
    assert.equal(responseHeaderId({ 'X-DebugBar-Id': 'req-3' }), 'req-3');
    assert.equal(responseHeaderId({ other: '1' }), null);
    assert.equal(responseHeaderId(null), null);
    assert.equal(responseHeaderId(42), null);
  });

  it('announces background and foreground discoveries', () => {
    const seen = [];
    const shell = {
      noticeRequest: (id, detail) => {
        seen.push(['notice', id, detail]);

        return { id };
      },
      switchRequest: (id, detail) => {
        seen.push(['switch', id, detail]);

        return { id };
      },
    };
    assert.deepEqual(
      announceDiscoveredProfile(shell, 'req-1', { transport: 'fetch' }),
      {
        id: 'req-1',
      },
    );
    assert.deepEqual(seen[0], ['notice', 'req-1', { transport: 'fetch' }]);
    announceDiscoveredProfile(shell, 'req-2', { foreground: true });
    assert.equal(seen[1][0], 'switch');

    assert.equal(announceDiscoveredProfile(shell, '', {}), null);
    assert.equal(announceDiscoveredProfile(null, 'req-1'), null);
    assert.equal(announceDiscoveredProfile({}, 'req-1'), null);
  });

  it('falls back to discoverProfile and swallows failures', async () => {
    const announced = [];
    const shell = {
      discoverProfile: (id, detail) => {
        announced.push([id, detail]);
      },
    };
    assert.equal(
      announceDiscoveredProfile(shell, 'req-1', { transport: 'xhr' }),
      true,
    );
    assert.deepEqual(announced, [['req-1', { transport: 'xhr' }]]);

    const rejecting = {
      noticeRequest: async () => {
        throw new Error('gone');
      },
    };
    announceDiscoveredProfile(rejecting, 'req-1');
    await new Promise((resolve) => setTimeout(resolve, 0));

    const throwing = {
      noticeRequest: () => {
        throw new Error('gone');
      },
    };
    assert.equal(announceDiscoveredProfile(throwing, 'req-1'), null);
    assert.equal(announceDiscoveredProfile('nope', 'req-1'), null);
  });
});

describe('fetch discovery', () => {
  it('notices same-origin responses carrying the header', async () => {
    const seen = [];
    const runtime = fakeRuntime({
      fetch: async () => jsonResponse('https://app.test/api/data', 'req-7'),
    });
    assert.equal(
      installRequestDiscovery(runtime, {
        noticeRequest: (id, detail) => seen.push([id, detail]),
      }),
      true,
    );

    const response = await runtime.fetch('/api/data');
    assert.equal(response.url, 'https://app.test/api/data');
    assert.deepEqual(seen, [
      ['req-7', { transport: 'fetch', foreground: false }],
    ]);
  });

  it('ignores own-path, foreign and headerless responses', async () => {
    const seen = [];
    const responses = {
      '/debug-bar/requests/recent.json': jsonResponse(
        'https://app.test/debug-bar/requests/recent.json',
        'req-own',
      ),
      'https://cdn.test/lib.js': jsonResponse(
        'https://cdn.test/lib.js',
        'req-foreign',
      ),
      '/api/blank': { url: 'https://app.test/api/blank', headers: {} },
      '/api/redirect': jsonResponse(
        'https://cdn.test/other.js',
        'req-redirect',
      ),
    };
    const runtime = fakeRuntime({
      fetch: async (input) =>
        responses[String(input)] ?? jsonResponse(String(input), null),
    });
    installRequestDiscovery(runtime, { noticeRequest: (id) => seen.push(id) });

    await runtime.fetch('/debug-bar/requests/recent.json');
    await runtime.fetch('https://cdn.test/lib.js');
    await runtime.fetch('/api/blank');
    await runtime.fetch('/api/redirect');
    assert.deepEqual(seen, []);
  });

  it('installs once and tolerates runtimes without fetch', () => {
    const runtime = fakeRuntime({ fetch: async () => ({ headers: {} }) });
    assert.equal(installRequestDiscovery(runtime, {}), true);
    const patched = runtime.fetch;
    assert.equal(installRequestDiscovery(runtime, {}), true);
    assert.equal(runtime.fetch, patched);

    assert.equal(installRequestDiscovery(fakeRuntime(), {}), true);
    assert.equal(installRequestDiscovery(null, {}), false);
    assert.equal(installRequestDiscovery(undefined, {}), false);
  });
});

describe('xhr discovery', () => {
  function xhrRuntime(seen) {
    const proto = {
      open() {},
      send() {
        return 'sent';
      },
    };
    const runtime = fakeRuntime({
      XMLHttpRequest: { prototype: proto },
    });
    installRequestDiscovery(runtime, {
      noticeRequest: (id, detail) => seen.push([id, detail]),
    });

    return { proto };
  }

  it('notices XHR responses carrying the header', () => {
    const seen = [];
    const { proto } = xhrRuntime(seen);
    const request = Object.create(proto);
    let loadend = null;
    request.addEventListener = (type, callback) => {
      loadend = [type, callback];
    };
    request.getResponseHeader = (name) =>
      name === DK_PROFILE_HEADER ? 'req-xhr' : null;
    request.responseURL = 'https://app.test/api/items';

    request.open('GET', 'https://app.test/api/items');
    assert.equal(request.send(), 'sent');
    assert.equal(loadend[0], 'loadend');
    loadend[1]();
    assert.deepEqual(seen, [
      ['req-xhr', { transport: 'xhr', foreground: false }],
    ]);
  });

  it('ignores own-path XHR and missing headers', () => {
    const seen = [];
    const { proto } = xhrRuntime(seen);

    const own = Object.create(proto);
    own.addEventListener = (type, callback) => callback();
    own.getResponseHeader = () => 'req-own';
    own.responseURL = 'https://app.test/debug-bar/requests/recent.json';
    own.open('GET', '/debug-bar/requests/recent.json');
    own.send();

    const blank = Object.create(proto);
    blank.addEventListener = (type, callback) => callback();
    blank.getResponseHeader = () => null;
    blank.responseURL = 'https://app.test/api/blank';
    blank.open('GET', '/api/blank');
    blank.send();

    assert.deepEqual(seen, []);
  });
});

describe('discovery shell composition', () => {
  it('registers the discovery part in createShell', () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    assert.equal(shell.requestDiscoveryInstalled, false);
    assert.equal(typeof shell.startRequestDiscovery, 'function');
  });

  it('starts discovery against a runtime and feeds the shell', async () => {
    const shell = createShell(
      { id: 'req-1' },
      {
        fetchClient: async () => ({ json: async () => ({ requests: [] }) }),
      },
    );
    const runtime = fakeRuntime({
      fetch: async () => jsonResponse('https://app.test/api/data', 'req-bg'),
    });
    assert.equal(shell.startRequestDiscovery(runtime), true);
    assert.equal(shell.requestDiscoveryInstalled, true);

    await runtime.fetch('/api/data');
    await new Promise((resolve) => setTimeout(resolve, 0));
    assert.deepEqual(shell.pendingRequestIds, []);
  });

  it('refuses to start without a runtime', () => {
    const part = createDiscoveryPart();
    assert.equal(part.requestDiscoveryInstalled, false);
    assert.equal(part.startRequestDiscovery(null), false);
  });
});
