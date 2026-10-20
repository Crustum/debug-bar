import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { handleMailClick } from '../../../webroot/js/shell/panels/mail.js';
import { FakeElement } from '../boot-fake-dom.js';

function mailDetail() {
  const detail = new FakeElement('section', { 'data-dk-mail-detail': 'm1' });
  const desktop = new FakeElement('button', {
    'data-dk-mail-viewport': 'desktop',
    'aria-pressed': 'true',
  });
  const mobile = new FakeElement('button', {
    'data-dk-mail-viewport': 'mobile',
    'aria-pressed': 'false',
  });
  const canvas = new FakeElement('div', {
    'data-dk-mail-preview-canvas': '',
  });
  detail.appendChild(desktop);
  detail.appendChild(mobile);
  detail.appendChild(canvas);

  return { detail, desktop, mobile, canvas };
}

describe('mail viewport toggle', () => {
  it('ignores clicks outside viewport buttons', () => {
    const { detail } = mailDetail();

    assert.equal(
      handleMailClick(new FakeElement('button'), { root: detail }),
      false,
    );
  });

  it('switches pressed states and constrains the canvas on mobile', () => {
    const { detail, desktop, mobile, canvas } = mailDetail();

    assert.equal(handleMailClick(mobile, { root: detail }), true);
    assert.equal(mobile.getAttribute('aria-pressed'), 'true');
    assert.equal(desktop.getAttribute('aria-pressed'), 'false');
    assert.equal(canvas.classList.contains('dk:max-w-[23.4375rem]'), true);
  });

  it('releases the canvas constraint on desktop', () => {
    const { detail, desktop, mobile, canvas } = mailDetail();
    handleMailClick(mobile, { root: detail });

    assert.equal(handleMailClick(desktop, { root: detail }), true);
    assert.equal(desktop.getAttribute('aria-pressed'), 'true');
    assert.equal(mobile.getAttribute('aria-pressed'), 'false');
    assert.equal(canvas.classList.contains('dk:max-w-[23.4375rem]'), false);
  });

  it('works without a preview canvas', () => {
    const detail = new FakeElement('section', {
      'data-dk-mail-detail': 'm1',
    });
    const mobile = new FakeElement('button', {
      'data-dk-mail-viewport': 'mobile',
      'aria-pressed': 'false',
    });
    detail.appendChild(mobile);

    assert.equal(handleMailClick(mobile, { root: detail }), true);
    assert.equal(mobile.getAttribute('aria-pressed'), 'true');
  });

  it('falls back to the shell root outside a mail detail', () => {
    const root = new FakeElement('div');
    const mobile = new FakeElement('button', {
      'data-dk-mail-viewport': 'mobile',
      'aria-pressed': 'false',
    });
    root.appendChild(mobile);

    assert.equal(handleMailClick(mobile, { root }), true);
    assert.equal(mobile.getAttribute('aria-pressed'), 'true');
  });
});
