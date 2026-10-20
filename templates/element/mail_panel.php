<?php
/**
 * Mail panel element.
 *
 * Faithful port of the upstream `mail` inspector: summary with total
 * duration, search + All/Attachments select, selectable rows (subject,
 * Sent status, primary recipient, duration), and detail with a header
 * (subject + Open preview), segmented Preview/Message/Source tabs, an
 * isolated preview iframe with viewport + format controls, message
 * definition lists with attachment downloads, and delivery details plus
 * the send callsite/stack. Interactive states are server-rendered
 * (`aria-pressed`/`aria-selected`, `hidden`); the boot script in
 * `Requests/view` owns search/filter/selection/preview switching over
 * the `data-dk-*` hooks. Styling rides the shared `dk:` Tailwind
 * utilities.
 *
 * CakePHP notes (no fake data): only sent mail is captured, so every row
 * reads Sent; there is no queue/worker, hence no delayed/queued states,
 * no related profiles and no EML download (raw MIME is not retained);
 * attachment bodies beyond the transport budget keep metadata with a
 * null body and count as omitted.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $emails Email records from `DebugBarTransport`
 * @var \Crustum\DebugBar\Model\Entity\Panel|null $panel
 */
use Crustum\DebugBar\Service\CallsiteCapture;
use Crustum\DebugBar\Service\QueryAnalyzer;
use function Cake\Core\h;

$emails = array_values((array)($emails ?? []));
$panelId = isset($panel) && is_object($panel) ? (string)$panel->id : '';
$formatBytes = static function (?int $bytes): string {
    if ($bytes === null || $bytes < 0) {
        return 'Size unavailable';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }

    return number_format($bytes) . ' B';
};
$addresses = static function ($value): string {
    $flat = [];
    foreach ((array)$value as $item) {
        if (is_array($item)) {
            $item = implode(' ', array_filter([
                (string)($item['name'] ?? ''),
                (string)($item['address'] ?? ''),
            ]));
        }
        $item = trim((string)$item);
        if ($item !== '') {
            $flat[] = $item;
        }
    }

    return implode(', ', $flat);
};
$parsed = [];
foreach ($emails as $index => $email) {
    $email = (array)$email;
    $headers = (array)($email['headers'] ?? []);
    $message = (array)($email['message'] ?? []);
    $subject = (string)($headers['Subject'] ?? '');
    if ($subject === '') {
        $subject = '(No subject)';
    }
    $to = $addresses($headers['to'] ?? []);
    $primary = $to !== '' ? explode(', ', $to)[0] : ($addresses($headers['cc'] ?? []) !== '' ? explode(', ', $addresses($headers['cc'] ?? []))[0] : 'No recipient captured');
    $attachments = array_values((array)($email['attachments'] ?? []));
    $attachmentNames = [];
    foreach ($attachments as $attachment) {
        $attachmentNames[] = (string)((array)$attachment)['name'] ?? '';
    }
    $hasHtml = (string)($message['html'] ?? '') !== '';
    $hasText = (string)($message['text'] ?? '') !== '';
    $durationMs = (float)($email['duration_ms'] ?? 0);
    $parsed[] = [
        'key' => 'mail-' . $index,
        'execution' => $index + 1,
        'subject' => $subject,
        'headers' => $headers,
        'message' => $message,
        'to' => $to,
        'primary' => $primary,
        'attachments' => $attachments,
        'attachments_omitted' => (int)($email['attachments_omitted'] ?? 0),
        'has_html' => $hasHtml,
        'has_text' => $hasText,
        'duration_ms' => $durationMs,
        'duration_label' => QueryAnalyzer::formatDuration($durationMs),
        'transport' => (string)($email['transport'] ?? ''),
        'callsite' => (array)($email['callsite'] ?? []),
        'stack' => array_values((array)($email['stack'] ?? [])),
        'search' => strtolower(implode(' ', array_filter([
            $subject,
            $to,
            $addresses($headers['from'] ?? []),
            $addresses($headers['cc'] ?? []),
            implode(' ', $attachmentNames),
        ]))),
    ];
}
$count = count($parsed);
$attachmentCount = 0;
$totalMs = 0.0;
foreach ($parsed as $row) {
    if ($row['attachments'] !== []) {
        $attachmentCount++;
    }
    $totalMs += $row['duration_ms'];
}
$filters = ['all' => ['All', $count]];
if ($attachmentCount > 0) {
    $filters['attachments'] = ['Attachments', $attachmentCount];
}
$segmentedTabClass = 'dk:relative dk:flex dk:min-w-0 dk:items-baseline dk:justify-center dk:gap-1.5 dk:whitespace-nowrap dk:rounded-md dk:border dk:border-transparent dk:bg-transparent dk:px-3 dk:py-1.5 dk:text-xs dk:font-semibold dk:text-zinc-500 dk:transition-[background-color,color,box-shadow] dk:hover:bg-white/60 dk:hover:text-zinc-950 dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:aria-pressed:bg-white dk:aria-pressed:text-zinc-950 dk:aria-pressed:shadow-sm dk:aria-pressed:hover:bg-white dk:aria-pressed:hover:text-zinc-950 dk:aria-selected:bg-white dk:aria-selected:text-zinc-950 dk:aria-selected:shadow-sm dk:aria-selected:hover:bg-white dk:aria-selected:hover:text-zinc-950 dk:dark:text-zinc-400 dk:dark:hover:bg-zinc-800/70 dk:dark:hover:text-white dk:dark:aria-pressed:bg-zinc-800 dk:dark:aria-pressed:text-white dk:dark:aria-selected:bg-zinc-800 dk:dark:aria-selected:text-white';
?>
<div data-dk-mail-panel data-dk-active-filter="all" class="dk:space-y-4 dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col dk:lg:space-y-0">
    <?php if ($parsed === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No emails were sent during this request']) ?>
    <?php else : ?>
    <?php
    ob_start();
    ?>
    <p
        data-dk-mail-summary
        aria-live="polite"
        class="dk:min-w-0 dk:text-xs dk:font-semibold dk:text-zinc-600 dk:dark:text-zinc-300"
    >
        <?= number_format($count) ?> <?= $count === 1 ? 'message' : 'messages' ?>
        <span class="dk:mt-0.5 dk:block dk:text-xs dk:font-medium dk:tabular-nums dk:text-zinc-400">
            <?= h(QueryAnalyzer::formatDuration($totalMs)) ?> total
        </span>
        <span
            data-dk-visible-count
            hidden
            class="dk:ml-1 dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"
        ></span>
    </p>
    <div
        data-dk-inspector-list-controls
        class="dk:grid dk:items-start dk:gap-x-2 dk:gap-y-3 dk:grid-cols-[minmax(0,1fr)_8.75rem]"
    >
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block dk:min-w-0">
                <span class="dk:sr-only">Search mail</span>
                <input
                    data-dk-search="mail"
                    type="search"
                    placeholder="Search subject or recipient"
                    aria-label="Search mail"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block">
                <span class="dk:sr-only">Filter mail</span>
                <select
                    data-dk-select="mail-filter"
                    class="dk:h-9 dk:w-full dk:appearance-none dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-8 dk:pl-3 dk:text-xs dk:font-semibold dk:outline-none dk:transition dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                >
                    <?php foreach ($filters as $value => [$label, $filterCount]) : ?>
                    <option value="<?= h($value) ?>"><?= h($label) ?> (<?= number_format($filterCount) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:right-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
    </div>
    <?php
    $controls = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-mail-list aria-label="Sent mail">
        <?php foreach ($parsed as $index => $row) :
            $pressed = $index === 0;
            ?>
        <button
            type="button"
            data-dk-mail-entry
            data-dk-mail-key="<?= h($row['key']) ?>"
            data-dk-filter-group="<?= $row['attachments'] !== [] ? 'attachments' : 'none' ?>"
            data-dk-search-text="<?= h($row['search']) ?>"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            class="dk:grid dk:h-auto dk:w-full dk:grid-cols-[minmax(0,1fr)_auto] dk:items-baseline dk:gap-x-3 dk:gap-y-1 dk:px-3 dk:py-2.5 dk:text-left dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:sm:py-3<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
        >
            <span class="dk:col-start-1 dk:row-start-1 dk:min-w-0 dk:truncate dk:text-xs dk:font-bold dk:text-zinc-900 dk:dark:text-zinc-100"><?= h($row['subject']) ?></span>
            <span class="dk:col-start-2 dk:row-start-1 dk:w-full dk:truncate dk:text-right dk:text-xs dk:font-bold dk:text-emerald-600 dk:dark:text-emerald-300">Sent</span>
            <span class="dk:col-start-1 dk:row-start-2 dk:min-w-0 dk:truncate dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">To <?= h($row['primary']) ?></span>
            <span class="dk:col-start-2 dk:row-start-2 dk:w-full dk:truncate dk:text-right dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($row['duration_label']) ?></span>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No mail matches this filter and search.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-detail-pane class="dk:min-w-0">
        <div data-dk-detail-empty hidden>No message is selected. Adjust the filter or search.</div>
        <?php foreach ($parsed as $index => $row) :
            $pressed = $index === 0;
            $tabScope = 'mail:' . $row['key'];
            $headers = $row['headers'];
            $from = $addresses($headers['from'] ?? []);
            $sender = $addresses($headers['sender'] ?? []);
            $htmlUrl = $panelId !== '' && $row['has_html'] ? $this->Url->build([
                'plugin' => 'Crustum/DebugBar',
                'controller' => 'MailPreview',
                'action' => 'sent',
                $panelId,
                $index,
                '?' => ['part' => 'html'],
            ]) : '';
            $textUrl = $panelId !== '' && $row['has_text'] ? $this->Url->build([
                'plugin' => 'Crustum/DebugBar',
                'controller' => 'MailPreview',
                'action' => 'sent',
                $panelId,
                $index,
                '?' => ['part' => 'text'],
            ]) : '';
            $defaultUrl = $htmlUrl !== '' ? $htmlUrl : $textUrl;
            $addressRows = [
                ['From', $from],
                ['To', $row['to']],
                ['CC', $addresses($headers['cc'] ?? [])],
                ['BCC', $addresses($headers['bcc'] ?? [])],
                ['Reply to', $addresses($headers['replyTo'] ?? [])],
            ];
            $deliveryRows = [
                ['Sender', $sender],
                ['Return path', $addresses($headers['returnPath'] ?? [])],
                ['Date', (string)($headers['date'] ?? '')],
                ['Transport', $row['transport']],
                ['Duration', $row['duration_label']],
            ];
            $callsite = (array)$row['callsite'];
            $callsiteFile = (string)($callsite['file'] ?? '');
            ?>
        <section
            data-dk-mail-detail="<?= h($row['key']) ?>"
            aria-label="Selected mail details"
            <?= $pressed ? '' : 'hidden' ?>
        >
            <div class="dk:flex dk:flex-col">
                <header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <div class="dk:grid dk:grid-cols-[minmax(0,1fr)_auto] dk:items-start dk:gap-x-3 dk:gap-y-2">
                        <div class="dk:min-w-0">
                            <h3 class="dk:break-words dk:text-base dk:font-bold dk:leading-6"><?= h($row['subject']) ?></h3>
                            <dl class="dk:mt-2 dk:space-y-2">
                                <div class="dk:grid dk:grid-cols-[4.75rem_minmax(0,1fr)] dk:items-baseline dk:gap-2">
                                    <dt class="dk:text-xs dk:font-semibold dk:text-zinc-400">Recipients</dt>
                                    <dd title="<?= h($row['to']) ?>" class="dk:flex dk:min-w-0 dk:items-baseline dk:gap-2">
                                        <span class="dk:truncate dk:text-xs dk:font-semibold dk:text-zinc-700 dk:dark:text-zinc-200"><?= h($row['to'] !== '' ? $row['to'] : 'No recipient captured') ?></span>
                                    </dd>
                                </div>
                                <div class="dk:grid dk:grid-cols-[4.75rem_minmax(0,1fr)] dk:items-baseline dk:gap-2">
                                    <dt class="dk:text-xs dk:font-semibold dk:text-zinc-400">Sender</dt>
                                    <dd title="<?= h($from) ?>" class="dk:min-w-0">
                                        <span class="dk:block dk:truncate dk:text-xs dk:font-medium dk:text-zinc-600 dk:dark:text-zinc-300"><?= h($from !== '' ? $from : 'No sender captured') ?></span>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <?php if ($defaultUrl !== '') : ?>
                        <a
                            href="<?= h($defaultUrl) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            title="Open preview in a new tab"
                            aria-label="Open preview in a new tab"
                            class="dk:inline-flex dk:size-9 dk:shrink-0 dk:items-center dk:justify-center dk:rounded-xl dk:text-zinc-700 dk:transition-colors dk:hover:text-indigo-600 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-300 dk:dark:hover:text-indigo-300"
                        >
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'external-link', 'size' => '4']) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </header>
                <div role="tablist" aria-label="Mail detail" data-dk-detail-tabs="<?= h($tabScope) ?>" class="dk:border-b dk:border-zinc-200/90 dk:px-3 dk:py-2.5 dk:sm:px-4 dk:dark:border-zinc-800 dk:grid dk:grid-cols-1 dk:justify-items-center dk:gap-2">
                    <div role="presentation" class="dk:min-w-0 dk:flex dk:rounded-lg dk:bg-zinc-100/70 dk:p-1 dk:dark:bg-zinc-900/70">
                        <?php foreach (['preview' => ['Preview', 'eye'], 'message' => ['Message', 'mail'], 'source' => ['Source', 'code']] as $tabKey => [$tabLabel, $tabIcon]) : ?>
                        <button
                            type="button"
                            role="tab"
                            aria-selected="<?= $tabKey === 'preview' ? 'true' : 'false' ?>"
                            aria-label="<?= h($tabLabel) ?>"
                            data-dk-tab="<?= h($tabScope . ':' . $tabKey) ?>"
                            class="<?= h($segmentedTabClass) ?> dk:h-auto"
                        >
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => $tabIcon, 'size' => '3.5', 'class' => 'dk:sm:hidden']) ?>
                            <span class="dk:hidden dk:sm:inline"><?= h($tabLabel) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div data-dk-tab-panel="<?= h($tabScope) ?>:preview" class="dk:flex dk:flex-col">
                    <?php if ($defaultUrl !== '') : ?>
                    <div class="dk:flex dk:items-center dk:gap-2 dk:border-b dk:border-zinc-200/90 dk:px-3 dk:py-2 dk:sm:px-4 dk:dark:border-zinc-800">
                        <div role="group" aria-label="Mail preview width" class="dk:flex dk:rounded-lg dk:bg-zinc-100/70 dk:p-1 dk:dark:bg-zinc-900/70">
                            <?php foreach (['desktop' => ['Desktop preview', 'monitor'], 'mobile' => ['Mobile preview', 'smartphone']] as $viewport => [$viewportLabel, $viewportIcon]) : ?>
                            <button
                                type="button"
                                data-dk-mail-viewport="<?= h($viewport) ?>"
                                aria-pressed="<?= $viewport === 'desktop' ? 'true' : 'false' ?>"
                                aria-label="<?= h($viewportLabel) ?>"
                                title="<?= h($viewportLabel) ?>"
                                class="<?= h($segmentedTabClass) ?> dk:size-7 dk:p-0"
                            >
                                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => $viewportIcon, 'size' => '3']) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($row['has_html'] && $row['has_text']) : ?>
                        <label class="dk:relative dk:block dk:w-20 dk:shrink-0 dk:sm:ml-auto">
                            <span class="dk:sr-only">Mail preview format</span>
                            <select
                                data-dk-mail-preview-format
                                class="dk:h-9 dk:w-full dk:appearance-none dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-8 dk:pl-3 dk:text-xs dk:font-semibold dk:outline-none dk:transition dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                            >
                                <option value="html">HTML</option>
                                <option value="text">Text</option>
                            </select>
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:right-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
                        </label>
                        <?php endif; ?>
                    </div>
                    <div class="dk:flex dk:bg-zinc-100/70 dk:p-3 dk:dark:bg-zinc-950/65">
                        <div
                            data-dk-mail-preview-canvas
                            class="dk:relative dk:mx-auto dk:h-80 dk:w-full dk:flex-1 dk:overflow-hidden dk:transition-[max-width]"
                        >
                            <iframe
                                data-dk-mail-preview-frame
                                data-dk-mail-html-url="<?= h($htmlUrl) ?>"
                                data-dk-mail-text-url="<?= h($textUrl) ?>"
                                src="<?= h($defaultUrl) ?>"
                                title="Preview of <?= h($row['subject']) ?>"
                                sandbox
                                loading="lazy"
                                class="dk:absolute dk:top-0 dk:left-0 dk:block dk:h-80 dk:w-full dk:max-w-none dk:box-border dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white dk:shadow-sm"
                            ></iframe>
                        </div>
                    </div>
                    <?php else : ?>
                    <div class="dk:m-3 dk:flex dk:min-h-80 dk:w-auto dk:flex-col dk:items-center dk:justify-center dk:rounded-lg dk:border dk:border-dashed dk:border-zinc-300 dk:bg-white/55 dk:px-6 dk:py-10 dk:text-center dk:dark:border-zinc-700 dk:dark:bg-zinc-900/45 sm:dk:m-4">
                        <span class="dk:grid dk:size-9 dk:place-items-center dk:rounded-xl dk:bg-zinc-100 dk:text-zinc-400 dk:dark:bg-zinc-800">
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'mail', 'size' => '4']) ?>
                        </span>
                        <p class="dk:mt-3 dk:text-xs dk:font-bold">No preview was retained for this message.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div data-dk-tab-panel="<?= h($tabScope) ?>:message" class="dk:p-3 dk:sm:p-4" hidden>
                    <dl class="dk:min-w-0 dk:space-y-2">
                        <?php foreach ($addressRows as [$addressLabel, $addressValue]) :
                            if ($addressValue === '' && ($addressLabel === 'CC' || $addressLabel === 'BCC' || $addressLabel === 'Reply to')) {
                                continue;
                            }
                            ?>
                        <div class="dk:grid dk:grid-cols-[4.75rem_minmax(0,1fr)] dk:items-baseline dk:gap-2">
                            <dt class="dk:text-xs dk:font-semibold dk:text-zinc-400"><?= h($addressLabel) ?></dt>
                            <dd class="dk:min-w-0 dk:break-all dk:text-xs dk:font-medium dk:text-zinc-700 dk:dark:text-zinc-200"><?= h($addressValue !== '' ? $addressValue : '—') ?></dd>
                        </div>
                        <?php endforeach; ?>
                    </dl>
                    <?php if ($row['attachments'] !== []) : ?>
                    <section class="dk:mt-4 dk:border-t dk:border-zinc-200/90 dk:pt-3 dk:sm:mt-5 dk:sm:pt-4 dk:dark:border-zinc-800">
                        <h4 class="dk:text-xs dk:font-bold">Attachments</h4>
                        <div class="dk:mt-2 dk:divide-y dk:divide-zinc-200/90 dk:overflow-hidden dk:rounded-xl dk:border dk:border-zinc-200/90 dk:dark:divide-zinc-800 dk:dark:border-zinc-800">
                            <?php foreach ($row['attachments'] as $attachmentIndex => $attachment) :
                                $attachment = (array)$attachment;
                                $attachmentName = (string)($attachment['name'] ?? 'Attachment');
                                $attachmentType = (string)($attachment['content_type'] ?? 'application/octet-stream');
                                $attachmentSize = isset($attachment['size_bytes']) ? (int)$attachment['size_bytes'] : null;
                                $downloadUrl = $panelId !== '' && !empty($attachment['body_base64']) ? $this->Url->build([
                                    'plugin' => 'Crustum/DebugBar',
                                    'controller' => 'MailPreview',
                                    'action' => 'attachment',
                                    $panelId,
                                    $index,
                                    $attachmentIndex,
                                ]) : '';
                                ?>
                            <div>
                                <?php if ($downloadUrl !== '') : ?>
                                <a
                                    href="<?= h($downloadUrl) ?>"
                                    download="<?= h($attachmentName) ?>"
                                    class="dk:flex dk:h-auto dk:min-w-0 dk:items-center dk:gap-3 dk:bg-transparent dk:px-3 dk:py-2.5 dk:no-underline dk:transition-colors dk:hover:bg-zinc-50/80 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-[-2px] dk:focus-visible:outline-indigo-500 dk:dark:hover:bg-zinc-900/60"
                                >
                                    <span class="dk:min-w-0 dk:flex-1">
                                        <span class="dk:block dk:truncate dk:text-xs dk:font-semibold"><?= h($attachmentName) ?></span>
                                        <span class="dk:mt-0.5 dk:flex dk:min-w-0 dk:flex-wrap dk:gap-x-2 dk:text-xs dk:text-zinc-400">
                                            <span class="dk:truncate"><?= h($attachmentType) ?></span>
                                            <span class="dk:tabular-nums"><?= h($formatBytes($attachmentSize)) ?></span>
                                        </span>
                                    </span>
                                    <span class="dk:flex dk:shrink-0 dk:items-center dk:gap-1.5 dk:text-xs dk:font-bold dk:text-indigo-600 dk:dark:text-indigo-300">
                                        Download
                                        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'download', 'size' => '3.5']) ?>
                                    </span>
                                </a>
                                <?php else : ?>
                                <div class="dk:flex dk:min-w-0 dk:items-center dk:gap-3 dk:px-3 dk:py-2.5">
                                    <span class="dk:min-w-0 dk:flex-1">
                                        <span class="dk:block dk:truncate dk:text-xs dk:font-semibold"><?= h($attachmentName) ?></span>
                                        <span class="dk:mt-0.5 dk:flex dk:min-w-0 dk:flex-wrap dk:gap-x-2 dk:text-xs dk:text-zinc-400">
                                            <span class="dk:truncate"><?= h($attachmentType) ?></span>
                                            <span class="dk:tabular-nums"><?= h($formatBytes($attachmentSize)) ?></span>
                                        </span>
                                    </span>
                                    <span class="dk:shrink-0 dk:text-xs dk:font-semibold dk:text-zinc-400">Not retained</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($row['attachments_omitted'] > 0) : ?>
                        <p class="dk:mt-2 dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400">
                            <?= number_format($row['attachments_omitted']) ?> <?= $row['attachments_omitted'] === 1 ? 'attachment was' : 'attachments were' ?> not retained because the message exceeded the capture budget or the file could not be read.
                        </p>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>
                    <section class="dk:mt-4 dk:border-t dk:border-zinc-200/90 dk:pt-3 dk:sm:mt-5 dk:sm:pt-4 dk:dark:border-zinc-800">
                        <h4 class="dk:text-xs dk:font-bold">Delivery details</h4>
                        <dl class="dk:mt-2 dk:min-w-0 dk:space-y-2">
                            <?php foreach ($deliveryRows as [$deliveryLabel, $deliveryValue]) :
                                if ($deliveryValue === '') {
                                    continue;
                                }
                                ?>
                            <div class="dk:grid dk:grid-cols-[4.75rem_minmax(0,1fr)] dk:items-baseline dk:gap-2">
                                <dt class="dk:text-xs dk:font-semibold dk:text-zinc-400"><?= h($deliveryLabel) ?></dt>
                                <dd class="dk:min-w-0 dk:break-all dk:text-xs dk:font-medium dk:text-zinc-700 dk:dark:text-zinc-200"><?= h($deliveryValue) ?></dd>
                            </div>
                            <?php endforeach; ?>
                        </dl>
                    </section>
                </div>
                <div data-dk-tab-panel="<?= h($tabScope) ?>:source" hidden>
                    <div class="dk:p-3 dk:sm:p-4">
                        <?php if ($callsiteFile !== '') : ?>
                        <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                            'file' => $callsiteFile,
                            'line' => $callsite['line'] ?? null,
                            'short' => CallsiteCapture::label($callsite),
                        ]) ?>
                        <?php else : ?>
                        <p class="dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">Source unavailable.</p>
                        <?php endif; ?>
                        <?php if ($row['stack'] !== []) : ?>
                        <div class="dk:mt-3">
                            <?= $this->element('Crustum/DebugBar.ui/inspector_stack', ['frames' => $row['stack']]) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php endforeach; ?>
    </div>
    <?php
    $detailBody = (string)ob_get_clean();
    ?>
    <?= $this->element('Crustum/DebugBar.ui/inspector_workspace', [
        'mode' => 'split',
        'frame' => 'top',
        'list' => $this->element('Crustum/DebugBar.ui/inspector_list_panel', [
            'controls' => $controls,
            'body' => $listBody,
        ]),
        'detail' => $detailBody,
    ]) ?>
    <?php endif; ?>
</div>
