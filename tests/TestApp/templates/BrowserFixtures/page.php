<?php
/**
 * Shared browser fixture page.
 *
 * Full document on purpose: the toolbar injection requires a body tag.
 *
 * @var \Cake\View\View $this
 * @var string $heading Page heading.
 */
use function Cake\Core\h;
?>
<!doctype html>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($heading) ?></title>
    </head>
    <body>
        <main>
            <h1 data-testid="host-page"><?= h($heading) ?></h1>
        </main>
    </body>
</html>
