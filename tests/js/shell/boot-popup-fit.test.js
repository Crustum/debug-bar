import { describe, it, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { fitPopupInViewport } from '../../../webroot/js/shell/boot/core.js';
import { FakeElement } from '../boot-fake-dom.js';

function popupWithRect(rect) {
  const popup = new FakeElement('ul');
  popup.getBoundingClientRect = () => ({ ...rect });

  return popup;
}

describe('fit popup in viewport', () => {
  afterEach(() => {
    delete globalThis.window;
  });

  it('leaves fitting popups untouched', () => {
    globalThis.window = { innerWidth: 1024, innerHeight: 768 };
    const popup = popupWithRect({
      left: 100,
      right: 300,
      top: 350,
      bottom: 400,
    });

    fitPopupInViewport(popup);

    assert.equal(popup.style.transform, '');
  });

  it('nudges overflowing popups left without vertical moves', () => {
    globalThis.window = { innerWidth: 1024, innerHeight: 768 };
    const popup = popupWithRect({
      left: 900,
      right: 1100,
      top: 700,
      bottom: 900,
    });

    fitPopupInViewport(popup);

    assert.equal(popup.style.transform, 'translateX(-88px)');
  });

  it('never translates vertically, even past the bottom edge', () => {
    globalThis.window = { innerWidth: 1024, innerHeight: 768 };
    const popup = popupWithRect({
      left: 100,
      right: 300,
      top: 750,
      bottom: 900,
    });

    fitPopupInViewport(popup);

    assert.equal(popup.style.transform, '');
  });
});
