import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { handleAttributesClick } from '../../../webroot/js/shell/panels/attributes.js';
import { FakeElement } from '../boot-fake-dom.js';

function setupPanel({ warmBox = true, inspectBox = true } = {}) {
  const panel = new FakeElement('div', {
    'data-dk-attributes-panel': '',
  });
  const scopeSelect = new FakeElement('select', {
    'data-dk-attributes-warm-config': '',
  });
  scopeSelect.value = 'default';
  panel.appendChild(scopeSelect);
  let warmResults = null;
  if (warmBox) {
    warmResults = new FakeElement('div', {
      'data-dk-attributes-warm-results': '',
    });
    warmResults.appendChild(new FakeElement('p'));
    panel.appendChild(warmResults);
  }
  let inspectResults = null;
  if (inspectBox) {
    inspectResults = new FakeElement('div', {
      'data-dk-attributes-inspect-results': '',
    });
    inspectResults.appendChild(new FakeElement('p'));
    panel.appendChild(inspectResults);
  }
  const attributeInput = new FakeElement('input', {
    'data-dk-attributes-inspect-attribute': '',
  });
  attributeInput.value = 'Handles';
  const classInput = new FakeElement('input', {
    'data-dk-attributes-inspect-class': '',
  });
  classInput.value = '';
  panel.appendChild(attributeInput);
  panel.appendChild(classInput);

  return { panel, warmResults, inspectResults };
}

function warmButton(panel) {
  const button = new FakeElement('button', {
    'data-dk-attributes-warm': '',
    'data-dk-attributes-warm-url': '/debug-bar/attributes/warm',
  });
  const label = new FakeElement('span', {
    'data-dk-attributes-warm-label': '',
  });
  label.textContent = 'Warm cache';
  button.appendChild(label);
  panel.appendChild(button);

  return { button, label };
}

function inspectButton(panel) {
  const button = new FakeElement('button', {
    'data-dk-attributes-inspect': '',
    'data-dk-attributes-inspect-url': '/debug-bar/attributes/inspect',
  });
  const label = new FakeElement('span', {
    'data-dk-attributes-inspect-label': '',
  });
  label.textContent = 'Inspect';
  button.appendChild(label);
  panel.appendChild(button);

  return { button, label };
}

function stubFetch(handler) {
  const calls = [];
  const realFetch = globalThis.fetch;
  const realDocument = globalThis.document;
  globalThis.document = {
    createElement: (tag) => new FakeElement(tag),
  };
  globalThis.fetch = (url, init) => {
    calls.push({ url, init });

    return handler(url, init);
  };

  return {
    calls,
    restore() {
      if (realFetch === undefined) {
        delete globalThis.fetch;
      } else {
        globalThis.fetch = realFetch;
      }
      if (realDocument === undefined) {
        delete globalThis.document;
      } else {
        globalThis.document = realDocument;
      }
    },
  };
}

async function flush(times = 6) {
  for (let i = 0; i < times; i += 1) {
    await new Promise((resolve) => setImmediate(resolve));
  }
}

function okPayload(payload) {
  return () =>
    Promise.resolve({ ok: true, json: () => Promise.resolve(payload) });
}

describe('attributes panel without result boxes', () => {
  let fetchStub;

  beforeEach(() => {
    fetchStub = null;
  });

  afterEach(() => {
    fetchStub?.restore();
  });

  it('ignores unrelated clicks', () => {
    assert.equal(
      handleAttributesClick(new FakeElement('button'), { root: null }),
      false,
    );
  });

  it('ignores scoped buttons outside the panel', () => {
    const button = new FakeElement('button', {
      'data-dk-attributes-warm': '',
    });

    assert.equal(handleAttributesClick(button, { root: null }), false);
  });

  it('warms without a result box and restores the button', async () => {
    const { panel } = setupPanel({ warmBox: false });
    const { button, label } = warmButton(panel);
    fetchStub = stubFetch(okPayload({ count: 2, elapsed: 0.1 }));

    assert.equal(handleAttributesClick(button, {}), true);
    assert.equal(button.hasAttribute('disabled'), true);
    await flush();

    assert.equal(button.hasAttribute('disabled'), false);
    assert.equal(label.textContent, 'Warm cache');
    assert.equal(
      fetchStub.calls[0].init.body,
      'config=' + encodeURIComponent('default'),
    );
  });

  it('renders warm errors without a result box', async () => {
    const { panel } = setupPanel({ warmBox: false });
    const { button } = warmButton(panel);
    fetchStub = stubFetch(okPayload({ error: 'Nope.' }));
    handleAttributesClick(button, {});
    await flush();

    assert.equal(button.hasAttribute('disabled'), false);
  });

  it('renders warm request failures without a result box', async () => {
    const { panel } = setupPanel({ warmBox: false });
    const { button } = warmButton(panel);
    fetchStub = stubFetch(() => Promise.reject(new Error('offline')));
    handleAttributesClick(button, {});
    await flush();

    assert.equal(button.hasAttribute('disabled'), false);
  });

  it('clears stale warm output before fetching', async () => {
    const { panel, warmResults } = setupPanel();
    const { button } = warmButton(panel);
    fetchStub = stubFetch(() => {
      warmResults.appendChild(new FakeElement('p'));

      return okPayload({ count: 1, elapsed: 0.2 })();
    });
    handleAttributesClick(button, {});
    await flush();

    assert.equal(warmResults.children.length, 1);
    assert.equal(
      warmResults.children[0].textContent,
      'Warmed 1 attribute in 0.2s (scope `default`).',
    );
  });

  it('renders inspect output into repopulated boxes', async () => {
    const { panel, inspectResults } = setupPanel();
    const { button } = inspectButton(panel);
    fetchStub = stubFetch(() => {
      inspectResults.appendChild(new FakeElement('p'));

      return okPayload({ items: [], config: 'default' })();
    });
    handleAttributesClick(button, {});
    await flush();

    assert.equal(inspectResults.children.length, 1);
  });

  it('inspects without a result box and restores the button', async () => {
    const { panel } = setupPanel({ inspectBox: false });
    const { button, label } = inspectButton(panel);
    fetchStub = stubFetch(okPayload({ items: [], config: 'default' }));

    assert.equal(handleAttributesClick(button, {}), true);
    await flush();

    assert.equal(button.hasAttribute('disabled'), false);
    assert.equal(label.textContent, 'Inspect');
  });

  it('ignores item sections without a result box', async () => {
    const { panel } = setupPanel({ inspectBox: false });
    const { button } = inspectButton(panel);
    fetchStub = stubFetch(
      okPayload({
        items: [
          {
            shortAttribute: 'Handles',
            className: 'Job',
            argumentsDisplay: { commandClass: 'Cmd' },
          },
        ],
        config: 'default',
      }),
    );
    handleAttributesClick(button, {});
    await flush();

    assert.equal(button.hasAttribute('disabled'), false);
  });

  it('renders inspect errors without a result box', async () => {
    const { panel } = setupPanel({ inspectBox: false });
    const { button } = inspectButton(panel);
    fetchStub = stubFetch(okPayload({ error: 'Bad scope.' }));
    handleAttributesClick(button, {});
    await flush();

    assert.equal(button.hasAttribute('disabled'), false);
  });

  it('renders found items into the result box', async () => {
    const { panel, inspectResults } = setupPanel();
    const { button } = inspectButton(panel);
    fetchStub = stubFetch(
      okPayload({
        items: [
          {
            shortAttribute: 'Handles',
            className: 'Job',
            pluginName: 'Crustum/Codex',
            lineNumber: 30,
            argumentsDisplay: { commandClass: 'Cmd' },
          },
        ],
        config: 'default',
      }),
    );
    handleAttributesClick(button, {});
    await flush();

    assert.ok(inspectResults.children.length > 1);
    assert.match(inspectResults.children[0].textContent, /Found 1 attribute/);
  });
});
