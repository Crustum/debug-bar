import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { createShell } from '../../../webroot/js/state.js';
import {
  createCopyPart,
  COPY_FEEDBACK_CLEAR_DELAY,
  COPY_FEEDBACK_OK,
  COPY_FEEDBACK_FAILED,
} from '../../../webroot/js/shell/copy.js';

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

function runTimers(browser) {
  for (const { callback } of [...browser.timers.values()]) callback();
}

describe('copy feedback', () => {
  it('exposes the feedback constants', () => {
    assert.equal(COPY_FEEDBACK_CLEAR_DELAY, 3000);
    assert.equal(COPY_FEEDBACK_OK, 'Copied');
    assert.equal(COPY_FEEDBACK_FAILED, 'Copy failed');
  });

  it('confirms a copy and clears after 3s', async () => {
    const browser = fakeBrowser({ writeClipboard: async () => true });
    const part = createCopyPart({ browser });
    await part.copyWithFeedback('hello');
    assert.equal(part.copyStatus, 'Copied');
    assert.equal(browser.timers.size, 1);
    const [{ delay }] = [...browser.timers.values()];
    assert.equal(delay, 3000);

    runTimers(browser);
    assert.equal(part.copyStatus, '');
  });

  it('reports a failed copy', async () => {
    const part = createCopyPart({
      browser: fakeBrowser({ writeClipboard: async () => false }),
    });
    await part.copyWithFeedback('hello');
    assert.equal(part.copyStatus, 'Copy failed');
  });

  it('falls back to the runtime clipboard helper', async () => {
    const part = createCopyPart({ browser: fakeBrowser() });
    await part.copyWithFeedback('hello');
    assert.equal(part.copyStatus, 'Copy failed');
  });

  it('drops stale results with the version guard', async () => {
    let release = null;
    const browser = fakeBrowser({
      writeClipboard: () =>
        new Promise((resolve) => {
          release = resolve;
        }),
    });
    const part = createCopyPart({ browser });
    const pending = part.copyWithFeedback('slow');
    part.resetCopyFeedback();
    release(true);
    assert.equal(await pending, null);
    assert.equal(part.copyStatus, '');
  });

  it('resets only on value changes', () => {
    const browser = fakeBrowser({ writeClipboard: async () => true });
    const part = createCopyPart({ browser });
    part.copyStatus = 'Copied';
    assert.equal(part.syncCopyValue(undefined), false);
    assert.equal(part.copyStatus, 'Copied');
    assert.equal(part.syncCopyValue('next'), true);
    assert.equal(part.copyStatus, '');
    assert.equal(part.syncCopyValue('next'), false);
  });

  it('cancels pending clears on reset and destroy', async () => {
    const browser = fakeBrowser({ writeClipboard: async () => true });
    const part = createCopyPart({ browser });
    await part.copyWithFeedback('one');
    assert.equal(browser.timers.size, 1);
    part.resetCopyFeedback();
    assert.equal(browser.timers.size, 0);
    assert.equal(part.copyStatus, '');

    await part.copyWithFeedback('two');
    assert.equal(browser.timers.size, 1);
    part.destroyCopyFeedback();
    assert.equal(browser.timers.size, 0);
    assert.equal(part.copyStatus, '');
  });

  it('copies without a scheduler', async () => {
    const part = createCopyPart({
      browser: { writeClipboard: async () => true },
    });
    assert.equal(await part.copyWithFeedback('hello'), true);
    assert.equal(part.copyStatus, 'Copied');
  });
});

describe('copy shell composition', () => {
  it('registers the copy part in createShell', async () => {
    const shell = createShell(
      { id: 'req-1' },
      { fetchClient: async () => null },
    );
    assert.equal(shell.copyStatus, '');
    assert.equal(typeof shell.copyWithFeedback, 'function');
    assert.equal(typeof shell.syncCopyValue, 'function');

    shell.browser = fakeBrowser({ writeClipboard: async () => true });
    await shell.copyWithFeedback('hello');
    assert.equal(shell.copyStatus, 'Copied');
  });
});
