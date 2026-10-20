<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Controller;

use Authorization\AuthorizationService;
use Authorization\Policy\OrmResolver;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Http\ServerRequest;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Controller\DebugBarController;
use TestApp\Application;

/**
 * Composer controller test.
 */
class DebugBarControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * tests `debug` is disabled
     *
     * @return void
     */
    public function testDebugDisabled(): void
    {
        Configure::write('debug', false);

        $this->configApplication(Application::class, []);

        $this->get('/debug-bar/toolbar/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $this->assertResponseError();
        $this->assertResponseContains('Not available without debug mode on.');
    }

    /**
     * Build controller with AuthorizationService
     * in request attribute
     *
     * @return Crustum\DebugBar\Controller\DebugBarController
     */
    private function _buildController(): DebugBarController
    {
        $request = new ServerRequest(['url' => '/debug-bar/']);

        $resolver = new OrmResolver();
        $authorization = new AuthorizationService($resolver);

        $request = $request->withAttribute('authorization', $authorization);

        return new DebugBarController($request);
    }

    /**
     * tests authorization is enabled but not ignored
     *
     * @return void
     */
    public function testDontIgnoreAuthorization(): void
    {
        $controller = $this->_buildController();
        $event = new Event('testing');
        $controller->beforeFilter($event);

        $this->assertFalse($controller->getRequest()->getAttribute('authorization')->authorizationChecked());
    }

    /**
     * tests authorization is checked to avoid
     * AuthorizationRequiredException throwned
     *
     * @return void
     */
    public function testIgnoreAuthorization(): void
    {
        Configure::write('DebugBar.ignoreAuthorization', true);

        $controller = $this->_buildController();
        $event = new Event('testing');
        $controller->beforeFilter($event);

        $this->assertTrue($controller->getRequest()->getAttribute('authorization')->authorizationChecked());
    }
}
