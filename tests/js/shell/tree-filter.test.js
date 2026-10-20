import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
  applyTreeFilter,
  expandTreeGroups,
  treeScopeFor,
} from '../../../webroot/js/shell/boot/core.js';
import {
  handleListInput,
  handleTreeClick,
} from '../../../webroot/js/shell/boot/bridges.js';
import { FakeElement } from '../boot-fake-dom.js';

globalThis.Element ??= FakeElement;

function treeRow(text) {
  const row = new FakeElement('li', { 'data-dk-tree-row': '' });
  row.textContent = text;

  return row;
}

function treeSection(detailAttr, needle) {
  const section = new FakeElement('section', { [detailAttr]: 'item-1' });
  const input = new FakeElement('input', { 'data-dk-tree-search': '' });
  input.value = needle;
  section.appendChild(input);
  const tree = new FakeElement('ul', { 'data-dk-debug-tree': '' });
  const group = new FakeElement('details', { 'data-dk-tree-group': '' });
  const match = treeRow('title hello world');
  const miss = treeRow('count 9');
  tree.appendChild(group);
  tree.appendChild(match);
  tree.appendChild(miss);
  section.appendChild(tree);

  return { section, input, tree, group, match, miss };
}

describe('generic debug tree filter', () => {
  it('scopes to the closest variable or view detail', () => {
    const variable = treeSection('data-dk-variable-detail', '').section;
    const view = treeSection('data-dk-view-detail', '').section;
    const loose = new FakeElement('input', { 'data-dk-tree-search': '' });

    assert.equal(treeScopeFor(variable.children[0]), variable);
    assert.equal(treeScopeFor(view.children[0]), view);
    assert.equal(treeScopeFor(loose), null);
    assert.equal(treeScopeFor(null), null);
    assert.equal(treeScopeFor({}), null);
  });

  it('scopes custom trees through a tree-scope wrapper', () => {
    const scope = new FakeElement('div', { 'data-dk-tree-scope': '' });
    const built = treeSection('data-dk-acme-detail', 'hello');
    scope.appendChild(built.section);
    const root = new FakeElement('div');
    root.appendChild(scope);

    assert.equal(treeScopeFor(built.section.children[0]), scope);
    handleTreeClick(
      (() => {
        const expand = new FakeElement('button', {
          'data-dk-tree-expand': '',
        });
        scope.appendChild(expand);

        return expand;
      })(),
    );
    assert.equal(built.group.hasAttribute('open'), true);
    built.input.value = 'hello';
    handleListInput(
      { target: built.input, preventDefault: () => {} },
      { root, summary: {} },
    );
    assert.equal(built.miss.hasAttribute('hidden'), true);
  });

  it('ignores scopes without query support', () => {
    assert.doesNotThrow(() => applyTreeFilter(null));
    assert.doesNotThrow(() => applyTreeFilter({}));
    assert.doesNotThrow(() => expandTreeGroups(null));
    assert.doesNotThrow(() => expandTreeGroups({}));
  });

  it('shows only matching rows and opens groups', () => {
    const { section, match, miss, group } = treeSection(
      'data-dk-variable-detail',
      'hello',
    );
    applyTreeFilter(section);

    assert.equal(match.hasAttribute('hidden'), false);
    assert.equal(miss.hasAttribute('hidden'), true);
    assert.equal(group.hasAttribute('open'), true);
  });

  it('restores every row on empty search', () => {
    const { section, input, match, miss } = treeSection(
      'data-dk-view-detail',
      'hello',
    );
    applyTreeFilter(section);
    assert.equal(miss.hasAttribute('hidden'), true);
    input.value = '';
    applyTreeFilter(section);

    assert.equal(match.hasAttribute('hidden'), false);
    assert.equal(miss.hasAttribute('hidden'), false);
  });

  it('matches keys and values case-insensitively', () => {
    const { section, match, miss } = treeSection(
      'data-dk-variable-detail',
      'TITLE',
    );
    applyTreeFilter(section);

    assert.equal(match.hasAttribute('hidden'), false);
    assert.equal(miss.hasAttribute('hidden'), true);
  });

  it('expands every group without filtering', () => {
    const { section, group, miss } = treeSection('data-dk-view-detail', '');
    expandTreeGroups(section);

    assert.equal(group.hasAttribute('open'), true);
    assert.equal(miss.hasAttribute('hidden'), false);
  });

  it('handles the expand action button', () => {
    const { section, group } = treeSection('data-dk-view-detail', '');
    const button = new FakeElement('button', { 'data-dk-tree-expand': '' });
    section.appendChild(button);

    assert.equal(handleTreeClick(button), true);
    assert.equal(group.hasAttribute('open'), true);
    assert.equal(handleTreeClick(new FakeElement('button')), false);
  });

  it('routes tree search input away from list filtering', () => {
    const root = new FakeElement('div', { 'data-dk-views-panel': '' });
    const { section, input, match, miss } = treeSection(
      'data-dk-view-detail',
      'hello',
    );
    const listRow = new FakeElement('button', {
      'data-dk-search-text': 'unrelated view',
    });
    root.appendChild(listRow);
    root.appendChild(section);
    handleListInput({ target: input }, { root });

    assert.equal(match.hasAttribute('hidden'), false);
    assert.equal(miss.hasAttribute('hidden'), true);
    assert.equal(listRow.hasAttribute('hidden'), false);
  });
});
