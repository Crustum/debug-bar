import { describe, it, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { readCsrfToken, CSRF_TOKEN_ATTRIBUTE } from '../../webroot/js/csrf.js';

function fakeHolder(token) {
  return {
    getAttribute: (name) => (name === CSRF_TOKEN_ATTRIBUTE ? token : null),
    closest: () => null,
    querySelector: () => null,
  };
}

afterEach(() => {
  delete globalThis.document;
});

describe('readCsrfToken', () => {
  it('finds no token outside a browser', () => {
    assert.equal(readCsrfToken(), null);
    assert.equal(readCsrfToken(null), null);
  });

  it('prefers the server-rendered token on an ancestor', () => {
    const holder = fakeHolder('server-token');
    const scope = {
      getAttribute: () => null,
      closest: (selector) =>
        selector === `[${CSRF_TOKEN_ATTRIBUTE}]` ? holder : null,
      querySelector: () => null,
    };
    globalThis.document = { cookie: 'csrfToken=cookie-token' };

    assert.equal(readCsrfToken(scope), 'server-token');
  });

  it('reads the token off the scope itself', () => {
    const scope = {
      getAttribute: (name) =>
        name === CSRF_TOKEN_ATTRIBUTE ? 'own-token' : null,
      closest: () => null,
      querySelector: () => null,
    };

    assert.equal(readCsrfToken(scope), 'own-token');
  });

  it('finds the token through the document', () => {
    globalThis.document = {
      cookie: '',
      querySelector: (selector) =>
        selector === `[${CSRF_TOKEN_ATTRIBUTE}]`
          ? fakeHolder('shell-token')
          : null,
    };

    assert.equal(readCsrfToken(), 'shell-token');
  });

  it('falls back to the cookie', () => {
    globalThis.document = {
      cookie: 'other=1; csrfToken=cookie-token',
      querySelector: () => null,
    };

    assert.equal(readCsrfToken(), 'cookie-token');
  });

  it('ignores an empty attribute and uses the cookie', () => {
    const scope = {
      getAttribute: () => null,
      closest: () => fakeHolder(''),
      querySelector: () => null,
    };
    globalThis.document = {
      cookie: 'csrfToken=cookie-token',
      querySelector: () => null,
    };

    assert.equal(readCsrfToken(scope), 'cookie-token');
  });

  it('returns null when neither source has a token', () => {
    globalThis.document = { cookie: '', querySelector: () => null };

    assert.equal(readCsrfToken(), null);
  });
});
