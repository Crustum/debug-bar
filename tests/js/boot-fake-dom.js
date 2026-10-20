// Minimal fake DOM to execute the real Requests/view boot script.
export class FakeNode {}

export class FakeElement extends FakeNode {
  constructor(tag = 'div', attrs = {}, children = []) {
    super();
    this.tagName = String(tag).toUpperCase();
    this.attrs = { ...attrs };
    this.children = [];
    this.parent = null;
    this.listeners = {};
    this.classList = new FakeClassList(
      (attrs.class || '').split(/\s+/).filter(Boolean),
    );
    this.style = {
      props: {},
      setProperty: (k, v) => {
        this.style.props[k] = v;
      },
      removeProperty: (k) => {
        delete this.style.props[k];
      },
    };
    this.textContent = '';
    this.hidden = false;
    this.isConnected = true;
    for (const child of children) this.appendChild(child);
  }
  getAttribute(name) {
    return name in this.attrs ? this.attrs[name] : null;
  }
  setAttribute(name, value) {
    this.attrs[name] = String(value);
    if (name === 'hidden') this.hidden = true;
  }
  removeAttribute(name) {
    delete this.attrs[name];
    if (name === 'hidden') this.hidden = false;
  }
  hasAttribute(name) {
    return name in this.attrs;
  }
  getAttributeNames() {
    return Object.keys(this.attrs);
  }
  appendChild(child) {
    if (
      child &&
      child.parent &&
      typeof child.parent.removeChild === 'function'
    ) {
      child.parent.removeChild(child);
    }
    child.parent = this;
    this.children.push(child);

    return child;
  }
  removeChild(child) {
    const index = this.children.indexOf(child);
    if (index !== -1) {
      this.children.splice(index, 1);
      child.parent = null;
    }

    return child;
  }
  insertBefore(child, ref) {
    if (ref === null || ref === undefined) {
      return this.appendChild(child);
    }
    const index = this.children.indexOf(ref);
    if (index === -1) {
      return this.appendChild(child);
    }
    child.parent = this;
    this.children.splice(index, 0, child);

    return child;
  }
  remove() {
    if (this.parent && typeof this.parent.removeChild === 'function') {
      this.parent.removeChild(this);
    }
  }
  get firstChild() {
    return this.children.length > 0 ? this.children[0] : null;
  }
  addEventListener(type, fn) {
    (this.listeners[type] ??= []).push(fn);
  }
  dispatch(type, target) {
    for (const fn of this.listeners[type] ?? []) {
      fn({
        target: target ?? this,
        preventDefault: () => {},
        stopPropagation: () => {},
      });
    }
  }
  matchesSimple(sel) {
    const parsed = parseSimpleSelector(sel.trim());
    if (!parsed) return false;
    if (parsed.tag && this.tagName !== parsed.tag) return false;
    if (parsed.name === null) return true;
    const actual = this.getAttribute(parsed.name);
    if (parsed.value === null) return actual !== null;

    return actual === parsed.value;
  }
  matchesSelectorList(list) {
    return String(list)
      .split(',')
      .some((sel) => this.matchesSimple(sel));
  }
  closest(selector) {
    let node = this;
    while (node) {
      if (
        typeof node.matchesSelectorList === 'function' &&
        node.matchesSelectorList(selector)
      ) {
        return node;
      }
      node = node.parent;
    }

    return null;
  }
  querySelectorAll(selector) {
    const out = [];
    const walk = (node) => {
      for (const child of node.children) {
        if (child.matchesSelectorList(selector)) out.push(child);
        walk(child);
      }
    };
    walk(this);

    return out;
  }
  querySelector(selector) {
    return this.querySelectorAll(selector)[0] ?? null;
  }
  contains(node) {
    let current = node;
    while (current) {
      if (current === this) return true;
      current = current.parent;
    }

    return false;
  }
  click() {
    let node = this;
    while (node) {
      node.dispatch('click', this);
      node = node.parent;
    }
  }
  focus() {}
}

export class FakeClassList {
  constructor(initial = []) {
    this.set = new Set(initial);
  }
  add(...names) {
    for (const n of names) this.set.add(n);
  }
  remove(...names) {
    for (const n of names) this.set.delete(n);
  }
  contains(name) {
    return this.set.has(name);
  }
  toggle(name, force) {
    if (force === true) this.set.add(name);
    else if (force === false) this.set.delete(name);
    else if (this.set.has(name)) this.set.delete(name);
    else this.set.add(name);

    return this.set.has(name);
  }
}

function parseSimpleSelector(selector) {
  const m = selector
    .trim()
    .match(/^([a-zA-Z][\w-]*)?\[([^\]=]+)(?:="([^"]*)")?\]$/);
  if (m) {
    return {
      tag: m[1] ? m[1].toUpperCase() : null,
      name: m[2],
      value: m[3] ?? null,
    };
  }
  const tag = selector.trim().match(/^([a-zA-Z][\w-]*)$/);
  if (tag) {
    return { tag: tag[1].toUpperCase(), name: null, value: null };
  }

  return null;
}
