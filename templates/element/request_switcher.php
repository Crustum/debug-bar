<?php
/**
 * Request switcher element.
 *
 * One switcher per scope (`toolbar`, `corner`, `header`, `header-mobile`).
 * Closed trigger shows METHOD tag + URL + status + response size (upstream
 * `request-switcher` contract: `corner` scope stacks compact, every other
 * scope shares the method-badge layout). Open picker options show METHOD
 * tag + URL + status + response size + unread dot.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $scope Switcher scope
 * @var array $switcherRequests Recent request rows with `id`, `url`, `method`, `status_code`, `response_size`
 * @var string $requestId Current request id
 * @var string $method Current request method
 * @var string $url Current request url
 * @var int $statusCode Current response status
 * @var string $statusTone `good`, `warn` or `bad`
 * @var string|null $responseSize Current response size label (e.g. `44.52 KB`)
 */
use function Cake\Core\h;

$scope = (string)($scope ?? 'toolbar');
$isCorner = $scope === 'corner';
$switcherRequests = array_values((array)($switcherRequests ?? []));
$requestId = (string)($requestId ?? '');
$method = (string)($method ?? 'GET');
$url = (string)($url ?? '/');
$statusCode = (int)($statusCode ?? 0);
$statusTone = (string)($statusTone ?? 'good');
$responseSize = isset($responseSize) && is_string($responseSize) && $responseSize !== '' ? $responseSize : null;
$switcherClass = 'dk-request-switcher';
if ($scope === 'header') {
    $switcherClass .= ' dk-header-switcher';
}
$formatRowSize = static function ($row): ?string {
    $method = (string)($row['method'] ?? 'GET');
    if ($method === 'CLI' || $method === 'JOB') {
        return null;
    }
    if (isset($row['response_size']) && is_string($row['response_size']) && $row['response_size'] !== '') {
        return $row['response_size'];
    }
    $bytes = $row['response_size_bytes'] ?? null;
    if (!is_int($bytes) || $bytes < 0) {
        return null;
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }

    return number_format($bytes) . ' B';
};
$statusClass = static function (int $code): string {
    if ($code >= 500) {
        return 'dk:text-red-600 dk:dark:text-red-300';
    }
    if ($code >= 400) {
        return 'dk:text-amber-600 dk:dark:text-amber-300';
    }
    if ($code >= 300) {
        return 'dk:text-sky-600 dk:dark:text-sky-300';
    }
    if ($code >= 200) {
        return 'dk:text-emerald-600 dk:dark:text-emerald-300';
    }

    return 'dk:text-zinc-500 dk:dark:text-zinc-400';
};
?>
<div data-dk-request-switcher="<?= h($scope) ?>" class="<?= h($switcherClass) ?>">
    <button
        type="button"
        data-dk-requests-refresh
        aria-label="Refresh list"
        title="Refresh list"
        class="dk-icon-button"
    >
        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'refresh', 'size' => '4']) ?>
    </button>
    <button
        type="button"
        data-dk-request-trigger="<?= h($scope) ?>"
        aria-haspopup="listbox"
        aria-expanded="false"
        aria-label="Choose request"
        title="<?= h($method . ' ' . $url) ?>"
        class="<?= $isCorner ? 'dk-corner-request' : 'dk-request-trigger' ?>"
    >
        <?php if ($isCorner) : ?>
        <span class="dk-corner-request-body">
            <span class="dk-corner-request-title">
                <span data-dk-current-request-method class="dk-method-tag dk-method-tag-plain"><?= h($method) ?></span>
                <span data-dk-current-request-url class="dk-corner-request-url"><?= h($url) ?></span>
            </span>
            <span class="dk-request-meta">
                <span data-dk-current-request-status data-dk-status-code="<?= $statusCode ?>" class="<?= h($statusClass($statusCode)) ?>"><?= $statusCode ?></span>
                <?php if ($responseSize !== null) : ?>
                <span data-dk-current-request-size class="dk-request-size"><?= h($responseSize) ?></span>
                <?php endif; ?>
            </span>
        </span>
        <?php else : ?>
        <span data-dk-current-request-method class="dk-method-tag"><?= h($method) ?></span>
        <span class="dk-request-label">
            <span class="dk-request-title">
                <span data-dk-current-request-url><?= h($url) ?></span>
            </span>
            <span class="dk-request-meta">
                <span data-dk-current-request-status data-dk-status-code="<?= $statusCode ?>" class="<?= h($statusClass($statusCode)) ?>"><?= $statusCode ?></span>
                <?php if ($responseSize !== null) : ?>
                <span data-dk-current-request-size class="dk-request-size"><?= h($responseSize) ?></span>
                <?php endif; ?>
            </span>
        </span>
        <?php endif; ?>
        <span data-dk-request-badge class="dk-request-badge" hidden></span>
    </button>
    <ul
        data-dk-request-picker="<?= h($scope) ?>"
        role="listbox"
        aria-label="Recent requests"
        tabindex="-1"
        class="dk-picker"
        hidden
    >
        <?php foreach ($switcherRequests as $index => $row) :
            $rowStatus = (int)($row['status_code'] ?? 0);
            $rowTone = $rowStatus >= 500 ? 'bad' : ($rowStatus >= 400 ? 'warn' : 'good');
            $rowSize = $formatRowSize($row);
            ?>
        <li
            role="option"
            tabindex="-1"
            aria-selected="<?= (string)$row['id'] === $requestId ? 'true' : 'false' ?>"
            data-dk-request-option="<?= h((string)$row['id']) ?>"
            data-dk-request-index="<?= (int)$index ?>"
            data-dk-status-code="<?= $rowStatus ?>"
            <?php if ($rowSize !== null) : ?>data-dk-response-size="<?= h($rowSize) ?>"<?php endif; ?>
            title="<?= h((string)($row['method'] ?? 'GET') . ' ' . (string)($row['url'] ?? '/')) ?>"
            class="dk-picker-option"
        >
            <span data-dk-status-tone="<?= $rowTone ?>" class="dk-status-dot"></span>
            <span class="dk-method-tag"><?= h((string)($row['method'] ?? 'GET')) ?></span>
            <span class="dk-picker-option-body">
                <span class="dk-picker-option-label">
                    <?= h((string)($row['url'] ?? '/')) ?>
                </span>
                <span class="dk-picker-option-meta">
                    <span data-dk-option-status class="<?= h($statusClass($rowStatus)) ?>"><?= $rowStatus ?></span>
                    <?php if ($rowSize !== null) : ?>
                    <span data-dk-option-size class="dk-request-size"><?= h($rowSize) ?></span>
                    <?php endif; ?>
                </span>
            </span>
            <span data-dk-request-unread class="dk-unread-dot" hidden></span>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
