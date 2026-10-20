# CakePHP DebugBar Plugin

The **DebugBar** plugin provides a debugging toolbar and left-side inspector for CakePHP applications. It captures the current request into an SQLite store (`requests` + `panels`) and surfaces per-panel evidence — request data, SQL queries with slow/repeated/N+1 analysis, a merged timeline, findings, cache, logs, exceptions, events, models, views, HTTP client, mail, and more — through server-rendered fragments plus a bounded JSON API.

The shell is dependency-free (vanilla ES modules, no jQuery, no Livewire) with a request-switcher for recent requests, and read-only MCP tools let coding agents inspect the same captured data. Any plugin can add a panel with one `DebugPanel` subclass and one template element.

> **Warning:** DebugBar is only intended for single-user local development
> environments. It captures configuration data, environment variables, and
> SQL bindings — keep it out of shared development environments, staging,
> or any place where that data must stay hidden.

## Requirements

* PHP 8.2+
* SQLite (`pdo_sqlite`) for the default `debug_bar` store, or a dedicated
  `debug_bar` datasource.

See [Versions.md](docs/Versions.md) for the supported CakePHP versions.

## Installation

Install the plugin with [Composer](https://getcomposer.org/) from your
CakePHP project's ROOT directory (where the **composer.json** file is
located), keeping it in `require-dev` so production installs skip it:

```sh
composer require --dev crustum/debug-bar
```

Load the plugin:

```sh
bin/cake plugin load Crustum/DebugBar
```

If the toolbar does not appear, see
[Troubleshooting](docs/index.md#troubleshooting) — the usual causes are
a missing `pdo_sqlite` extension, a hostname outside
`DebugBar.safeTld`, and the Authorization plugin blocking toolbar
requests (`DebugBar.ignoreAuthorization`).

## Documentation

For documentation, as well as tutorials, see the [docs](docs/index.md) directory of this repository.
To add your own inspector panel with zero JavaScript, see
[Custom Panels](docs/index.md#custom-panels).

## License

Licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) License. Redistributions of the source code included in this repository must retain the copyright notice found in each file.
