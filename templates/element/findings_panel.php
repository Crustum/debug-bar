<?php
/**
 * Findings panel element.
 *
 * Rule list with Why/Next/Action plus a link to the source inspector.
 * Server HTML only — no dedicated JS module unless filters are needed.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $findings List of finding rows with `rule_id`, `severity`,
 *   `panel`, `summary`, `why`, `location`, `next`, `action`
 * @var int $retained Total findings before the cap
 * @var int $omitted Findings dropped by `findings.max_findings`
 */
use function Cake\Core\h;

$findings = array_values((array)($findings ?? []));
$retained = (int)($retained ?? count($findings));
$omitted = (int)($omitted ?? 0);
?>
<div data-dk-findings>
    <?php if ($omitted > 0) : ?>
    <p class="dk-banner" role="note">Showing <?= h((string)($retained - $omitted)) ?> of <?= h((string)$retained) ?> findings.</p>
    <?php endif; ?>
    <?php if ($findings === []) : ?>
    <div data-dk-detail-empty class="dk-detail-empty">No findings — nothing slow, repeated, or failing in this request.</div>
    <?php else : ?>
    <div data-dk-findings-list class="dk-list-body">
        <?php foreach ($findings as $finding) :
            $finding = (array)$finding;
            $severity = (string)($finding['severity'] ?? 'info');
            $tone = $severity === 'error' ? 'bad' : ($severity === 'warning' ? 'warn' : 'neutral');
            ?>
        <article data-dk-finding="<?= h((string)($finding['rule_id'] ?? '')) ?>" class="dk-validation-entry">
            <div class="dk-detail-header">
                <span data-dk-operation-tone="<?= h($tone) ?>" class="dk-operation-badge"><?= h(strtoupper($severity)) ?></span>
                <h3 class="dk-detail-title"><?= h((string)($finding['summary'] ?? '')) ?></h3>
            </div>
            <?php if ((string)($finding['location'] ?? '') !== '') : ?>
            <p class="dk-query-note"><?= h((string)$finding['location']) ?></p>
            <?php endif; ?>
            <?php if ((string)($finding['why'] ?? '') !== '') : ?>
            <div class="dk-explanation">
                <p class="dk-explanation-title">Why</p>
                <p class="dk-explanation-text"><?= h((string)$finding['why']) ?></p>
            </div>
            <?php endif; ?>
            <?php if ((string)($finding['next'] ?? '') !== '') : ?>
            <p class="dk-query-note">Next: <?= h((string)$finding['next']) ?></p>
            <?php endif; ?>
            <div class="dk-error-actions">
                <button
                    type="button"
                    data-dk-open-inspector="<?= h((string)($finding['panel'] ?? '')) ?>"
                    class="dk-button dk-button-outline"
                ><?= h((string)($finding['action'] ?? 'Open inspector')) ?></button>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
