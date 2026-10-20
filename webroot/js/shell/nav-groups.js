export const NAV_GROUPS = ['favorites', 'inspectors'];

export function navRowKey(row) {
  const item =
    row && typeof row.querySelector === 'function'
      ? row.querySelector('[data-dk-inspector-nav-item]')
      : null;

  return item && typeof item.getAttribute === 'function'
    ? item.getAttribute('data-dk-inspector-nav-item')
    : null;
}

export function collectNavRows(root) {
  const rows = {};
  const nodes =
    typeof root?.querySelectorAll === 'function'
      ? root.querySelectorAll('[data-dk-nav-row]')
      : [];
  [...nodes].forEach((row) => {
    const key = navRowKey(row);
    if (key) rows[key] = row;
  });

  return rows;
}

function navLists(root) {
  const byGroup = {};
  for (const group of NAV_GROUPS) {
    byGroup[group] =
      typeof root?.querySelector === 'function'
        ? (root.querySelector(`[data-dk-nav-list="${group}"]`) ?? null)
        : null;
  }

  return byGroup;
}

function shellEntries(shell, favorite) {
  try {
    if (shell && typeof shell.navigationInspectors === 'function') {
      return shell.navigationInspectors(favorite) ?? [];
    }
  } catch {}

  return [];
}

function isFavoriteKey(shell, key) {
  try {
    if (shell && typeof shell.isFavorite === 'function')
      return !!shell.isFavorite(key);
  } catch {}

  return false;
}

function rowLabel(row, fallback) {
  const item =
    typeof row?.querySelector === 'function'
      ? row.querySelector('[data-dk-inspector-nav-item]')
      : null;
  const label =
    item && typeof item.getAttribute === 'function'
      ? item.getAttribute('aria-label')
      : null;

  return label || fallback;
}

function syncStar(row, key, favorite) {
  const star =
    typeof row?.querySelector === 'function'
      ? row.querySelector('[data-dk-inspector-favorite]')
      : null;
  if (!star || typeof star.setAttribute !== 'function') return;

  const label = rowLabel(row, key);
  star.setAttribute('aria-pressed', favorite ? 'true' : 'false');
  star.setAttribute(
    'aria-label',
    (favorite ? 'Remove ' : 'Add ') +
      label +
      (favorite ? ' from favorites' : ' to favorites'),
  );
  star.setAttribute(
    'title',
    favorite ? 'Remove from favorites' : 'Add to favorites',
  );

  const outline =
    typeof star.querySelector === 'function'
      ? star.querySelector('.dk-nav-star-outline')
      : null;
  const filled =
    typeof star.querySelector === 'function'
      ? star.querySelector('.dk-nav-star-filled')
      : null;
  if (outline && typeof outline.setAttribute === 'function') {
    if (favorite) outline.setAttribute('hidden', '');
    else if (typeof outline.removeAttribute === 'function')
      outline.removeAttribute('hidden');
  }
  if (filled && typeof filled.setAttribute === 'function') {
    if (favorite && typeof filled.removeAttribute === 'function')
      filled.removeAttribute('hidden');
    else filled.setAttribute('hidden', '');
  }
}

export function renderNavGroups(root, shell = null) {
  const lists = navLists(root);
  if (!lists.favorites || !lists.inspectors)
    return { favorites: 0, inspectors: 0 };

  const rows = collectNavRows(root);
  const favorites = shellEntries(shell, true);
  const rest = shellEntries(shell, false);

  for (const entry of favorites) {
    const row = entry && rows[entry.key];
    if (row && typeof lists.favorites.appendChild === 'function')
      lists.favorites.appendChild(row);
  }
  for (const entry of rest) {
    const row = entry && rows[entry.key];
    if (row && typeof lists.inspectors.appendChild === 'function')
      lists.inspectors.appendChild(row);
  }

  const favGroup =
    typeof root?.querySelector === 'function'
      ? (root.querySelector('[data-dk-nav-group="favorites"]') ?? null)
      : null;
  if (favGroup && typeof favGroup.setAttribute === 'function') {
    if (
      favorites.length > 0 &&
      typeof favGroup.removeAttribute === 'function'
    ) {
      favGroup.removeAttribute('hidden');
    } else {
      favGroup.setAttribute('hidden', '');
    }
  }

  for (const key of Object.keys(rows)) {
    syncStar(rows[key], key, isFavoriteKey(shell, key));
  }

  return { favorites: favorites.length, inspectors: rest.length };
}

export function syncNavCounts(root, panels = [], createElement = null) {
  if (!Array.isArray(panels)) return 0;

  const create =
    typeof createElement === 'function'
      ? createElement
      : (tag) => globalThis.document?.createElement(tag);
  let updated = 0;
  for (const panel of panels) {
    if (!panel || !panel.key) continue;
    const row =
      typeof root?.querySelector === 'function'
        ? (root.querySelector(`[data-dk-nav-row="${panel.key}"]`) ?? null)
        : null;
    if (!row || typeof row.querySelector !== 'function') continue;

    const countEl = row.querySelector('[data-dk-inspector-count]');
    if (panel.count === null || panel.count === undefined) {
      if (countEl && typeof countEl.remove === 'function') {
        countEl.remove();
        updated += 1;
      }
      continue;
    }
    if (countEl && 'textContent' in countEl) {
      countEl.textContent = String(panel.count);
      updated += 1;
    } else {
      const side = row.querySelector('.dk-nav-side');
      if (
        !side ||
        typeof side.appendChild !== 'function' ||
        typeof create !== 'function'
      )
        continue;
      const badge = create('span');
      if (!badge || typeof badge.setAttribute !== 'function') continue;
      badge.setAttribute('data-dk-inspector-count', '');
      badge.setAttribute('class', 'dk-nav-count dk-tabular');
      badge.textContent = String(panel.count);
      side.appendChild(badge);
      updated += 1;
    }
  }

  return updated;
}
