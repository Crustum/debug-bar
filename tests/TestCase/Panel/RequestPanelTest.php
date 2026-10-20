<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Authentication\Authenticator\Result;
use Authentication\Authenticator\ResultInterface;
use Authentication\Identity;
use Cake\Console\Arguments;
use Cake\Controller\Controller;
use Cake\Error\Debugger;
use Cake\Event\Event;
use Cake\Http\ServerRequest;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Panel\RequestPanel;
use Crustum\DebugBar\Service\NodeFlattener;
use TestApp\Command\RequestProbeCommand;

/**
 * Class RequestPanelTest
 *
 * @inspired-by \DebugKit\Test\TestCase\Panel\RequestPanelTest
 */
class RequestPanelTest extends TestCase
{
    /**
     * @var RequestPanel
     */
    protected $panel;

    /**
     * set up
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->panel = new RequestPanel();
    }

    /**
     * Request entry point covers web and console contexts.
     *
     * @return void
     */
    public function testContextsCoverWebAndCli(): void
    {
        $this->assertSame(['web', 'cli'], $this->panel->contexts());
    }

    /**
     * Startup facts survive actions throwing past shutdown.
     *
     * @return void
     */
    public function testStartupCollectsWithoutResponse(): void
    {
        $request = new ServerRequest([
            'url' => '/tasks/add?debug=1',
            'environment' => ['REQUEST_METHOD' => 'POST'],
            'post' => ['title' => 'Buy milk'],
        ]);
        $controller = new Controller($request);
        $this->panel->startup(new Event('Controller.startup', $controller));

        $data = $this->panel->data();
        $this->assertSame('web', $data['context']);
        $this->assertStringContainsString('/tasks/add', (string)$data['fullUrl']);
        $this->assertNull($data['responseSize']);

        $this->panel->shutdown(new Event('Controller.shutdown', $controller));
        $enriched = $this->panel->data();
        $this->assertSame('web', $enriched['context']);
        $this->assertArrayHasKey('responseSize', $enriched);
    }

    /**
     * Test that shutdown summarizes unserializable attributes.
     *
     * @return void
     */
    public function testShutdownSkipAttributes(): void
    {
        $request = new ServerRequest([
            'url' => '/',
            'post' => ['name' => 'bob'],
            'query' => ['page' => 1],
        ]);
        $request = $request
            ->withAttribute('ok', 'string')
            ->withAttribute('closure', function (): void {
            });

        $controller = new Controller($request);
        $event = new Event('Controller.shutdown', $controller);
        $this->panel->shutdown($event);

        $data = $this->panel->data();
        $this->assertArrayHasKey('attributes', $data);
        $this->assertArrayHasKey('session', $data);
        $this->assertArrayHasKey('params', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('cookie', $data);
        $this->assertArrayHasKey('httpHeaders', $data);
        $this->assertArrayHasKey('routeParams', $data);
        $this->assertEquals('string', $data['attributes']['ok']->getType());
        // Closures export as class nodes under any attribute name.
        $closure = NodeFlattener::toPlain($data['attributes']['closure']);
        $this->assertSame('Closure', $closure['__class']);
        // Route params stay in the attributes tab with the prefix intact.
        $this->assertArrayHasKey('params', $data['attributes']);
    }

    /**
     * Test that any plugin object exports its real data.
     *
     * A closure inside only collapses that leaf — the rest of the
     * object (config, maps, flags) stays visible.
     *
     * @return void
     */
    public function testShutdownExportsForeignObjects(): void
    {
        $service = new class () {
            /**
             * @var string
             */
            public $name = 'my-plugin';

            /**
             * @var \Closure
             */
            public $handler;

            /**
             * Constructor.
             */
            public function __construct()
            {
                $this->handler = function (): void {
                };
            }
        };
        $request = (new ServerRequest(['url' => '/']))
            ->withAttribute('somePluginService', $service);
        $controller = new Controller($request);
        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $plain = NodeFlattener::toPlain($this->panel->data()['attributes']['somePluginService']);
        $this->assertSame('my-plugin', $plain['name']);
        $this->assertSame('Closure', $plain['handler']['__class']);
    }

    /**
     * Test that password-like values are masked at capture.
     *
     * @return void
     */
    public function testShutdownMasksSensitiveValues(): void
    {
        $request = new ServerRequest([
            'url' => '/login',
            'post' => ['username' => 'ann', 'password' => 'hunter2'],
            'query' => ['token' => 'abc123', 'page' => '1'],
        ]);
        $controller = new Controller($request);
        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $data = $this->panel->data();
        $this->assertSame('********', NodeFlattener::toPlain($data['data'])['password']);
        $this->assertSame('ann', NodeFlattener::toPlain($data['data'])['username']);
        $this->assertSame('********', NodeFlattener::toPlain($data['query'])['token']);
        $this->assertSame('1', NodeFlattener::toPlain($data['query'])['page']);
    }

    /**
     * Test that the route prefix survives capture unmasked.
     *
     * @return void
     */
    public function testShutdownKeepsRoutePrefixRaw(): void
    {
        $request = (new ServerRequest(['url' => '/admin/users/edit/1']))
            ->withAttribute('params', [
                'controller' => 'Users',
                'action' => 'edit',
                'prefix' => 'Admin',
                'pass' => ['1'],
            ]);
        $controller = new Controller($request);
        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $routeParams = $this->panel->data()['routeParams'];
        $this->assertSame('Admin', $routeParams['prefix']);
        $this->assertSame(['1'], $routeParams['pass']);
    }

    /**
     * Test that the identity attribute exports all fields, masked by core.
     *
     * @return void
     */
    public function testShutdownSummarizesIdentityAttribute(): void
    {
        $request = (new ServerRequest(['url' => '/admin']))
            ->withAttribute('identity', new Identity(['id' => 42, 'password' => 'hash'], []));
        $controller = new Controller($request);
        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $identity = NodeFlattener::toPlain($this->panel->data()['attributes']['identity']);
        $this->assertSame(Identity::class, $identity['__class']);
        $this->assertSame(42, $identity['data']['id']);
        $this->assertSame('********', $identity['data']['password']);
    }

    /**
     * Test that a result-styled object masks nested secrets, keeps data.
     *
     * Mirrors the host `authenticationResult`: user fields stay visible,
     * the password leaf blanks.
     *
     * @return void
     */
    public function testShutdownMasksNestedSecretsInObjects(): void
    {
        $request = (new ServerRequest(['url' => '/login']))
            ->withAttribute(
                'authenticationResult',
                new Result(
                    new Entity(['id' => 7, 'username' => 'ann', 'password' => 'hash'], ['markClean' => true]),
                    ResultInterface::SUCCESS,
                ),
            );
        $controller = new Controller($request);
        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $data = $this->panel->data();
        $result = NodeFlattener::toPlain($data['attributes']['authenticationResult']);
        $this->assertSame('ann', $result['_data']['username']);
        $this->assertSame('********', $result['_data']['password']);
        // The auth fact still resolves the login result.
        $this->assertSame(ResultInterface::SUCCESS, $data['auth']['status']);
    }

    /**
     * Test that an entity identity exports its full fields.
     *
     * @return void
     */
    public function testShutdownExportsEntityIdentity(): void
    {
        $user = new Entity(['id' => 7, 'username' => 'ann'], ['markClean' => true]);
        $request = (new ServerRequest(['url' => '/admin']))
            ->withAttribute('identity', new Identity($user, []));
        $controller = new Controller($request);
        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $identity = NodeFlattener::toPlain($this->panel->data()['attributes']['identity']);
        $this->assertSame('ann', $identity['data']['username']);
        $this->assertSame(7, $identity['data']['id']);
    }

    /**
     * Test that the output mask is restored after capture.
     *
     * @return void
     */
    public function testShutdownRestoresOutputMask(): void
    {
        $mask = Debugger::outputMask();
        $controller = new Controller(new ServerRequest(['url' => '/']));
        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $this->assertSame($mask, Debugger::outputMask());
    }

    /**
     * Test that shutdown collects middleware lists without failing.
     *
     * @return void
     */
    public function testShutdownCollectsMiddlewareLists(): void
    {
        $controller = new Controller(new ServerRequest(['url' => '/']));
        $event = new Event('Controller.shutdown', $controller);
        $this->panel->shutdown($event);

        $data = $this->panel->data();
        $this->assertArrayHasKey('routeMiddleware', $data);
        $this->assertArrayHasKey('appMiddleware', $data);
        $this->assertIsArray($data['routeMiddleware']);
        $this->assertIsArray($data['appMiddleware']);
    }

    /**
     * Test that an authenticated identity reports its identifier.
     *
     * @return void
     */
    public function testShutdownCollectsAuthenticatedIdentity(): void
    {
        $request = (new ServerRequest(['url' => '/admin']))
            ->withAttribute('identity', new Identity(['id' => 42], []))
            ->withAttribute(
                'authentication',
                new Result(['id' => 42], ResultInterface::SUCCESS),
            );
        $controller = new Controller($request);
        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $auth = $this->panel->data()['auth'];
        $this->assertTrue($auth['authenticated']);
        $this->assertSame('42', $auth['identifier']);
        $this->assertSame('Identity', $auth['identity_class']);
        $this->assertSame(ResultInterface::SUCCESS, $auth['status']);
        $this->assertSame([], $auth['errors']);
        $this->assertSame('42', $auth['label']);
    }

    /**
     * Test that a username wins over the identifier for display.
     *
     * @return void
     */
    public function testShutdownPrefersDisplayName(): void
    {
        $request = (new ServerRequest(['url' => '/admin']))
            ->withAttribute('identity', new Identity(
                ['id' => '8c7d9503', 'username' => 'ann', 'email' => 'ann@example.com'],
                [],
            ))
            ->withAttribute(
                'authentication',
                new Result(['id' => '8c7d9503'], ResultInterface::SUCCESS),
            );
        $controller = new Controller($request);
        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $auth = $this->panel->data()['auth'];
        $this->assertSame('ann', $auth['display']);
        $this->assertSame('8c7d9503', $auth['identifier']);
        $this->assertSame('ann', $auth['label']);
    }

    /**
     * Test that a bodyless request measures zero bytes.
     *
     * @return void
     */
    public function testShutdownMeasuresEmptyRequestBody(): void
    {
        $controller = new Controller(new ServerRequest(['url' => '/posts/add']));
        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $data = $this->panel->data();
        $this->assertSame(0, $data['requestSize']);
    }

    /**
     * Test that a failed login reports its status instead of a guest.
     *
     * @return void
     */
    public function testShutdownCollectsFailedResult(): void
    {
        $request = (new ServerRequest(['url' => '/login']))
            ->withAttribute(
                'authentication',
                new Result(null, ResultInterface::FAILURE_CREDENTIALS_INVALID, ['Bad password']),
            );
        $controller = new Controller($request);
        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $auth = $this->panel->data()['auth'];
        $this->assertFalse($auth['authenticated']);
        $this->assertSame('Failed: Credentials Invalid', $auth['label']);
        $this->assertSame(['Bad password'], $auth['errors']);
    }

    /**
     * Test that origin and full URL resolve with port and query.
     *
     * @return void
     */
    public function testShutdownCollectsOriginAndFullUrl(): void
    {
        $uri = (new ServerRequest(['url' => '/admin/users/edit/1']))
            ->getUri()
            ->withScheme('https')
            ->withHost('qa.zz')
            ->withPort(8443)
            ->withQuery('tab=main');
        $request = (new ServerRequest(['url' => '/admin/users/edit/1']))->withUri($uri);
        $controller = new Controller($request);
        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $data = $this->panel->data();
        $this->assertSame('https://qa.zz:8443', $data['origin']);
        $this->assertSame('https://qa.zz:8443/admin/users/edit/1?tab=main', $data['fullUrl']);
    }

    /**
     * Test that guests report no identity and nullable sizes.
     *
     * @return void
     */
    public function testShutdownCollectsGuestSizes(): void
    {
        $controller = new Controller(new ServerRequest(['url' => '/']));
        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $data = $this->panel->data();
        $this->assertSame('Guest', $data['auth']['label']);
        $this->assertFalse($data['auth']['authenticated']);
        $this->assertTrue($data['requestSize'] === null || is_int($data['requestSize']));
        $this->assertTrue($data['responseSize'] === null || is_int($data['responseSize']));
    }

    /**
     * Command runs collect class, filtered options, exit code and duration.
     *
     * @return void
     */
    public function testCommandRunFiltersDefaultOptions(): void
    {
        $command = new RequestProbeCommand();
        $args = new Arguments(['Projects'], ['dry-run' => true, 'verbose' => false], ['name']);
        $this->panel->captureCommandStart(new Event('Command.beforeExecute', $command, ['args' => $args]));
        $this->panel->captureCommandFinish(new Event('Command.afterExecute', $command, ['result' => 2]));

        $data = $this->panel->data();
        $this->assertSame('cli', $data['context']);
        $this->assertSame(RequestProbeCommand::class, $data['command']);
        $this->assertSame('request-probe', $data['name']);
        $this->assertSame([['name' => 'name', 'value' => 'Projects']], $data['arguments']);
        $this->assertSame(['dry-run' => true], $data['options']);
        $this->assertSame('request-probe Projects --dry-run', $data['copy_command']);
        $this->assertSame('name', $data['definition']['arguments'][0]['name']);
        $this->assertArrayNotHasKey('help', $data['definition']['arguments'][0]);
        $optionNames = array_column($data['definition']['options'], 'name');
        $this->assertContains('dry-run', $optionNames);
        $this->assertSame(PHP_VERSION, $data['environment']['php']);
        $this->assertSame(2, $data['exit_code']);
        $this->assertGreaterThanOrEqual(0.0, (float)$data['duration_ms']);
    }

    /**
     * Workers headline processor outcomes with their own attention code.
     *
     * @return void
     */
    public function testFinishCommandAcceptsExplicitExitCode(): void
    {
        $command = new RequestProbeCommand();
        $args = new Arguments([], [], []);
        $this->panel->captureCommandStart(new Event('Command.beforeExecute', $command, ['args' => $args]));
        $this->panel->finishCommand('ack', 0);

        $data = $this->panel->data();
        $this->assertSame('ack', $data['result']);
        $this->assertSame(0, $data['exit_code']);
    }
}
