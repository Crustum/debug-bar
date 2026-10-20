import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { createShell } from '../../../webroot/js/state.js';
import {
  createActivityPart,
  hasPendingActivity,
  activityChanged,
  findActivityEntry,
  ACTIVITY_POLL_LIMIT,
  ACTIVITY_POLL_INTERVAL,
  ACTIVITY_REFRESH_ERROR,
  ACTIVITY_RELOAD_INSPECTORS,
} from '../../../webroot/js/shell/activity-refresh.js';

function fakeBrowser(overrides = {}) {
  const timers = new Map();
  let nextId = 1;
  const browser = {
    schedule: (callback, delay) => {
      const id = nextId++;
      timers.set(id, { callback, delay });

      return id;
    },
    cancelSchedule: (id) => {
      timers.delete(id);
    },
    timers,
    ...overrides,
  };

  return browser;
}

function pendingEntry(id, overrides = {}) {
  return {
    id,
    url: `/page/${id}`,
    background_pending: true,
    ...overrides,
  };
}

function shellWith(summary, options = {}) {
  return createShell(summary, {
    runtime: fakeBrowser(),
    fetchClient: async () => ({ json: async () => ({ requests: [] }) }),
    ...options,
  });
}

describe('activity helpers', () => {
  it('exposes the poll budget and reload inspectors', () => {
    assert.equal(ACTIVITY_POLL_LIMIT, 30);
    assert.equal(ACTIVITY_POLL_INTERVAL, 1000);
    assert.match(ACTIVITY_REFRESH_ERROR, /could not be refreshed/);
    assert.deepEqual(ACTIVITY_RELOAD_INSPECTORS, [
      'timeline',
      'queue',
      'mail',
      'notifications',
    ]);
  });

  it('detects pending activity', () => {
    assert.equal(
      hasPendingActivity({ id: 'a', completion_state: 'terminating' }),
      true,
    );
    assert.equal(
      hasPendingActivity({ id: 'a', background_pending: true }),
      true,
    );
    assert.equal(
      hasPendingActivity({ id: 'a', background_error: 'boom' }),
      true,
    );
    assert.equal(
      hasPendingActivity({ id: 'a' }, { profileId: 'a', message: 'stale' }),
      true,
    );
    assert.equal(
      hasPendingActivity({ id: 'a' }, { profileId: 'b', message: 'other' }),
      false,
    );
    assert.equal(hasPendingActivity({ id: 'a' }), false);
    assert.equal(hasPendingActivity(null), false);
    assert.equal(hasPendingActivity(), false);
  });

  it('detects background changes only', () => {
    const before = pendingEntry('a', { background_activity_count: 1 });
    assert.equal(activityChanged(before, { ...before }), false);
    assert.equal(
      activityChanged(before, { ...before, background_activity_count: 2 }),
      true,
    );
    assert.equal(
      activityChanged(before, { ...before, related_profile_ids: ['x'] }),
      true,
    );
    assert.equal(activityChanged(before, { ...before, url: '/other' }), false);
    assert.equal(activityChanged(null, null), false);
    assert.equal(activityChanged(null, pendingEntry('a')), true);
  });

  it('finds the current entry in recent payloads', () => {
    const payload = { requests: [pendingEntry('a'), pendingEntry('b')] };
    assert.equal(findActivityEntry(payload, 'b').id, 'b');
    assert.equal(findActivityEntry(payload, 'missing'), null);
    assert.equal(findActivityEntry({ nope: true }, 'a'), null);
    assert.equal(findActivityEntry(payload, ''), null);
    assert.equal(findActivityEntry(null, 'a'), null);
  });
});

describe('activity scheduling', () => {
  it('reads the banner error for the current profile', () => {
    const shell = shellWith({ id: 'a', background_error: 'late failure' });
    assert.equal(shell.backgroundActivityError, 'late failure');
    shell.activityRefreshError = {
      profileId: 'a',
      message: ACTIVITY_REFRESH_ERROR,
    };
    assert.equal(shell.backgroundActivityError, ACTIVITY_REFRESH_ERROR);
    shell.activityRefreshError = { profileId: 'b', message: 'other' };
    assert.equal(shell.backgroundActivityError, 'late failure');
    shell.summary = { id: 'a' };
    assert.equal(shell.backgroundActivityError, null);
  });

  it('schedules at most 30 one-second polls while open and pending', () => {
    const shell = shellWith(pendingEntry('a'));
    assert.equal(shell.scheduleActivityRefresh(), false);

    shell.inspectorOpen = true;
    assert.equal(shell.scheduleActivityRefresh(), true);
    assert.equal(shell.activityPollTimer !== null, true);
    const [{ delay }] = [...shell.browser.timers.values()];
    assert.equal(delay, 1000);
    assert.equal(shell.scheduleActivityRefresh(), false);

    shell.cancelActivityRefresh();
    assert.equal(shell.activityPollTimer, null);

    const done = shellWith({ id: 'a' });
    done.inspectorOpen = true;
    assert.equal(done.scheduleActivityRefresh(), false);

    const busy = shellWith(pendingEntry('a'));
    busy.inspectorOpen = true;
    busy.activityRefreshPending = true;
    assert.equal(busy.scheduleActivityRefresh(), false);

    const capped = shellWith(pendingEntry('a'));
    capped.inspectorOpen = true;
    capped.activityPollAttempts = 30;
    assert.equal(capped.scheduleActivityRefresh(), false);
    capped.cancelActivityRefresh(true);
    assert.equal(capped.activityPollAttempts, 0);
  });
});

describe('activity refresh round-trips', () => {
  it('merges the fresh entry into the summary', async () => {
    const fresh = pendingEntry('a', { background_pending: false });
    const shell = shellWith(pendingEntry('a'), {
      fetchClient: async () => ({ json: async () => ({ requests: [fresh] }) }),
    });
    shell.inspectorOpen = true;

    const result = await shell.refreshBackgroundActivity();
    assert.equal(shell.summary.background_pending, false);
    assert.equal(shell.activityRefreshPending, false);
    assert.equal(shell.activityPollAttempts, 1);
    assert.deepEqual(result, {
      changed: true,
      relatedChanged: false,
      reloaded: false,
    });
    assert.equal(shell.activityPollTimer, null);
  });

  it('reloads background inspectors when their data changed', async () => {
    const fresh = pendingEntry('a', { background_activity_count: 4 });
    const shell = shellWith(pendingEntry('a'), {
      fetchClient: async () => ({ json: async () => ({ requests: [fresh] }) }),
    });
    shell.inspectorOpen = true;
    shell.selected = 'timeline';
    shell.loadedInspector = 'timeline';
    const requested = [];
    shell.requestInspector = async (key, options) => {
      requested.push([key, options]);

      return { key };
    };

    const result = await shell.refreshBackgroundActivity();
    assert.deepEqual(requested, [['timeline', { force: true }]]);
    assert.equal(result.reloaded, true);
  });

  it('skips the reload when nothing is watching', async () => {
    const fresh = pendingEntry('a', { background_activity_count: 4 });
    const shell = shellWith(pendingEntry('a'), {
      fetchClient: async () => ({ json: async () => ({ requests: [fresh] }) }),
    });
    shell.inspectorOpen = true;
    shell.selected = 'request';
    shell.loadedInspector = 'request';
    let requested = 0;
    shell.requestInspector = async () => {
      requested += 1;

      return {};
    };

    const result = await shell.refreshBackgroundActivity();
    assert.equal(requested, 0);
    assert.equal(result.reloaded, false);
  });

  it('merges related profiles into the recent list', async () => {
    const shell = shellWith(pendingEntry('a'));
    shell.inspectorOpen = true;
    const result = shell.receiveActivityRefresh(
      { ...pendingEntry('a'), background_pending: false },
      [{ id: 'worker-1' }],
    );
    assert.equal(result.relatedChanged, true);
    assert.ok(shell.recentRequests.some((entry) => entry.id === 'worker-1'));
  });

  it('keeps polling when the entry is missing', async () => {
    const shell = shellWith(pendingEntry('a'));
    shell.inspectorOpen = true;
    assert.equal(await shell.refreshBackgroundActivity(), null);
    assert.equal(shell.activityRefreshPending, false);
    assert.equal(shell.activityPollTimer !== null, true);
  });

  it('banners errors and retries', async () => {
    const shell = shellWith(pendingEntry('a'), {
      fetchClient: async () => {
        throw new Error('offline');
      },
    });
    shell.inspectorOpen = true;
    assert.equal(await shell.refreshBackgroundActivity(), null);
    assert.equal(shell.backgroundActivityError, ACTIVITY_REFRESH_ERROR);
    assert.equal(shell.activityPollTimer !== null, true);
  });

  it('ignores stale responses after a profile switch', async () => {
    let release = null;
    const gate = new Promise((resolve) => {
      release = resolve;
    });
    const shell = shellWith(pendingEntry('a'), {
      fetchClient: async () => {
        await gate;

        return { json: async () => ({ requests: [pendingEntry('a')] }) };
      },
    });
    shell.inspectorOpen = true;
    const pending = shell.refreshBackgroundActivity();
    shell.switchProfile('b');
    shell.summary = { id: 'b' };
    release();
    assert.equal(await pending, null);
    assert.equal(shell.activityRefreshPending, false);
  });

  it('refuses invalid refresh attempts', async () => {
    const shell = shellWith(pendingEntry('a'));
    assert.equal(await shell.refreshBackgroundActivity(), null);

    shell.inspectorOpen = true;
    shell.activityRefreshPending = true;
    assert.equal(await shell.refreshBackgroundActivity(), null);

    const capped = shellWith(pendingEntry('a'));
    capped.inspectorOpen = true;
    capped.activityPollAttempts = 30;
    assert.equal(await capped.refreshBackgroundActivity(), null);

    const bare = createActivityPart({ browser: fakeBrowser() });
    bare.inspectorOpen = true;
    bare.summary = pendingEntry('a');
    assert.equal(await bare.refreshBackgroundActivity(), null);

    assert.equal(shell.receiveActivityRefresh(null), null);
    assert.equal(shell.receiveActivityRefresh({ id: 'other' }), null);
  });
});

describe('activity shell composition', () => {
  it('registers the activity part in createShell', () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    assert.equal(shell.activityPollAttempts, 0);
    assert.equal(shell.activityPollTimer, null);
    assert.equal(typeof shell.refreshBackgroundActivity, 'function');
    assert.equal(typeof shell.receiveActivityRefresh, 'function');
  });
});
