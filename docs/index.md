# CakePHP DebugBar Plugin

- [Introduction](#introduction)
- [Installation](#installation)
    - [Requirements](#requirements)
    - [Load the Plugin](#load-the-plugin)
    - [Configuration File](#configuration-file)
    - [Check It Works](#check-it-works)
    - [Troubleshooting](#troubleshooting)
- [Configuration](#configuration)
    - [Enablement](#enablement)
    - [Panels](#panels)
    - [Storage](#storage)
    - [Thresholds and Findings](#thresholds-and-findings)
    - [Collection Limits and Sensitive Values](#collection-limits-and-sensitive-values)
    - [Editor Links](#editor-links)
    - [Settings Reference](#settings-reference)
- [The Bar](#the-bar)
    - [Toolbar](#toolbar)
    - [Request Switcher](#request-switcher)
    - [Inspector](#inspector)
    - [Command Palette](#command-palette)
    - [Keyboard](#keyboard)
- [Panels](#panels-intro)
- [Request](#request)
- [Attributes](#attributes)
- [Authorization](#authorization)
- [Cache](#cache)
- [Deprecations](#deprecations)
- [Environment](#environment)
- [Events](#events)
- [Exceptions](#exceptions)
- [Findings](#findings)
- [HTTP Client](#http-client)
- [Log](#log)
- [Mail](#mail)
- [Models](#models)
- [Packages](#packages)
- [Plugins](#plugins)
- [Queries](#queries)
    - [Query Intelligence](#query-intelligence)
    - [EXPLAIN](#explain)
- [Routes](#routes)
- [Timer](#timer)
- [Timeline](#timeline)
- [Validation](#validation)
- [Variables](#variables)
- [Views](#views)
- [Agents](#agents)
    - [JSON API](#json-api)
    - [MCP](#mcp)
- [Custom Panels](#custom-panels)
    - [Shell integration](#panel-chrome)
- [CLI](#cli)
- [Testing](#testing)

<a name="introduction"></a>
## Introduction

DebugBar captures the current request and shows what the application did while
serving it: request data, SQL queries, a merged timeline of framework and
application events, findings that call out slow or repeated work, cache
activity, logs, exceptions, events, models, views, HTTP client calls, mail,
validation, authorization, environment, routes, plugins, packages, timers,
and variables.

The request flows through `DebugBarMiddleware`, which asks `ToolbarService`
whether the toolbar is enabled, loads the panels from the `PanelRegistry`,
and saves one `requests` row with its `panels` rows into SQLite after the
response is built. When the response is HTML with a `</body>` tag, the
service injects a `<script>` tag that boots the toolbar shell and always
sends the request id back as an `X-DEBUGBAR-ID` response header, so API and
`fetch` responses stay inspectable without a visible bar.

For example, a slow order page can be diagnosed without guessing: open the
request in the toolbar, read the Queries panel for the repeated shape, follow
the N+1 explanation to the application call site, and confirm the fix by
comparing the query count on the next request.

> **Warning:** DebugBar is only intended for single-user local development
> environments. It captures configuration data, environment variables, and
> SQL bindings into the SQLite store — avoid shared development
> environments, staging, or anything where that data must stay hidden.

<a name="installation"></a>
## Installation

<a name="requirements"></a>
### Requirements

* PHP 8.2+
* `cakephp/cakephp` `^5.4`
* SQLite (`pdo_sqlite`) for the default `debug_bar` store, or a dedicated
  `debug_bar` datasource. Rows that cannot be saved are skipped with a log
  warning; the application response is never broken by storage failures.

<a name="load-the-plugin"></a>
### Load the Plugin

Install via Composer (keep it in `require-dev` so production installs skip
it) from your project ROOT directory (where the **composer.json** file is
located):

```bash
composer require --dev crustum/debug-bar
```

Load the plugin:

```bash
bin/cake plugin load Crustum/DebugBar
```

Alternatively, load it in your `Application.php`:

```php
// In src/Application.php
public function bootstrap(): void
{
    parent::bootstrap();

    $this->addPlugin('Crustum/DebugBar');
}
```

<a name="configuration-file"></a>
### Configuration File

The plugin reads `Configure::read('DebugBar.*')`. On bootstrap it loads the
host application's `config/debug_bar.php` when present, otherwise it falls
back to the plugin's own `config/debug_bar.php` defaults, so copying the
file is optional until a value needs to change:

```bash
cp vendor/crustum/debug-bar/config/debug_bar.php config/debug_bar.php
```

A manifest provider (`src/Manifest/DebugBarManifest.php`) is available for
hosts that use `crustum/plugin-manifest`, but it is conditional: the plugin
loads and runs without that package installed (it is `require-dev` plus
`suggest` only). There is no required manifest step.

<a name="check-it-works"></a>
### Check It Works

Open any normal HTML page while `debug` is on and the host looks local. The
toolbar appears, and the response carries the request id:

```text
X-DEBUGBAR-ID: 5ef39604-ad5d-4ca4-85d8-8595e52373bb
```

That id opens the toolbar view at `/debug-bar/toolbar/<id>` and selects the
request in the switcher, which also works for JSON APIs that never render
a page.

<a name="troubleshooting"></a>
### Troubleshooting

Diagnose in layers: capture first, then the response body, then the
browser. This tells you where the toolbar stopped appearing.

1. **Was the response captured?** In the browser Network panel, select the
   application request and look for `X-DEBUGBAR-ID`. Check the request you
   just made, not an asset or the toolbar's own request. No header means
   the toolbar never ran for it: recheck `debug`, host safety (`safeTld`
   / `forceEnable`), and whether the URL matches `ignored_url_masks` or
   `ignorePathsPattern`. The header and successful storage are separate
   checks — a present header with no readable row points at SQLite
   (`pdo_sqlite` missing or no `debug_bar` datasource) or at garbage
   collection already pruning the request window
   (`DebugBar.storage.max_requests`, 20 by default). If the app uses the
   Authorization plugin, its policy may deny the toolbar's own requests —
   set `DebugBar.ignoreAuthorization` to `true` in that case.
2. **Should this response contain a toolbar?** Injection needs an HTML
   response with a closing `</body>` tag:

   | Response | Expected result |
   |----------|-----------------|
   | Normal HTML document | Header plus injected toolbar |
   | JSON or API response | Header only; read the row via `/debug-bar/toolbar/<id>` or MCP |
   | Redirect, download, or stream | No injection into the body |
   | HTML fragment without `</body>` | No injection |

   A captured API request needs no HTML added to it.
3. **Is the markup present but the interface broken?** Search the response
   HTML for `id="__debug_bar_script"`. When present, check the Console and
   Network panels for the first error or missing asset. Script injection
   honors the request's CSP nonce (`cspScriptNonce` attribute) when one is
   set; a blocked-script message points at the named policy, not at the
   toolbar.

Reload the same HTML page to verify: header present, request opens,
Queries loads, Console has no related error.

<a name="configuration"></a>
## Configuration

All keys live under `DebugBar` in `config/debug_bar.php` and are read as
`Configure::read('DebugBar.*')`. Redaction and caps apply at capture time:
raising a limit later does not rewrite rows that are already stored.

<a name="enablement"></a>
### Enablement

The toolbar is enabled when `debug` is on, the host is not suspiciously
production, and the SAPI is not `phpdbg`. Otherwise `DebugBar.forceEnable`
(`false` by default, bool or callable) decides. `DebugBar.safeTld` extends
the built-in safe list (`localhost`, `invalid`, `test`, `example`, `local`,
`internal`).

<a name="panels"></a>
### Panels

`DebugBar.panels` enables or disables individual panels by alias. Any
plugin shipping `src/Panel/FooPanel.php` plus its element is loadable;
third-party panels written against `DebugKit\DebugPanel` or the
`DebugKit.panels` key keep working through the compatibility layer, which
aliases the class and translates `DebugKit.*` aliases to
`Crustum/DebugBar.*`:

```php
// In src/Application.php bootstrap()
Configure::write('DebugBar.panels', ['Crustum/DebugBar.Packages' => false]);
```

<a name="storage"></a>
### Storage

Panel data is stored in SQLite in the `debug_bar` connection
(`src/schema.php`: `requests` with `id uuid, url, content_type, method,
status_code, requested_at`, plus `panels` with
`id uuid, request_id FK, panel, title, element, summary, content`).
`DebugBar.storage.max_requests` (default `20`) caps retained requests;
`DebugBar.requestCount` is the legacy alias read by the table garbage
collection. `tmp/debug_bar.sqlite` can be deleted at any time; it is
recreated as needed.

Capture limits and read limits are different things. A capture limit
decides which values reach storage; the JSON cursor and the MCP item/byte
caps decide how much retained evidence fits in one reply. Follow the
cursor to read the next part; capture again if the needed value was
omitted before storage.

`DebugBar.ignorePathsPattern` skips matching URLs for 2xx responses only
(for example `'/\\.(jpg|png|gif)$/'`). `DebugBar.ignored_url_masks` holds
glob masks against the request path (a trailing `/*` also covers the bare
prefix) and applies to every status, so noise URLs never reach the store
or the request switcher.

<a name="thresholds-and-findings"></a>
### Thresholds and Findings

Thresholds decide when attention is called, not what is collected:

| Key | Default | Runtime effect |
|-----|---------|----------------|
| `slow_query_ms` | `100` | Marks slow queries (`QueryAnalyzer`, `FindingsService`) |
| `slow_http_request_ms` | `250` | Marks slow outbound HTTP calls (`HttpClientPanel`) |
| `slow_request_ms` | `1000` | Creates a `request.slow` finding when the request takes at least this long |
| `findings.minimum_cache_operations` | `5` | Minimum cache reads before a high-miss finding |
| `findings.high_cache_miss_rate` | `0.8` | Miss-rate fraction that triggers the finding |
| `findings.max_findings` | `50` | Maximum retained findings per request |

<a name="collection-limits-and-sensitive-values"></a>
### Collection Limits and Sensitive Values

`RedactionService` bounds retained data at capture time. Bounds are part
of the evidence: when an inspector reports retained and omitted counts,
do not read the retained sample as a complete count. Raise a limit only
when the missing detail is needed for the investigation. Call sites are
captured separately per event (`CallsiteCapture`):

| Key | Default | Runtime effect |
|-----|---------|----------------|
| `collection.max_items` | `500` | Per-panel record cap |
| `collection.max_items_per_array` | `100` | Nested array cap |
| `collection.max_depth` | `5` | Capture depth cap |
| `collection.max_string_length` | `2000` | Ordinary string cap |
| `maxDepth` | `10` | Nested rendering depth (Request, Views) |
| `variablesPanelMaxDepth` | `5` | Variables panel depth |
| `query_bindings` | `full` | `full`, `safe` (masks strings), or `none` |
| `key_policy` | `full` | `full`, or `hash` for cache keys |
| `includeSchemaReflection` | `false` | Log schema-reflection queries |
| `ignoreAuthorization` | `false` | Skip the Authorization check on toolbar requests |
| `exception_frames.application` / `.vendor` | `12` / `12` | Frames retained per exception |
| `exception_frames.source_context_lines` | `9` | Source lines around exception locations |
| `call_sites` | `true` | Record the first application frame plus a short stack per event |
| `call_site_frames` | `12` | Useful application frames kept per call site |
| `mcp.max_items` / `mcp.max_bytes` | `50` / `100000` | Bounds for MCP tool responses |

<a name="editor-links"></a>
### Editor Links

Stack traces and sources can link into an editor instead of copying
`file:line`. Disabled by default; enable and pick an editor (or a custom
`{file}`/`{line}` template):

```php
'links' => ['enabled' => true, 'editor' => 'cursor'],
```

<a name="settings-reference"></a>
### Settings Reference

Every key below is relative to `DebugBar` in `config/debug_bar.php`.
Defaults describe normal local use. Copy the file only when an actual
workflow needs a different value; each entry above documents the setting's
runtime effect.

<a name="the-bar"></a>
## The Bar

The bar stays quiet until something needs attention: the current request,
its status, duration, query count, and peak memory are visible without
opening anything, and dense detail is one click deeper.

<a name="toolbar"></a>
### Toolbar

The toolbar has two forms. The centered bar shows the request switcher,
the Duration / Peak / Queries facts, and the tool actions; the corner
pill shows the switcher with an expand button. Both start hidden and boot
from the embedded summary JSON. Facts are buttons: Duration and Queries
open the Request and Queries inspectors directly, and the warning dot
turns warn tone when findings or errors deserve a look.

The toolbar is draggable to six placements (top, bottom, and all four
corners, persisted across visits). Tool actions are the command palette,
copying the request id (for MCP analysis), the theme menu
(`system`/`light`/`dark`, persisted per browser), and the window controls
(expand the inspector, shrink back, hide the bar to its mini toggle).

The 40x40 mini icon is the bar's closed state. It docks to any host
corner or middle edge (`DebugBar.miniPosition`, default
`bottom-right`); the bar itself always opens bottom-docked. A click
opens the bar, or jumps straight into the inspector when
`DebugBar.miniAction` is `inspector` (default `bar`). Both can be
pinned per browser from the command palette (`mini:<pos>`,
`mini:open-bar`, `mini:open-inspector`); a stored pin is dropped as soon
as the server value changes, so configuration always wins.

<a name="request-switcher"></a>
### Request Switcher

The switcher lists recent requests with method, path, status tone (5xx
red, 4xx amber, 3xx sky, 2xx emerald), relative time, and unread dots.
Selecting another row swaps every panel in place through
`GET /debug-bar/requests/panels` while keeping the open inspector, with
an automatic fallback to a fresh toolbar page. The current request is
prepended when it is not in the recent list yet. There is no separate
History tab: switching replaced it, served by the same recent-request
data. Noise URLs never reach the switcher — see URL filtering under
[Storage](#storage).

<a name="inspector"></a>
### Inspector

The inspector is a dialog sheet: header (switcher, facts, actions), a
navigation with one entry per panel, and the content stage.
Navigation is favorites-first with per-panel counts and attention dots;
stars persist, `Shift`+arrow keys reorder rows in place — the focused row
stays focused, so repeated presses keep moving it without re-clicking.
Panel bodies render server-side — third-party elements work untouched —
and open with a skeleton, an error card with Retry/Reload when a
fragment fails, and a background-activity banner with Retry now while
async work lands. Focus is trapped inside while open and restored on
close. Mobile layouts collapse the header and navigation into popover
menus.

<a name="command-palette"></a>
### Command Palette

The palette jumps anywhere: `inspector:<key>` entries (starred panels
first, in favorite order, then priority-sorted), `theme:system|light|dark`,
and the six toolbar placements. Filtering is
word-AND, `↑↓↵` move and confirm, backdrop click cancels.

<a name="keyboard"></a>
### Keyboard

`Ctrl`/`Cmd`+`Alt`+`P` toggles the palette (matched by key code, so it
works on any layout), including from the host page while only the mini
icon is visible — the host forwards the chord into the shell frame.
`Esc` walks down the stack: palette, theme menu,
request picker, inspector, then bar back to the mini icon (on a full
toolbar page the bar stays put). Arrow keys move inside pickers and the
palette; `Shift`+arrow reorders navigation favorites.

<a name="panels-intro"></a>
## Panels

Each panel keeps one kind of evidence focused. Start with the request
overview or a finding, then open the smallest panel that can explain the
symptom:

| Question | Panel |
|----------|-------|
| Which request or response am I looking at? | [Request](#request) |
| Which attributes are declared? | [Attributes](#attributes) |
| Why was this action denied? | [Authorization](#authorization) |
| What did the cache return? | [Cache](#cache) |
| What is deprecated? | [Deprecations](#deprecations) |
| What environment ran this? | [Environment](#environment) |
| Why did an event or listener run twice? | [Events](#events) |
| What failed? | [Exceptions](#exceptions) |
| What deserves attention? | [Findings](#findings) |
| Which remote call failed or waited? | [HTTP Client](#http-client) |
| What was logged nearby? | [Log](#log) |
| Which message was sent, and how does it render? | [Mail](#mail) |
| Which records were retrieved or changed? | [Models](#models) |
| Which packages are installed or outdated? | [Packages](#packages) |
| Which plugins are loaded? | [Plugins](#plugins) |
| Why is SQL slow or repeated? | [Queries](#queries) |
| Which route matched, and what else exists? | [Routes](#routes) |
| How long did each phase take? | [Timer](#timer) |
| Where did the request spend its time? | [Timeline](#timeline) |
| Which field or rule stopped submission? | [Validation](#validation) |
| Which variables reached the template? | [Variables](#variables) |
| Which template received the wrong data? | [Views](#views) |
| How does an agent read all this? | [Agents](#agents) |
| How do I add my own panel? | [Custom Panels](#custom-panels) |
| What ran on the console? | [CLI](#cli) |
| How do I lock query budgets in tests? | [Testing](#testing) |

An empty panel can be correct: a request that sent no mail has no mail
records. That is different from a bounded panel — when collection limits
drop records, the panel reports retained and omitted counts so the sample
is never mistaken for a complete one.

<a name="request"></a>
## Request

Every page load, redirect, `fetch`, and CLI run stores its own request.
Select the request you meant to inspect before judging its data: the
request switcher lists recent requests with method, path, status, and
recorded time. The newest row is not always the one you care about — a
`fetch` call, polling request, or later command may be newer than the page
that triggered it. `fetch`/XHR responses are discovered automatically via
the `X-DEBUGBAR-ID` header; when several rows look similar, copy the exact
id from the header and open `/debug-bar/toolbar/<id>` directly.

Read the overview first. The compact toolbar and the request facts answer
the first questions without opening every inspector: what happened
(method, path, status, duration), what needs attention (errors and
findings surface before quiet diagnostics), and where time went (query
count and total query time point toward Queries; high duration with little
database time points away from SQL). Treat findings as leads, not verdicts:
local data can be unusual for a valid reason, so open the source evidence
and compare another request before changing code.

A request can be captured without toolbar HTML. JSON, redirects, and error
responses keep their format; the `X-DEBUGBAR-ID` header still identifies
the stored row, which agents read through MCP without a browser page.

The panel walks the request in three steps — Received, Matched,
Responded — with detail tabs for headers, input, query, and session, plus
the matched route, middleware, call trace, and identity with its auth
result. Copy the URL or the whole payload as JSON for an agent; the size
shown is the final body, patched at save time because the panel itself
only sees the pre-render response. CLI and `JOB` runs show a command
card instead: name, arguments, options, command definition, and
environment — stored bare, never with a leading slash.

<a name="attributes"></a>
## Attributes

The Attributes panel is unique to this plugin: it inspects PHP
attribute usage through the optional `crustum/cakephp-attribute-resolver`
package (a suggested dependency, never required). On CakePHP 5.4 the
resolver package is the only source; native support lands with CakePHP
6. Pick a configured scope, search and filter the snapshot, and open a
row to query or inspect one result at a time — like the Views panel's
lazy data. When the resolver is missing or unconfigured, the panel says
so instead of showing an empty list.

<a name="authorization"></a>
## Authorization

Authorization is the `cakephp/authorization` plugin (identity plus
policies with `can<Action>` methods) together with `cakephp/authentication`
for identity — and in many apps CakeDC Auth RBAC rules on top. The panel
records checks from both without changing any decision: the service
decorator (installed by `DebugBarAuthorizationMiddleware` after the
authorization middleware) reports every `can()`/`canResult()` with user,
action, resource, result, and the resolved policy class, method, and
source; CakeDC Auth vendor events (`Auth.Rbac.checked`,
`Auth.Authorization.checked`) add role, matched permission rule, and
reason. Resources keep class names, identifiers, and labels only — values
are never stored, and there is no result code, only the message.

When a request is denied, filter to denied decisions and open the
ability: the detail shows user, arguments, result, and the resolved
policy method with its source — copy the `file:line` straight into the
editor. A missing identity or wrong resource explains the result before
any rule. An expected denial is healthy — it is a lead to inspect, not
an instruction to grant access. Authorization answers whether
the action was allowed; authentication context (identity, guard, route)
lives in the Request panel. Verify with a repeat as the intended user
and keep the denial when the rule correctly protects the action.

<a name="cache"></a>
## Cache

Search operations, filter by All, Reads, Writes, Deletes, or Failed, and
open a row for its store, duration, value evidence, and source. The
hit-rate block turns warn on a high miss rate; failures stand apart from
normal misses. Caches can be cleared from the panel, one store or all at
once, with inline success or failure text. There is no Redis panel: direct Redis work belongs to a separate
plugin, so an empty result there never means the cache abstraction missed
something.

A high miss rate needs at least `minimum_cache_operations` reads (default
`5`) with a miss rate at or above `high_cache_miss_rate` (default `0.8`).
Confirm the keys should be warm, the store and TTLs are right, and no
earlier flush or forget cleared them — a first request after a deliberate
clear can validly miss everywhere. Keys follow the `key_policy`: `full`
keeps exact keys for private local debugging, `hash` keeps stable hashes
when matching operations is enough.

```php
$key = 'docs:journey-summary';
Cache::delete($key);
$first = Cache::remember($key, fn () => ['days' => 8]);
$second = Cache::remember($key, fn () => ['days' => 99]);
```

The second read should hit and skip the producer. If both miss in a real
app, compare key construction, store, TTL, and any earlier forget or
flush — an array store is process-local and never survives into the next
request.

<a name="deprecations"></a>
## Deprecations

A static grouped list by origin (cake, app, plugins, vendor, other) —
no filters by design. Each group is a future upgrade break: migrate the
flagged calls before the next major. The `deprecations.present` finding
points here whenever the count is non-zero.

<a name="environment"></a>
## Environment

The Environment panel describes the process that served the request: PHP
version and configuration, `$_SERVER`, ini settings, CakePHP and user
constants, and include paths and files grouped into cake, app, plugins,
vendor, and other. Database passwords and DSN secrets are masked. It is
reference data for interpreting everything else, not a diagnostic by
itself.

<a name="events"></a>
## Events

Search dispatches, filter by source, and open a group for its Overview,
Payload, and Source tabs — occurrences, sequence, per-listener
registrations with duplicates surfaced, and shape-only payloads (field
names plus types, never values). Tracking rides the manager's own event
list; the true dispatch site is remembered per event. Synchronous
listeners of a non-stopped event all ran and count as completed; stopped
propagation marks the group partial instead of guessing which listener
ran.

When one action causes duplicate work, separate the causes: several
dispatches of the same event means repeated controller calls, model
hooks, or retries dispatch it — open the retained source before changing
a listener. One dispatch with a duplicate listener registration means the
same callable was registered twice; keep one intended registration.
Payload shape checks the contract (types, fields, counts); when the bug
depends on a value that was not retained, read the application code or
add a focused log line — MCP cannot recover an omitted value.

<a name="exceptions"></a>
## Exceptions

The Exceptions panel captures throwables bubbling through
`DebugBarMiddleware` (stored as failed 500 rows) plus ones recorded from
`Exception.beforeRender`, which is where handled throwables surface. Each
entry keeps class, message, chained causes (truncated after 5), and
`exception_frames` of stack (12 application + 12 vendor by default) with
source context lines around the throwing frame.

Status and exceptions answer different questions: the status tells how
the response ended, Exceptions shows failures reported during the
request. A caught exception that was never reported or recorded may not
appear — follow the app's reporting path before treating absence as a
collector failure. Begin where the application first enters the failing
path; vendor frames stay available for deeper tracing. Follow causes
toward the original failure, check nearby logs and timeline items for
what happened immediately before, and after a fix confirm the behavior
plus all error findings — removing a log message is not a fix.

<a name="findings"></a>
## Findings

The Findings panel has no collector either. `FindingsService` runs pure
rules over the stored sibling data at `saveData()` time, sorts errors
before warnings before info, and caps the list at
`findings.max_findings`, keeping an `omitted` count. Every finding links
back to its source inspector with a `Review in <panel>` action and
carries `rule_id`, `severity`, `panel`, `summary`, `why`, `location`,
`next`, and `action`. Follow a row's Review action to jump straight
into the evidence behind it.

> [!NOTE]
> A finding is a lead, not a verdict. Local data can be unusual for a
> valid reason — open the source evidence, confirm the work is
> unexpected, and compare another request before changing code.

| Rule | Severity | Fires when |
|------|----------|------------|
| `query.n_plus_one` | error | Same fingerprint with varying bindings (see Queries) |
| `query.repeated` | warning | Identical statements ran more than once |
| `query.slow` | warning | A statement took at least `slow_query_ms` |
| `request.slow` | warning | The request took at least `slow_request_ms` (links to Timeline) |
| `cache.high_miss_rate` | warning | At least `minimum_cache_operations` reads with miss rate at or above `high_cache_miss_rate` |
| `exception.captured` | error | Error-level entries were logged during the request |
| `request.error` | error (5xx) / warning (4xx) | The response status is 400 or above |
| `deprecations.present` | warning | Deprecation notices were triggered |
| `collector.truncated` | warning | The timeline dropped events under collection caps |

<a name="http-client"></a>
## HTTP Client

The HTTP Client panel instruments every application
`Cake\Http\Client` through the global `HttpClient.beforeSend` /
`HttpClient.afterSend` events — no client-side changes needed. Each call
opens Request/Response tabs with method, host, path, query, status,
duration, bounded headers and bodies, plus copy buttons for the URL,
either body, and a safe cURL (redacted values stay redacted in the
copy). Header values under sensitive names (`authorization`, `token`,
`secret`, …) are masked at capture via `RedactionService::maskSensitive`,
so stored rows never carry cleartext credentials. Requests that never
complete stay pending and report as failed with
no response; a response without a real HTTP status counts as failed too.
Slow means at or above `slow_http_request_ms` (default `250`).

Distinguish completed responses from connection failures (no status,
exception class instead), and remember the app may accept a non-2xx
status — compare it against the code path and the remote contract, not
the number alone. Follow the first useful application frame into the
service or wrapper that sent the call and inspect its timeout, retry
policy, and payload handling; retries show as separate attempts in
execution order. Repeat with a controlled remote response and confirm
call count, status handling, fallback, and result before comparing
duration — and never call a missing result a performance win.

<a name="log"></a>
## Logs

Search messages, filter by level (All, Needs attention, then each) and
channel, and open a group for its severity, channel, from-start offset,
related exception link, context, occurrences, and source. Context is
captured exported the Variables way (expandable inner objects, sensitive
leaves masked) and renders in the detail pane through the shared tree;
repeats collapse into `N records in M entries` instead of one row per
line, keeping the first non-empty context per message. Because
entries belong to one profile, there is no large log file to search by
time.

Start with warning and error levels, but keep lower-level entries when
they record the state transition or identifier that explains the
failure. Error-level entries feed the `exception.captured` finding. Write
small concrete context (operation, record id, transition) instead of
object dumps; common sensitive keys are redacted at capture. For handled
form feedback use Validation rather than treating every validation
message as a crash; for a thrown failure use Exceptions.

<a name="mail"></a>
## Mail

The Mail panel observes the app's mail workflow through a transport
wrapper. Open a message for recipients, subject, headers, source, and
the Preview/Message/Source tabs; switch the preview viewport between
desktop and mobile and the format between HTML and text, download any
retained attachment from its row (raw MIME is not retained, so there is
no EML download), and check the transport, duration,
and call site when a message is created more than once.

Previews come from preview classes: `*Preview.php` files in any
`Mailer/Preview/` directory, each public method rendering one email
through `getMailer()`:

```php
namespace App\Mailer\Preview;

use Crustum\DebugBar\Mailer\MailPreview;

class WelcomePreview extends MailPreview
{
    public function welcome(): \Cake\Mailer\Mailer
    {
        return $this->getMailer('Welcome');
    }
}
```

Opening a preview never intercepts delivery; choose the intended local
transport yourself. Bodies and attachments are bounded (metadata stays
visible past the budget); only mail the request sent is captured, so use
the exact request profile rather than the page around it. Notifications
have no panel here — delivery belongs to a separate plugin.

<a name="models"></a>
## Models

Sort the groups by Model, Retrieved, Writes, or Reloads, and open one for
its Records and Source tabs: identifiers, changed fields, connection,
table, timing, and the application source. Global `Model.*` listeners
catch every table without app changes. Identifiers come from primary-key
equality in the query, so `get()` and find-by-id render real identities
while bare list queries stay unidentified — an honest gap, not a guess.
Only shapes are stored: identifiers and truncated changed fields, never
full rows.

A large retrieval count is not automatically a problem. Check how many
unique records loaded, whether one record repeats, and which source caused
each group. Repeated retrievals point at relationship access in a loop,
duplicated resource work, or several layers requesting the same model —
confirm the matching query shape in Queries (same fingerprint, varying
bindings) before eager-loading with `contain()`, batching, or reusing the
already-loaded instance. If the later code needs fresh database state,
keep that requirement: fewer retrievals never justify stale data.

An unexpected write may come from a behavior, event listener, or model
hook rather than the controller line where the request began — compare the
changed fields with the intended action. Verify records as well as
counts: identifiers, preserved attributes and ordering, and the actual
database values for a write, including whether its transaction committed.

<a name="packages"></a>
## Packages

Search and filter the installed packages, open a row for its version and
repository details, and run the two actions from the panel: Check for
Updates (composer outdated, semver-compatible vs breaking split, inline
in the panel) and Audit vulnerabilities (adds a Vulnerable filter tab and
`VULN` badges on affected rows; advisories render inline with severity
and CVE links). A red dependency rarely explains a local failure, but a
major behind on a database or queue library can.

<a name="plugins"></a>
## Plugins

Search and filter the loaded plugins, open a row for its app
configuration (`isLoaded`, `onlyDebug`, `onlyCli`, `optional`). When a
panel, route, or command is missing, check here first whether its plugin
loaded at all.

<a name="queries"></a>
## Queries

The Queries panel logs every query on every configured connection except
the `debug_bar` store itself. Schema-reflection queries are skipped unless
`DebugBar.includeSchemaReflection` is on. The toolbar summary is
`"<count> / <time> ms"`.

Stored data keeps three shapes: `items` (one row per captured query with
SQL, redacted bindings, `took`, row count, role, driver, connection,
in-transaction flag, call site and stack, start/end), `records` (the same
rows grouped by fingerprint, see below), and `summary` (`count`, `groups`,
`repeated`, `slow`, `duration_ms`, `duration_label`).

<a name="query-intelligence"></a>
### Query Intelligence

`QueryAnalyzer` groups executions by fingerprint: SQL is normalized
(single-quoted strings and standalone numbers become `?`, `IN` lists
collapse) and hashed, so the same statement with different values lands in
one group. Double-quoted segments are left intact because the ORM quotes
identifiers. Each group is flagged:

* `slow` — any execution at or above `slow_query_ms`.
* `repeated` — the fingerprint ran more than once in the request.
* `likely_n_plus_one` — repeated at least 3 times with varying bindings,
  the classic per-row loop.

> [!NOTE]
> Likely N+1 is a heuristic. Repetition can be intentional — confirm the
> count scales with the collection and that eager loading preserves
> behavior before changing anything.

Triage in order: confirm the request first (a background call has a
completely different query profile), then compare count against total
time. Many fast queries suggest repeated application work; a few
expensive ones suggest planning, indexing, or locking. Keep the two costs
separate: query time is only the time observed around database execution,
not the full request.

A CakePHP N+1 looks like this — one query per row in a loop:

```php
foreach ($articles as $article) {
    $comments = $article->comments; // one query per article
}
```

The repeated group keeps those executions and their bindings together with
one shared source pointing at the loop. Eager-load the association
instead:

```php
$articles = $articlesTable->find()->contain(['Comments'])->all();
```

Repeat the same action with comparable records and confirm the group
disappeared, the count and total time dropped, and the returned behavior
is unchanged. When a query budget protects the path, lock it in with an
assertion (see Testing).

Groups where `slow || repeated` need attention. Sort by Time, filter the
list, and open a group for normalized SQL, per-execution rows with
bindings and duration, the N+1 explanation, the call-site stack, and the
EXPLAIN tab — copy the bound SQL straight from the detail when you need
to reproduce the exact local case. Bindings follow the
`query_bindings` policy: `full` keeps values, `safe` masks strings,
`none` drops them — and `bindings_complete` tells the inspector whether
the shown SQL is the whole story.

The source location and short application stack are usually more useful
than the SQL text alone: they show the controller, service, table, or
template path that caused the query. If the first frame is shared
infrastructure, move down the retained application frames
(`call_site_frames`) until you reach the caller that controls the work.

Begin, commit, and rollback markers (`inTransaction`,
`isCommitOrRollback`) place SQL inside its transaction on that
connection. A rollback explains why a captured write never became a
lasting change — check the actual database result as well as the marker.

<a name="explain"></a>
### EXPLAIN

Read queries with complete bindings offer an EXPLAIN tab. It is served by
`POST /debug-bar/panels/explain` with `{request_id, execution}` and is
strictly live-only: the statement is re-read from the stored panel and
explained on the same connection it was captured on, never stored.

* Only `SELECT`/`WITH` statements are explained. Writes are refused with
  an `unavailable_reason`, never executed.
* The prefix matches the driver: `EXPLAIN QUERY PLAN` on SQLite,
  `EXPLAIN` on MySQL/Postgres; other drivers get an
  `unavailable_reason`.
* Missing bindings, a missing connection, or a request already garbage
  collected from the request window surface as `unavailable_reason`
  or `error` text the inspector renders inline with a retry. When old
  bindings were masked or omitted, recapture with the `full`
  `query_bindings` policy first.
* The plan is requested when you open it. It is a current database
  action, not a measurement of the old execution, and its result is not
  saved into the profile — do not expect it through MCP.

> [!NOTE]
> EXPLAIN is evidence, not an automatic fix: a plan depends on the
> database, schema, statistics, and bindings. Judge an index against real
> query patterns and write cost, not one local plan alone.

<a name="routes"></a>
## Routes

Search the route table, filter and sort it, and open a row for the full
route definition. When the wrong controller answers, compare the matched
route for the request against the table before suspecting middleware.
The summary keeps the table count.

<a name="timer"></a>
## Timer

The Timer is a static read of framework phases — `Controller.*`,
`View.*`, request and memory marks with a bar graph, summarized as
`"<time> s / <memory>"` on the toolbar. No filters by design; phases
read top to bottom. Custom spans join the same view with two calls:

```php
use Crustum\DebugBar\DebugTimer;

DebugTimer::start('load_article', 'Fetching article from database');
// Code to measure.
DebugTimer::stop('load_article');
```

Completed custom timers appear for the request. The Timeline merges these
phases with queries, logs, and cache on one axis; the Timer panel itself
stays phase-oriented.

<a name="timeline"></a>
## Timeline

The Timeline panel has no collector of its own. At `saveData()` time,
`TimelineBuilder` merges already-stored sibling data onto one millisecond
scale: Timer framework phases, SqlLog queries, Log entries, and Cache
operations, ordered by start offset. (Mail has no captured timestamps and
stays out.) Each event carries `at`, `duration_ms`, `type`, `label`,
`panel`, `source`, and `ref` back to its inspector; the row stores
`events` plus `retained`/`omitted` counts and the request window
(`start_ms`/`end_ms`).

The inspector pages through the stored list with cursor paging on the
JSON endpoint (`cursor` over `events`, `has_more`/`matching_count`
envelope), filters by `key`, `all`, or one panel, and searches SQL,
labels, and sources. When collection caps drop events, the waterfall
says so (`Timeline incomplete: <omitted> omitted`) instead of silently
showing a partial picture.

Read work in execution order: the timeline shows whether expensive work
is isolated, repeated, or waiting on another operation. Click a
waterfall row for its detail — at, type, duration, source — and follow
the Open-inspector link straight into the owning panel.

| Signal | Useful next inspector |
|--------|----------------------|
| Query time explains much of the request | Queries, Models |
| Duration is high but query time is low | Timeline, HTTP Client, Views, Events |
| Peak memory is unexpectedly high | Models, Views, returned data |

Know what the measurements mean: timed operations have durations while
point events only show when something was observed, and related rows may
overlap — adding every row does not produce exclusive request time. Local
timings move with cold caches and machine load, so compare the same warm
path before and after a change. The timeline is not a production latency
monitor.

<a name="validation"></a>
## Validation

Validation failures are handled responses, not crashes: the form stays
on screen and the page may still return 200. The Validation panel walks
the controller view vars on shutdown — entities recursively (with cycle
and depth guards) plus plain forms — and flattens each to one row per
field: field, message, bag (the view-var name), response status,
redirect target, and failed rules. Display is static by design: stacked
articles, no search or filter.

Choose the submission profile (the POST, not the form page) and read bag,
fields, messages, and rules. Keep the submitted shape when reproducing:
a form and a JSON request can receive different responses to the same
failure. After correcting the input, submit again and check the valid
path continues — a lack of error rows alone does not prove the form
saved. Test the failure and the valid path separately; thrown or
reported failures belong to Exceptions and Logs.

<a name="variables"></a>
## Variables

Search the variable list, pick a row, and read its collapsible tree —
large graphs stay usable because each variable loads its data lazily,
with loading, empty, and error states plus Retry. Entities and forms in
the list also contribute an errors map, but explicit failures belong to
Validation: open that panel for field, rule, and message rows.

<a name="views"></a>
## Views

Search the render list, filter Application/All/Framework, and pick the
occurrence that built the visible output — a template can render several
times with different data. The detail keeps origin, passed values, and
composers; the data itself loads lazily (`GET
/debug-bar/panels/view-data?id=&render=N`) with loading and error states,
so an empty list stays instant. Compare its retained vars against what
the template reads:

```php
// In a controller action
$this->set('title', $trip->title);
```

If the template prints `$journeyTitle`, seeing `title` in the retained
vars points at the data-contract mismatch: align the key, reload, and
check the heading. Bounded vars may hide values behind toggles or class
labels; renderable objects are never executed to make a value. The panel
records composition activity, not per-template render durations — use the
Timeline for measured work. Repeated partials are normal once per row;
when one partial or data lookup runs more often than the output needs,
check the parent loop and whether repeated view work also repeats SQL.

<a name="agents"></a>
## Agents

Agents read the same retained rows the browser shows — through a JSON
API for quick scripts and through a local MCP server for coding agents.
Both apply capture-time redaction and response bounds; neither invents
missing evidence.

<a name="json-api"></a>
### JSON API

- `GET /debug-bar/requests/recent.json?limit=` — recent summaries
  (`id`, `url`, `method`, `status_code`, `content_type`,
  `requested_at`, response sizes), capped by
  `DebugBar.storage.max_requests`. Powers the switcher.
- `GET /debug-bar/panels/<id>.json?cursor=&limit=` — one panel as
  `{title, summary, data, pagination{cursor, has_more, matching_count}}`
  plus `retained`/`dropped`/`truncated` counts. The cursor pages the
  panel's `items`/`events` lists; a `decode_error` flag marks content
  that no longer unserializes.
- `GET /debug-bar/requests/panels/<id>` — the full on-the-fly switch
  payload: `{request, panels[{key, id, title, summary, count, html}], recent}`.
- `POST /debug-bar/panels/explain`, `GET
  /debug-bar/panels/view-data`, and `GET
  /debug-bar/panels/view-variable` are documented under Queries and
  Views.

<a name="mcp"></a>
### MCP

The local STDIO server runs on `crustum/mcp` (optional dependency) and
registers as `Mcp.local['cake-debugbar']` only when that package is
installed — without it the plugin loads and runs normally. Start it with:

```bash
bin/cake debug-bar mcp
```

Four read-only tools share one envelope (`version: 2`, `status:
ok|not_found`, `data`). Malformed ids fail validation; absent UUIDs
answer `not_found` naming the missing row. Unexpected failures surface
as a plain tool error, never internals.

| Tool | Reads |
|------|-------|
| `list-requests` | Recent summaries with duration, query count/time, warning flag, and per-panel counts. Filters: `method` (uppercased), `path` fragment (≤200 chars), `status` (100–599), `limit` (default 10, cap 50). No cursor — narrow the filters or raise the limit. |
| `get-panel-data` | Any stored value by JSON Pointer. `request_id` (UUID, required), `path` (default `''` for the root — start at `/panels` for names, then follow returned paths), `cursor` (default 0), `limit` (default 10, capped by `mcp.max_items`). Oversized strings chunk with `omitted_due_to_bytes`. |
| `inspect-queries` | Query groups with the browser's grouping and flags. `filter`: `all`, `attention`, `repeated`, `slow`, `read`, `write` (default `all`); `search` (≤200 chars); `sort`: `execution` or `duration`; `cursor`/`limit` (default 5). |
| `get-findings` | Stored findings: rule id, severity, panel, summary, why, location, next, action. Optional `severity` keeps that level and above. |

Bounds come from `mcp.max_items` (default `50`) and `mcp.max_bytes`
(default `100000`). Follow `next_cursor` with the same tool, path, and
filters — and remember a response limit is not capture truncation:
another page cannot recover values omitted before storage.

Debug one request with an agent: reproduce the action and keep its exact
`X-DEBUGBAR-ID`; read findings first; open one focused inspector or a
filtered query group; follow JSON Pointer paths only when the focused
result leaves something out; change the caller, recapture, and compare
like with like; lock the budget with an assertion (see Testing). If the
id expired, reproduce and capture a new one.

<a name="custom-panels"></a>
## Custom Panels

One class plus one element is a working panel — no build step. Place the
class in `src/Panel` (filename matches the class, e.g. `MyCustomPanel`
in `src/Panel/MyCustomPanel.php`):

```php
namespace App\Panel;

use Crustum\DebugBar\DebugPanel;

class MyCustomPanel extends DebugPanel
{
    // ...
}
```

Panels hook `Controller.initialize` and `Controller.shutdown` by default;
implement `implementedEvents()` for more. Collect in
`shutdown()` (usually into `$this->_data`), return a short string from
`summary()`, and the serialized data from `data()`. The element name is
the underscored class name (`session_panel.php` for `SessionPanel`);
override `title()` and `elementName()` for custom behavior. Panels
shipped by plugins work the same way with one extra requirement: set
`public string $plugin` to the plugin directory name, and register
everything in the panels map:

```php
// In src/Application.php bootstrap()
Configure::write('DebugBar.panels', [
    'Crustum/DebugBar.Packages' => false,
    'MyPlugin.MyCustom' => true,
]);
```

Panels written against `DebugKit\DebugPanel` or the `DebugKit.panels`
key keep resolving through the compatibility layer. Unknown panels render
their server HTML as-is in the new inspector and expose `data()` as JSON
with zero panel-side changes.

<a name="panel-chrome"></a>
### Shell integration: zero-registration hooks

Custom markup must stay inside the host-isolation contract: `data-dk-*`
attributes for hooks, `dk-*` classes, `--dk-*` variables, everything
under `#debugbar`, theme only through the root `data-dk-theme`. Generic
page styles never reach in, and panel styles never leak out.

Behavior comes from hooks, not panel JavaScript. The shell owns one
delegated click listener; a panel opts into each interaction by marking
up its element. Nothing needs registering — any third-party panel
follows this shape:

* **Row select** — give each row `data-dk-<ns>-entry` plus
  `data-dk-<ns>-key`; give each detail pane `data-dk-<ns>-detail`
  with the same key. Clicking a row toggles `aria-pressed`, shows the
  one matching detail, paints code blocks, and drills list into detail
  with Back support inside `focus` workspaces. Scope is the closest
  `[data-dk-<ns>-panel]`, then the closest inspector panel, then the
  root — third-party panels coexist without touching shell code. The
  bare `data-dk-entry` / `data-dk-key` / `data-dk-detail` trio works
  the same when no namespace is needed. Rows owned by dedicated
  modules (`exception`, `view`, `variable`) keep their lazy loaders
  and never auto-match; the old JSON `data-dk-selection-config`
  block still wins when present for explicit scoping.
* **Search and filter** — wrap the list in `[data-dk-active-filter]`;
  add a `data-dk-search` input; give every row `data-dk-search-text`
  (lowercase haystack), optional `data-dk-filter-group` tokens
  (space-separated, so one row can sit behind several facets) and
  `data-dk-filter-attention` for the Needs-attention tab. `data-dk-filter`
  tabs and `data-dk-select` dropdowns inside the same scope switch the
  active key. Add `data-dk-list-empty` for the empty state and
  `data-dk-visible-count` for the "N shown" counter. Filtering,
  counting, and empty states then work with no panel code.
* **Tabs** — `data-dk-tab` buttons with matching `data-dk-tab-panel`
  panes (optionally scoped by `data-dk-detail-tabs`); the shell toggles
  `aria-selected`, shows one pane, repaints code blocks, and runs the
  EXPLAIN tab action for query details automatically.
* **Sort and select** — `data-dk-sort` buttons and `data-dk-select`
  dropdowns (render pickers, filters, viewports). Models, HTTP client,
  and packages patterns are implemented generically; queries owns its
  module for grouping-aware sort.
* **Copy** — `data-dk-copy` with `data-dk-copy-value` and a
  `data-dk-copy-label` gives Copied/failed feedback with clipboard
  fallback. `data-dk-copy-all` copies every `data-dk-copy-value`
  inside the closest `[data-dk-copy-scope]` (fallback: inspector
  panel, then root), joined with newlines.
* **Panel scripts** — when markup hooks are not enough (a custom
  endpoint owned by the panel), ship a vanilla script with the plugin and
  declare it once in the element — no host layout changes, no shell edits:
```html
<script type="application/json" data-dk-panel-scripts>["/my-plugin/js/my_panel.js"]</script>
```
  The shell appends each same-origin URL once (on boot and after every
  request switch) and never twice. Scripts run in page scope under the
  host CSP, so keep them dependency-free and namespaced.
* **Server actions** — POST to the panel's endpoint with the CSRF header
  the shell attaches; render inline results and error text into the
  panel (cache clear, package check/audit, attribute warm/inspect all
  follow this shape).
* **Trees** — render values through the shared `ui/debug_tree` element;
  wrap the tree plus `ui/tree_filter` controls in `[data-dk-tree-scope]`
  and expand-all plus key/value search work with no panel code.

Capture data the Variables way instead of hand-rolling normalization:
`Crustum\DebugBar\Service\VarExporter::export($value)` keeps inner
objects (entities, dates, throwables) expandable instead of collapsing
them to bare class names, masks sensitive leaves, and returns JSON-safe
plain arrays ready for `ui/debug_tree`. `VarExporter::flatten($node)`
does the same for already-exported node trees. Never throws — strange
values collapse to a scalar marker instead of breaking capture.

<a name="cli"></a>
## CLI

Console runs are captured with the same pipeline: command name (bare,
never `/cli/...`), arguments, options, command definition, environment
(`argv`, working directory, PHP version), exit code, and duration.
`CliRecorder` opens before panels record and finishes after them, so
mid-dispatch rows attach directly instead of missing the start event.
Find command runs through `list-requests` (methods `CLI`/`JOB`) and
inspect them exactly like web requests.

Two commands ship with the plugin:

```bash
bin/cake benchmark https://example.test/slow-page
bin/cake debug-bar mcp
```

`benchmark` hits a fully qualified URL and reports timings;
`debug-bar mcp` starts the local MCP server (usually from `mcp.json`).

<a name="testing"></a>
## Testing

`Crustum\DebugBar\TestSuite\DebugBarAssertionsTrait` brings the same
performance assertions the inspector is built on into your app's test
suite. Adding it arms query logging on every configured connection
(except the toolbar store); only queries logged after setup count, so
keep schema DDL in `setUpBeforeClass` (or seed before measuring):

```php
class ArticlesTest extends TestCase
{
    use DebugBarAssertionsTrait;

    public function testIndexStaysLean(): void
    {
        $this->Articles->find()->all();

        $this->assertQueryCountAtMost(3);
        $this->assertNoNPlusOne();
    }
}
```

| Assertion | Meaning |
|-----------|---------|
| `assertQueryCountAtMost($max, $connection = null)` | At most `$max` queries logged after setup |
| `assertQueryTimeAtMost($ms, $connection = null)` | Total logged query time within `$ms` |
| `assertNoNPlusOne($maxRepeats = 5, $connection = null)` | No fingerprint group repeats past the limit with varying bindings |
| `assertDurationAtMost($ms, $callback)` | The callable runs within `$ms`; returns its result |
| `assertNoErrors()` | No throwables recorded through the exceptions panel |

`debugBarNewQueries($connection = null)` reads the raw post-setup query
rows for custom assertions.
