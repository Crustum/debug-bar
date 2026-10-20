<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\Core\Plugin;
use Cake\Error\PhpError;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\MiddlewareQueue;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\DebugBarPlugin;
use Crustum\DebugBar\Panel\DeprecationsPanel;
use Crustum\DebugBar\ToolbarService;
use TestApp\Application;

/**
 * Test the Plugin
 */
class DebugBarPluginTest extends TestCase
{
    /**
     * Test setDeprecationHandler.
     *
     * @return void
     */
    public function testSetDeprecationHandler(): void
    {
        DeprecationsPanel::clearDeprecatedErrors();
        $service = new ToolbarService(new EventManager(), []);
        $plugin = new DebugBarPlugin();
        $plugin->setDeprecationHandler($service);

        $panel = new DeprecationsPanel();

        $error = new PhpError(E_USER_WARNING, 'ignored', __FILE__, __LINE__, []);
        $event = new Event('Error.beforeRender', null, ['error' => $error]);
        EventManager::instance()->dispatch($event);

        // No file/line in message.
        $expectedLine = __LINE__ + 1;
        $error = new PhpError(E_USER_DEPRECATED, 'going away', __FILE__, $expectedLine, []);
        $event = new Event('Error.beforeRender', null, ['error' => $error]);
        EventManager::instance()->dispatch($event);

        // Formatted like deprecationWarning()
        $message = <<<TEXT
Something deprecated happened.
Don't use that thing.
src/Plugin.php, line: 51
You can disable all deprecation warnings by setting `Error.errorLevel` to `E_ALL & ~E_USER_DEPRECATED`.
TEXT;
        $error = new PhpError(E_USER_DEPRECATED, $message, __FILE__, __LINE__, []);
        $event = new Event('Error.beforeRender', null, ['error' => $error]);
        EventManager::instance()->dispatch($event);

        $panel->shutdown($event);
        $data = $panel->data();

        $this->assertArrayHasKey('plugins', $data);
        $this->assertArrayHasKey('Crustum/DebugBar', $data['plugins']);
        $this->assertCount(1, $data['plugins']['Crustum/DebugBar']);

        $first = $data['plugins']['Crustum/DebugBar'][0];
        $this->assertEquals($first['message'], 'going away');
        $this->assertEquals($first['file'], __FILE__);
        $this->assertEquals($first['line'], $expectedLine);

        $this->assertArrayHasKey('other', $data);
        $parsed = $data['other'][0];
        $this->assertEquals($parsed['message'], $message);
        $this->assertEquals($parsed['file'], 'src/Plugin.php');
        $this->assertEquals($parsed['line'], 51);
    }

    /**
     * Make sure the DebugkitMiddleware is not loaded while running PHPUnit
     *
     * @return void
     */
    public function testMiddlewareNotLoadedInTests(): void
    {
        $collection = Plugin::getCollection();
        try {
            $baseApp = new Application(__DIR__ . '/../config');
            $baseApp->pluginBootstrap();
            $middlewareQueue = $baseApp->middleware(new MiddlewareQueue());
            $beforeCount = $middlewareQueue->count();
            $middlewareQueue = $baseApp->pluginMiddleware($middlewareQueue);
            $afterCount = $middlewareQueue->count();
            $this->assertSame($beforeCount, $afterCount);
        } finally {
            Plugin::setCollection($collection);
        }
    }
}
