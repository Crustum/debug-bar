<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Service;

use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\MiniLauncher;

/**
 * Covers the mini launcher position/action contract.
 */
class MiniLauncherTest extends TestCase
{
    /**
     * Six host positions with a bottom-right default.
     *
     * @return void
     */
    public function testSanitizePosition(): void
    {
        $this->assertSame(
            ['bottom-right', 'bottom-left', 'top-right', 'top-left', 'middle-right', 'middle-left'],
            MiniLauncher::POSITIONS,
        );
        $this->assertSame('top-left', MiniLauncher::sanitizePosition('top-left'));
        $this->assertSame('bottom-right', MiniLauncher::sanitizePosition('nowhere'));
        $this->assertSame('bottom-right', MiniLauncher::sanitizePosition(null));
        $this->assertSame('bottom-right', MiniLauncher::sanitizePosition(42));
    }

    /**
     * Two click actions with a bar default.
     *
     * @return void
     */
    public function testSanitizeAction(): void
    {
        $this->assertSame(['bar', 'inspector'], MiniLauncher::ACTIONS);
        $this->assertSame('inspector', MiniLauncher::sanitizeAction('inspector'));
        $this->assertSame('bar', MiniLauncher::sanitizeAction('bar'));
        $this->assertSame('bar', MiniLauncher::sanitizeAction('nowhere'));
        $this->assertSame('bar', MiniLauncher::sanitizeAction(null));
    }
}
