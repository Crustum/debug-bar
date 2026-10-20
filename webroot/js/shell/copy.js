import { writeTextToClipboard } from '../runtime.js';

export const COPY_FEEDBACK_CLEAR_DELAY = 3000;
export const COPY_FEEDBACK_OK = 'Copied';
export const COPY_FEEDBACK_FAILED = 'Copy failed';

export function createCopyPart(context = {}) {
  const browser = context.browser ?? {};

  const readBrowser = (shell) =>
    shell?.browser && typeof shell.browser === 'object'
      ? shell.browser
      : browser;
  const readWriter = (shell) => {
    const shellBrowser = readBrowser(shell);
    if (typeof shellBrowser.writeClipboard === 'function')
      return shellBrowser.writeClipboard;

    return writeTextToClipboard;
  };

  let timer = null;
  let version = 0;
  let currentValue;

  return {
    copyStatus: '',

    resetCopyFeedback() {
      version += 1;
      readBrowser(this).cancelSchedule?.(timer);
      timer = null;
      this.copyStatus = '';
    },

    syncCopyValue(value) {
      if (value === currentValue) return false;
      currentValue = value;
      this.resetCopyFeedback();

      return true;
    },

    async copyWithFeedback(value) {
      this.resetCopyFeedback();
      const current = version;
      const copied = await readWriter(this)(value);
      if (current !== version) return null;

      this.copyStatus =
        copied === true ? COPY_FEEDBACK_OK : COPY_FEEDBACK_FAILED;
      const shellBrowser = readBrowser(this);
      const shell = this;
      timer =
        typeof shellBrowser.schedule === 'function'
          ? (shellBrowser.schedule(() => {
              shell.copyStatus = '';
              timer = null;
            }, COPY_FEEDBACK_CLEAR_DELAY) ?? null)
          : null;

      return copied;
    },

    destroyCopyFeedback() {
      this.resetCopyFeedback();
    },
  };
}

const flashTimers = new WeakMap();

export function flashButtonIcon(button, icon, flashClass) {
  const list = icon?.classList ?? null;
  if (
    !button ||
    !list ||
    typeof list.add !== 'function' ||
    typeof list.remove !== 'function'
  ) {
    return;
  }
  try {
    const pending = flashTimers.get(button);
    if (pending !== undefined && typeof window.clearTimeout === 'function') {
      window.clearTimeout(pending);
    }
  } catch (error) {}
  try {
    list.add(flashClass);
  } catch (error) {
    return;
  }
  if (typeof window.setTimeout !== 'function') {
    return;
  }
  try {
    flashTimers.set(
      button,
      window.setTimeout(function () {
        try {
          list.remove(flashClass);
        } catch (error) {}
        flashTimers.delete(button);
      }, COPY_FEEDBACK_CLEAR_DELAY),
    );
  } catch (error) {}
}
