# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1]

Mini launcher control: the 40x40 icon is no longer fixed to the
bottom-right corner.

### Added

- Mini icon position (`DebugBar.miniPosition`, default
  `bottom-right`): six host corners and middle edges. The bar itself
  always stays bottom-docked; only the icon moves. Positions can be
  pinned from the command palette (`mini:<pos>`); a stored pin survives
  reloads but is dropped as soon as the server value changes, so the
  server always wins.
- Mini click action (`DebugBar.miniAction`, `bar` (default) or
  `inspector`): a click opens just the bar or jumps straight into the
  inspector. Switchable from the palette (`mini:open-bar`,
  `mini:open-inspector`, plus `mini:server-default` and
  `mini:open-server-default` resets); the mini button label always names
  the current action.
- `Esc` now walks the whole stack down to the icon: palette, theme menu,
  request picker, inspector, then bar back to mini. On a full toolbar
  page the bar keeps its previous behavior.
- The command palette hotkey (`Ctrl`/`Cmd`+`Alt`+`P`) works from the
  host page: the host forwards the chord into the shell frame, so the
  palette also opens while only the mini icon is visible. Previously the
  shortcut only worked with focus inside the toolbar frame.
- Starred panels come first in the palette `Go to` list, in favorite
  order, and stay listed even when inactive; the hidden-commands count
  ignores them.
- Every scalar setting in `config/debug_bar.php` can now be overridden
  via environment variables (`DEBUGBAR_*`).
- Log context capture: `DebugBarLog` exports record context via
  `VarExporter` (expandable inner objects, sensitive leaves masked,
  JSON-safe) and interpolates `{placeholder}` values into the message.
  The Logs inspector renders context in the detail pane through the
  shared `debug_tree` with search; collapsed `N records in M entries`
  groups keep the first non-empty context and report how many records
  carry one. Empty contexts stay `[]`.

### Fixed

- HTTP Client panel redaction: request and response header values under
  sensitive names (`authorization`, `token`, `secret`, …) are masked via
  `RedactionService::maskSensitive` at capture, so stored rows and the
  safe-cURL copy never carry cleartext credentials.

## [1.0.0]

Initial release of `crustum/debug-bar` (`Crustum\DebugBar`).

Started as a fork of [cakephp/debug_kit](https://github.com/cakephp/debug_kit)
and rebuilt around a left-side inspector shell: the toolbar captures the
request into an SQLite store, the inspector reads it back through
server-rendered fragments plus a bounded JSON API. Panels written against
`DebugKit\DebugPanel` or the `DebugKit.panels` key keep working through the
compatibility layer.

### Inspector panels

Request, Attributes, Authorization, Cache, Deprecations, Environment,
Events, Exceptions, Findings, HTTP Client, Logs, Mail, Models, Packages,
Plugins, Queries, Routes, Timer, Timeline, Validation, Variables, Views:

- Request — headers, input, query, session, cookies, view attributes, the
  matched route, and a CLI card (`argv`, working directory) for console runs.
- Attributes — class attributes with on-demand inspect and cache warming.
- Authorization — policy checks behind the request.
- Cache — per-store operations with hit-rate warnings and inline
  clear (one store or all).
- Deprecations — grouped by origin (cake, app, plugins, vendor, other).
- Environment — PHP version and server vars, INI, CakePHP constants, app
  constants, include paths and included files.
- Events — dispatched application events with payloads.
- Exceptions — captured throwables with call sites.
- Findings — rule-based callouts (`request.slow`, `query.slow`,
  `query.n_plus_one`, `cache.high_miss_rate`, `deprecations.present`,
  `collector.truncated`), each linking back to its source inspector.
- HTTP Client — outbound calls with Request/Response tabs, safe cURL
  copy, and slow-call marking.
- Logs — leveled records grouped by repeats, with channel facets.
- Mail — previews with viewport and format switching, retained
  attachments, transports, and source.
- Models — per-model query behavior with capture/retrieved/write sorting.
- Packages — installed versions, semver-compatible vs breaking updates,
  and vulnerability auditing with inline advisories.
- Plugins — loaded plugins with their app configuration.
- Queries — per-execution rows with bindings policies, grouping-aware
  sort, N+1 explanations, call-site stacks, and EXPLAIN tabs.
- Routes — the route table plus the matched route for the request.
- Timer — framework phases with a bar graph.
- Timeline — merged waterfall of framework and application spans with
  per-row detail and Open-inspector jumps.
- Validation — field, rule, and message failures.
- Variables — lazy per-variable trees with loading, empty, and error
  states plus Retry.
- Views — template renders with lazy per-render data loading.

### Frontend shell

- Dependency-free shell (vanilla ES modules, no jQuery, no Livewire)
  with host isolation (`data-dk-*` hooks, `dk-*` classes, `--dk-*`
  variables, everything under `#debugbar`).
- Toolbar in two forms (center bar, corner pill) with drag placement,
  theme menu, and request-id copy for MCP analysis.
- Request switcher over recent requests (method, path, status tone),
  working for JSON APIs through the `X-DEBUGBAR-ID` response header.
- Inspector dialog with focus trap, header facts, and per-panel state
  that survives request switches and long-lived workers.
- Command palette (`Ctrl`/`Cmd`+`Alt`+`P`), `Esc` stack unwinding,
  `Shift`+Arrow favorite reordering with focus restore.
- Two-column list+detail workspaces with Back support and scroll restore.
- Generic interactions from markup alone: search, filters, tabs, sort,
  copy, copy-all, expand-all, tree search.
- Zero-registration third-party panels: any `data-dk-*-entry/key/detail`
  markup is selectable with no shell edits; `VarExporter` gives panels
  the same Variables-quality trees.
- Read-only MCP tools plus a bounded JSON API so coding agents inspect
  the same captured data without a browser.
- CLI capture (`CLI`/`JOB` runs inspectable like web requests) with
  `benchmark` and `debug-bar mcp` commands, and
  `DebugBarAssertionsTrait` for performance assertions in app suites.

### Backend capture and storage

- `DebugBarMiddleware` captures the request after the response is built;
  panel data lands in SQLite (`requests` + `panels`).
- URL filtering keeps noise out of the store:
  `DebugBar.ignored_url_masks` (glob masks, every status) and
  `DebugBar.ignorePathsPattern` (2xx responses only).
- Findings engine with configurable thresholds
  (`slow_query_ms`, `slow_request_ms`, `slow_http_request_ms`,
  miss-rate, caps); redaction of sensitive values at capture time;
  per-request panel state reset for long-lived workers.
