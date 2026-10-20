<?php
/**
 * Article form for the browser fixture flow.
 *
 * @var \Cake\View\View $this
 * @var string $heading Page heading.
 * @var \Cake\ORM\Entity $article Article entity with errors.
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
            <?php if ($article->hasErrors()) : ?>
            <ul data-testid="form-errors">
                <?php foreach ($article->getErrors() as $field => $messages) : ?>
                <li><?= h((string)$field) ?>: <?= h(implode(', ', (array)$messages)) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <form data-testid="article-form" method="post" action="/browser-fixtures/article-add">
                <label>Title <input type="text" name="title" value="<?= h((string)$article->title) ?>"></label>
                <label>Body <textarea name="body"><?= h((string)$article->body) ?></textarea></label>
                <button type="submit">Save</button>
            </form>
        </main>
    </body>
</html>
