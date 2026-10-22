<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Controller;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Event\EventInterface;
use Cake\Http\Exception\NotFoundException;
use Cake\View\JsonView;
use Cake\View\ViewBuilder;
use Crustum\DebugBar\Service\RequestFacts;
use Throwable;

/**
 * Provides access to panel data.
 *
 * @property \Crustum\DebugBar\Model\Table\RequestsTable $Requests
 * @rewritten-from \DebugKit\Controller\RequestsController
 */
class RequestsController extends DebugBarController
{
    /**
     * @inheritDoc
     */
    public function viewClasses(): array
    {
        return [JsonView::class];
    }

    /**
     * Before filter handler.
     *
     * @param \Cake\Event\EventInterface $event The event.
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        $this->response = $this->response->withHeader('Content-Security-Policy', '');
    }

    /**
     * Before render handler.
     *
     * @param \Cake\Event\EventInterface $event The event.
     * @return void
     */
    public function beforeRender(EventInterface $event): void
    {
        $this->viewBuilder()
            ->addHelpers([
                'Form', 'Html', 'Number', 'Url', 'Crustum/DebugBar.Toolbar',
                'Crustum/DebugBar.Credentials', 'Crustum/DebugBar.SimpleGraph',
            ])
            ->setLayout('Crustum/DebugBar.toolbar');

        if (!$this->request->is('json')) {
            $this->viewBuilder()->setClassName('Crustum/DebugBar.Ajax');
        }
    }

    /**
     * View a request's data.
     *
     * @param string $id The id.
     * @return void
     */
    public function view(?string $id = null): void
    {
        $toolbar = $this->Requests->get($id, contain: 'Panels');
        foreach ($toolbar->panels as $panel) {
            $panel->set('request', $toolbar);
        }

        $this->set('toolbar', $toolbar);

        $recent = $this->Requests->unhydratedFind('recent')
            ->limit((int)Configure::read('DebugBar.storage.max_requests', 20))
            ->select(['id', 'url', 'method', 'status_code', 'content_type', 'requested_at'])
            ->toArray();
        $this->set('recent', $this->withResponseSizes($recent));
        $this->set('assetVersion', static::assetVersion());
    }

    /**
     * Reads the dist bundle version for the client freshness handshake.
     *
     * The toolbar iframe outlives htmx navigation, so rebuilt dist assets
     * would otherwise run against newer panel markup forever: the boot
     * script compares this against its own `data-dk-asset-version` on
     * every panels swap and reloads once on mismatch.
     *
     * @return int Bundle filemtime or 0 when unreadable.
     */
    public static function assetVersion(): int
    {
        $path = Plugin::path('Crustum/DebugBar')
            . 'webroot' . DIRECTORY_SEPARATOR
            . 'dist' . DIRECTORY_SEPARATOR . 'debugbar.js';
        if (!is_file($path)) {
            return 0;
        }

        $mtime = filemtime($path);

        return $mtime === false ? 0 : $mtime;
    }

    /**
     * Serves one stored request with every panel body rendered.
     *
     * Powers the on-the-fly request switch: the shell swaps each
     * `data-dk-inspector-panel` section in place, so picking another URL
     * shows its data without reloading the toolbar page. Panel HTML uses
     * the exact element vars of `templates/element/inspector.php`.
     *
     * @param string|null $id Request id.
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException
     */
    public function panels(?string $id = null): void
    {
        $toolbar = $this->Requests->find()
            ->where(['Requests.id' => $id])
            ->contain('Panels')
            ->first();
        if ($toolbar === null) {
            throw new NotFoundException('Request not found.');
        }

        /** @var array $toolbarPanels */
        $toolbarPanels = $toolbar->get('panels');
        foreach ($toolbarPanels as $panel) {
            $panel->set('request', $toolbar);
        }

        $method = (string)$toolbar->get('method');
        $url = (string)$toolbar->get('url');
        $statusCode = (int)$toolbar->get('status_code');

        $elementView = new ViewBuilder()
            ->setClassName('Crustum/DebugBar.Ajax')
            ->addHelpers([
                'Form', 'Html', 'Number', 'Url', 'Crustum/DebugBar.Toolbar',
                'Crustum/DebugBar.Credentials', 'Crustum/DebugBar.SimpleGraph',
            ])
            ->build($this->request, $this->response, $this->getEventManager());

        $panels = [];
        foreach ($toolbarPanels as $panel) {
            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            $stored = @unserialize((string)$panel->content);
            $panelData = is_array($stored) ? $stored : [];
            $panelData += [
                'panel' => $panel,
                'sort' => false,
                'requestMethod' => $method,
                'requestUrl' => $url,
                'requestStatus' => $statusCode,
                'panelId' => $panel->id,
            ];
            $panels[] = [
                'key' => RequestFacts::inspectorKey((string)$panel->title),
                'id' => $panel->id,
                'title' => (string)$panel->title,
                'summary' => (string)$panel->summary,
                'count' => is_array($stored)
                    ? RequestFacts::panelCount((string)$panel->get('panel'), $stored)
                    : null,
                'html' => $elementView->element($panel->element, $panelData),
            ];
        }

        $recent = $this->Requests->unhydratedFind('recent')
            ->limit((int)Configure::read('DebugBar.storage.max_requests', 20))
            ->select(['id', 'url', 'method', 'status_code', 'content_type', 'requested_at'])
            ->toArray();

        $this->set([
            'request' => RequestFacts::summarize($toolbar),
            'panels' => $panels,
            'recent' => $this->withResponseSizes($recent),
            'asset_version' => static::assetVersion(),
        ]);
        $this->viewBuilder()->setOption('serialize', ['request', 'panels', 'recent', 'asset_version']);
    }

    /**
     * Lists recent request summaries for the request switcher.
     *
     * @return void
     */
    public function recent(): void
    {
        $max = (int)Configure::read('DebugBar.storage.max_requests', 20);
        $limit = (int)$this->request->getQuery('limit', 20);
        $limit = max(1, min($limit, $max));

        $rows = $this->Requests->unhydratedFind('recent')
            ->limit($limit)
            ->select(['id', 'url', 'method', 'status_code', 'content_type', 'requested_at'])
            ->toArray();
        $rows = $this->withResponseSizes($rows);

        $summaries = [];
        foreach ($rows as $row) {
            $summaries[] = [
                'id' => $row['id'],
                'url' => $row['url'],
                'method' => $row['method'],
                'status_code' => $row['status_code'],
                'content_type' => $row['content_type'],
                'requested_at' => $row['requested_at'],
                'response_size_bytes' => $row['response_size_bytes'] ?? null,
                'response_size' => $row['response_size'] ?? null,
            ];
        }

        $this->set([
            'requests' => $summaries,
            'pagination' => ['limit' => $limit, 'count' => count($summaries)],
        ]);
        $this->viewBuilder()->setOption('serialize', ['requests', 'pagination']);
    }

    /**
     * Enriches recent rows with the stored response size.
     *
     * The `requests` table keeps no body size, but the `Request` panel
     * already persists `responseSize` per request. Loading those panel
     * blobs keeps the switcher closed/open states on the upstream
     * contract (`status` + `response_size`) without a schema migration.
     *
     * @param array<int, array<string, mixed>> $rows Recent rows.
     * @return array<int, array<string, mixed>>
     */
    protected function withResponseSizes(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (isset($row['id']) && is_string($row['id']) && $row['id'] !== '') {
                $ids[] = $row['id'];
            }
        }

        if ($ids === []) {
            return $rows;
        }

        $sizes = [];
        try {
            /** @var \Crustum\DebugBar\Model\Table\PanelsTable $panels */
            $panels = $this->fetchTable('Crustum/DebugBar.Panels');
            $stored = $panels->unhydratedFind()
                ->select(['request_id', 'content'])
                ->where([
                    'request_id IN' => $ids,
                    'panel IN' => ['Request', 'Crustum/DebugBar.Request'],
                ])
                ->toArray();
            foreach ($stored as $panelRow) {
                $requestId = (string)($panelRow['request_id'] ?? '');
                if ($requestId === '') {
                    continue;
                }

                $content = $panelRow['content'] ?? '';
                if (is_resource($content)) {
                    $read = stream_get_contents($content);
                    $content = $read === false ? '' : $read;
                }

                // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
                $data = @unserialize((string)$content);
                $size = is_array($data) ? ($data['responseSize'] ?? null) : null;
                $sizes[$requestId] = is_int($size) && $size >= 0 ? $size : null;
            }
        } catch (Throwable) {
            $sizes = [];
        }

        foreach ($rows as $index => $row) {
            $id = (string)($row['id'] ?? '');
            $bytes = RequestFacts::responseSizeFor(
                (string)($row['method'] ?? ''),
                $sizes[$id] ?? null,
            );
            $rows[$index]['response_size_bytes'] = $bytes;
            $rows[$index]['response_size'] = RequestFacts::formatBytes($bytes);
        }

        return $rows;
    }
}
