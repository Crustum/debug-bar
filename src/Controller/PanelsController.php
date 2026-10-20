<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Controller;

use Cake\Core\Configure;
use Cake\Error\Debug\NodeInterface;
use Cake\Event\EventInterface;
use Cake\Http\Exception\NotFoundException;
use Cake\View\JsonView;
use Cake\View\ViewBuilder;
use Crustum\DebugBar\Model\Entity\Panel;
use Crustum\DebugBar\Service\NodeFlattener;
use Crustum\DebugBar\Service\QueryExplainer;
use Crustum\DebugBar\Service\RedactionService;
use Crustum\DebugBar\Service\RequestFacts;
use Crustum\DebugBar\Service\VarExporter;

/**
 * Provides access to panel data.
 *
 * @property \Crustum\DebugBar\Model\Table\PanelsTable $Panels
 * @inspired-by \DebugKit\Controller\PanelsController
 */
class PanelsController extends DebugBarController
{
    /**
     * @inheritDoc
     */
    public function viewClasses(): array
    {
        return [JsonView::class];
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
     * Index method that lets you get requests by panelid.
     *
     * @param string|null $requestId Request id
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException
     */
    public function index(?string $requestId = null): void
    {
        if ($requestId === null) {
            throw new NotFoundException();
        }

        $query = $this->Panels->find('byRequest', requestId: $requestId);
        $panels = $query->toArray();
        if (empty($panels)) {
            throw new NotFoundException();
        }

        $this->set([
            'panels' => $panels,
        ]);
        $this->viewBuilder()->setOption('serialize', ['panels']);
    }

    /**
     * View a panel's data.
     *
     * @param string $id The id.
     * @return void
     */
    public function view(?string $id = null): void
    {
        if (!$this->Panels->exists(['id' => $id])) {
            throw new NotFoundException('Panel not found.');
        }

        $panel = $this->Panels->get($id, ...['contain' => ['Requests']]);

        if ($this->request->is('json')) {
            $this->jsonView($panel);

            return;
        }

        $this->set('sort', $this->request->getCookie('debugBar_sort'));
        $this->set('panel', $panel);
        $this->set('panelId', (string)$id);

        $parent = $panel->request ?? null;
        $this->set('requestMethod', is_object($parent) ? ($parent->method ?? 'GET') : 'GET');
        $this->set('requestUrl', is_object($parent) ? ($parent->url ?? '') : '');
        $this->set('requestStatus', is_object($parent) ? (int)($parent->status_code ?? 0) : 0);
        $this->set('requestContentType', is_object($parent) ? (string)($parent->content_type ?? '') : '');
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        $this->set(@unserialize($panel->content));
    }

    /**
     * Serves the same panel data as bounded JSON.
     *
     * @param \Crustum\DebugBar\Model\Entity\Panel $panel The panel.
     * @return void
     */
    protected function jsonView(Panel $panel): void
    {
        $redaction = new RedactionService((array)Configure::read('DebugBar'));
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        $data = @unserialize($panel->content);
        $decodeError = !is_array($data);
        if ($decodeError) {
            $data = [];
        }

        $cursor = max(0, (int)$this->request->getQuery('cursor', 0));
        $limit = $this->request->getQuery('limit');
        $limit = $limit === null ? null : max(1, (int)$limit);

        $pagination = ['cursor' => $cursor, 'has_more' => false, 'matching_count' => count($data)];

        $listKey = null;
        foreach (['items', 'events'] as $candidate) {
            if (
                isset($data[$candidate]) && is_array($data[$candidate]) &&
                array_is_list($data[$candidate])
            ) {
                $listKey = $candidate;
                break;
            }
        }

        if ($listKey !== null) {
            $total = count($data[$listKey]);
            $pageLimit = $limit === null ? $redaction->maxItems() : min($limit, $redaction->maxItems());
            $page = array_slice($data[$listKey], $cursor, $pageLimit);
            $data[$listKey] = array_map(
                fn(mixed $item): mixed => $redaction->redactValue($item),
                $page,
            );
            $pagination['has_more'] = $cursor + $pageLimit < $total;
            $pagination['matching_count'] = $total;
            $data['retained'] = $total;
            $data['dropped'] = max(0, $total - $redaction->maxItems());
            $data['truncated'] = $data['dropped'] > 0;
        } else {
            $data = $redaction->redactValue($data);
        }

        $payload = [
            'title' => $panel->title,
            'summary' => $panel->summary,
            'data' => $data,
            'pagination' => $pagination,
        ];
        if ($decodeError) {
            $payload['decode_error'] = true;
        }

        $this->set($payload);
        $this->viewBuilder()->setOption('serialize', array_keys($payload));
    }

    /**
     * Runs a live-only read-only EXPLAIN for one captured execution.
     *
     * Never stored: the statement is re-read from the stored panel content
     * and explained on its own connection. Non-read statements are refused
     * with `unavailable_reason`, never executed.
     *
     * @return void
     */
    public function explain(): void
    {
        $this->request->allowMethod(['post']);

        $body = $this->request->getData();
        if (!isset($body['request_id']) && !isset($body['id'])) {
            $decoded = json_decode((string)$this->request->getBody(), true);
            if (is_array($decoded)) {
                $body = $decoded + $body;
            }
        }

        $requestId = (string)($body['request_id'] ?? $body['id'] ?? '');
        $execution = max(1, (int)($body['execution'] ?? 1));

        if ($requestId === '' || !$this->Panels->Requests->exists(['id' => $requestId])) {
            $this->set(['error' => 'Request is no longer stored. Reload the page and retry EXPLAIN at once.']);
            $this->viewBuilder()->setOption('serialize', ['error']);

            return;
        }

        $panel = $this->Panels->find('byRequest', requestId: $requestId)
            ->where(['panel' => 'SqlLog'])
            ->first();
        if ($panel === null) {
            $this->set(['error' => 'This request has no SQL panel.']);
            $this->viewBuilder()->setOption('serialize', ['error']);

            return;
        }

        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        $content = @unserialize((string)$panel->get('content'));
        $items = is_array($content) ? array_values((array)($content['items'] ?? [])) : [];
        $item = $items[$execution - 1] ?? null;
        if (!is_array($item) || ((string)($item['sql'] ?? '') === '')) {
            $this->set(['error' => 'Execution not found.']);
            $this->viewBuilder()->setOption('serialize', ['error']);

            return;
        }

        $explainer = new QueryExplainer();
        $result = $explainer->explain(
            (string)($item['connection'] ?? 'default'),
            (string)$item['sql'],
            (array)($item['params'] ?? []),
        );
        $this->set($result);
        $this->viewBuilder()->setOption('serialize', array_keys($result));
    }

    /**
     * Serves one lazy render with the other views stripped.
     *
     * `GET /debug-bar/panels/view-data/:id?render=N` returns the Nth render
     * (1-based) plus the shared variable dump as server HTML. Anything else
     * is a 404 — the inspector shows its error state with Retry.
     *
     * @param string|null $id The panel id.
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException
     */
    public function viewData(?string $id = null): void
    {
        $panel = $id !== null ? $this->Panels->find()->where(['id' => $id])->first() : null;
        if ($panel === null || ($panel->panel ?? null) !== 'Views') {
            throw new NotFoundException('Views panel not found.');
        }

        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        $content = @unserialize((string)$panel->get('content'));
        $renders = is_array($content) ? array_values((array)($content['renders'] ?? [])) : [];
        $index = max(1, (int)$this->request->getQuery('render', 1)) - 1;
        $render = $renders[$index] ?? null;
        if (!is_array($render)) {
            throw new NotFoundException('Render not found.');
        }

        $vars = is_array($content) ? ($content['vars'] ?? null) : null;

        $plain = null;
        if ($vars instanceof NodeInterface) {
            $plain = VarExporter::flatten($vars);
        } elseif (is_array($vars)) {
            $masked = [];
            foreach ($vars as $varKey => $varNode) {
                $masked[$varKey] = $varNode instanceof NodeInterface
                    ? NodeFlattener::maskSensitive($varNode)
                    : $varNode;
            }

            $plain = NodeFlattener::map($masked);
        }

        $html = '';
        if (is_array($plain) && $plain !== []) {
            $elementView = (new ViewBuilder())
                ->setClassName('Crustum/DebugBar.Ajax')
                ->build($this->request, $this->response, $this->getEventManager());
            $html = (string)$elementView->element('Crustum/DebugBar.ui/debug_tree', [
                'nodes' => $plain,
                'openDepth' => 1,
            ]);
        }

        $this->set([
            'file' => (string)($render['file'] ?? ''),
            'order' => (int)($render['order'] ?? $index + 1),
            'html' => $html,
        ]);
        $this->viewBuilder()->setOption('serialize', ['file', 'order', 'html']);
    }

    /**
     * Serves one view variable with the other variables stripped.
     *
     * `GET /debug-bar/panels/view-variable/:id?var=name` returns the named
     * variable rendered as collapsible tree HTML. Anything else is a 404 —
     * the inspector shows its error state with Retry.
     *
     * @param string|null $id The panel id.
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException
     */
    public function viewVariable(?string $id = null): void
    {
        $panel = $id !== null ? $this->Panels->find()->where(['id' => $id])->first() : null;
        if ($panel === null || !RequestFacts::isPanel($panel->panel ?? '', 'Variables')) {
            throw new NotFoundException('Variables panel not found.');
        }

        $name = (string)$this->request->getQuery('var', '');
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        $content = @unserialize((string)$panel->get('content'));
        $variables = is_array($content) ? ($content['variables'] ?? null) : null;
        $node = is_array($variables) && $name !== '' ? ($variables[$name] ?? null) : null;
        if (!$node instanceof NodeInterface) {
            throw new NotFoundException('Variable not found.');
        }

        $html = '';
        $plain = VarExporter::flatten($node);
        $elementView = (new ViewBuilder())
            ->setClassName('Crustum/DebugBar.Ajax')
            ->build($this->request, $this->response, $this->getEventManager());
        $html = (string)$elementView->element('Crustum/DebugBar.ui/debug_tree', [
            'nodes' => [$name => $plain],
            'openDepth' => 1,
        ]);

        $this->set([
            'name' => $name,
            'html' => $html,
        ]);
        $this->viewBuilder()->setOption('serialize', ['name', 'html']);
    }
}
