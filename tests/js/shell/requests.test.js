import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { createShell } from '../../../webroot/js/state.js';
import {
  createRequestsPart,
  mergeRecentRequests,
  seedRecentRequests,
  isRequestUnread,
  requestStatusClass,
  requestStatusTone,
  relativeRequestTime,
  pickerNextIndex,
  pickerEdgeIndex,
  recentRequestsUrl,
  requestPanelUrl,
  requestToolbarUrl,
  requestPanelsUrl,
  requestOptionId,
  hostAjaxRequestId,
  MAX_RECENT_REQUESTS,
  RECENT_REQUESTS_URL,
} from '../../../webroot/js/shell/requests.js';

function fakeBrowser(overrides = {}) {
  return {
    afterPaint: (callback) => callback(),
    activeElement: () => null,
    ...overrides,
  };
}

function entry(id, overrides = {}) {
  return {
    id,
    url: `/page/${id}`,
    method: 'GET',
    status_code: 200,
    requested_at: '2026-10-11T12:00:00+00:00',
    ...overrides,
  };
}

describe('requests helpers', () => {
  it('merges recent lists newest-first with a cap of 20', () => {
    assert.equal(MAX_RECENT_REQUESTS, 20);
    const merged = mergeRecentRequests(
      [entry('a'), entry('b')],
      [entry('b'), entry('c')],
      3,
    );
    assert.deepEqual(
      merged.map((known) => known.id),
      ['b', 'c', 'a'],
    );
    assert.deepEqual(
      mergeRecentRequests([], [entry('x'), null, { id: '' }, {}], 20).map(
        (known) => known.id,
      ),
      ['x'],
    );

    const many = Array.from({ length: 25 }, (_, index) =>
      entry(`req-${index}`),
    );
    assert.equal(mergeRecentRequests([], many).length, 20);
    assert.equal(mergeRecentRequests([], many)[0].id, 'req-0');
  });

  it('seeds from the summary first', () => {
    const seeded = seedRecentRequests(entry('a'), [entry('b'), entry('a')]);
    assert.deepEqual(
      seeded.map((known) => known.id),
      ['a', 'b'],
    );
    assert.deepEqual(seedRecentRequests(null, []), []);
  });

  it('maps status codes to dk colors', () => {
    assert.match(requestStatusClass(500), /red/);
    assert.match(requestStatusClass(404), /amber/);
    assert.match(requestStatusClass(302), /sky/);
    assert.match(requestStatusClass(200), /emerald/);
    assert.match(requestStatusClass(0), /zinc/);
    assert.match(requestStatusClass(undefined), /zinc/);
    assert.match(requestStatusClass('201'), /emerald/);
    for (const status of [500, 404, 302, 200, 0]) {
      assert.match(requestStatusClass(status), /^dk:/);
    }
  });

  it('maps status codes to status tones shared with the boot picker', () => {
    assert.equal(requestStatusTone(500), 'bad');
    assert.equal(requestStatusTone(404), 'warn');
    assert.equal(requestStatusTone(200), 'good');
    assert.equal(requestStatusTone(302), 'good');
    assert.equal(requestStatusTone(0), 'good');
    assert.equal(requestStatusTone('503'), 'bad');
  });

  it('formats relative times', () => {
    const now = Date.parse('2026-10-11T12:01:30+00:00');
    assert.equal(
      relativeRequestTime(
        entry('a', { requested_at: '2026-10-11T12:01:28+00:00' }),
        now,
      ),
      'now',
    );
    assert.equal(
      relativeRequestTime(
        entry('a', { requested_at: '2026-10-11T12:01:00+00:00' }),
        now,
      ),
      '30s ago',
    );
    assert.equal(
      relativeRequestTime(
        entry('a', { requested_at: '2026-10-11T12:00:00+00:00' }),
        now,
      ),
      '1m ago',
    );
    assert.equal(
      relativeRequestTime(
        entry('a', {
          requested_at: '2026-10-11T10:00:00+00:00',
          recorded_time: '10:00',
        }),
        now,
      ),
      '10:00',
    );
    assert.equal(
      relativeRequestTime(entry('a', { requested_at: 'nope' }), now),
      '',
    );
    assert.equal(
      relativeRequestTime(
        { id: 'a', recorded_time: 'earlier', requested_at: 'nope' },
        now,
      ),
      'earlier',
    );
  });

  it('moves the picker index with wraparound', () => {
    assert.equal(pickerNextIndex(0, 1, 3), 1);
    assert.equal(pickerNextIndex(2, 1, 3), 0);
    assert.equal(pickerNextIndex(0, -1, 3), 2);
    assert.equal(pickerNextIndex(0, 1, 0), -1);
    assert.equal(pickerEdgeIndex('start', 3), 0);
    assert.equal(pickerEdgeIndex('end', 3), 2);
    assert.equal(pickerEdgeIndex('end', 0), -1);
  });

  it('builds the M1 endpoint urls', () => {
    assert.equal(RECENT_REQUESTS_URL, '/debug-bar/requests/recent.json');
    assert.equal(recentRequestsUrl(), '/debug-bar/requests/recent.json');
    assert.equal(
      recentRequestsUrl(10),
      '/debug-bar/requests/recent.json?limit=10',
    );
    assert.equal(
      requestPanelUrl('panel 1'),
      '/debug-bar/panels/view/panel%201.json',
    );
  });

  it('builds the toolbar page url for request switching', () => {
    assert.equal(requestToolbarUrl('req-1'), '/debug-bar/toolbar/req-1');
    assert.equal(requestToolbarUrl('req 1'), '/debug-bar/toolbar/req%201');
    assert.equal(requestToolbarUrl(''), null);
    assert.equal(requestToolbarUrl(null), null);
    assert.equal(requestToolbarUrl(undefined), null);
  });

  it('builds the panels payload url for on-the-fly switching', () => {
    assert.equal(
      requestPanelsUrl('req-1'),
      '/debug-bar/requests/panels/req-1.json',
    );
    assert.equal(
      requestPanelsUrl('req 1'),
      '/debug-bar/requests/panels/req%201.json',
    );
    assert.equal(requestPanelsUrl(''), null);
    assert.equal(requestPanelsUrl(null), null);
    assert.equal(requestPanelsUrl(undefined), null);
  });

  it('flags unread requests', () => {
    assert.equal(isRequestUnread(entry('b'), 'a', ['a']), true);
    assert.equal(isRequestUnread(entry('a'), 'a', []), false);
    assert.equal(isRequestUnread(entry('b'), 'a', ['b']), false);
    assert.equal(isRequestUnread(null, 'a', []), false);
  });
  it('reads request option ids', () => {
    assert.equal(
      requestOptionId({ dataset: { dkRequestId: 'req-1' } }),
      'req-1',
    );
    assert.equal(
      requestOptionId({ dataset: { dkProfileId: 'req-2' } }),
      'req-2',
    );
    assert.equal(
      requestOptionId({ dataset: {}, getAttribute: () => 'req-3' }),
      'req-3',
    );
    assert.equal(
      requestOptionId({ dataset: {}, getAttribute: () => '' }),
      null,
    );
    assert.equal(requestOptionId({}), null);
    assert.equal(requestOptionId(null), null);
  });
});

describe('requests part state', () => {
  it('tracks unread dots and the badge cap', () => {
    const part = createRequestsPart({
      browser: fakeBrowser(),
      summary: entry('a'),
      recentRequests: [entry('b'), entry('c')],
    });
    assert.equal(part.currentRequestId, 'a');
    assert.equal(part.unreadRequestCount, 2);
    assert.equal(part.requestBadgeCount, '2');
    assert.equal(part.hasOtherRequests, true);
    assert.equal(
      part.requestPickerButtonLabel,
      'Choose request, 2 unread requests',
    );

    part.viewedRequestIds = ['a', 'b', 'c'];
    assert.equal(part.requestPickerButtonLabel, 'Choose request');

    const many = Array.from({ length: 12 }, (_, index) =>
      entry(`req-${index}`),
    );
    const crowded = createRequestsPart({
      browser: fakeBrowser(),
      summary: entry('current'),
      recentRequests: many,
    });
    assert.equal(crowded.requestBadgeCount, '9+');
    assert.equal(
      crowded.requestPickerButtonLabel,
      'Choose request, 12 unread requests',
    );

    const solo = createRequestsPart({
      browser: fakeBrowser(),
      summary: entry('only'),
    });
    assert.equal(solo.requestPickerButtonLabel, 'No later requests yet');
    assert.equal(solo.requestIsUnread(entry('other')), true);
    assert.equal(solo.requestIsUnread(entry('only')), false);
  });

  it('remembers and receives requests with pruning', () => {
    const part = createRequestsPart({
      browser: fakeBrowser(),
      summary: entry('a'),
    });
    assert.equal(part.rememberRequest(entry('b')), true);
    assert.equal(part.rememberRequest({}), false);
    assert.deepEqual(
      part.recentRequests.map((known) => known.id),
      ['b', 'a'],
    );

    part.viewedRequestIds = ['a', 'b', 'gone'];
    part.pendingRequestIds = ['b'];
    assert.equal(part.receiveRequest(entry('b', { status_code: 500 })), true);
    assert.equal(part.recentRequests[0].status_code, 500);
    assert.deepEqual(part.pendingRequestIds, []);
    assert.deepEqual(part.viewedRequestIds, ['a', 'b']);
    assert.equal(part.receiveRequest(null), false);
  });
});

describe('requests part fetch', () => {
  it('loads the recent list from the M1 endpoint', async () => {
    const calls = [];
    const part = createRequestsPart({
      browser: fakeBrowser(),
      fetchClient: async (url) => {
        calls.push(url);

        return {
          json: async () => ({
            requests: [entry('c'), entry('b')],
            pagination: { count: 2 },
          }),
        };
      },
      summary: entry('a'),
    });

    const list = await part.loadRecentRequests({ limit: 10 });
    assert.deepEqual(calls, ['/debug-bar/requests/recent.json?limit=10']);
    assert.deepEqual(
      list.map((known) => known.id),
      ['c', 'b', 'a'],
    );
  });

  it('returns null without a client, on errors or bad payloads', async () => {
    const bare = createRequestsPart({
      browser: fakeBrowser(),
      summary: entry('a'),
    });
    assert.equal(await bare.loadRecentRequests(), null);

    const failing = createRequestsPart({
      browser: fakeBrowser(),
      fetchClient: async () => {
        throw new Error('offline');
      },
      summary: entry('a'),
    });
    assert.equal(await failing.loadRecentRequests(), null);

    const shapeless = createRequestsPart({
      browser: fakeBrowser(),
      fetchClient: async () => ({ json: async () => ({ nope: true }) }),
      summary: entry('a'),
    });
    assert.equal(await shapeless.loadRecentRequests(), null);
  });

  it('switches requests and announces the profile', async () => {
    const announced = [];
    const part = createRequestsPart({
      browser: fakeBrowser(),
      summary: entry('a'),
      recentRequests: [entry('b')],
    });
    part.switchProfile = (id, profile) => announced.push([id, profile?.id]);
    part.markContentUpdated = (detail) =>
      announced.push(['updated', detail.profileId]);

    const known = await part.switchRequest('b');
    assert.equal(known.id, 'b');
    assert.equal(part.currentRequestId, 'b');
    assert.deepEqual(part.viewedRequestIds, ['a', 'b']);
    assert.deepEqual(announced, [
      ['b', 'b'],
      ['updated', 'b'],
    ]);
    assert.equal(part.loadedInspector, null);
    assert.equal(part.selected, 'request');
  });

  it('fetches the panel payload when a panel id is given', async () => {
    const calls = [];
    const part = createRequestsPart({
      browser: fakeBrowser(),
      fetchClient: async (url) => {
        calls.push(url);

        return { json: async () => ({ title: 'Request' }) };
      },
      summary: entry('a'),
      recentRequests: [entry('b')],
    });
    const announced = [];
    part.switchProfile = (id, profile) => announced.push([id, profile]);

    await part.switchRequest('b', { panelId: 'panel-9' });
    assert.deepEqual(calls, ['/debug-bar/panels/view/panel-9.json']);
    assert.equal(announced[0][1].title, 'Request');
  });

  it('refuses unknown or invalid ids', async () => {
    const part = createRequestsPart({
      browser: fakeBrowser(),
      fetchClient: async () => ({ json: async () => ({ requests: [] }) }),
      summary: entry('a'),
    });
    assert.equal(await part.switchRequest('missing'), null);
    assert.equal(await part.switchRequest(''), null);
    assert.equal(part.currentRequestId, 'a');
  });

  it('keeps the inspector list when the target entry has none', async () => {
    const inspectors = [
      { key: 'request', label: 'Request' },
      { key: 'log', label: 'Log' },
    ];
    const recentB = entry('b');
    const part = createRequestsPart({
      browser: fakeBrowser(),
      summary: entry('a', { inspectors }),
      recentRequests: [recentB],
    });
    part.switchProfile = () => {};
    part.markContentUpdated = () => {};

    const known = await part.switchRequest('b');
    assert.equal(known.id, 'b');
    assert.deepEqual(part.summary.inspectors, inspectors);
    // The stored recent entry stays pristine (summary is a copy).
    assert.equal(recentB.inspectors, undefined);
  });

  it('notices background profiles and switches foreground ones', async () => {
    const noticed = [];
    const part = createRequestsPart({
      browser: fakeBrowser(),
      fetchClient: async () => ({
        json: async () => ({ requests: [entry('c')], pagination: {} }),
      }),
      summary: entry('a'),
    });
    part.noticeProfile = (id, meta) => noticed.push([id, meta?.id]);

    assert.equal(await part.noticeRequest('a'), null);
    assert.equal(await part.noticeRequest(''), null);
    const found = await part.noticeRequest('c');
    assert.equal(found.id, 'c');
    assert.deepEqual(noticed, [['c', 'c']]);
    assert.equal(await part.noticeRequest('c'), null);

    const foreground = createRequestsPart({
      browser: fakeBrowser(),
      fetchClient: async () => ({
        json: async () => ({ requests: [entry('e')], pagination: {} }),
      }),
      summary: entry('a'),
      recentRequests: [entry('d')],
    });
    foreground.switchProfile = () => {};
    foreground.markContentUpdated = () => {};
    assert.equal(
      (await foreground.noticeRequest('e', { foreground: true })).id,
      'e',
    );
    assert.equal(foreground.currentRequestId, 'e');
  });

  it('selects requests from the picker', async () => {
    const part = createRequestsPart({
      browser: fakeBrowser(),
      summary: entry('a'),
      recentRequests: [entry('b')],
    });
    part.switchProfile = () => {};
    part.markContentUpdated = () => {};

    assert.equal(await part.selectRequest(''), null);
    assert.deepEqual(await part.selectRequest('b'), entry('b'));
    assert.equal(part.requestSelectionPending, null);

    part.requestPickerScope = 'toolbar';
    const opened = [];
    part.openInspector = (key) => opened.push(key);
    assert.deepEqual(await part.selectRequest('b'), { same: true, id: 'b' });
    assert.deepEqual(opened, ['request']);

    part.requestSelectionPending = 'b';
    assert.deepEqual(await part.selectRequest('b'), { same: true, id: 'b' });
  });

  it('opens worker rows on the queue inspector', async () => {
    const part = createRequestsPart({
      browser: fakeBrowser(),
      summary: entry('a'),
      recentRequests: [
        entry('w', { url: 'DebugBarProbeJob', method: 'JOB' }),
        entry('c', { url: '/cli/other', method: 'CLI' }),
      ],
    });
    part.switchProfile = () => {};
    part.markContentUpdated = () => {};

    await part.selectRequest('w');
    assert.equal(part.selected, 'queue');

    await part.selectRequest('c');
    assert.equal(part.selected, 'request');
  });
});

describe('requests picker keyboard', () => {
  it('opens and closes per scope and inspector state', () => {
    const part = createRequestsPart({
      browser: fakeBrowser(),
      summary: entry('a'),
      recentRequests: [entry('b')],
    });
    assert.equal(part.openRequestPicker('nowhere'), null);
    assert.equal(part.openRequestPicker('toolbar'), 'toolbar');
    assert.equal(part.toggleRequestPicker('toolbar'), null);
    assert.equal(part.requestPickerScope, null);
    assert.equal(part.closeRequestPicker(), false);

    part.inspectorOpen = true;
    assert.equal(part.openRequestPicker('toolbar'), null);
    assert.equal(part.openRequestPicker('header'), 'header');
  });

  it('moves and jumps across options', () => {
    const part = createRequestsPart({
      browser: fakeBrowser(),
      summary: entry('a'),
      recentRequests: [entry('b')],
    });
    const focused = [];
    const options = [0, 1, 2].map((index) => ({
      focus: () => focused.push(index),
    }));

    assert.equal(part.moveRequestPicker(1, options), 1);
    assert.equal(part.moveRequestPicker(1, []), -1);
    assert.equal(part.focusRequestPickerEdge('end', options), 2);
    assert.equal(part.focusRequestPickerEdge('start', options), 0);
    assert.equal(part.focusRequestPickerEdge('end', []), -1);
    assert.deepEqual(focused, [1, 2, 0]);
  });
});

describe('requests part guards', () => {
  it('falls back through current request shapes', () => {
    const part = createRequestsPart({
      browser: fakeBrowser(),
      summary: entry('a'),
    });
    assert.deepEqual(part.currentRequest, entry('a'));
    part.currentRequestId = 'missing';
    assert.deepEqual(part.currentRequest, entry('a'));
    part.summary = null;
    part.recentRequests = [];
    assert.deepEqual(part.currentRequest, entry('a'));
  });

  it('switches even when the panel fetch fails', async () => {
    const part = createRequestsPart({
      browser: fakeBrowser(),
      fetchClient: async () => {
        throw new Error('offline');
      },
      summary: entry('a'),
      recentRequests: [entry('b')],
    });
    const announced = [];
    part.switchProfile = (id, profile) => announced.push([id, profile?.id]);

    assert.equal(
      (await part.switchRequest('b', { panelId: 'panel-1' })).id,
      'b',
    );
    assert.deepEqual(announced, [['b', 'b']]);
  });

  it('clears pending notices that never resolve', async () => {
    const part = createRequestsPart({
      browser: fakeBrowser(),
      fetchClient: async () => ({ json: async () => ({ requests: [] }) }),
      summary: entry('a'),
    });
    assert.equal(await part.noticeRequest('ghost'), null);
    assert.deepEqual(part.pendingRequestIds, []);
  });

  it('survives failing notice and select round-trips', async () => {
    const part = createRequestsPart({
      browser: fakeBrowser(),
      summary: entry('a'),
    });
    part.loadRecentRequests = async () => {
      throw new Error('offline');
    };
    assert.equal(await part.noticeRequest('ghost'), null);
    assert.deepEqual(part.pendingRequestIds, []);

    part.switchRequest = async () => {
      throw new Error('offline');
    };
    assert.equal(await part.selectRequest('ghost'), null);
    assert.equal(part.requestSelectionPending, null);
  });

  it('closes the picker without paint scheduling', () => {
    const part = createRequestsPart({
      browser: { activeElement: () => null },
      summary: entry('a'),
      recentRequests: [entry('b')],
    });
    const focused = [];
    assert.equal(part.openRequestPicker('toolbar'), 'toolbar');
    part.requestPickerReturnFocus = { focus: () => focused.push('back') };
    assert.equal(part.closeRequestPicker(), true);
    assert.deepEqual(focused, ['back']);
    assert.equal(part.toggleRequestPicker('corner'), 'corner');
  });
});

describe('requests shell composition', () => {
  it('registers the requests part in createShell', () => {
    const shell = createShell(
      { id: 'a', inspectors: [{ key: 'request', label: 'Request' }] },
      { fetchClient: async () => null },
    );
    assert.equal(shell.currentRequestId, 'a');
    assert.deepEqual(shell.viewedRequestIds, ['a']);
    assert.equal(typeof shell.switchRequest, 'function');
    assert.equal(typeof shell.loadRecentRequests, 'function');
  });
});

describe('host ajax bridge', () => {
  it('parses the injector message into a request id', () => {
    assert.equal(
      hostAjaxRequestId(
        'ajax-completed$$' + JSON.stringify({ requestId: 'abc123' }),
      ),
      'abc123',
    );
    assert.equal(hostAjaxRequestId('expand'), null);
    assert.equal(hostAjaxRequestId('ajax-completed$$nope{'), null);
    assert.equal(
      hostAjaxRequestId(
        'ajax-completed$$' + JSON.stringify({ requestId: null }),
      ),
      null,
    );
    assert.equal(
      hostAjaxRequestId('ajax-completed$$' + JSON.stringify({})),
      null,
    );
    assert.equal(hostAjaxRequestId(null), null);
  });
});
