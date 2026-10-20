import { describe, it, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import DebugBarDefault, {
  DebugBar,
  installDebugBar,
  createShell,
  createPanelRegistry,
  requestToolbarUrl,
  requestPanelsUrl,
  defaultRuntime,
  formatDuration,
  SHELL_EVENTS,
  SHELL_ROOT_ID,
} from '../../webroot/js/debugbar.js';

afterEach(() => {
  delete globalThis.window;
});

describe('debugbar entry', () => {
  it('exposes the core factories and helpers', () => {
    for (const factory of [
      DebugBar.createShell,
      DebugBar.createPanelRegistry,
      DebugBar.defaultRuntime,
    ]) {
      assert.equal(typeof factory, 'function');
    }
    assert.equal(DebugBar.createShell, createShell);
    assert.equal(DebugBar.createPanelRegistry, createPanelRegistry);
    assert.equal(DebugBar.defaultRuntime, defaultRuntime);
    assert.equal(DebugBar.formatDuration, formatDuration);
    assert.equal(DebugBar.requestToolbarUrl, requestToolbarUrl);
    assert.equal(
      DebugBar.requestToolbarUrl('req-1'),
      '/debug-bar/toolbar/req-1',
    );
    assert.equal(DebugBar.requestPanelsUrl, requestPanelsUrl);
    assert.equal(
      DebugBar.requestPanelsUrl('req-1'),
      '/debug-bar/requests/panels/req-1.json',
    );
    assert.equal(DebugBar.events, SHELL_EVENTS);
    assert.equal(DebugBar.rootId, SHELL_ROOT_ID);
    assert.equal(DebugBarDefault, DebugBar);
  });

  it('installs onto an explicit host', () => {
    const host = {};
    assert.equal(installDebugBar(host), DebugBar);
    assert.equal(host.DebugBar, DebugBar);
  });

  it('returns null without a host or window', () => {
    assert.equal(installDebugBar(null), null);
    assert.equal(installDebugBar(undefined), null);
  });

  it('installs onto the window when no host is given', () => {
    globalThis.window = {};
    assert.equal(installDebugBar(), DebugBar);
    assert.equal(globalThis.window.DebugBar, DebugBar);
  });
});
