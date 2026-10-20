export function handleMailClick(target, ctx) {
  if (!target.hasAttribute('data-dk-mail-viewport')) {
    return false;
  }
  const viewportDetail = target.closest
    ? target.closest('[data-dk-mail-detail]')
    : null;
  const viewportScope = viewportDetail || ctx.root;
  const viewport = target.getAttribute('data-dk-mail-viewport') || 'desktop';
  viewportScope
    .querySelectorAll('[data-dk-mail-viewport]')
    .forEach(function (button) {
      button.setAttribute(
        'aria-pressed',
        (button.getAttribute('data-dk-mail-viewport') || '') === viewport
          ? 'true'
          : 'false',
      );
    });
  const canvas = viewportScope.querySelector('[data-dk-mail-preview-canvas]');
  if (canvas) {
    if (viewport === 'mobile') {
      canvas.classList.add('dk:max-w-[23.4375rem]');
    } else {
      canvas.classList.remove('dk:max-w-[23.4375rem]');
    }
  }

  return true;
}
