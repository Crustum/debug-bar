<?php
/**
 * Toolbar layout.
 *
 * Minimal host-frame page: reset margin/overflow so the 40x40 frame fits
 * the mini launcher exactly, then dist CSS plus the content, the packed
 * shell bundle, and queued scripts. Dist URLs carry `?filemtime` cache-busting
 * resolved from the plugin webroot (raw tags over UrlHelper URLs, since
 * HtmlHelper would leak `timestamp` as a tag attribute).
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 */
use function Cake\Core\h;
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= isset($title) ? h($title) : 'DebugBar Toolbar' ?></title>
        <style>
            html, body {
                margin: 0;
                padding: 0;
                height: 100%;
                overflow: hidden;
                background: transparent;
            }
            body {
                display: block;
            }
        </style>
        <?php
        $debugbarCss = (string)$this->Url->css('Crustum/DebugBar./dist/debugbar', ['timestamp' => 'force']);
        $shellJs = (string)$this->Url->script('Crustum/DebugBar./dist/shell', ['timestamp' => 'force']);
        ?>
        <link rel="stylesheet" href="<?= h($debugbarCss) ?>">
    </head>
    <body>
    <?= $this->fetch('content') ?>
    <script type="module" src="<?= h($shellJs) ?>"></script>
    </body>
    <?= $this->fetch('script') ?>
</html>
