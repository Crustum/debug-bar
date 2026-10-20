let elem = document.getElementById('__debug_bar_script');
if (elem && window.self === window.top) {
  window.debugKitId = elem.getAttribute('data-id');
  window.debugKitBaseUrl = elem.getAttribute('data-url');
  window.debugBarMiniPosition = elem.getAttribute('data-mini-position');
  elem = null;
}

((win, doc) => {
  let iframe;
  let bodyOverflow;

  const MINI_POSITIONS = [
    'bottom-right',
    'bottom-left',
    'top-right',
    'top-left',
    'middle-right',
    'middle-left',
  ];

  const MINI_POSITION_DEFAULT = 'bottom-right';

  const MINI_STORAGE_KEY = 'debugbar.mini.position.v1';

  const sanitizeMiniPosition = (position) => {
    if (typeof position === 'string' && MINI_POSITIONS.includes(position)) {
      return position;
    }

    return MINI_POSITION_DEFAULT;
  };

  const readServerMiniPosition = () =>
    sanitizeMiniPosition(win.debugBarMiniPosition);

  const readPinnedMini = () => {
    let raw = null;
    try {
      raw = win.localStorage?.getItem?.(MINI_STORAGE_KEY) ?? null;
    } catch {
      return null;
    }
    if (typeof raw !== 'string' || raw === '') {
      return null;
    }
    try {
      const parsed = JSON.parse(raw);
      if (
        parsed !== null &&
        typeof parsed === 'object' &&
        typeof parsed.pos === 'string' &&
        MINI_POSITIONS.includes(parsed.pos)
      ) {
        return {
          pos: parsed.pos,
          srv: typeof parsed.srv === 'string' ? parsed.srv : null,
        };
      }
    } catch {
      // Legacy plain-string pins fall through to the server default below.
    }

    return null;
  };

  const persistMiniPin = (position, server) => {
    try {
      win.localStorage?.setItem?.(
        MINI_STORAGE_KEY,
        JSON.stringify({ pos: position, srv: server }),
      );

      return true;
    } catch {
      return false;
    }
  };

  const clearMiniPin = () => {
    try {
      if (typeof win.localStorage?.removeItem === 'function') {
        win.localStorage.removeItem(MINI_STORAGE_KEY);
      } else {
        win.localStorage?.setItem?.(MINI_STORAGE_KEY, '');
      }
    } catch {}
  };

  const resolveMiniPosition = () => {
    const server = readServerMiniPosition();
    const pinned = readPinnedMini();
    if (pinned === null) {
      return server;
    }
    if (pinned.srv !== server) {
      clearMiniPin();

      return server;
    }

    return pinned.pos;
  };

  const applyMiniPosition = (position) => {
    const next = sanitizeMiniPosition(position);
    iframe.style.top = '';
    iframe.style.bottom = '';
    iframe.style.left = '';
    iframe.style.right = '';
    iframe.style.transform = '';
    if (next === 'bottom-right') {
      iframe.style.bottom = '0';
      iframe.style.right = '0';
    } else if (next === 'bottom-left') {
      iframe.style.bottom = '0';
      iframe.style.left = '0';
    } else if (next === 'top-right') {
      iframe.style.top = '0';
      iframe.style.right = '0';
    } else if (next === 'top-left') {
      iframe.style.top = '0';
      iframe.style.left = '0';
    } else if (next === 'middle-right') {
      iframe.style.top = '50%';
      iframe.style.right = '0';
      iframe.style.transform = 'translateY(-50%)';
    } else {
      iframe.style.top = '50%';
      iframe.style.left = '0';
      iframe.style.transform = 'translateY(-50%)';
    }
  };

  const dockFrameBottom = () => {
    iframe.style.top = '';
    iframe.style.bottom = '0';
    iframe.style.left = '';
    iframe.style.right = '0';
    iframe.style.transform = '';
  };

  const onHostKeydown = (event) => {
    try {
      if (!event || event.defaultPrevented === true) return;
    } catch {
      return;
    }
    if (event.shiftKey === true) return;
    if (event.metaKey !== true && event.ctrlKey !== true) return;
    if (event.altKey !== true) return;
    const key = typeof event.key === 'string' ? event.key.toLowerCase() : '';
    if (event.code !== 'KeyP' && key !== 'p') return;
    const target = event.target;
    if (
      target !== null &&
      target !== undefined &&
      typeof target.closest === 'function'
    ) {
      try {
        if (
          target.closest(
            'input, textarea, select, [contenteditable="true"]',
          ) !== null
        ) {
          return;
        }
      } catch {}
    }
    if (!iframe || !iframe.contentWindow) return;
    try {
      event.preventDefault?.();
    } catch {}
    try {
      iframe.contentWindow.postMessage('open-palette', win.location.origin);
    } catch {}
  };

  const onMessage = (event) => {
    if (event.origin !== win.location.origin) {
      return;
    }
    if (!iframe || event.source !== iframe.contentWindow) {
      return;
    }
    if (
      typeof event.data === 'string' &&
      event.data.startsWith('mini-position$$')
    ) {
      const token = event.data.slice('mini-position$$'.length);
      if (token === 'server-default') {
        clearMiniPin();
      } else {
        persistMiniPin(sanitizeMiniPosition(token), readServerMiniPosition());
      }
      const width = Number(iframe.width);
      const height = Number(iframe.height);
      if (width === 40 && height === 40) {
        applyMiniPosition(resolveMiniPosition());
      }

      return;
    }
    if (event.data === 'collapse') {
      setFrame(40, 40);
      applyMiniPosition(resolveMiniPosition());
      doc.body.style.overflow = bodyOverflow;
      return;
    }
    if (event.data === 'toolbar') {
      dockFrameBottom();
      setFrame('100%', 40);
      doc.body.style.overflow = bodyOverflow;
      return;
    }
    if (event.data === 'picker') {
      dockFrameBottom();
      setFrame('100%', 320);
      return;
    }
    if (event.data === 'expand') {
      dockFrameBottom();
      setFrame('100%', '100%');
      doc.body.style.overflow = 'hidden';
    }
    if (event.data === 'error') {
      dockFrameBottom();
      setFrame('40%', '40%');
      doc.body.style.overflow = bodyOverflow;
    }
  };

  const setFrame = (width, height) => {
    iframe.width = width;
    iframe.height = height;
    iframe.style.width = typeof width === 'number' ? `${width}px` : width;
    iframe.style.height = typeof height === 'number' ? `${height}px` : height;
  };

  const onReady = () => {
    if (!win.debugKitId) {
      return;
    }
    const { body } = doc;
    iframe = doc.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.border = 0;
    iframe.style.outline = 0;
    iframe.style.overflow = 'hidden';
    iframe.style.zIndex = 99999;
    iframe.width = 40;
    iframe.height = 40;
    iframe.style.width = '40px';
    iframe.style.height = '40px';
    applyMiniPosition(resolveMiniPosition());
    iframe.src = `${window.debugKitBaseUrl}debug-bar/toolbar/${window.debugKitId}`;

    body.appendChild(iframe);
    bodyOverflow = body.style.overflow;

    window.addEventListener('message', onMessage, false);
  };

  const logAjaxRequest = (original) =>
    function ajaxRequest() {
      if (this.readyState === 4 && this.getResponseHeader('X-DEBUGBAR-ID')) {
        const params = {
          requestId: this.getResponseHeader('X-DEBUGBAR-ID'),
          status: this.status,
          date: new Date(),
          method: this._arguments && this._arguments[0],
          url: this._arguments && this._arguments[1],
          type: this.getResponseHeader('Content-Type'),
        };
        iframe?.contentWindow?.postMessage(
          `ajax-completed$$${JSON.stringify(params)}`,
          window.location.origin,
        );
      }
      if (original) {
        return original.apply(this, [].slice.call(arguments));
      }
      return false;
    };

  const proxyAjaxOpen = () => {
    const proxied = window.XMLHttpRequest.prototype.open;
    window.XMLHttpRequest.prototype.open = function ajaxCall(...args) {
      this._arguments = args;
      return proxied.apply(this, [].slice.call(args));
    };
  };

  const proxyAjaxSend = () => {
    const proxied = window.XMLHttpRequest.prototype.send;
    window.XMLHttpRequest.prototype.send = function ajaxCall(...args) {
      this.onreadystatechange = logAjaxRequest(this.onreadystatechange);
      return proxied.apply(this, [].slice.call(args));
    };
  };

  const proxyFetch = () => {
    if (!window.fetch) return;
    const proxied = window.fetch;

    window.fetch = async function (...args) {
      const [input, init = {}] = args;
      const method = (init.method || 'GET').toUpperCase();
      const url = typeof input === 'string' ? input : input.url;

      let response;
      try {
        response = await proxied.apply(this, args);

        const debugId = response.headers.get('X-DEBUGBAR-ID');
        if (debugId) {
          const params = {
            requestId: debugId,
            status: response.status,
            date: new Date(),
            method,
            url,
            type: response.headers.get('Content-Type'),
          };
          iframe?.contentWindow?.postMessage(
            `ajax-completed$$${JSON.stringify(params)}`,
            window.location.origin,
          );
        }
        return response;
      } catch (error) {
        const params = {
          requestId: null,
          status: 0,
          date: new Date(),
          method,
          url,
          type: null,
          error: error.message,
        };
        iframe.contentWindow?.postMessage(
          `ajax-completed$$${JSON.stringify(params)}`,
          window.location.origin,
        );
        throw error;
      }
    };
  };

  if (doc.addEventListener) {
    doc.addEventListener('keydown', onHostKeydown, false);
    if (!win.debugKitListenersApplied) {
      const boot = () => {
        onReady();
        proxyAjaxOpen();
        proxyAjaxSend();
        proxyFetch();
      };
      if (doc.readyState === 'loading') {
        const loadedEvent =
          typeof Turbo !== 'undefined' && Turbo !== null
            ? 'turbo:load'
            : 'DOMContentLoaded';
        doc.addEventListener(loadedEvent, boot, false);
      } else {
        boot();
      }
      win.debugKitListenersApplied = true;
    }
  } else {
    throw new Error(
      'Unable to add event listener for Crustum/DebugBar. Please use a browser' +
        ' that supports addEventListener().',
    );
  }
})(window, document);
