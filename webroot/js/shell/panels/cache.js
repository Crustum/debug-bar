import { readCsrfToken } from '../../csrf.js';
import { flashButtonIcon } from '../copy.js';

export function handleCacheClearClick(target) {
  const clearAll =
    target.hasAttribute && target.hasAttribute('data-dk-cache-clear-all');
  if (!clearAll && !target.hasAttribute('data-dk-cache-clear')) {
    return false;
  }
  const clearName = clearAll
    ? ''
    : target.getAttribute('data-dk-cache-clear-name') || '';
  const clearUrl = clearAll
    ? target.getAttribute('data-dk-cache-clear-all-url') || ''
    : target.getAttribute('data-dk-cache-clear-url') || '';
  const csrfToken = readCsrfToken(target) ?? '';
  const clearIcon = target.querySelector ? target.querySelector('svg') : null;
  target.setAttribute('disabled', '');
  showCacheResult(
    target,
    clearAll ? 'Clearing all caches…' : 'Clearing ' + clearName + '…',
  );
  fetch(clearUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': csrfToken,
    },
    body: clearAll ? '' : 'name=' + encodeURIComponent(clearName),
  })
    .then(function (response) {
      if (!response || response.ok !== true) {
        throw new Error('clear-cache');
      }

      return response.json();
    })
    .then(function (payload) {
      showCacheResult(
        target,
        payload && payload.message ? String(payload.message) : 'Cache cleared.',
      );
      flashButtonIcon(target, clearIcon, 'dk-cache-flash');
    })
    .catch(function () {
      showCacheResult(
        target,
        clearAll
          ? 'Could not clear caches.'
          : 'Could not clear ' + clearName + '.',
      );
    })
    .finally(function () {
      target.removeAttribute('disabled');
    });

  return true;
}

const CACHE_RESULT_HIDE_DELAY = 10000;
const cacheResultTimers = new WeakMap();

function showCacheResult(target, text) {
  const row = target && target.closest ? target.closest('li') : null;
  const result =
    row && row.querySelector
      ? row.querySelector('[data-dk-cache-clear-result]')
      : null;
  if (!result) {
    return;
  }
  result.textContent = text;
  if (typeof result.removeAttribute === 'function') {
    result.removeAttribute('hidden');
  }
  try {
    const pending = cacheResultTimers.get(result);
    if (pending !== undefined && typeof window.clearTimeout === 'function') {
      window.clearTimeout(pending);
    }
  } catch (error) {}
  if (typeof window.setTimeout !== 'function') {
    return;
  }
  try {
    cacheResultTimers.set(
      result,
      window.setTimeout(function () {
        try {
          result.textContent = '';
          if (typeof result.setAttribute === 'function') {
            result.setAttribute('hidden', '');
          }
        } catch (error) {}
        cacheResultTimers.delete(result);
      }, CACHE_RESULT_HIDE_DELAY),
    );
  } catch (error) {}
}
