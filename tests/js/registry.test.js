import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
  createGatedPanel,
  createPanelRegistry,
  createPassthroughPanel,
} from '../../webroot/js/registry.js';

describe('createPanelRegistry', () => {
  it('starts empty and registers factories with chaining', () => {
    const registry = createPanelRegistry();
    assert.equal(registry.size, 0);
    assert.deepEqual(registry.types(), []);

    const factory = () => ({});
    assert.equal(registry.register('queries', factory), registry);
    assert.equal(registry.size, 1);
    assert.equal(registry.has('queries'), true);
    assert.equal(registry.has('missing'), false);
    assert.deepEqual(registry.types(), ['queries']);
  });

  it('rejects empty types and non-function factories', () => {
    const registry = createPanelRegistry();
    assert.throws(() => registry.register('', () => ({})), /non-empty string/);
    assert.throws(() => registry.register(42, () => ({})), /non-empty string/);
    assert.throws(
      () => registry.register('queries', null),
      /must be a function/,
    );
  });

  it('unregisters known types', () => {
    const registry = createPanelRegistry();
    registry.register('queries', () => ({}));
    assert.equal(registry.unregister('queries'), true);
    assert.equal(registry.unregister('queries'), false);
    assert.equal(registry.has('queries'), false);
  });

  it('dispatches known types to their factory with context', () => {
    const seen = [];
    const registry = createPanelRegistry({ summary: { id: 'req-1' } });
    registry.register('queries', (context) => {
      seen.push(context);

      return { refresh() {} };
    });

    const panel = registry.create('queries');
    assert.equal(panel.type, 'queries');
    assert.equal(panel.profileId, 'req-1');
    assert.equal(seen.length, 1);
    assert.equal(seen[0].type, 'queries');
    assert.equal(seen[0].profileId, 'req-1');
  });

  it('keeps an explicit profile id and tracks the shell profile', () => {
    const context = { summary: { id: 'req-1' }, shell: null };
    const registry = createPanelRegistry(context);
    registry.register('queries', () => ({}));

    assert.equal(
      registry.create('queries', { profileId: 'req-9' }).profileId,
      'req-9',
    );

    context.shell = { currentProfileId: 'req-2' };
    assert.equal(registry.create('queries').profileId, 'req-2');

    context.shell = {};
    assert.equal(registry.create('queries').profileId, 'req-1');

    delete context.summary;
    assert.equal(registry.create('queries').profileId, null);
  });

  it('tolerates factories returning nothing', () => {
    const registry = createPanelRegistry();
    registry.register('queries', () => null);
    const panel = registry.create('queries');
    assert.equal(panel.type, 'queries');
    assert.equal(panel.refresh(), false);
  });

  it('passes unknown types through to server HTML', () => {
    const registry = createPanelRegistry();
    const panel = registry.create('vendor-panel', { html: '<p>server</p>' });
    assert.equal(panel.unknown, true);
    assert.equal(panel.html, '<p>server</p>');
    assert.equal(panel.refresh(), '<p>server</p>');
  });
});

describe('createGatedPanel', () => {
  const setup = (controller = {}, hooks = {}) =>
    createGatedPanel('queries', controller, {
      currentProfileId: () => 'req-1',
      ...hooks,
    });

  it('only runs init while visible on the current profile', () => {
    let calls = 0;
    const panel = setup(
      {
        init() {
          calls += 1;
        },
      },
      { profileId: 'req-1' },
    );

    panel.init();
    assert.equal(calls, 0);
    assert.equal(panel.initialized, true);

    panel.setVisible(true);
    assert.equal(calls, 0);

    panel.initialized = false;
    panel.init();
    assert.equal(calls, 1);

    panel.init();
    assert.equal(calls, 1);
  });

  it('supports a static current profile value', () => {
    let calls = 0;
    const panel = createGatedPanel(
      'queries',
      {
        init() {
          calls += 1;
        },
      },
      { profileId: null, currentProfileId: 'req-1' },
    );
    panel.setVisible(true);
    panel.init();
    assert.equal(calls, 1);
  });

  it('gates refresh on visibility and profile', () => {
    let calls = 0;
    const panel = setup(
      {
        refresh() {
          calls += 1;
        },
      },
      { profileId: 'req-1' },
    );
    panel.init();

    assert.equal(panel.refresh(), false);
    assert.equal(calls, 0);

    panel.setVisible(true);
    assert.equal(calls, 1);
    assert.equal(panel.refresh(), true);
    assert.equal(calls, 2);

    panel.profileId = 'req-2';
    assert.equal(panel.refresh(), false);
    assert.equal(calls, 2);

    panel.destroy();
    panel.profileId = 'req-1';
    panel.setVisible(true);
    assert.equal(panel.refresh(), false);
  });

  it('refresh without a controller refresh still reports active state', () => {
    const panel = setup({}, { profileId: 'req-1' });
    panel.init();
    panel.setVisible(true);
    assert.equal(panel.refresh(), true);
  });

  it('setVisible coerces values and returns the flag', () => {
    const panel = setup();
    assert.equal(panel.setVisible(1), true);
    assert.equal(panel.setVisible(0), false);
  });

  it('destroy runs once and blocks init', () => {
    let calls = 0;
    const panel = setup({
      destroy() {
        calls += 1;
      },
    });
    panel.destroy();
    panel.destroy();
    assert.equal(calls, 1);
    assert.equal(panel.destroyed, true);
    panel.init();
    assert.equal(panel.initialized, false);
  });

  it('destroy tolerates controllers without teardown', () => {
    const panel = setup();
    panel.destroy();
    assert.equal(panel.destroyed, true);
  });

  it('exposes activity state', () => {
    const panel = setup({}, { profileId: 'req-1' });
    assert.equal(panel.isActive(), false);
    panel.setVisible(true);
    panel.init();
    assert.equal(panel.isActive(), true);
  });
});

describe('createPassthroughPanel', () => {
  it('keeps server HTML unchanged and defaults to empty', () => {
    const panel = createPassthroughPanel('vendor-panel', {
      html: '<p>server</p>',
    });
    assert.equal(panel.unknown, true);
    assert.equal(panel.html, '<p>server</p>');
    assert.equal(panel.profileId, null);

    const empty = createPassthroughPanel('vendor-panel', { html: 42 });
    assert.equal(empty.html, '');
  });

  it('mounts into element targets', () => {
    const panel = createPassthroughPanel('vendor-panel', {
      html: '<p>server</p>',
    });
    const target = { innerHTML: '' };
    assert.equal(panel.mount(target), '<p>server</p>');
    assert.equal(target.innerHTML, '<p>server</p>');
  });

  it('mounts through callback targets', () => {
    const panel = createPassthroughPanel('vendor-panel', {
      html: '<p>server</p>',
    });
    let received = null;
    assert.equal(
      panel.mount((html) => {
        received = html;
      }),
      '<p>server</p>',
    );
    assert.equal(received, '<p>server</p>');
  });

  it('mounts without a target by returning the HTML', () => {
    const panel = createPassthroughPanel('vendor-panel', {
      html: '<p>server</p>',
    });
    assert.equal(panel.mount(), '<p>server</p>');
    assert.equal(panel.mount(null), '<p>server</p>');
    assert.equal(panel.mount({}), '<p>server</p>');
  });

  it('init uses the configured mount target', () => {
    const target = { innerHTML: '' };
    const panel = createPassthroughPanel('vendor-panel', {
      html: '<p>server</p>',
      mount: target,
      profileId: 'req-1',
    });
    panel.init();
    assert.equal(panel.initialized, true);
    assert.equal(target.innerHTML, '<p>server</p>');
    assert.equal(panel.profileId, 'req-1');
  });

  it('init accepts an explicit target and destroy flags the panel', () => {
    const panel = createPassthroughPanel('vendor-panel', {
      html: '<p>server</p>',
    });
    assert.equal(panel.init(), '<p>server</p>');
    panel.destroy();
    assert.equal(panel.destroyed, true);
  });
});
