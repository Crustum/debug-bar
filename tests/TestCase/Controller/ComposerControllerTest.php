<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use TestApp\Application;

/**
 * Composer controller test.
 *
 * @ported-from \DebugKit\Test\TestCase\Controller\ComposerControllerTest
 */
class ComposerControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Setup method.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->configApplication(Application::class, []);
    }

    /**
     * tests index page returns as JSON
     *
     * @return void
     */
    public function testCheckDependencies(): void
    {
        $this->configRequest([
            'headers' => [
                'accept' => 'application/json, text/javascript, */*; q=0.01',
            ],
        ]);
        $this->post('/debug-bar/composer/check-dependencies');
        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $data = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('packages', $data);
    }

    /**
     * tests audit returns the normalized advisory envelope
     *
     * @return void
     */
    public function testAudit(): void
    {
        $this->configRequest([
            'headers' => [
                'accept' => 'application/json, text/javascript, */*; q=0.01',
            ],
        ]);
        $this->post('/debug-bar/composer/audit');
        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $data = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('vulnerable', $data);
        $this->assertArrayHasKey('advisories', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('error', $data);
        $this->assertSame(count((array)$data['vulnerable']), (int)$data['count']);
    }
}
