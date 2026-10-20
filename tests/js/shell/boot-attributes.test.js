import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { handleAttributesClick } from '../../../webroot/js/shell/panels/attributes.js';

class FakeElement {
  constructor(tag = 'div') {
    this.tagName = String(tag).toUpperCase();
    this.attrs = {};
    this.children = [];
    this.textContent = '';
    this.value = '';
    this.parentNode = null;
  }

  get firstChild() {
    return this.children.length > 0 ? this.children[0] : null;
  }

  setAttribute(name, value) {
    this.attrs[name] = String(value);
  }

  getAttribute(name) {
    return name in this.attrs ? this.attrs[name] : null;
  }

  hasAttribute(name) {
    return name in this.attrs;
  }

  removeAttribute(name) {
    delete this.attrs[name];
  }

  appendChild(child) {
    child.parentNode = this;
    this.children.push(child);

    return child;
  }

  removeChild(child) {
    this.children = this.children.filter((item) => item !== child);

    return child;
  }

  querySelector() {
    return null;
  }

  querySelectorAll() {
    return [];
  }

  closest() {
    return null;
  }
}

function makeItem(overrides = {}) {
  return {
    key: 'k1',
    config: 'codex_command_handlers',
    attributeName: 'Crustum\\Codex\\Queue\\Handles',
    shortAttribute: 'Handles',
    className: 'Crustum\\Codex\\Job\\EmbedDocChunkJob',
    shortClass: 'EmbedDocChunkJob',
    pluginName: 'Crustum/Codex',
    targetType: 'class',
    targetName: 'Crustum\\Codex\\Job\\EmbedDocChunkJob',
    filePath: '/app/plugins/Codex/src/Job/EmbedDocChunkJob.php',
    lineNumber: 30,
    fileTimeDisplay: '2026-09-19 15:05:22',
    argumentsDisplay: { commandClass: 'EmbedChunkCommand' },
    ...overrides,
  };
}

function setupPanel() {
  const panel = new FakeElement('div');
  panel.setAttribute('data-dk-active-filter', 'all');
  const scopeSelect = new FakeElement('select');
  scopeSelect.value = 'codex_command_handlers';
  const warmBox = new FakeElement('div');
  const inspectBox = new FakeElement('div');
  const attributeInput = new FakeElement('input');
  attributeInput.value = 'Handles';
  const classInput = new FakeElement('input');
  classInput.value = '';
  panel.querySelector = (selector) => {
    if (selector === '[data-dk-attributes-warm-config]') return scopeSelect;
    if (selector === '[data-dk-attributes-warm-results]') return warmBox;
    if (selector === '[data-dk-attributes-inspect-results]') return inspectBox;
    if (selector === '[data-dk-attributes-inspect-attribute]')
      return attributeInput;
    if (selector === '[data-dk-attributes-inspect-class]') return classInput;

    return null;
  };

  return {
    panel,
    scopeSelect,
    warmBox,
    inspectBox,
    attributeInput,
    classInput,
  };
}

function setupButton({ panel, warm = false } = {}) {
  const label = new FakeElement('span');
  label.textContent = warm ? 'Warm cache' : 'Inspect';
  const button = new FakeElement('button');
  if (warm) {
    button.setAttribute('data-dk-attributes-warm', '');
    button.setAttribute(
      'data-dk-attributes-warm-url',
      '/debug-bar/attributes/warm',
    );
  } else {
    button.setAttribute('data-dk-attributes-inspect', '');
    button.setAttribute(
      'data-dk-attributes-inspect-url',
      '/debug-bar/attributes/inspect',
    );
  }
  button.closest = (selector) =>
    selector === '[data-dk-attributes-panel]' ? panel : null;
  button.querySelector = (selector) => {
    if (selector === '[data-dk-attributes-warm-label]' && warm) return label;
    if (selector === '[data-dk-attributes-inspect-label]' && !warm)
      return label;

    return null;
  };

  return { button, label };
}

function stubFetch({ ok = true, payload = null } = {}) {
  const fetchCalls = [];
  globalThis.document = {
    createElement: (tag) => new FakeElement(tag),
  };
  globalThis.fetch = async (url, init) => {
    fetchCalls.push({ url, init });

    return ok
      ? {
          ok: true,
          json: async () => payload,
        }
      : { ok: false };
  };

  return { fetchCalls };
}

async function flush() {
  await Promise.resolve();
  await new Promise((resolve) => setImmediate(resolve));
}

describe('boot attributes warm feedback', () => {
  beforeEach(() => {
    delete globalThis.document;
    delete globalThis.fetch;
  });

  afterEach(() => {
    delete globalThis.document;
    delete globalThis.fetch;
  });

  it('posts the selected scope and renders the warmed count', async () => {
    const { panel, warmBox } = setupPanel();
    const { button, label } = setupButton({ panel, warm: true });
    const { fetchCalls } = stubFetch({
      payload: {
        config: 'codex_command_handlers',
        count: 3,
        elapsed: 0.5,
        error: null,
      },
    });
    assert.equal(handleAttributesClick(button, {}), true);
    assert.equal(label.textContent, 'Warming…');
    assert.equal(button.hasAttribute('disabled'), true);

    await flush();
    await flush();
    assert.equal(fetchCalls.length, 1);
    assert.equal(fetchCalls[0].url, '/debug-bar/attributes/warm');
    assert.ok(
      fetchCalls[0].init.body.includes('config=codex_command_handlers'),
    );
    assert.equal(
      warmBox.children[0].textContent,
      'Warmed 3 attributes in 0.5s (scope `codex_command_handlers`).',
    );
    assert.equal(label.textContent, 'Warm cache');
    assert.equal(button.hasAttribute('disabled'), false);
  });

  it('renders the payload error without throwing', async () => {
    const { panel, warmBox } = setupPanel();
    const { button } = setupButton({ panel, warm: true });
    stubFetch({
      payload: { config: null, count: 0, elapsed: 0, error: 'Nope.' },
    });
    assert.equal(handleAttributesClick(button, {}), true);

    await flush();
    await flush();
    assert.equal(
      warmBox.children[0].textContent,
      'Nope. (scope `codex_command_handlers`)',
    );
    assert.equal(button.hasAttribute('disabled'), false);
  });

  it('renders the request failure message', async () => {
    const { panel, warmBox } = setupPanel();
    const { button } = setupButton({ panel, warm: true });
    stubFetch({ ok: false });
    assert.equal(handleAttributesClick(button, {}), true);

    await flush();
    await flush();
    assert.equal(
      warmBox.children[0].textContent,
      'Could not warm attribute cache (request failed — scope `codex_command_handlers`).',
    );
  });
});

describe('boot attributes inspect feedback', () => {
  beforeEach(() => {
    delete globalThis.document;
    delete globalThis.fetch;
  });

  afterEach(() => {
    delete globalThis.document;
    delete globalThis.fetch;
  });

  it('posts filters and renders one section per match', async () => {
    const { panel, inspectBox } = setupPanel();
    const { button, label } = setupButton({ panel });
    const { fetchCalls } = stubFetch({
      payload: {
        items: [makeItem(), makeItem({ key: 'k2' })],
        count: 2,
        matching: 2,
        truncated: false,
        config: 'codex_command_handlers',
        error: null,
      },
    });
    assert.equal(handleAttributesClick(button, {}), true);
    assert.equal(label.textContent, 'Inspecting…');
    assert.equal(button.hasAttribute('disabled'), true);

    await flush();
    await flush();
    await flush();
    assert.equal(fetchCalls.length, 1);
    assert.equal(fetchCalls[0].url, '/debug-bar/attributes/inspect');
    assert.ok(fetchCalls[0].init.body.includes('attribute=Handles'));
    assert.ok(
      fetchCalls[0].init.body.includes('config=codex_command_handlers'),
    );
    assert.equal(
      inspectBox.children[0].textContent,
      'Found 2 attributes in scope `codex_command_handlers`:',
    );
    assert.equal(inspectBox.children.length, 3);
    assert.equal(inspectBox.children[1].children[0].textContent, 'Handles');
    assert.equal(label.textContent, 'Inspect');
    assert.equal(button.hasAttribute('disabled'), false);
  });

  it('renders file time and scope facts per match', async () => {
    const { panel, inspectBox } = setupPanel();
    const { button } = setupButton({ panel });
    stubFetch({
      payload: {
        items: [makeItem()],
        count: 1,
        matching: 1,
        truncated: false,
        config: 'codex_command_handlers',
        error: null,
      },
    });
    assert.equal(handleAttributesClick(button, {}), true);

    await flush();
    await flush();
    await flush();
    const section = inspectBox.children[1];
    const texts = [];
    const collect = (node) => {
      if (node.textContent && node.children.length === 0) {
        texts.push(node.textContent);
      }
      node.children.forEach(collect);
    };
    collect(section);
    assert.ok(texts.includes('File time'));
    assert.ok(texts.includes('2026-09-19 15:05:22'));
    assert.ok(texts.includes('Scope'));
    assert.ok(texts.includes('codex_command_handlers'));
    assert.ok(texts.includes('commandClass: EmbedChunkCommand'));
  });

  it('renders the empty state when nothing matches', async () => {
    const { panel, inspectBox } = setupPanel();
    const { button } = setupButton({ panel });
    stubFetch({
      payload: {
        items: [],
        count: 0,
        matching: 0,
        truncated: false,
        config: 'codex_command_handlers',
        error: null,
      },
    });
    assert.equal(handleAttributesClick(button, {}), true);

    await flush();
    await flush();
    assert.equal(
      inspectBox.children[0].textContent,
      'No attributes found matching the criteria.',
    );
    assert.equal(inspectBox.children.length, 1);
  });

  it('renders the inspect error message', async () => {
    const { panel, inspectBox } = setupPanel();
    const { button } = setupButton({ panel });
    stubFetch({
      payload: { error: 'Please provide an attribute name.' },
    });
    assert.equal(handleAttributesClick(button, {}), true);

    await flush();
    await flush();
    assert.equal(
      inspectBox.children[0].textContent,
      'Please provide an attribute name.',
    );
  });

  it('renders the request failure message', async () => {
    const { panel, inspectBox } = setupPanel();
    const { button } = setupButton({ panel });
    stubFetch({ ok: false });
    assert.equal(handleAttributesClick(button, {}), true);

    await flush();
    await flush();
    assert.equal(
      inspectBox.children[0].textContent,
      'Could not inspect attributes (request failed).',
    );
  });

  it('ignores targets without the attributes hooks', () => {
    const { panel } = setupPanel();
    const { button } = setupButton({ panel });
    button.removeAttribute('data-dk-attributes-inspect');
    assert.equal(handleAttributesClick(button, {}), false);
  });

  it('ignores hook targets outside an attributes panel', () => {
    const { panel } = setupPanel();
    const { button } = setupButton({ panel });
    button.closest = () => null;
    assert.equal(handleAttributesClick(button, {}), false);
  });
});
