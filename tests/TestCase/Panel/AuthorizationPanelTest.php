<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Authorization\AuthorizationService;
use Authorization\Policy\MapResolver;
use Authorization\Policy\Result;
use Authorization\Policy\ResultInterface;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\ServerRequest;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Authorization\DebugBarAuthorizationMiddleware;
use Crustum\DebugBar\Authorization\DebugBarAuthorizationServiceDecorator;
use Crustum\DebugBar\Panel\AuthorizationPanel;
use TestApp\Authorization\TestHandler;
use TestApp\Authorization\TestIdentity;
use TestApp\Authorization\TestPolicy;
use TestApp\Authorization\TestResource;
use TestApp\Authorization\TestSuperuserPolicy;

/**
 * Covers authorization check recording.
 */
class AuthorizationPanelTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        AuthorizationPanel::clear();
        new AuthorizationPanel()->initialize();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        AuthorizationPanel::clear();
        $manager = EventManager::instance();
        $manager->off('DebugBar.Authorization.checked');
        $manager->off('Auth.Rbac.checked');
        $manager->off('Auth.Authorization.checked');
        parent::tearDown();
    }

    /**
     * Decorated service records allowed and denied checks with policy source.
     *
     * @return void
     */
    public function testDecoratorRecordsAllowAndDeny(): void
    {
        $service = new AuthorizationService(new MapResolver([
            TestResource::class => TestPolicy::class,
        ]));
        $decorated = new DebugBarAuthorizationServiceDecorator($service);

        $this->assertTrue($decorated->can(null, 'view', new TestResource(7, true)));
        $this->assertFalse($decorated->can(null, 'view', new TestResource(8, false)));

        $data = new AuthorizationPanel()->data();

        $this->assertCount(2, $data['decisions']);
        $this->assertSame('view', $data['decisions'][0]['ability']);
        $this->assertSame('Policy check', $data['decisions'][0]['origin']);
        $this->assertSame('allowed', $data['decisions'][0]['result']);
        $this->assertSame('Allowed', $data['decisions'][0]['result_label']);
        $this->assertSame('denied', $data['decisions'][1]['result']);
        $this->assertSame(
            TestPolicy::class . '@canView',
            $data['decisions'][0]['handler_name'],
        );
        $this->assertSame('policy', $data['decisions'][0]['handler_kind']);
        $this->assertSame('Matched policy method', $data['decisions'][0]['handler_label']);
        $this->assertStringContainsString(
            'TestPolicy.php',
            (string)$data['decisions'][0]['handler_source_label'],
        );
        $this->assertSame('Guest', $data['decisions'][0]['user_label']);
        $this->assertSame('TestResource', $data['decisions'][0]['argument_summary']);
        $this->assertSame(1, $data['decisions'][0]['execution']);
        $this->assertSame(2, $data['decisions'][1]['execution']);
        $this->assertSame(1, $data['summary']['allowed']);
        $this->assertSame(1, $data['summary']['denied']);
        $this->assertSame(2, $data['summary']['count']);
        $this->assertNotSame('', $data['decisions'][0]['callsite_label']);
        $this->assertNotSame([], $data['decisions'][0]['stack']);
    }

    /**
     * Result reasons surface as the response message.
     *
     * @return void
     */
    public function testResultReasonBecomesMessage(): void
    {
        $service = new AuthorizationService(new MapResolver([
            TestResource::class => TestPolicy::class,
        ]));
        $decorated = new DebugBarAuthorizationServiceDecorator($service);
        $decorated->can(null, 'view', new TestResource(9, false));

        $data = new AuthorizationPanel()->data();

        $this->assertSame('Not the owner.', $data['decisions'][0]['result_message']);
    }

    /**
     * canResult keeps the status while recording the same decision.
     *
     * @return void
     */
    public function testCanResultDelegatesWithStatus(): void
    {
        $service = new AuthorizationService(new MapResolver([
            TestResource::class => TestPolicy::class,
        ]));
        $decorated = new DebugBarAuthorizationServiceDecorator($service);
        $result = $decorated->canResult(null, 'view', new TestResource(10, true));

        $this->assertInstanceOf(ResultInterface::class, $result);
        $this->assertTrue($result->getStatus());
        $data = new AuthorizationPanel()->data();
        $this->assertCount(1, $data['decisions']);
        $this->assertSame('allowed', $data['decisions'][0]['result']);
    }

    /**
     * Named identities render the login name with identifier.
     *
     * @return void
     */
    public function testNamedUserLabel(): void
    {
        $panel = new AuthorizationPanel();
        $panel->captureServiceCheck(new Event('DebugBar.Authorization.checked', $panel, [
            'user' => new TestIdentity(42, ['username' => 'mara', 'id' => 42]),
            'action' => 'edit',
            'resource' => new TestResource(3, true),
            'optional_args' => [],
            'result' => new Result(true),
            'allowed' => true,
            'policy' => TestPolicy::class,
            'policy_method' => 'canEdit',
            'policy_source' => null,
            'callsite' => ['file' => null, 'line' => null],
            'stack' => [],
        ]));

        $data = $panel->data();

        $this->assertSame('mara', $data['decisions'][0]['user_label']);
        $this->assertSame(42, $data['decisions'][0]['user_identifier']);
        $this->assertSame('id', $data['decisions'][0]['user_identifier_name']);
        $this->assertStringContainsString('mara', $data['decisions'][0]['search']);
    }

    /**
     * CakeDC Auth rule events record ability, role and reason.
     *
     * The vendor passes the identity entity as the user — fields must
     * resolve off the entity, not a blind array cast.
     *
     * @return void
     */
    public function testRbacEventRecordsRule(): void
    {
        $request = new ServerRequest([
            'url' => '/articles/view',
            'params' => ['controller' => 'Articles', 'action' => 'view', 'plugin' => null, 'prefix' => null],
        ]);
        $user = new Entity([
            'id' => '8c7d9503-fbfa-4f82-b02c-cd1a79f2cb20',
            'username' => 'admiral',
            'email' => 'admiral@example.com',
            'role' => 'admin',
        ], ['markNew' => false]);
        EventManager::instance()->dispatch(new Event('Auth.Rbac.checked', $this, [
            'allowed' => true,
            'role' => 'admin',
            'user' => $user,
            'request' => $request,
            'permission' => ['role' => 'admin', 'controller' => 'Articles', 'action' => 'view', 'allowed' => true],
            'result' => null,
            'reason' => 'For {"controller":"Articles","action":"view","role":"admin"}'
                . ' --> Rule matched {"role":"admin","allowed":true} with result = 1',
        ]));

        $data = new AuthorizationPanel()->data();

        $this->assertCount(1, $data['decisions']);
        $this->assertSame('Articles/view', $data['decisions'][0]['ability']);
        $this->assertSame('Rbac rule', $data['decisions'][0]['origin']);
        $this->assertSame('allowed', $data['decisions'][0]['result']);
        $this->assertSame('admiral', $data['decisions'][0]['user_label']);
        $this->assertSame('8c7d9503-fbfa-4f82-b02c-cd1a79f2cb20', $data['decisions'][0]['user_identifier']);
        $this->assertSame('admin', $data['decisions'][0]['role']);
        $this->assertSame('Rbac permission rule', $data['decisions'][0]['handler_name']);
        // The structured rule-matched reason duplicates the rule block.
        $this->assertNull($data['decisions'][0]['result_message']);
        $this->assertStringContainsString('matched_rule', $data['decisions'][0]['copy_evidence']);
        $this->assertNotNull($data['decisions'][0]['matched_rule_pretty']);
        $this->assertStringContainsString('admin', $data['decisions'][0]['search']);
        $arguments = $data['decisions'][0]['arguments'];
        $this->assertSame('Controller', $arguments[0]['role_label']);
        $this->assertSame('Articles', $arguments[0]['label']);
        $this->assertSame('Action', $arguments[1]['role_label']);
        $this->assertSame('view', $arguments[1]['label']);
    }

    /**
     * Human rule reasons stay visible as the response message.
     *
     * @return void
     */
    public function testHumanRbacReasonKept(): void
    {
        EventManager::instance()->dispatch(new Event('Auth.Rbac.checked', $this, [
            'allowed' => false,
            'role' => 'user',
            'user' => ['id' => 5, 'username' => 'bob'],
            'request' => new ServerRequest([
                'url' => '/articles/delete',
                'params' => ['controller' => 'Articles', 'action' => 'delete'],
            ]),
            'permission' => null,
            'result' => null,
            'reason' => 'No permission rule matched',
        ]));

        $data = new AuthorizationPanel()->data();

        $this->assertSame('bob', $data['decisions'][0]['user_label']);
        $this->assertSame('user', $data['decisions'][0]['role']);
        $this->assertSame('No permission rule matched', $data['decisions'][0]['result_message']);
        $this->assertNull($data['decisions'][0]['matched_rule_pretty']);
    }

    /**
     * Superuser checks resolve the policy handler with source.
     *
     * @return void
     */
    public function testSuperuserEventRecordsPolicy(): void
    {
        $request = new ServerRequest([
            'url' => '/admin',
            'params' => ['controller' => 'Dashboard', 'action' => 'index', 'plugin' => null, 'prefix' => 'Admin'],
        ]);
        EventManager::instance()->dispatch(new Event('Auth.Authorization.checked', $this, [
            'allowed' => true,
            'policy' => TestSuperuserPolicy::class,
            'role' => 'superuser',
            'user' => ['id' => 1, 'email' => 'root@example.com'],
            'request' => $request,
            'permission' => null,
            'reason' => 'SuperuserPolicy allowed',
        ]));

        $data = new AuthorizationPanel()->data();

        $this->assertSame('Dashboard/index', $data['decisions'][0]['ability']);
        $this->assertSame('Superuser check', $data['decisions'][0]['origin']);
        $this->assertSame('allowed', $data['decisions'][0]['result']);
        $this->assertSame('root@example.com', $data['decisions'][0]['user_label']);
        $this->assertSame(
            TestSuperuserPolicy::class . '@canAccess',
            $data['decisions'][0]['handler_name'],
        );
        $this->assertStringContainsString(
            'TestSuperuserPolicy.php',
            (string)$data['decisions'][0]['handler_source_label'],
        );
    }

    /**
     * Collection drains the inbox so workers never bleed across requests.
     *
     * @return void
     */
    public function testDataDrainsInbox(): void
    {
        EventManager::instance()->dispatch(new Event('Auth.Rbac.checked', $this, [
            'allowed' => true,
            'role' => 'user',
            'user' => [],
            'request' => new ServerRequest(),
            'permission' => null,
            'result' => null,
            'reason' => null,
        ]));

        $panel = new AuthorizationPanel();
        $this->assertCount(1, $panel->data()['decisions']);
        $this->assertSame([], $panel->data()['decisions']);
    }

    /**
     * Middleware decorates exactly once and passes through without a service.
     *
     * @return void
     */
    public function testMiddlewareDecoratesOnce(): void
    {
        $service = new AuthorizationService(new MapResolver());
        $handler = new TestHandler();

        $middleware = new DebugBarAuthorizationMiddleware();
        $request = new ServerRequest()->withAttribute('authorization', $service);
        $middleware->process($request, $handler);
        $first = $handler->seen->getAttribute('authorization');
        $this->assertInstanceOf(DebugBarAuthorizationServiceDecorator::class, $first);

        $again = $handler->seen->withAttribute('authorization', $first);
        $middleware->process($again, $handler);
        $this->assertSame($first, $handler->seen->getAttribute('authorization'));

        $middleware->process(new ServerRequest(), $handler);
        $this->assertNull($handler->seen->getAttribute('authorization'));
    }
}
