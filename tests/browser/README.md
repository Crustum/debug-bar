# Browser specs

Real-browser coverage for the toolbar shell, run with `node:test` and a
local PHP server. No PHP test runner drives the browser; the specs below
start their own server and close it on teardown.

## Run

```powershell
npm run test:browser
```

Single file:

```powershell
node --test tests/browser/smoke.test.js
```

Files run serially (`--test-concurrency=1` in `test:browser`):
parallel files share CPU between PHP servers and browsers and time out
cold boots. One invocation per checkout: parallel runs must set distinct
`DEBUGBAR_BROWSER_PORT` values (default `8765`) since server processes
share no state. `PHP_BINARY` overrides the PHP executable when it is not
on `PATH`.

## Layout

- `docroot/` — front controller + router for `php -S`; boots the same
  test application as the PHPUnit suite (middleware, routes, assets).
- `server.mjs` — starts/stops the server and waits for the port.
- `helpers.mjs` — browser launch, settled-rendering waits, inspector
  selection assertion, JS-error collection.
- `fixtures.mjs` — page paths used by the specs (grows per milestone).
- `smoke.test.js` — server + injection + shell boot wiring proof.
