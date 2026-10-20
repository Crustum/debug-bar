<?php
/**
 * Inspector source fact element.
 *
 * File reference with the application/vendor frame badge. Paths come from
 * `Service\AppPath` classification (slash-agnostic, no hardcoded needles).
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $file File path
 * @var int|string $line Line number
 * @var bool $app True for application frames, false for vendor
 */
use function Cake\Core\h;

$file = (string)($file ?? '');
$line = (string)($line ?? '');
$isApp = (bool)($app ?? true);
$ref = $file . ($line !== '' ? ':' . $line : '');
?>
<span data-dk-source-fact class="dk-source-fact">
    <span data-dk-source-frame="<?= $isApp ? 'app' : 'vendor' ?>" class="dk-source-frame"><?= $isApp ? 'App' : 'Vendor' ?></span>
    <code class="dk-code"><?= h($ref) ?></code>
</span>
