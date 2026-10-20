<?php
declare(strict_types=1);

namespace TestApp\Command;

use Cake\Console\ConsoleOptionParser;

/**
 * Probe console command with known option defaults.
 */
class RequestProbeCommand
{
    /**
     * Routed command name for display and copy-line tests.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'request-probe';
    }

    /**
     * Option parser with documented defaults for panel tests.
     *
     * Note: `verbose`/`quiet`/`help` ship built into every parser —
     * redefining them throws, which is exactly why the panel filters
     * against parser defaults instead of assuming shapes.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser(): ConsoleOptionParser
    {
        return (new ConsoleOptionParser())
            ->addArgument('name', ['help' => 'Project name.'])
            ->addOption('dry-run', ['boolean' => true, 'default' => false]);
    }
}
