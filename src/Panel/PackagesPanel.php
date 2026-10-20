<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Composer\Json\JsonFile;
use Crustum\DebugBar\DebugPanel;

/**
 * Packages Panel - Reads all installed packages in the project.
 *
 * @ported-from \DebugKit\Panel\PackagesPanel
 */
class PackagesPanel extends DebugPanel
{
    /**
     * Get the panel data
     *
     * @return array
     */
    public function data(): array
    {
        $packages = [];
        $devPackages = [];
        $lockFile = new JsonFile(ROOT . DIRECTORY_SEPARATOR . 'composer.lock');
        if ($lockFile->exists()) {
            $lockContent = $lockFile->read();
            $packages = $lockContent['packages'];
            $devPackages = $lockContent['packages-dev'];
        }

        return [
            'packages' => $packages,
            'devPackages' => $devPackages,
        ];
    }
}
