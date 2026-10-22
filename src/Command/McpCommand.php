<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Command;

use Cake\Command\Command;
use Cake\Console\ConsoleOptionParser;
use Crustum\Mcp\Command\McpStartCommand;
use Override;

/**
 * Starts the DebugBar MCP server over STDIO.
 *
 * Thin wrapper around the `crustum/mcp` starter so IDE agents get the
 * bounded diagnostic tools (`ListRequests`, `GetPanelData`, `GetFindings`,
 * `InspectQueries`) without hand-writing the server handle. Requires the
 * optional `crustum/mcp` dependency.
 */
class McpCommand extends Command
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'debug-bar mcp';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Start the DebugBar MCP server (usually from mcp.json).';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription(static::getDescription());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function execute(): ?int
    {
        if (!class_exists(McpStartCommand::class)) {
            $this->io->error('The optional `crustum/mcp` dependency is not installed.');

            return static::CODE_ERROR;
        }

        return $this->executeCommand(McpStartCommand::class, ['cake-debugbar']);
    }
}
