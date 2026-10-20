<?php
/**
 * Context fixture page with view variables for the views inspector.
 *
 * @var \Cake\View\View $this
 * @var string $label View label.
 * @var array<array<string, mixed>> $rows Rendered rows.
 */
use function Cake\Core\h;
?>
<!doctype html>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($label) ?></title>
    </head>
    <body>
        <main>
            <h1 data-testid="host-page"><?= h($label) ?></h1>
            <p>Reference <?= h((string)($rows[0]['reference'] ?? '')) ?></p>
        </main>
    </body>
</html>
