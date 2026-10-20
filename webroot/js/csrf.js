export const CSRF_TOKEN_ATTRIBUTE = 'data-dk-csrf-token';

const CSRF_COOKIE_PATTERN = /(?:^|;\s*)csrfToken=([^;]*)/;

function tokenFromHolder(holder) {
  if (!holder || typeof holder.getAttribute !== 'function') return null;
  const value = holder.getAttribute(CSRF_TOKEN_ATTRIBUTE);
  if (typeof value !== 'string' || value === '') return null;

  return value;
}

export function readCsrfToken(scope = null) {
  if (
    scope &&
    typeof scope.closest === 'function' &&
    typeof scope.getAttribute === 'function'
  ) {
    if (scope.getAttribute(CSRF_TOKEN_ATTRIBUTE) !== null) {
      return tokenFromHolder(scope);
    }
    const holder = scope.closest(`[${CSRF_TOKEN_ATTRIBUTE}]`);
    const token = tokenFromHolder(holder);
    if (token !== null) return token;
  }

  const doc = typeof document === 'undefined' ? null : document;
  const searchRoot =
    scope && typeof scope.querySelector === 'function' ? scope : doc;
  if (searchRoot && typeof searchRoot.querySelector === 'function') {
    const token = tokenFromHolder(
      searchRoot.querySelector(`[${CSRF_TOKEN_ATTRIBUTE}]`),
    );
    if (token !== null) return token;
  }

  if (doc && typeof doc.cookie === 'string') {
    const match = doc.cookie.match(CSRF_COOKIE_PATTERN);
    if (match) return decodeURIComponent(match[1]);
  }

  return null;
}
