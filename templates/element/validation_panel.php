<?php
/**
 * Validation panel element.
 *
 * One compact table for all errors by design — no search/filter. Columns
 * Field (bag-prefixed dotted path), Message and Failed rules keep five
 * errors to five rows instead of five full-screen cards.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $errors Validation error rows
 */
use function Cake\Core\h;

$errors = array_values((array)($errors ?? []));
$count = count($errors);
$meta = [];
if ($errors !== []) {
    $first = (array)$errors[0];
    if ((string)($first['status'] ?? '') !== '') {
        $meta[] = 'Status ' . (string)$first['status'];
    }
    if ((string)($first['redirect'] ?? '') !== '') {
        $meta[] = 'Redirect ' . (string)$first['redirect'];
    }
}
?>
<div data-dk-validation-panel>
    <?php if ($errors === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', [
        'message' => 'No validation errors.',
        'success' => true,
    ]) ?>
    <?php else : ?>
    <p class="dk:min-w-0 dk:text-xs dk:font-semibold dk:text-zinc-600 dk:dark:text-zinc-300">
        <?= number_format($count) ?> <?= $count === 1 ? 'error' : 'errors' ?>
        <?php if ($meta !== []) : ?>
        <span class="dk:ml-1 dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"><?= h(implode(' · ', $meta)) ?></span>
        <?php endif; ?>
    </p>
    <table class="dk:mt-2 dk:w-full dk:min-w-0 dk:border-collapse dk:text-left dk:text-xs">
        <thead>
            <tr class="dk:border-b dk:border-zinc-200 dk:dark:border-zinc-800">
                <th scope="col" class="dk:py-1.5 dk:pr-3 dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400">Field</th>
                <th scope="col" class="dk:py-1.5 dk:pr-3 dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400">Message</th>
                <th scope="col" class="dk:py-1.5 dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400">Failed rules</th>
            </tr>
        </thead>
        <tbody class="dk:divide-y dk:divide-zinc-200/90 dk:dark:divide-zinc-800">
            <?php foreach ($errors as $error) :
                $error = (array)$error;
                $rules = array_values((array)($error['failed_rules'] ?? []));
                ?>
            <tr data-dk-validation-entry>
                <td class="dk:min-w-0 dk:break-words dk:py-1.5 dk:pr-3 dk:font-bold dk:text-zinc-900 dk:dark:text-zinc-100"><?= h((string)($error['field'] ?? 'unknown')) ?></td>
                <td class="dk:min-w-0 dk:break-words dk:py-1.5 dk:pr-3 dk:text-zinc-700 dk:dark:text-zinc-200"><?= h((string)($error['message'] ?? '')) ?></td>
                <td class="dk:whitespace-nowrap dk:py-1.5 dk:font-mono dk:text-zinc-500 dk:dark:text-zinc-400"><?= h(implode(', ', $rules)) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
