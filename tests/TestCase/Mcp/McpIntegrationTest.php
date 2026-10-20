<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Mcp;

use Cake\Console\CommandCollection;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Command\McpCommand;
use Crustum\DebugBar\DebugBarPlugin;
use Crustum\DebugBar\Manifest\DebugBarManifest;
use Crustum\DebugBar\Mcp\DebugBarServer;
use Crustum\Mcp\Server;
use TestApp\Application;

/**
 * Covers the MCP wiring: local server registration, console
 * command and manifest dependency.
 */
class McpIntegrationTest extends TestCase
{
    /**
     * Bootstrap registers the local STDIO server handle.
     *
     * @return void
     */
    public function testBootstrapRegistersLocalServer(): void
    {
        if (!class_exists(Server::class)) {
            $this->markTestSkipped('crustum/mcp is not installed.');
        }

        $collection = Plugin::getCollection();
        $local = Configure::read('Mcp.local');
        try {
            Configure::write('Mcp.local', []);
            (new DebugBarPlugin())->bootstrap(new Application(CONFIG));
            $registered = Configure::read('Mcp.local', []);
            $this->assertSame(
                DebugBarServer::class,
                $registered['cake-debugbar'] ?? null,
            );
        } finally {
            Plugin::setCollection($collection);
            if ($local === null) {
                Configure::delete('Mcp.local');
            } else {
                Configure::write('Mcp.local', $local);
            }
        }
    }

    /**
     * The console exposes the STDIO starter command.
     *
     * @return void
     */
    public function testConsoleHasMcpCommand(): void
    {
        $commands = (new DebugBarPlugin())->console(new CommandCollection([]));

        $this->assertTrue($commands->has('debug-bar mcp'));
        $this->assertSame('debug-bar mcp', McpCommand::defaultName());
    }

    /**
     * The manifest declares the optional MCP runtime dependency.
     *
     * @return void
     */
    public function testManifestDeclaresOptionalMcpDependency(): void
    {
        $found = null;
        foreach (DebugBarManifest::manifest() as $op) {
            if (isset($op['dependencies']['Crustum/Mcp'])) {
                $found = $op['dependencies']['Crustum/Mcp']['required'] ?? 'missing';
            }
        }

        $this->assertFalse($found, 'Crustum/Mcp must stay an optional dependency.');
    }
}
