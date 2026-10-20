import { createShell, SHELL_EVENTS, SHELL_ROOT_ID } from './state.js';
import {
  composeState,
  decodeInspectorPayload,
  defaultRuntime,
  dkToolbarHorizontal,
  dkToolbarVertical,
  DK_TOOLBAR_PLACEMENTS,
  readInspectorPayload,
  resolveToolbarPlacement,
  writeTextToClipboard,
} from './runtime.js';
import { formatDuration } from './duration.js';
import { highlightCodeBlocks, resetHighlight } from './highlight.js';
import {
  createGatedPanel,
  createPanelRegistry,
  createPassthroughPanel,
} from './registry.js';
import { createInspectorPart, inspectorPanelUrl } from './shell/inspector.js';
import { createToolbarPart } from './shell/toolbar.js';
import { createNavigationPart, DEFAULT_INSPECTOR } from './shell/navigation.js';
import {
  collectNavRows,
  navRowKey,
  renderNavGroups,
  syncNavCounts,
} from './shell/nav-groups.js';
import {
  createRequestsPart,
  requestToolbarUrl,
  requestPanelsUrl,
  hostAjaxRequestId,
  MAX_RECENT_REQUESTS,
  RECENT_REQUESTS_URL,
} from './shell/requests.js';
import {
  createPreferencesPart,
  PREFERENCES_STORAGE_KEY,
} from './shell/preferences.js';
import { createPalettePart } from './shell/palette.js';
import { createCopyPart, COPY_FEEDBACK_CLEAR_DELAY } from './shell/copy.js';
import {
  createActivityPart,
  ACTIVITY_POLL_LIMIT,
  ACTIVITY_POLL_INTERVAL,
} from './shell/activity-refresh.js';
import {
  createDiscoveryPart,
  installRequestDiscovery,
  DK_PROFILE_HEADER,
  DK_OWN_PATH_PREFIX,
} from './shell/discovery.js';
import {
  createQueriesInspector,
  QUERIES_EXPLAIN_URL,
} from './inspectors/queries.js';

export const DebugBar = {
  createShell,
  createPanelRegistry,
  createGatedPanel,
  createPassthroughPanel,
  createInspectorPart,
  createToolbarPart,
  createNavigationPart,
  collectNavRows,
  navRowKey,
  renderNavGroups,
  syncNavCounts,
  createRequestsPart,
  createPreferencesPart,
  createPalettePart,
  createCopyPart,
  createActivityPart,
  createDiscoveryPart,
  installRequestDiscovery,
  createQueriesInspector,
  hostAjaxRequestId,
  DEFAULT_INSPECTOR,
  MAX_RECENT_REQUESTS,
  RECENT_REQUESTS_URL,
  requestToolbarUrl,
  requestPanelsUrl,
  PREFERENCES_STORAGE_KEY,
  COPY_FEEDBACK_CLEAR_DELAY,
  ACTIVITY_POLL_LIMIT,
  ACTIVITY_POLL_INTERVAL,
  DK_PROFILE_HEADER,
  DK_OWN_PATH_PREFIX,
  QUERIES_EXPLAIN_URL,
  inspectorPanelUrl,
  defaultRuntime,
  composeState,
  decodeInspectorPayload,
  readInspectorPayload,
  writeTextToClipboard,
  resolveToolbarPlacement,
  dkToolbarHorizontal,
  dkToolbarVertical,
  DK_TOOLBAR_PLACEMENTS,
  formatDuration,
  highlightCodeBlocks,
  resetHighlight,
  events: SHELL_EVENTS,
  rootId: SHELL_ROOT_ID,
};

export {
  createShell,
  createPanelRegistry,
  createGatedPanel,
  createPassthroughPanel,
  createInspectorPart,
  createToolbarPart,
  createNavigationPart,
  collectNavRows,
  navRowKey,
  renderNavGroups,
  syncNavCounts,
  createRequestsPart,
  createPreferencesPart,
  createPalettePart,
  createCopyPart,
  createActivityPart,
  createDiscoveryPart,
  installRequestDiscovery,
  createQueriesInspector,
  hostAjaxRequestId,
  DEFAULT_INSPECTOR,
  MAX_RECENT_REQUESTS,
  RECENT_REQUESTS_URL,
  requestToolbarUrl,
  requestPanelsUrl,
  PREFERENCES_STORAGE_KEY,
  COPY_FEEDBACK_CLEAR_DELAY,
  ACTIVITY_POLL_LIMIT,
  ACTIVITY_POLL_INTERVAL,
  DK_PROFILE_HEADER,
  DK_OWN_PATH_PREFIX,
  QUERIES_EXPLAIN_URL,
  inspectorPanelUrl,
  defaultRuntime,
  composeState,
  decodeInspectorPayload,
  readInspectorPayload,
  writeTextToClipboard,
  resolveToolbarPlacement,
  dkToolbarHorizontal,
  dkToolbarVertical,
  DK_TOOLBAR_PLACEMENTS,
  formatDuration,
  highlightCodeBlocks,
  resetHighlight,
  SHELL_EVENTS,
  SHELL_ROOT_ID,
};

export function installDebugBar(host) {
  const target = host ?? (typeof window !== 'undefined' ? window : null);
  if (!target) return null;
  target.DebugBar = DebugBar;
  target.DebugBarHighlight = highlightCodeBlocks;

  return DebugBar;
}

installDebugBar();

export default DebugBar;
