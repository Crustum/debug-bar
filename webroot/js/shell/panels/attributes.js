import { readCsrfToken } from '../../csrf.js';

export function handleAttributesClick(target, ctx) {
  if (
    !target.hasAttribute('data-dk-attributes-warm') &&
    !target.hasAttribute('data-dk-attributes-inspect')
  ) {
    return false;
  }
  const attributesPanel = target.closest
    ? target.closest('[data-dk-attributes-panel]')
    : null;
  if (!attributesPanel) {
    return false;
  }
  const scopeSelect = attributesPanel.querySelector(
    '[data-dk-attributes-warm-config]',
  );
  const scope = scopeSelect ? scopeSelect.value || 'default' : 'default';
  const csrf = readCsrfToken(target) ?? '';

  if (target.hasAttribute('data-dk-attributes-warm')) {
    const warmBox = attributesPanel.querySelector(
      '[data-dk-attributes-warm-results]',
    );
    const warmLabel = target.querySelector('[data-dk-attributes-warm-label]');
    const renderWarmText = function (text, tone) {
      if (!warmBox) {
        return;
      }
      while (warmBox.firstChild) {
        warmBox.removeChild(warmBox.firstChild);
      }
      const note = document.createElement('p');
      note.setAttribute(
        'class',
        'dk:mt-3 dk:text-xs dk:leading-5 dk:font-semibold ' + tone,
      );
      note.textContent = text;
      warmBox.appendChild(note);
    };
    target.setAttribute('disabled', '');
    if (warmLabel) {
      warmLabel.textContent = 'Warming…';
    }
    if (warmBox) {
      while (warmBox.firstChild) {
        warmBox.removeChild(warmBox.firstChild);
      }
    }
    fetch(target.getAttribute('data-dk-attributes-warm-url') || '', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': csrf,
      },
      body: 'config=' + encodeURIComponent(scope),
    })
      .then(function (response) {
        if (!response || response.ok !== true) {
          throw new Error('attributes-warm');
        }

        return response.json();
      })
      .then(function (payload) {
        if (!payload || payload.error) {
          renderWarmText(
            (payload && payload.error
              ? String(payload.error)
              : 'Could not warm attribute cache.') +
              ' (scope `' +
              scope +
              '`)',
            'dk:text-red-700 dk:dark:text-red-300',
          );

          return;
        }
        const count = typeof payload.count === 'number' ? payload.count : 0;
        const elapsed =
          typeof payload.elapsed === 'number' ? payload.elapsed : 0;
        renderWarmText(
          'Warmed ' +
            count +
            ' ' +
            (count === 1 ? 'attribute' : 'attributes') +
            ' in ' +
            elapsed +
            's (scope `' +
            (payload.config || scope) +
            '`).',
          'dk:text-emerald-700 dk:dark:text-emerald-300',
        );
      })
      .catch(function () {
        renderWarmText(
          'Could not warm attribute cache (request failed — scope `' +
            scope +
            '`).',
          'dk:text-red-700 dk:dark:text-red-300',
        );
      })
      .finally(function () {
        target.removeAttribute('disabled');
        if (warmLabel) {
          warmLabel.textContent = 'Warm cache';
        }
      });

    return true;
  }

  const inspectBox = attributesPanel.querySelector(
    '[data-dk-attributes-inspect-results]',
  );
  const inspectLabel = target.querySelector(
    '[data-dk-attributes-inspect-label]',
  );
  const attributeInput = attributesPanel.querySelector(
    '[data-dk-attributes-inspect-attribute]',
  );
  const classInput = attributesPanel.querySelector(
    '[data-dk-attributes-inspect-class]',
  );
  const attributeValue =
    attributeInput && typeof attributeInput.value === 'string'
      ? attributeInput.value.trim()
      : '';
  const classValue =
    classInput && typeof classInput.value === 'string'
      ? classInput.value.trim()
      : '';
  const renderInspectText = function (text, tone) {
    if (!inspectBox) {
      return;
    }
    while (inspectBox.firstChild) {
      inspectBox.removeChild(inspectBox.firstChild);
    }
    const note = document.createElement('p');
    note.setAttribute(
      'class',
      'dk:mt-3 dk:text-xs dk:leading-5 dk:font-semibold ' + tone,
    );
    note.textContent = text;
    inspectBox.appendChild(note);
  };
  target.setAttribute('disabled', '');
  if (inspectLabel) {
    inspectLabel.textContent = 'Inspecting…';
  }
  if (inspectBox) {
    while (inspectBox.firstChild) {
      inspectBox.removeChild(inspectBox.firstChild);
    }
  }
  fetch(target.getAttribute('data-dk-attributes-inspect-url') || '', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': csrf,
    },
    body:
      'config=' +
      encodeURIComponent(scope) +
      '&attribute=' +
      encodeURIComponent(attributeValue) +
      '&class=' +
      encodeURIComponent(classValue),
  })
    .then(function (response) {
      if (!response || response.ok !== true) {
        throw new Error('attributes-inspect');
      }

      return response.json();
    })
    .then(function (payload) {
      if (!payload || payload.error) {
        renderInspectText(
          payload && payload.error
            ? String(payload.error)
            : 'Could not inspect attributes.',
          'dk:text-red-700 dk:dark:text-red-300',
        );

        return;
      }
      const items = (payload && payload.items) || [];
      if (!Array.isArray(items) || items.length === 0) {
        renderInspectText(
          'No attributes found matching the criteria.',
          'dk:text-zinc-600 dk:dark:text-zinc-300',
        );

        return;
      }
      renderInspectText(
        'Found ' +
          items.length +
          ' ' +
          (items.length === 1 ? 'attribute' : 'attributes') +
          (payload.truncated ? ' (truncated)' : '') +
          ' in scope `' +
          (payload.config || scope) +
          '`:',
        'dk:text-emerald-700 dk:dark:text-emerald-300',
      );
      items.forEach(function (item) {
        renderInspectSection(inspectBox, item || {});
      });
    })
    .catch(function () {
      renderInspectText(
        'Could not inspect attributes (request failed).',
        'dk:text-red-700 dk:dark:text-red-300',
      );
    })
    .finally(function () {
      target.removeAttribute('disabled');
      if (inspectLabel) {
        inspectLabel.textContent = 'Inspect';
      }
    });

  return true;
}

function renderInspectSection(inspectBox, item) {
  if (!inspectBox) {
    return;
  }
  const section = document.createElement('section');
  section.setAttribute('class', 'dk:mt-3');
  const heading = document.createElement('h4');
  heading.setAttribute('class', 'dk:break-all dk:text-xs dk:font-bold');
  heading.textContent =
    item.shortAttribute || item.attributeName || 'Attribute';
  section.appendChild(heading);
  const sub = document.createElement('p');
  sub.setAttribute(
    'class',
    'dk:mt-1 dk:break-words dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400',
  );
  sub.textContent = item.className || '';
  section.appendChild(sub);
  const facts = [
    ['Target class', item.className || '—'],
    ['Plugin', item.pluginName || '-'],
    ['Type', item.targetType || '—'],
    ['Target', item.targetName || '—'],
    ['Scope', item.config || '—'],
    [
      'File',
      (item.filePath || '') + (item.lineNumber ? ':' + item.lineNumber : '') ||
        '—',
    ],
    ['File time', item.fileTimeDisplay || '—'],
  ];
  const list = document.createElement('dl');
  list.setAttribute(
    'class',
    'dk:m-0 dk:mt-2 dk:grid dk:min-w-0 dk:gap-x-6 dk:gap-y-3 dk:p-0 dk:grid-cols-1',
  );
  facts.forEach(function (fact) {
    const label = fact[0];
    const value = fact[1];
    const wrapper = document.createElement('div');
    wrapper.setAttribute('class', 'dk:min-w-0');
    const term = document.createElement('dt');
    term.setAttribute(
      'class',
      'dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400',
    );
    term.textContent = label;
    wrapper.appendChild(term);
    const definition = document.createElement('dd');
    definition.setAttribute(
      'class',
      'dk:min-w-0 dk:break-words dk:text-xs dk:font-semibold dk:text-zinc-800 dk:dark:text-zinc-200',
    );
    definition.textContent = value;
    wrapper.appendChild(definition);
    list.appendChild(wrapper);
  });
  section.appendChild(list);
  const args = item.argumentsDisplay || {};
  Object.keys(args).forEach(function (name) {
    const row = document.createElement('p');
    row.setAttribute(
      'class',
      'dk:mt-1 dk:min-w-0 dk:break-words dk:font-mono dk:text-xs dk:text-zinc-800 dk:dark:text-zinc-200',
    );
    row.textContent = name + ': ' + args[name];
    section.appendChild(row);
  });
  inspectBox.appendChild(section);
}
