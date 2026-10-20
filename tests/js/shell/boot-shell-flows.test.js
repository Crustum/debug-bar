import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  buildRoot,
  installGlobals,
  cleanupGlobals,
  bootUrl,
  keydown,
} from './boot-shell-harness.mjs';

describe('boot shell flows', () => {
  beforeEach(() => {
    cleanupGlobals();
  });

  afterEach(() => {
    cleanupGlobals();
  });
  it('boots the bar and walks the main click chain', async () => {
    const built = buildRoot();
    const { listeners, posted, reloaded, timers } = installGlobals(built.root);
    await import(bootUrl('main'));

    assert.equal(built.root.getAttribute('data-dk-frame'), 'full');
    assert.ok(built.root.__dkShell);

    built.mini.click();
    assert.equal(built.root.getAttribute('data-dk-frame'), 'bar');

    built.backdrop.click();
    assert.equal(built.root.getAttribute('data-dk-frame'), 'bar');

    built.openBtn.click();
    assert.equal(built.root.getAttribute('data-dk-frame'), 'inspector');
    assert.ok(posted.includes('expand'));

    built.shrink.click();
    assert.equal(built.root.getAttribute('data-dk-frame'), 'bar');

    keydown(listeners, built.root, {
      key: 'p',
      code: 'KeyP',
      metaKey: true,
      altKey: true,
    });
    assert.equal(built.palette.hasAttribute('hidden'), false);
    assert.equal(built.root.getAttribute('data-dk-frame'), 'palette');

    keydown(listeners, built.paletteSearch, { key: 'ArrowDown' });
    keydown(listeners, built.paletteSearch, { key: 'Enter' });

    keydown(listeners, built.root, { key: 'Escape' });
    assert.equal(built.palette.hasAttribute('hidden'), true);
    assert.equal(built.root.getAttribute('data-dk-frame'), 'bar');

    built.hide.click();
    assert.equal(built.root.getAttribute('data-dk-frame'), 'mini');
    assert.ok(posted.includes('collapse'));

    built.expand.click();
    assert.equal(built.root.getAttribute('data-dk-frame'), 'inspector');

    built.requestRow.item.click();
    assert.equal(built.requestRow.item.getAttribute('aria-current'), 'page');
    assert.equal(built.heading.textContent, 'Request');

    const pressedBefore = built.cacheRow.star.getAttribute('aria-pressed');
    built.cacheRow.star.click();
    assert.notEqual(
      built.cacheRow.star.getAttribute('aria-pressed'),
      pressedBefore,
    );

    built.retry.click();
    built.activityRetry.click();
    built.reload.click();
    assert.deepEqual(reloaded, [true]);

    built.copyBtn.click();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(built.copyLabel.textContent, 'Copied');
    for (const run of timers.splice(0)) run();
    assert.equal(built.copyLabel.textContent, 'Copy id');

    built.copyAllBtn.click();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(built.copyAllLabel.textContent, 'Copied');

    built.tabBtn.click();
    assert.equal(built.tabBtn.getAttribute('aria-selected'), 'true');
    assert.equal(built.tabPanel.hasAttribute('hidden'), false);

    built.filterTab.click();
    assert.equal(built.filterTab.getAttribute('aria-selected'), 'true');

    built.treeBtn.click();
    assert.equal(built.treeGroup.hasAttribute('open'), true);

    built.backBtn.click();

    built.httpRow.click();
    assert.equal(built.httpRow.getAttribute('aria-pressed'), 'true');
    assert.equal(built.httpDetail.hasAttribute('hidden'), false);
  });
});
