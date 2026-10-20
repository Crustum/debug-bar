<?php
/**
 * Hostile fixture page: neutral data with clashing generic styles.
 *
 * The inline style block deliberately fights generic selectors and a
 * few package hooks; shell scoping must win anyway.
 *
 * @var \Cake\View\View $this
 * @var string $heading Page heading.
 */
use function Cake\Core\h;
?>
<!doctype html>
<html data-theme="dark">
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($heading) ?></title>
        <style>
            body { font-family: serif; }
            button { background: rgb(255, 0, 0); color: rgb(0, 128, 0); height: 91px; }
            a { background: rgb(255, 0, 255); color: rgb(0, 128, 0); }
            h1 { margin: 31px; }
            pre, code { background: rgb(243, 243, 243); color: rgb(0, 0, 0); }
            [data-dk-toolbar-shell] { background: rgb(255, 0, 0); height: 91px; }
            [data-dk-inspector-nav-item] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); }
        </style>
    </head>
    <body>
        <main>
            <h1 data-testid="host-page"><?= h($heading) ?></h1>
            <button type="button" data-testid="host-button">Host button</button>
            <a href="/browser-fixtures/profiled">Host link</a>
        </main>
    </body>
</html>
