<?php
/**
 * Articles index for the browser fixture flow.
 *
 * @var \Cake\View\View $this
 * @var string $heading Page heading.
 * @var array<\Cake\ORM\Entity> $rows Article rows.
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
            <p><a data-testid="new-article" href="/browser-fixtures/article-add">New article</a></p>
            <ul>
                <?php foreach ($rows as $row) : ?>
                <li>
                    <a data-testid="article-<?= (int)$row->id ?>" href="/browser-fixtures/article-view/<?= (int)$row->id ?>"><?= h((string)$row->title) ?></a>
                </li>
                <?php endforeach; ?>
            </ul>
        </main>
    </body>
</html>
