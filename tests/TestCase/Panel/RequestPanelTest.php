<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Panel\RequestPanel;
use TestApp\Command\RequestProbeCommand;
use TestApp\Command\UnknownNameCommand;

/**
 * Covers CLI command name resolution.
 */
class RequestPanelTest extends TestCase
{
    /**
     * Routed names render as-is.
     *
     * @return void
     */
    public function testStartCommandKeepsRoutedName(): void
    {
        $panel = new RequestPanel();
        $panel->startCommand(new RequestProbeCommand(), []);

        $data = $panel->data();

        $this->assertSame('cli', $data['context']);
        $this->assertSame('request-probe', $data['name']);
    }

    /**
     * Unassigned runner names fall back to the default name.
     *
     * Nested runs via `executeCommand()` never receive `setName()`,
     * so `getName()` keeps the `BaseCommand` default and the panel
     * resolves the display name through `defaultName()` instead of
     * rendering `cake unknown`.
     *
     * @return void
     */
    public function testStartCommandFallsBackToDefaultName(): void
    {
        $panel = new RequestPanel();
        $panel->startCommand(new UnknownNameCommand(), []);

        $data = $panel->data();

        $this->assertSame('cli', $data['context']);
        $this->assertSame('cake mcp start', $data['name']);
        $this->assertSame(UnknownNameCommand::class, $data['command']);
    }
}
