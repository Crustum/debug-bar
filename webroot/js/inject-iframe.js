let elem = document.getElementById('__debug_bar_script');
if (elem && window.self === window.top) {
  window.debugKitId = elem.getAttribute('data-id');
  window.debugKitBaseUrl = elem.getAttribute('data-url');
  elem = null;
}

((win, doc) => {
  let iframe;
  let bodyOverflow;

  const onMessage = (event) => {
    if (event.origin !== win.location.origin) {
      return;
    }
    if (!iframe || event.source !== iframe.contentWindow) {
      return;
    }
    if (event.data === 'collapse') {
      setFrame(40, 40);
      doc.body.style.overflow = bodyOverflow;
      return;
    }
    if (event.data === 'toolbar') {
      setFrame('100%', 40);
      doc.body.style.overflow = bodyOverflow;
      return;
    }
    if (event.data === 'picker') {
      setFrame('100%', 320);
      return;
    }
    if (event.data === 'expand') {
      setFrame('100%', '100%');
      doc.body.style.overflow = 'hidden';
    }
    if (event.data === 'error') {
      setFrame('40%', '40%');
      doc.body.style.overflow = bodyOverflow;
    }
  };

  const setFrame = (width, height) => {
    if (!iframe) return;
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
    iframe.style.bottom = 0;
    iframe.style.right = 0;
    iframe.style.border = 0;
    iframe.style.outline = 0;
    iframe.style.overflow = 'hidden';
    iframe.style.zIndex = 99999;
    iframe.width = 40;
    iframe.height = 40;
    iframe.style.width = '40px';
    iframe.style.height = '40px';
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
        iframe.contentWindow?.postMessage(
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
          iframe.contentWindow?.postMessage(
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
