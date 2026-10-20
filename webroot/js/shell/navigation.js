export const DEFAULT_INSPECTOR = 'request';

export function sanitizeInspectorKey(
  key,
  keys = null,
  fallback = DEFAULT_INSPECTOR,
) {
  if (typeof key !== 'string' || key === '') return fallback;
  if (Array.isArray(keys) && keys.length > 0 && !keys.includes(key))
    return fallback;

  return key;
}

export function sortInspectorsByOrder(all = [], order = []) {
  const list = Array.isArray(all) ? [...all] : [];
  const orderList = Array.isArray(order) ? order : [];
  const byKey = new Map(list.map((inspector) => [inspector?.key, inspector]));
  const ordered = orderList.map((key) => byKey.get(key)).filter(Boolean);
  const placed = new Set(ordered.map((inspector) => inspector.key));
  const rest = list
    .filter((inspector) => !placed.has(inspector?.key))
    .sort((left, right) =>
      String(left?.label ?? '').localeCompare(
        String(right?.label ?? ''),
        undefined,
        {
          sensitivity: 'base',
        },
      ),
    );

  return [...ordered, ...rest];
}

export function favoritesFirst(inspectors = [], favorites = []) {
  const list = Array.isArray(inspectors) ? inspectors : [];
  const favoriteKeys = Array.isArray(favorites) ? favorites : [];
  const byKey = new Map(list.map((inspector) => [inspector?.key, inspector]));
  const starred = favoriteKeys.map((key) => byKey.get(key)).filter(Boolean);
  const starredKeys = new Set(starred.map((inspector) => inspector.key));

  return [
    ...starred,
    ...list.filter((inspector) => !starredKeys.has(inspector?.key)),
  ];
}

export function createNavigationPart(context = {}) {
  const summary = context.summary ?? {};

  const readSummary = (shell) => shell?.summary ?? summary;
  const readInspectors = (shell) => readSummary(shell)?.inspectors ?? [];

  return {
    selected: DEFAULT_INSPECTOR,
    favorites: [],
    inspectorOrder: [],

    get inspectorKeys() {
      return readInspectors(this)
        .map((inspector) => inspector?.key)
        .filter((key) => typeof key === 'string' && key !== '');
    },

    get inspectorsInOrder() {
      return sortInspectorsByOrder(
        readInspectors(this),
        this.inspectorOrder ?? [],
      );
    },

    get orderedInspectors() {
      return favoritesFirst(this.inspectorsInOrder, this.favorites ?? []);
    },

    get selectedInspector() {
      return (
        readInspectors(this).find(
          (inspector) => inspector?.key === this.selected,
        ) ?? {
          key: DEFAULT_INSPECTOR,
          label: 'Request',
          description: '',
          count: null,
        }
      );
    },

    navigationInspectors(favorite) {
      return this.orderedInspectors.filter(
        (inspector) =>
          this.isFavorite(inspector?.key) === favorite &&
          this.isInspectorVisible(inspector),
      );
    },

    isFavorite(key) {
      return (this.favorites ?? []).includes(key);
    },

    isInspectorActive(inspector) {
      return inspector?.active !== false;
    },

    isInspectorVisible(inspector) {
      return (
        this.isInspectorActive(inspector) ||
        this.isFavorite(inspector?.key) ||
        inspector?.key === this.selected
      );
    },

    toggleFavorite(key) {
      const favorites = [...(this.favorites ?? [])];
      const index = favorites.indexOf(key);
      if (index === -1) favorites.push(key);
      else favorites.splice(index, 1);
      this.favorites = favorites;

      return favorites;
    },

    selectInspector(key, requestOptions = null) {
      const next = sanitizeInspectorKey(key, this.inspectorKeys);
      this.selected = next;
      const needsLoad =
        this.inspectorOpen === true &&
        (this.loadedInspector !== next || this.inspectorError === true);
      if (needsLoad && typeof this.requestInspector === 'function') {
        this.requestInspector(next, requestOptions ?? {});
      }

      return next;
    },

    navigateToInspector(key, requestOptions = null) {
      return this.selectInspector(key, requestOptions);
    },

    openRequestInspector(returnFocus = null) {
      if (typeof this.closeRequestPicker === 'function')
        this.closeRequestPicker(false);
      if (this.inspectorOpen === true) {
        this.selectInspector(DEFAULT_INSPECTOR);

        return this.selected;
      }
      if (typeof this.openInspector === 'function') {
        this.openInspector(DEFAULT_INSPECTOR, returnFocus);

        return this.selected ?? DEFAULT_INSPECTOR;
      }
      this.selected = DEFAULT_INSPECTOR;

      return this.selected;
    },
  };
}
