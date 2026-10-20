import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { registerPanelScripts } from '../../../webroot/js/shell/boot/panels.js';
import { FakeElement } from '../boot-fake-dom.js';

function manifest(urls, tag = 'script') {
  const node = new FakeElement(tag, { 'data-dk-panel-scripts': '' });
  node.textContent = typeof urls === 'string' ? urls : JSON.stringify(urls);

  return node;
}

describe('boot panel scripts', () => {
  beforeEach(() => {
    delete globalThis.window;
    delete globalThis.document;
  });

  afterEach(() => {
    delete globalThis.window;
    delete globalThis.document;
  });

  it('loads manifest scripts once across rescans', () => {
    const seen = [];
    const root = new FakeElement('div', {}, [
      manifest(['/mongo/js/one.js', '/mongo/js/two.js']),
    ]);

    assert.equal(
      registerPanelScripts(root, (url) => seen.push(url)),
      2,
    );
    assert.deepEqual(seen, ['/mongo/js/one.js', '/mongo/js/two.js']);
    assert.equal(
      registerPanelScripts(root, (url) => seen.push(url)),
      0,
    );
    assert.deepEqual(seen, ['/mongo/js/one.js', '/mongo/js/two.js']);
  });

  it('ignores non-script tags, broken payloads and bad entries', () => {
    const seen = [];
    const root = new FakeElement('div', {}, [
      manifest(['/ok.js'], 'div'),
      manifest('{oops'),
      manifest({ entry: '/map.js' }),
      manifest(['', null, 7, '/fine.js']),
    ]);

    assert.equal(
      registerPanelScripts(root, (url) => seen.push(url)),
      1,
    );
    assert.deepEqual(seen, ['/fine.js']);
    assert.equal(registerPanelScripts(null), 0);
    assert.equal(registerPanelScripts({}), 0);
  });

  it('rejects foreign origins when a window location exists', () => {
    globalThis.window = {
      location: { origin: 'http://localhost', href: 'http://localhost/' },
    };
    const seen = [];
    const root = new FakeElement('div', {}, [
      manifest(['https://evil.test/x.js', '/mongo/js/local.js']),
    ]);

    assert.equal(
      registerPanelScripts(root, (url) => seen.push(url)),
      1,
    );
    assert.deepEqual(seen, ['/mongo/js/local.js']);
  });

  it('appends scripts to the document head by default', () => {
    const head = new FakeElement('head');
    const created = [];
    globalThis.document = {
      createElement: (tag) => {
        const node = new FakeElement(tag);
        created.push(node);

        return node;
      },
      head,
    };
    const root = new FakeElement('div', {}, [
      manifest(['http://localhost/mongo/js/mongo_panel.js']),
    ]);

    assert.equal(registerPanelScripts(root), 1);
    assert.equal(created.length, 1);
    assert.equal(
      created[0].getAttribute('src'),
      'http://localhost/mongo/js/mongo_panel.js',
    );
    assert.equal(head.children.length, 1);
    assert.equal(head.children[0], created[0]);
  });

  it('tolerates throwing loaders and missing document parts', () => {
    const root = new FakeElement('div', {}, [
      manifest(['/throw.js', '/skip.js']),
    ]);

    assert.equal(
      registerPanelScripts(root, () => {
        throw new Error('nope');
      }),
      2,
    );

    globalThis.document = {};
    const bare = new FakeElement('div', {}, [manifest(['/x.js'])]);

    assert.equal(registerPanelScripts(bare), 1);
  });
});
