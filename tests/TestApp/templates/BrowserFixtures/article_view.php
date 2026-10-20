<?php
/**
 * Article page for the browser fixture flow.
 *
 * @var \Cake\View\View $this
 * @var string $heading Page heading.
 * @var \Cake\ORM\Entity $article Article entity.
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
            <p data-testid="article-body"><?= h((string)$article->body) ?></p>
            <p><a data-testid="back-to-articles" href="/browser-fixtures/articles">Back to articles</a></p>
        </main>
    </body>
</html>
