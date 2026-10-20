<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\DebugPanel;
use Crustum\DebugBar\Panel\LegacyPanelAdapter;
use Crustum\DebugBar\Panel\PanelRegistry;
use RuntimeException;
use stdClass;
use TestApp\Panel\LegacyStubPanel;

/**
 * Covers the legacy panel adapter and registry fallback.
 */
class LegacyPanelAdapterTest extends TestCase
{
    /**
     * The adapter delegates the panel contract to the wrapped instance.
     *
     * @return void
     */
    public function testAdapterDelegatesContract(): void
    {
        $adapter = new LegacyPanelAdapter(new LegacyStubPanel());

        $this->assertInstanceOf(DebugPanel::class, $adapter);
        $this->assertSame('Legacy Stub', $adapter->title());
        $this->assertSame('TestApp.legacy_stub_panel', $adapter->elementName());
        $this->assertSame(['stub' => true], $adapter->data());
        $this->assertSame('stub', $adapter->summary());
        $this->assertSame('TestApp', $adapter->plugin);
        $this->assertSame([], $adapter->implementedEvents());
    }

    /**
     * The registry wraps legacy panels instead of failing.
     *
     * @return void
     */
    public function testRegistryLoadsLegacyPanel(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $panel = $registry->load('TestApp.LegacyStub');

        $this->assertInstanceOf(LegacyPanelAdapter::class, $panel);
        $this->assertSame('Legacy Stub', $panel->title());
    }

    /**
     * Classes without the panel contract are rejected with a clear message.
     *
     * @return void
     */
    public function testAdapterRejectsNonPanel(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must implement title\(\)/');
        new LegacyPanelAdapter(new stdClass());
    }

    /**
     * Legacy panels without contexts stay visible everywhere.
     *
     * @return void
     */
    public function testAdapterDefaultsContexts(): void
    {
        $adapter = new LegacyPanelAdapter(new LegacyStubPanel());

        $this->assertSame(['web', 'cli'], $adapter->contexts());
    }
}
