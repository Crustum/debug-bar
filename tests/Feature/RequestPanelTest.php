<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\Error\Debugger;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\ORM\Entity;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the request inspector body.
 */
class RequestPanelTest extends TestCase
{
    use DebugBarTestTrait;
    use IntegrationTestTrait;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetToolbarStore();
    }

    /**
     * Steps, tabs and copy actions render for HTTP requests.
     *
     * @return void
     */
    public function testRequestStepsAndTabs(): void
    {
        $this->seedRequest(
            [
                'Request' => [
                    'headers' => ['response' => false, 'file' => '', 'line' => 0],
                    'params' => ['controller' => 'Tasks', 'action' => 'add'],
                    'routeParams' => ['controller' => 'Tasks', 'action' => 'add'],
                    'attributes' => [
                        'params' => Debugger::exportVarAsNodes(['controller' => 'Tasks']),
                        'identity' => Debugger::exportVarAsNodes(
                            new Entity(['name' => 'Ann'], ['markClean' => true]),
                        ),
                        'authorization' => Debugger::exportVarAsNodes(['service' => 'AuthService']),
                        'formTokenData' => Debugger::exportVarAsNodes(
                            ['token' => 'tok123', 'fields' => ['title']],
                        ),
                    ],
                    'httpHeaders' => Debugger::exportVarAsNodes(['Accept' => 'text/html']),
                    'data' => Debugger::exportVarAsNodes(['title' => 'Buy milk', 'password' => 'hunter2', 'nickname' => '']),
                    'query' => Debugger::exportVarAsNodes(['page' => '2']),
                    'cookie' => Debugger::exportVarAsNodes(['session_id' => 'abc']),
                    'session' => Debugger::exportVarAsNodes(['Auth' => ['id' => 7]]),
                    'routeMiddleware' => ['RoutingMiddleware'],
                    'appMiddleware' => [
                        ErrorHandlerMiddleware::class,
                        RoutingMiddleware::class,
                    ],
                    'auth' => [
                        'authenticated' => true,
                        'identifier' => '8c7d9503',
                        'display' => 'ann',
                        'identity_class' => 'Identity',
                        'status' => 'SUCCESS',
                        'errors' => [],
                        'label' => 'ann',
                    ],
                    'requestSize' => 13,
                    'responseSize' => 2345678,
                    'origin' => 'https://qa.zz',
                    'fullUrl' => 'https://qa.zz/tasks/add',
                ],
            ],
            ['method' => 'POST', 'url' => '/tasks/add', 'status_code' => 200],
        );
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Request'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('data-dk-request-steps', $body);
        $this->assertStringContainsString('data-dk-request-step="received"', $body);
        $this->assertStringContainsString('data-dk-request-step="matched"', $body);
        $this->assertStringContainsString('data-dk-request-step="responded"', $body);
        $this->assertStringContainsString('POST', $body);
        $this->assertStringContainsString('/tasks/add', $body);
        $this->assertStringContainsString('200 OK', $body);
        $this->assertStringContainsString('data-dk-request-details', $body);
        $this->assertStringContainsString('data-dk-tab="request:headers"', $body);
        $this->assertStringContainsString('data-dk-tab-panel="request:headers"', $body);
        $this->assertStringContainsString('data-dk-tab="request:cookies"', $body);
        $this->assertStringContainsString('data-dk-tab="request:attributes"', $body);
        $this->assertStringContainsString('data-dk-tab-panel="request:session"', $body);
        $this->assertStringContainsString('Copy URL', $body);
        // Trace dots use the duotone lifecycle icons.
        $this->assertStringContainsString('data-dk-request-dot', $body);
        $this->assertStringContainsString('viewBox="0 0 256 256"', $body);
        // Detail groups render the shared debug tree (same as Passed data).
        $this->assertStringContainsString('data-dk-debug-tree', $body);
        $this->assertStringContainsString('Buy milk', $body);
        $this->assertStringContainsString('Copy all', $body);
        $this->assertStringContainsString('data-dk-request-detail-count', $body);
        // Objects expand to class plus fields; secrets mask as ********.
        $this->assertStringContainsString('Ann', $body);
        $this->assertStringContainsString('********', $body);
        $this->assertStringNotContainsString('hunter2', $body);
        // Framework attributes render verbatim, never blanked by key name.
        $this->assertStringContainsString('AuthService', $body);
        $this->assertStringContainsString('formTokenData', $body);
        $this->assertStringNotContainsString('tok123', $body);
        // Tree rows align key/value columns; empty values show a dash.
        $this->assertStringContainsString('dk:grid-cols-[12rem_minmax(0,1fr)]', $body);
        $this->assertStringContainsString('aria-label="empty value"', $body);
        // Route middleware renders inline; the app stack is collapsible.
        $this->assertStringContainsString('>Middleware<', $body);
        $this->assertStringContainsString('RoutingMiddleware', $body);
        $this->assertStringContainsString('App middleware', $body);
        $this->assertStringContainsString('ErrorHandlerMiddleware', $body);
        $this->assertStringContainsString('RoutingMiddleware', $body);
        // Authentication, request and response sizes render as facts.
        $this->assertStringContainsString('Authentication', $body);
        $this->assertStringContainsString('>ann<', $body);
        $this->assertStringContainsString('title="ID: 8c7d9503"', $body);
        $this->assertStringContainsString('Request size', $body);
        $this->assertStringContainsString('Response size', $body);
        $this->assertStringContainsString('13 B', $body);
        $this->assertStringContainsString('2.24 MB', $body);
        // Server origin renders and the copy carries the absolute URL.
        $this->assertStringContainsString('https://qa.zz', $body);
        $this->assertStringContainsString('data-dk-copy-value="https://qa.zz/tasks/add"', $body);
    }

    /**
     * An empty route stack hides the Middleware fact instead of `(none)`.
     *
     * @return void
     */
    public function testEmptyRouteMiddlewareHidesFact(): void
    {
        $this->seedRequest(
            [
                'Request' => [
                    'headers' => ['response' => false, 'file' => '', 'line' => 0],
                    'params' => ['controller' => 'Tasks', 'action' => 'add'],
                    'attributes' => [],
                    'data' => Debugger::exportVarAsNodes([]),
                    'query' => Debugger::exportVarAsNodes([]),
                    'routeMiddleware' => [],
                    'appMiddleware' => [],
                ],
            ],
            ['method' => 'GET', 'url' => '/tasks/add', 'status_code' => 200],
        );
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Request'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();

        $this->assertStringNotContainsString('>Middleware<', (string)$this->_response->getBody());
    }

    /**
     * Runs without request facts render an honest empty state.
     *
     * Worker and foreign captures store no request payload — the panel
     * must not fake a Matched route and Guest identity around nothing.
     *
     * @return void
     */
    public function testRequestEmptyFacts(): void
    {
        $this->seedRequest(
            [
                'Request' => [
                    'headers' => ['response' => false, 'file' => '', 'line' => 0],
                ],
            ],
            ['method' => 'JOB', 'url' => 'DebugBarProbeJob', 'status_code' => 200],
        );
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Request'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('data-dk-request-empty', $body);
        $this->assertStringContainsString('No request facts', $body);
        $this->assertStringNotContainsString('data-dk-request-step="matched"', $body);
        $this->assertStringNotContainsString('Unnamed route', $body);
    }

    /**
     * CLI context renders the command timeline instead of HTTP steps.
     *
     * @return void
     */
    public function testRequestCliContext(): void
    {
        $this->seedRequest(
            [
                'Request' => [
                    'context' => 'cli',
                    'command' => 'App\\Command\\ShowSettingCommand',
                    'label' => 'ShowSettingCommand',
                    'name' => 'setting-ext show',
                    'arguments' => [['name' => 'project', 'value' => 'Projects']],
                    'options' => ['index' => true],
                    'definition' => ['arguments' => [], 'options' => []],
                    'environment' => ['argv' => ['bin/cake.php', 'setting-ext', 'show'], 'cwd' => '/app', 'php' => '8.4.0'],
                    'copy_command' => 'setting-ext show Projects --index',
                    'result' => null,
                    'exit_code' => 1,
                    'duration_ms' => 1.0,
                ],
            ],
            ['method' => 'CLI', 'url' => '/console/setting-ext', 'status_code' => 200],
        );
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Request'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('data-dk-request-context="cli"', $body);
        $this->assertStringContainsString('setting-ext show', $body);
        $this->assertStringContainsString('Copy command', $body);
        $this->assertStringContainsString('data-dk-copy-value="setting-ext show Projects --index"', $body);
        $this->assertStringContainsString('Exit 1', $body);
        $this->assertStringContainsString('data-dk-request-step="received"', $body);
        $this->assertStringContainsString('data-dk-request-step="responded"', $body);
        $this->assertStringNotContainsString('data-dk-request-step="matched"', $body);
        $this->assertStringContainsString('project=Projects', $body);
        $this->assertStringContainsString('--index', $body);
        $this->assertStringContainsString('data-dk-tab="request:arguments"', $body);
        $this->assertStringContainsString('data-dk-tab="request:options"', $body);
        $this->assertStringContainsString('data-dk-tab="request:definition"', $body);
        $this->assertStringContainsString('data-dk-tab="request:environment"', $body);
        $this->assertStringNotContainsString('data-dk-tab="request:headers"', $body);
        $this->assertStringNotContainsString('data-dk-tab="request:result"', $body);
        $this->assertStringNotContainsString('Request details', $body);
        $this->assertStringContainsString('Command details', $body);
    }

    /**
     * Processor outcomes headline the run instead of an exit code.
     *
     * @return void
     */
    public function testRequestCliOutcomePrimary(): void
    {
        $this->seedRequest(
            [
                'Request' => [
                    'context' => 'cli',
                    'command' => 'App\\Job\\DemoJob',
                    'label' => 'DemoJob',
                    'name' => 'DemoJob',
                    'arguments' => [],
                    'options' => [],
                    'definition' => ['arguments' => [], 'options' => []],
                    'environment' => [],
                    'copy_command' => 'DemoJob',
                    'result' => 'ack',
                    'exit_code' => 0,
                    'duration_ms' => 65.6,
                ],
            ],
            ['method' => 'JOB', 'url' => 'DemoJob', 'status_code' => 200],
        );
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Request'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('>ack<', $body);
        $this->assertStringNotContainsString('Exit 0', $body);
        $this->assertStringContainsString('65.6 ms', $body);
    }
}
