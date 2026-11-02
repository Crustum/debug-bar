<?php
declare(strict_types=1);

namespace TestApp\Command;

use Cake\Console\ConsoleOptionParser;

/**
 * Command double without a runner-assigned name.
 *
 * Mirrors nested runs via `executeCommand()` (OpenCode MCP servers):
 * the runner never calls `setName()`, so `getName()` keeps the
 * `BaseCommand` default while `defaultName()` carries the real slug.
 */
class UnknownNameCommand
{
    /**
     * Unassigned runner name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'cake unknown';
    }

    /**
     * Real command slug.
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'mcp start';
    }

    /**
     * Empty parser for panel tests.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser(): ConsoleOptionParser
    {
        return new ConsoleOptionParser();
    }
}
