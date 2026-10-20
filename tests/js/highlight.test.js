import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  highlightCodeBlocks,
  resetHighlight,
} from '../../webroot/js/highlight.js';
import { FakeElement } from './boot-fake-dom.js';

function codeBlock(language, source) {
  const block = new FakeElement('code', { 'data-dk-language': language });
  block.dataset = { dkLanguage: language };
  block.textContent = source;
  block.className = 'hljs';

  return block;
}

describe('highlight code blocks', () => {
  let realDocument;

  beforeEach(() => {
    realDocument = globalThis.document;
  });

  afterEach(() => {
    if (realDocument === undefined) {
      delete globalThis.document;
    } else {
      globalThis.document = realDocument;
    }
  });

  it('returns zero without a scope', () => {
    delete globalThis.document;

    assert.equal(highlightCodeBlocks(null), 0);
    assert.equal(highlightCodeBlocks({}), 0);
  });

  it('falls back to the document scope', () => {
    globalThis.document = { querySelectorAll: () => [] };

    assert.equal(highlightCodeBlocks(null), 0);
  });

  it('highlights sql blocks once per source', () => {
    const root = new FakeElement('div');
    const block = codeBlock('sql', 'SELECT 1');
    root.appendChild(block);

    assert.equal(highlightCodeBlocks(root), 1);
    assert.match(String(block.innerHTML), /hljs-number/);

    block.setAttribute('data-highlighted', '');
    assert.equal(highlightCodeBlocks(root), 0);
  });

  it('re-highlights changed sources', () => {
    const root = new FakeElement('div');
    const block = codeBlock('sql', 'SELECT 1');
    root.appendChild(block);
    highlightCodeBlocks(root);
    block.setAttribute('data-highlighted', '');
    block.textContent = 'SELECT 2';

    assert.equal(highlightCodeBlocks(root), 1);
    assert.match(String(block.innerHTML), /hljs-number/);
  });

  it('resets highlight markers', () => {
    const block = codeBlock('sql', 'SELECT 1');
    block.setAttribute('data-highlighted', '');
    resetHighlight(block);

    assert.equal(block.hasAttribute('data-highlighted'), false);

    assert.equal(resetHighlight(null), undefined);
    assert.equal(resetHighlight({}), undefined);
  });
});
