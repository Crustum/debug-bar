<?php
/**
 * Inspector evidence element.
 *
 * Wraps a highlighted code block with its language tag.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $language `json`, `http`, `php` or `sql`
 * @var string $content Raw source text (escaped inside)
 */
use function Cake\Core\h;

$language = (string)($language ?? 'json');
if (!in_array($language, ['json', 'http', 'php', 'sql'], true)) {
    $language = 'json';
}
?>
<div data-dk-evidence data-dk-evidence-language="<?= h($language) ?>" class="dk-evidence">
    <pre class="dk-code"><code data-dk-language="<?= h($language) ?>"><?= h((string)($content ?? '')) ?></code></pre>
</div>
