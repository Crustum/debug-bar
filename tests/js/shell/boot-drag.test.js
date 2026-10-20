import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
  syncToolbarForm,
  syncToolbarPlacement,
} from '../../../webroot/js/shell/boot/drag.js';
import { FakeElement } from '../boot-fake-dom.js';

function setup(corner) {
  const root = new FakeElement('div');
  const center = new FakeElement('div', { 'data-dk-center-toolbar': '' });
  const pill = new FakeElement('div', {
    'data-dk-corner-toolbar': '',
    hidden: '',
  });
  root.appendChild(center);
  root.appendChild(pill);
  const ctx = {
    root,
    shell: { toolbarPlacement: corner ? 'bottom-right' : 'bottom' },
  };
  Object.defineProperty(ctx.shell, 'toolbarIsCorner', {
    get() {
      return this.toolbarPlacement.includes('-');
    },
  });

  return { center, pill, ctx };
}

describe('boot toolbar form sync', () => {
  it('shows the center bar for edge placements', () => {
    const { center, pill, ctx } = setup(false);
    syncToolbarForm(ctx);

    assert.equal(center.hasAttribute('hidden'), false);
    assert.equal(pill.hasAttribute('hidden'), true);
  });

  it('shows the corner pill for corner placements', () => {
    const { center, pill, ctx } = setup(true);
    syncToolbarForm(ctx);

    assert.equal(center.hasAttribute('hidden'), true);
    assert.equal(pill.hasAttribute('hidden'), false);
  });

  it('falls back to the center bar without shell state', () => {
    const root = new FakeElement('div');
    const center = new FakeElement('div', { 'data-dk-center-toolbar': '' });
    root.appendChild(center);
    syncToolbarForm({ root, shell: null });

    assert.equal(center.hasAttribute('hidden'), false);
  });

  it('mirrors the live placement onto the shell', () => {
    const root = new FakeElement('div');
    const shell = new FakeElement('div', {
      'data-dk-toolbar-shell': '',
      'data-dk-placement': 'bottom',
    });
    root.appendChild(shell);

    syncToolbarPlacement({
      root,
      shell,
      toolbar: shell,
      shell: { toolbarPlacement: 'top' },
    });
    assert.equal(shell.getAttribute('data-dk-placement'), 'top');

    syncToolbarPlacement({ root, shell, toolbar: shell, shell: null });
    assert.equal(shell.getAttribute('data-dk-placement'), 'bottom');
  });

  it('writes placement while syncing the form', () => {
    const root = new FakeElement('div');
    const shell = new FakeElement('div', { 'data-dk-toolbar-shell': '' });
    root.appendChild(shell);
    const center = new FakeElement('div', { 'data-dk-center-toolbar': '' });
    root.appendChild(center);
    const ctx = {
      root,
      toolbar: shell,
      shell: { toolbarPlacement: 'top-right', toolbarIsCorner: true },
    };
    const pill = new FakeElement('div', { 'data-dk-corner-toolbar': '' });
    root.appendChild(pill);
    syncToolbarForm(ctx);

    assert.equal(shell.getAttribute('data-dk-placement'), 'top-right');
  });
});
