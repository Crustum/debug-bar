<?php
declare(strict_types=1);

namespace TestApp\Controller;

use Cake\Cache\Cache;
use Cake\Controller\Controller;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\Client\Request as ClientRequest;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\Mailer\Mailer;
use Crustum\DebugBar\Panel\ExceptionsPanel;
use LogicException;
use RuntimeException;

/**
 * Hosts the fixture flows and diagnostics pages for the browser specs.
 *
 * The articles index/view/add/delete actions form a small real
 * application flow: cached listing, validated create with mail and
 * redirect, authorized delete. Specs drive these flows like a user
 * would; the toolbar captures each step through the production hooks.
 * Diagnostics actions below exist only where a real flow cannot
 * produce the state deterministically (log levels, event storms,
 * client failures, hostile styles, edge responses).
 */
class BrowserFixturesController extends Controller
{
    /**
     * Shared page render with toolbar-friendly layout.
     *
     * @param string $heading Page heading.
     * @return void
     */
    protected function renderPage(string $heading): void
    {
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('page');
        $this->set('heading', $heading);
    }

    /**
     * Articles index: cached listing plus queries, log and event.
     *
     * @return void
     */
    public function articles(): void
    {
        $articles = $this->fetchTable('BrowserArticles');
        $rows = Cache::remember(
            'dbb_articles_index',
            fn() => $articles->find()->select(['id', 'title'])->limit(20)->all()->toList(),
            'dbb_fixtures',
        );
        Cache::read('dbb_articles_missing', 'dbb_fixtures');
        // Uncached fingerprinted queries keep the SQL panel deterministic
        // no matter which test warmed the list cache first.
        $connection = ConnectionManager::get('test');
        foreach ([1, 2, 3] as $number) {
            $connection->execute('select ? as number', [$number])->fetchAll('assoc');
        }

        // Uncached model retrieval keeps the Models panel deterministic too.
        $articles->find()->where(['id' => 1])->first();
        Log::info('Articles index rendered.', ['scope' => 'browser']);
        EventManager::instance()->dispatch(new Event('dbb.articles.indexed', null, ['page' => 'articles']));

        if ($this->request->getQuery('large')) {
            foreach (range(1, 40) as $number) {
                $articles->find()->where(['id' => $number])->first();
            }
        }

        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('articles');
        $this->set('heading', 'Articles');
        $this->set('rows', $rows);
    }

    /**
     * Article page with model and view activity.
     *
     * @param string|null $id Article id.
     * @return void
     */
    public function articleView(?string $id = null): void
    {
        $articles = $this->fetchTable('BrowserArticles');
        $article = $articles->get((int)$id);
        $articles->find()->limit(3)->all()->toList();

        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('article_view');
        $this->set('heading', $article->title);
        $this->set('article', $article);
    }

    /**
     * Article create flow: form on GET, validated save on POST.
     *
     * Invalid posts re-render with status 422 so the validation panel
     * captures explicit errors; valid posts send mail and redirect to
     * the created article.
     *
     * @return \Cake\Http\Response|null
     */
    public function articleAdd(): ?Response
    {
        $articles = $this->fetchTable('BrowserArticles');
        $article = $articles->newEmptyEntity();
        if ($this->request->is('post')) {
            $article = $articles->patchEntity($article, $this->request->getData());
            if ($articles->save($article)) {
                Log::info('Article created.', ['id' => $article->id]);
                $this->sendFixtureMail('Article created', 'A new article was published.');
                EventManager::instance()->dispatch(
                    new Event('dbb.article.created', $this, ['id' => $article->id]),
                );

                return $this->redirect(['action' => 'articleView', $article->id]);
            }

            $this->response = $this->response->withStatus(422);
        }

        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('article_add');
        $this->set('heading', 'New article');
        $this->set('article', $article);

        return null;
    }

    /**
     * Article delete flow with an authorization check and redirect.
     *
     * @param string|null $id Article id.
     * @return \Cake\Http\Response|null
     */
    public function articleDelete(?string $id = null): ?Response
    {
        $this->request->allowMethod(['post']);
        EventManager::instance()->dispatch(new Event('DebugBar.Authorization.checked', null, [
            'action' => 'delete',
            'resource' => 'article',
            'optional_args' => [(int)$id],
            'user' => 'planner-8',
            'allowed' => true,
            'result' => true,
            'policy' => 'ArticlePolicy',
            'policy_method' => 'canDelete',
            'policy_source' => 'src/Policy/ArticlePolicy.php',
            'callsite' => ['file' => 'src/Controller/ArticlesController.php', 'line' => 41],
            'stack' => [],
        ]));
        $articles = $this->fetchTable('BrowserArticles');
        $articles->delete($articles->get((int)$id));

        return $this->redirect(['action' => 'articles']);
    }

    /**
     * Log-heavy diagnostics for the logs inspector.
     *
     * @return void
     */
    public function logs(): void
    {
        Log::debug('Preparing the journey workspace.', ['trip' => 1]);
        Log::info('Workspace accepted the refresh.', ['trip' => 1]);
        Log::notice('Partner response is delayed. The cached itinerary remains available.');
        foreach (range(1, 3) as $attempt) {
            Log::warning('Rail reservation refresh needs attention.', ['trip' => 1, 'attempt' => $attempt]);
        }

        Log::error('Rail reservation refresh failed.', [
            'trip' => 1,
            'exception' => new RuntimeException('The rail partner rejected reservation KYO-441.'),
        ]);
        Log::critical('Itinerary integrity warning.', ['trip' => 1]);
        foreach (range(1, 8) as $index) {
            Log::debug('Journey step checked.', ['step' => $index]);
        }

        $this->renderPage('Log diagnostics');
    }

    /**
     * Event-heavy diagnostics for the events inspector.
     *
     * Registers a duplicated listener on purpose so the grouping
     * surface has something to report.
     *
     * @return void
     */
    public function events(): void
    {
        $manager = EventManager::instance();
        $noop = function (): void {
        };
        $manager->on('dbb.trip.planned', $noop);
        $manager->on('dbb.trip.planned', $noop);
        $manager->on('dbb.trip.archived', $noop);
        $manager->dispatch(new Event('dbb.trip.planned', $this, ['trip' => 42]));
        $manager->dispatch(new Event('dbb.trip.archived', $this, ['trip' => 42]));
        $manager->dispatch(new Event('dbb.trip.announced'));

        $this->renderPage('Event diagnostics');
    }

    /**
     * View-heavy diagnostics for the views inspector.
     *
     * @return void
     */
    public function views(): void
    {
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('views');
        $this->set('label', 'Context view');
        $this->set('rows', [
            ['reference' => 'NL-1042', 'ready' => true],
            ['reference' => 'KYO-441', 'ready' => false],
        ]);
    }

    /**
     * Context diagnostics with view variables for the views inspector.
     *
     * @return void
     */
    public function context(): void
    {
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('context');
        $this->set('label', 'Context view');
        $this->set('rows', [
            ['reference' => 'NL-1042', 'ready' => true],
        ]);
    }

    /**
     * Cache-heavy diagnostics for the cache inspector.
     *
     * @return void
     */
    public function cacheRich(): void
    {
        Cache::delete('dbb_trip_weather', 'dbb_fixtures');
        Cache::delete('dbb_trip_stale', 'dbb_fixtures');
        Cache::write('dbb_trip_weather', ['high' => 24, 'low' => 15], 'dbb_fixtures');
        Cache::read('dbb_trip_weather', 'dbb_fixtures');
        Cache::read('dbb_trip_missing', 'dbb_fixtures');
        Cache::read('dbb_trip_missing', 'dbb_fixtures');
        Cache::writeMany(['dbb_trip_summary' => 'A compact autumn itinerary'], 'dbb_fixtures');
        Cache::readMany(['dbb_trip_summary', 'dbb_trip_note'], 'dbb_fixtures');
        Cache::write('dbb_trip_stale', 'stale quote', 'dbb_fixtures');
        Cache::delete('dbb_trip_stale', 'dbb_fixtures');

        $this->renderPage('Cache diagnostics');
    }

    /**
     * HTTP client diagnostics: one request/response pair through the
     * same `HttpClient.*` events the real client dispatches.
     *
     * A live self call would deadlock the single-threaded fixture
     * server, so the pair is dispatched directly; the panel path is
     * identical either way.
     *
     * @return void
     */
    public function httpClient(): void
    {
        $port = (int)($this->request->getServerParams()['SERVER_PORT'] ?? 80);
        $request = new ClientRequest('http://127.0.0.1:' . $port . '/browser-fixtures/ping');
        $manager = EventManager::instance();
        $manager->dispatch(new Event('HttpClient.beforeSend', null, ['request' => $request]));
        $manager->dispatch(new Event('HttpClient.afterSend', null, [
            'request' => $request,
            'response' => $this->response->withType('txt')->withStringBody('pong'),
        ]));

        $this->renderPage('HTTP client diagnostics');
    }

    /**
     * Plain-text target for the fixture self call.
     *
     * @return \Cake\Http\Response
     */
    public function ping(): Response
    {
        return $this->response->withType('txt')->withStringBody('pong');
    }

    /**
     * Mail diagnostics sending through the fixture Debug transport.
     *
     * @return void
     */
    public function mail(): void
    {
        $this->sendFixtureMail('Autumn options', 'Temples and gardens.');

        $this->renderPage('Mail diagnostics');
    }

    /**
     * Timeline diagnostics with enough queries for cursor paging.
     *
     * @return void
     */
    public function timelineLong(): void
    {
        $connection = ConnectionManager::get('test');
        foreach (range(1, 110) as $number) {
            $connection->execute('select ? as tick', [$number])->fetchAll('assoc');
        }

        $this->renderPage('Long timeline');
    }

    /**
     * Reported-throwable diagnostics for the exceptions inspector.
     *
     * Records two throwables without throwing so the page itself stays
     * 200 and both the list and the detail panes have content.
     *
     * @return void
     */
    public function exceptions(): void
    {
        ExceptionsPanel::record(new RuntimeException(
            'Reported failure.',
            0,
            new LogicException('Earlier itinerary failure.'),
        ));
        ExceptionsPanel::record(new LogicException('Second reported failure.'));

        $this->renderPage('Reported failure');
    }

    /**
     * Authorization diagnostics dispatching allowed and denied checks.
     *
     * @return void
     */
    public function authorization(): void
    {
        $manager = EventManager::instance();
        $manager->dispatch(new Event('DebugBar.Authorization.checked', null, [
            'action' => 'view',
            'resource' => 'trip',
            'optional_args' => [],
            'user' => 'planner-8',
            'allowed' => true,
            'result' => true,
            'policy' => 'TripPolicy',
            'policy_method' => 'canView',
            'policy_source' => 'src/Policy/TripPolicy.php',
            'callsite' => ['file' => 'src/Controller/TripsController.php', 'line' => 41],
            'stack' => [],
        ]));
        $manager->dispatch(new Event('DebugBar.Authorization.checked', null, [
            'action' => 'refund',
            'resource' => 'trip',
            'optional_args' => [],
            'user' => 'planner-8',
            'allowed' => false,
            'result' => false,
            'policy' => 'TripPolicy',
            'policy_method' => 'canRefund',
            'policy_source' => 'src/Policy/TripPolicy.php',
            'callsite' => ['file' => 'src/Controller/TripsController.php', 'line' => 88],
            'stack' => [],
        ]));

        $this->renderPage('Authorization diagnostics');
    }

    /**
     * Hostile diagnostics: neutral data with clashing generic styles.
     *
     * The inline style block deliberately fights generic selectors and a
     * few package hooks; the shell must win through scoping.
     *
     * @return void
     */
    public function hostile(): void
    {
        $connection = ConnectionManager::get('test');
        foreach (['alpha', 'beta', 'gamma'] as $value) {
            $connection->execute('select ? as hostile_value', [$value])->fetchAll('assoc');
        }

        Cache::delete('dbb_hostile', 'dbb_fixtures');
        Cache::write('dbb_hostile', ['ready' => true], 'dbb_fixtures');
        Cache::read('dbb_hostile', 'dbb_fixtures');
        Log::warning('Hostile style log needs attention.', ['trip' => 41]);
        EventManager::instance()->dispatch(new Event('dbb.hostile.ready'));

        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('hostile');
        $this->set('heading', 'Hostile styles');
    }

    /**
     * JSON response with toolbar tracking but no injected tag.
     *
     * @return \Cake\Http\Response
     */
    public function plainJson(): Response
    {
        return $this->response->withType('json')->withStringBody('{"ready":true}');
    }

    /**
     * Redirect to the articles flow.
     *
     * @return \Cake\Http\Response|null
     */
    public function redirectToArticles(): ?Response
    {
        return $this->redirect('/browser-fixtures/articles');
    }

    /**
     * Plain-text response with toolbar tracking but no injected tag.
     *
     * @return \Cake\Http\Response
     */
    public function plainText(): Response
    {
        return $this->response->withType('txt')->withStringBody('Plain text');
    }

    /**
     * Attachment response: HTML body with a download disposition.
     *
     * @return \Cake\Http\Response
     */
    public function download(): Response
    {
        return $this->response
            ->withType('html')
            ->withDownload('debug.html')
            ->withStringBody('<html><body>Download</body></html>');
    }

    /**
     * Failing HTML page: 422 with toolbar injection.
     *
     * @return void
     */
    public function failed(): void
    {
        $this->response = $this->response->withStatus(422);
        $this->renderPage('Failed');
    }

    /**
     * Headless HTML: no body tag, so injection is skipped by design.
     *
     * @return \Cake\Http\Response
     */
    public function headless(): Response
    {
        return $this->response
            ->withType('html')
            ->withStringBody('<html><head><title>No body</title></head><main>No body</main></html>');
    }

    /**
     * Sends one message through the fixture transport.
     *
     * @param string $subject Message subject.
     * @param string $body Message body.
     * @return void
     */
    protected function sendFixtureMail(string $subject, string $body): void
    {
        (new Mailer())
            ->setTransport('dbb')
            ->setFrom('sender@example.com', 'Sender')
            ->setTo('recipient@example.com', 'Recipient')
            ->setSubject($subject)
            ->deliver($body);
    }
}
