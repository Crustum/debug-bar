export function createPanelRegistry(context = {}) {
  const factories = new Map();
  const readCurrentProfileId = () => {
    const shell = context.shell ?? null;
    if (shell && shell.currentProfileId !== undefined)
      return shell.currentProfileId;
    const summary = context.summary ?? null;
    if (summary && summary.id !== undefined) return summary.id;

    return null;
  };

  return {
    get size() {
      return factories.size;
    },
    register(type, factory) {
      if (typeof type !== 'string' || type === '') {
        throw new Error('Panel type must be a non-empty string.');
      }
      if (typeof factory !== 'function') {
        throw new Error(`Panel factory for "${type}" must be a function.`);
      }
      factories.set(type, factory);

      return this;
    },
    unregister(type) {
      return factories.delete(type);
    },
    has(type) {
      return factories.has(type);
    },
    types() {
      return [...factories.keys()];
    },
    create(type, panelOptions = {}) {
      const factory = factories.get(type);
      if (typeof factory !== 'function')
        return createPassthroughPanel(type, panelOptions);

      const options = { ...panelOptions };
      if (options.profileId === undefined)
        options.profileId = readCurrentProfileId();
      const controller =
        factory({ ...context, type, profileId: options.profileId }) ?? {};

      return createGatedPanel(type, controller, {
        ...options,
        currentProfileId: readCurrentProfileId,
      });
    },
  };
}

export function createGatedPanel(type, controller = {}, hooks = {}) {
  const readCurrent =
    typeof hooks.currentProfileId === 'function'
      ? hooks.currentProfileId
      : () => null;

  const panel = {
    type,
    profileId: hooks.profileId ?? null,
    visible: false,
    initialized: false,
    destroyed: false,
    isActive() {
      return (
        this.destroyed === false &&
        this.visible === true &&
        this.profileId === readCurrent()
      );
    },
    init() {
      if (this.destroyed || this.initialized) return this;
      this.initialized = true;
      if (this.isActive() && typeof controller.init === 'function')
        controller.init.call(panel);

      return this;
    },
    setVisible(visible) {
      this.visible = Boolean(visible);
      if (
        this.visible &&
        this.initialized &&
        this.isActive() &&
        typeof controller.refresh === 'function'
      ) {
        controller.refresh.call(panel);
      }

      return this.visible;
    },
    refresh() {
      if (this.isActive() === false) return false;
      if (typeof controller.refresh === 'function')
        controller.refresh.call(panel);

      return true;
    },
    destroy() {
      if (this.destroyed) return;
      this.destroyed = true;
      if (typeof controller.destroy === 'function')
        controller.destroy.call(panel);
    },
  };

  return panel;
}

export function createPassthroughPanel(type, panelOptions = {}) {
  const html = typeof panelOptions.html === 'string' ? panelOptions.html : '';

  const panel = {
    type,
    unknown: true,
    html,
    profileId: panelOptions.profileId ?? null,
    visible: false,
    initialized: false,
    destroyed: false,
    init(target) {
      this.initialized = true;

      return this.mount(
        target === undefined ? (panelOptions.mount ?? null) : target,
      );
    },
    mount(target) {
      const resolved =
        target === undefined ? (panelOptions.mount ?? null) : target;
      if (typeof resolved === 'function') {
        resolved(html);

        return html;
      }
      if (resolved && typeof resolved.innerHTML === 'string') {
        resolved.innerHTML = html;

        return html;
      }

      return html;
    },
    refresh() {
      return html;
    },
    destroy() {
      this.destroyed = true;
    },
  };

  return panel;
}
