import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { handlePackagesClick } from '../../../webroot/js/shell/panels/packages.js';
import { FakeElement } from '../boot-fake-dom.js';

function stubDom() {
  const realDocument = globalThis.document;
  globalThis.document = {
    createElement: (tag) => new FakeElement(tag),
  };

  return function restore() {
    if (realDocument === undefined) {
      delete globalThis.document;
    } else {
      globalThis.document = realDocument;
    }
  };
}

function stubFetch(handler) {
  const calls = [];
  const realFetch = globalThis.fetch;
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
    },
  };
}

async function flush(times = 8) {
  for (let i = 0; i < times; i += 1) {
    await new Promise((resolve) => setImmediate(resolve));
  }
}

function okPayload(payload) {
  return () =>
    Promise.resolve({ ok: true, json: () => Promise.resolve(payload) });
}

function setupPanel({ direct = false, filterValue = 'all' } = {}) {
  const panel = new FakeElement('div', {
    'data-dk-packages-panel': '',
    'data-dk-active-filter': 'all',
  });
  const updates = new FakeElement('div', {
    'data-dk-packages-updates': '',
  });
  updates.appendChild(new FakeElement('p'));
  panel.appendChild(updates);
  let directBox = null;
  if (direct !== null) {
    directBox = new FakeElement('input', {
      'data-dk-packages-check-direct': '',
    });
    directBox.checked = direct;
    panel.appendChild(directBox);
  }
  const auditResults = new FakeElement('div', {
    'data-dk-packages-audit-results': '',
  });
  auditResults.appendChild(new FakeElement('p'));
  panel.appendChild(auditResults);
  const filter = new FakeElement('select', {
    'data-dk-select': 'packages-filter',
  });
  filter.value = filterValue;
  panel.appendChild(filter);

  return { panel, updates, auditResults, filter };
}

function checkButton(panel) {
  const button = new FakeElement('button', {
    'data-dk-packages-check': '',
    'data-dk-packages-check-url': '/debug-bar/packages/check',
  });
  const label = new FakeElement('span', {
    'data-dk-packages-check-label': '',
  });
  label.textContent = 'Check for Updates';
  button.appendChild(label);
  panel.appendChild(button);

  return { button, label };
}

function auditButton(panel) {
  const button = new FakeElement('button', {
    'data-dk-packages-audit': '',
    'data-dk-packages-audit-url': '/debug-bar/packages/audit',
  });
  const label = new FakeElement('span', {
    'data-dk-packages-audit-label': '',
  });
  label.textContent = 'Audit vulnerabilities';
  button.appendChild(label);
  panel.appendChild(button);

  return { button, label };
}

function packageRow(panel, name) {
  const row = new FakeElement('div', {
    'data-dk-package-entry': '',
    'data-dk-package-key': name,
    'data-dk-filter-attention': 'false',
    'data-dk-search-text': name,
  });
  const version = new FakeElement('span', {
    'data-dk-package-version': '',
  });
  version.textContent = '1.0.0';
  row.appendChild(version);
  panel.appendChild(row);

  return { row, version };
}

describe('packages dependency check', () => {
  let fetchStub;
  let restoreDom;

  beforeEach(() => {
    restoreDom = stubDom();
    fetchStub = null;
  });

  afterEach(() => {
    fetchStub?.restore();
    restoreDom();
  });

  it('ignores unrelated clicks', () => {
    assert.equal(handlePackagesClick(new FakeElement('button'), {}), false);
  });

  it('reports up-to-date dependencies and clears stale output', async () => {
    const { panel, updates } = setupPanel();
    const { button, label } = checkButton(panel);
    fetchStub = stubFetch(() => {
      updates.appendChild(new FakeElement('p'));

      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ packages: {} }),
      });
    });
    handlePackagesClick(button, {});
    await flush();

    assert.equal(updates.children.length, 1);
    assert.equal(
      updates.children[0].textContent,
      'All dependencies are up to date.',
    );
    assert.equal(label.textContent, 'Check for Updates');
    assert.equal(button.hasAttribute('disabled'), false);
  });

  it('posts the direct flag from the checkbox', async () => {
    const { panel } = setupPanel({ direct: true });
    const { button } = checkButton(panel);
    fetchStub = stubFetch(okPayload({ packages: {} }));
    handlePackagesClick(button, {});
    await flush();

    assert.match(fetchStub.calls[0].init.body, /direct=1/);
  });

  it('renders semver and breaking update blocks', async () => {
    const { panel, updates } = setupPanel({ direct: null });
    const { button } = checkButton(panel);
    fetchStub = stubFetch(
      okPayload({
        packages: {
          semverCompatible: 'cakephp/cakephp: 5.1.0',
          bcBreaks: 'phpunit/phpunit: 12.0.0',
        },
      }),
    );
    handlePackagesClick(button, {});
    await flush();

    assert.equal(updates.children.length, 2);
  });

  it('renders breaking updates only', async () => {
    const { panel, updates } = setupPanel();
    const { button } = checkButton(panel);
    fetchStub = stubFetch(
      okPayload({ packages: { bcBreaks: 'phpunit/phpunit: 12.0.0' } }),
    );
    handlePackagesClick(button, {});
    await flush();

    assert.equal(updates.children.length, 1);
  });

  it('reports failed checks without throwing', async () => {
    const { panel } = setupPanel();
    const { button } = checkButton(panel);
    fetchStub = stubFetch(() => Promise.resolve({ ok: false }));
    handlePackagesClick(button, {});
    await flush();

    assert.equal(
      panel
        .querySelector('[data-dk-packages-updates]')
        .children[0].textContent.includes('Could not check'),
      true,
    );
  });

  it('checks without a panel and stays silent', async () => {
    const button = new FakeElement('button', {
      'data-dk-packages-check': '',
      'data-dk-packages-check-url': '/debug-bar/packages/check',
    });
    fetchStub = stubFetch(okPayload({ packages: {} }));

    assert.equal(handlePackagesClick(button, {}), true);
    await flush();

    assert.equal(button.hasAttribute('disabled'), false);
  });
});

describe('packages vulnerability audit', () => {
  let fetchStub;
  let restoreDom;

  beforeEach(() => {
    restoreDom = stubDom();
    fetchStub = null;
  });

  afterEach(() => {
    fetchStub?.restore();
    restoreDom();
  });

  it('reports a clean audit', async () => {
    const { panel, auditResults } = setupPanel();
    const { button } = auditButton(panel);
    fetchStub = stubFetch(okPayload({ vulnerable: [] }));
    handlePackagesClick(button, {});
    await flush();

    assert.equal(
      auditResults.children[0].textContent,
      'No known vulnerabilities in locked dependencies.',
    );
  });

  it('reports audit errors from the payload', async () => {
    const { panel, auditResults } = setupPanel();
    const { button } = auditButton(panel);
    fetchStub = stubFetch(okPayload({ error: 'Audit failed.' }));
    handlePackagesClick(button, {});
    await flush();

    assert.equal(auditResults.children[0].textContent, 'Audit failed.');
  });

  it('flags vulnerable rows and offers the attention filter', async () => {
    const { panel, auditResults, filter } = setupPanel();
    const { row, version } = packageRow(panel, 'vendor/pkg');
    const { button } = auditButton(panel);
    fetchStub = stubFetch(
      okPayload({
        vulnerable: ['vendor/pkg'],
        advisories: {
          'vendor/pkg': [
            {
              title: 'RCE',
              cve: 'CVE-2026-1',
              link: 'https://example.test/rce',
              severity: 'critical',
              tone: 'bad',
            },
          ],
        },
      }),
    );
    handlePackagesClick(button, {});
    await flush();

    assert.equal(row.getAttribute('data-dk-filter-attention'), 'true');
    assert.equal(
      version.querySelector('[data-dk-audit-badge]').textContent,
      'VULN',
    );
    assert.equal(
      filter.children[filter.children.length - 1].textContent,
      'Vulnerable (1)',
    );
    assert.ok(auditResults.children.length > 0);
  });

  it('removes stale badges before auditing', async () => {
    const { panel, filter } = setupPanel({ filterValue: 'attention' });
    const stale = new FakeElement('option', { value: 'attention' });
    filter.appendChild(stale);
    const badge = new FakeElement('span', { 'data-dk-audit-badge': '' });
    panel.appendChild(badge);
    packageRow(panel, 'vendor/pkg');
    const { button } = auditButton(panel);
    fetchStub = stubFetch(okPayload({ vulnerable: [] }));
    handlePackagesClick(button, {});
    await flush();

    assert.equal(filter.value, 'all');
    assert.equal(panel.getAttribute('data-dk-active-filter'), 'all');
    assert.equal(stale.parent, null);
    assert.equal(badge.parent, null);
  });

  it('audits without badges, rows, or filter select', async () => {
    const panel = new FakeElement('div', {
      'data-dk-packages-panel': '',
    });
    const { button } = auditButton(panel);
    fetchStub = stubFetch(okPayload({ vulnerable: [] }));

    assert.equal(handlePackagesClick(button, {}), true);
    await flush();

    assert.equal(button.hasAttribute('disabled'), false);
  });

  it('reports failed audits without throwing', async () => {
    const { panel, auditResults } = setupPanel();
    const { button } = auditButton(panel);
    fetchStub = stubFetch(() => {
      auditResults.appendChild(new FakeElement('p'));

      return Promise.resolve({ ok: false });
    });
    handlePackagesClick(button, {});
    await flush();

    assert.match(auditResults.children[0].textContent, /Could not audit/);
  });

  it('reports offline audits without throwing', async () => {
    const { panel, auditResults } = setupPanel();
    const { button } = auditButton(panel);
    fetchStub = stubFetch(() => Promise.reject(new Error('offline')));
    handlePackagesClick(button, {});
    await flush();

    assert.match(auditResults.children[0].textContent, /Could not audit/);
  });

  it('audits without a panel and stays silent', async () => {
    const button = new FakeElement('button', {
      'data-dk-packages-audit': '',
      'data-dk-packages-audit-url': '/debug-bar/packages/audit',
    });
    fetchStub = stubFetch(okPayload({ vulnerable: [] }));

    assert.equal(handlePackagesClick(button, {}), true);
    await flush();

    assert.equal(button.hasAttribute('disabled'), false);
  });
});
