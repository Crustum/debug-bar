import { readCsrfToken } from '../../csrf.js';
import { applyListFilter } from '../boot/core.js';

export function handlePackagesClick(target, ctx) {
  if (target.hasAttribute('data-dk-packages-check')) {
    const packagesPanel = target.closest
      ? target.closest('[data-dk-packages-panel]')
      : null;
    const updatesBox = packagesPanel
      ? packagesPanel.querySelector('[data-dk-packages-updates]')
      : null;
    const directBox = packagesPanel
      ? packagesPanel.querySelector('[data-dk-packages-check-direct]')
      : null;
    const checkUrl = target.getAttribute('data-dk-packages-check-url') || '';
    const checkLabel = target.querySelector('[data-dk-packages-check-label]');
    const checkCsrf = readCsrfToken(target) ?? '';
    const renderUpdatesText = function (text, tone) {
      if (!updatesBox) {
        return;
      }
      while (updatesBox.firstChild) {
        updatesBox.removeChild(updatesBox.firstChild);
      }
      const note = document.createElement('p');
      note.setAttribute(
        'class',
        'dk:mt-3 dk:text-xs dk:leading-5 dk:font-semibold ' + tone,
      );
      note.textContent = text;
      updatesBox.appendChild(note);
    };
    const renderUpdatesBlock = function (title, text) {
      const section = document.createElement('section');
      section.setAttribute('class', 'dk:mt-3');
      const heading = document.createElement('h4');
      heading.setAttribute('class', 'dk:text-xs dk:font-bold');
      heading.textContent = title;
      const pre = document.createElement('pre');
      pre.setAttribute(
        'class',
        'dk-code dk-scrollbar dk:m-0 dk:mt-2 dk:min-w-0 dk:overflow-x-auto dk:rounded-lg dk:bg-zinc-100 dk:p-4 dk:font-mono dk:text-sm dk:leading-6 dk:text-zinc-700 dk:dark:bg-zinc-950 dk:dark:text-zinc-200 dk:whitespace-pre-wrap dk:break-words',
      );
      const code = document.createElement('code');
      code.textContent = text.replace(/<\/?highlight>/g, '');
      pre.appendChild(code);
      section.appendChild(heading);
      section.appendChild(pre);
      updatesBox.appendChild(section);
    };
    target.setAttribute('disabled', '');
    if (checkLabel) {
      checkLabel.textContent = 'Checking…';
    }
    if (updatesBox) {
      while (updatesBox.firstChild) {
        updatesBox.removeChild(updatesBox.firstChild);
      }
    }
    fetch(checkUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': checkCsrf,
      },
      body: 'direct=' + (directBox && directBox.checked ? '1' : '0'),
    })
      .then(function (response) {
        if (!response || response.ok !== true) {
          throw new Error('check-dependencies');
        }

        return response.json();
      })
      .then(function (payload) {
        const groups = (payload && payload.packages) || {};
        const semver =
          typeof groups.semverCompatible === 'string'
            ? groups.semverCompatible.trim()
            : '';
        const breaks =
          typeof groups.bcBreaks === 'string' ? groups.bcBreaks.trim() : '';
        if (semver === '' && breaks === '') {
          renderUpdatesText(
            'All dependencies are up to date.',
            'dk:text-emerald-700 dk:dark:text-emerald-300',
          );
        } else {
          if (semver !== '') {
            renderUpdatesBlock('Semver-compatible updates', semver);
          }
          if (breaks !== '') {
            renderUpdatesBlock('Possibly breaking updates', breaks);
          }
        }
      })
      .catch(function () {
        renderUpdatesText(
          'Could not check for updates (composer outdated failed — offline?).',
          'dk:text-red-700 dk:dark:text-red-300',
        );
      })
      .finally(function () {
        target.removeAttribute('disabled');
        if (checkLabel) {
          checkLabel.textContent = 'Check for Updates';
        }
      });

    return true;
  }
  if (!target.hasAttribute('data-dk-packages-audit')) {
    return false;
  }
  const auditPanel = target.closest
    ? target.closest('[data-dk-packages-panel]')
    : null;
  const auditBox = auditPanel
    ? auditPanel.querySelector('[data-dk-packages-audit-results]')
    : null;
  const auditUrl = target.getAttribute('data-dk-packages-audit-url') || '';
  const auditLabel = target.querySelector('[data-dk-packages-audit-label]');
  const auditCsrf = readCsrfToken(target) ?? '';
  const clearAuditBadges = function () {
    if (!auditPanel) {
      return;
    }
    auditPanel
      .querySelectorAll('[data-dk-audit-badge]')
      .forEach(function (badge) {
        badge.remove();
      });
    auditPanel
      .querySelectorAll('[data-dk-package-entry]')
      .forEach(function (row) {
        row.setAttribute('data-dk-filter-attention', 'false');
      });
    const auditFilter = auditPanel.querySelector(
      '[data-dk-select="packages-filter"]',
    );
    if (auditFilter) {
      auditFilter
        .querySelectorAll('option[value="attention"]')
        .forEach(function (option) {
          option.remove();
        });
      if (auditFilter.value === 'attention') {
        auditFilter.value = 'all';
        auditPanel.setAttribute('data-dk-active-filter', 'all');
        applyListFilter(auditPanel);
      }
    }
  };
  const renderAuditText = function (text, tone) {
    if (!auditBox) {
      return;
    }
    while (auditBox.firstChild) {
      auditBox.removeChild(auditBox.firstChild);
    }
    const note = document.createElement('p');
    note.setAttribute(
      'class',
      'dk:mt-3 dk:text-xs dk:leading-5 dk:font-semibold ' + tone,
    );
    note.textContent = text;
    auditBox.appendChild(note);
  };
  target.setAttribute('disabled', '');
  if (auditLabel) {
    auditLabel.textContent = 'Auditing…';
  }
  clearAuditBadges();
  if (auditBox) {
    while (auditBox.firstChild) {
      auditBox.removeChild(auditBox.firstChild);
    }
  }
  fetch(auditUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': auditCsrf,
    },
    body: '',
  })
    .then(function (response) {
      if (!response || response.ok !== true) {
        throw new Error('audit');
      }

      return response.json();
    })
    .then(function (payload) {
      if (payload && payload.error) {
        renderAuditText(
          String(payload.error),
          'dk:text-red-700 dk:dark:text-red-300',
        );

        return;
      }
      const vulnerable = (payload && payload.vulnerable) || [];
      const advisories = (payload && payload.advisories) || {};
      if (!Array.isArray(vulnerable) || vulnerable.length === 0) {
        renderAuditText(
          'No known vulnerabilities in locked dependencies.',
          'dk:text-emerald-700 dk:dark:text-emerald-300',
        );

        return;
      }
      vulnerable.forEach(function (name) {
        renderAuditSection(auditPanel, auditBox, advisories, name);
      });
      const filter = auditPanel
        ? auditPanel.querySelector('[data-dk-select="packages-filter"]')
        : null;
      if (filter) {
        const option = document.createElement('option');
        option.setAttribute('value', 'attention');
        option.textContent = 'Vulnerable (' + vulnerable.length + ')';
        filter.appendChild(option);
      }
    })
    .catch(function () {
      renderAuditText(
        'Could not audit dependencies (request failed — offline?).',
        'dk:text-red-700 dk:dark:text-red-300',
      );
    })
    .finally(function () {
      target.removeAttribute('disabled');
      if (auditLabel) {
        auditLabel.textContent = 'Audit vulnerabilities';
      }
    });

  return true;
}

function renderAuditSection(auditPanel, auditBox, advisories, name) {
  const section = document.createElement('section');
  section.setAttribute('class', 'dk:mt-3');
  const heading = document.createElement('h4');
  heading.setAttribute('class', 'dk:break-all dk:text-xs dk:font-bold');
  heading.textContent = name;
  section.appendChild(heading);
  const list = document.createElement('ul');
  list.setAttribute(
    'class',
    'dk:m-0 dk:mt-2 dk:min-w-0 dk:list-none dk:space-y-2 dk:p-0',
  );
  (advisories[name] || []).forEach(function (advisory) {
    const item = document.createElement('li');
    item.setAttribute(
      'class',
      'dk:rounded-lg dk:border dk:border-zinc-200 dk:p-3 dk:dark:border-zinc-800',
    );
    const title = document.createElement('p');
    title.setAttribute(
      'class',
      'dk:min-w-0 dk:break-words dk:text-xs dk:font-semibold',
    );
    title.textContent = advisory.title || advisory.cve || 'Advisory';
    item.appendChild(title);
    const meta = document.createElement('p');
    meta.setAttribute(
      'class',
      'dk:mt-1 dk:flex dk:flex-wrap dk:items-center dk:gap-x-2 dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400',
    );
    const tone = document.createElement('span');
    tone.setAttribute('class', 'dk-operation-badge');
    tone.setAttribute('data-dk-operation-badge', '');
    tone.setAttribute('data-dk-operation-tone', advisory.tone || 'neutral');
    tone.textContent = advisory.severity || 'unknown';
    meta.appendChild(tone);
    if (advisory.cve) {
      const cve = document.createElement('span');
      cve.setAttribute('class', 'dk:font-mono');
      cve.textContent = advisory.cve;
      meta.appendChild(cve);
    }
    if (advisory.link) {
      const link = document.createElement('a');
      link.setAttribute('href', advisory.link);
      link.setAttribute('target', '_blank');
      link.setAttribute('rel', 'noopener noreferrer');
      link.setAttribute(
        'class',
        'dk:font-semibold dk:text-indigo-600 dk:underline dk:underline-offset-2 dk:dark:text-indigo-300',
      );
      link.textContent = 'Details';
      meta.appendChild(link);
    }
    item.appendChild(meta);
    list.appendChild(item);
  });
  section.appendChild(list);
  auditBox.appendChild(section);
  const row = auditPanel
    ? auditPanel.querySelector('[data-dk-package-key="' + name + '"]')
    : null;
  const versionCell = row
    ? row.querySelector('[data-dk-package-version]')
    : null;
  if (row && versionCell) {
    row.setAttribute('data-dk-filter-attention', 'true');
    const badge = document.createElement('span');
    badge.setAttribute('class', 'dk-operation-badge');
    badge.setAttribute('data-dk-operation-badge', '');
    badge.setAttribute('data-dk-operation-tone', 'bad');
    badge.setAttribute('data-dk-audit-badge', '');
    badge.textContent = 'VULN';
    versionCell.insertBefore(badge, versionCell.firstChild);
  }
}
