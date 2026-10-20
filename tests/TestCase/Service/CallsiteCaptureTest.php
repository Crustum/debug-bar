<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Service;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\CallsiteCapture;

/**
 * Covers callsite labels and Windows-safe splitting.
 */
class CallsiteCaptureTest extends TestCase
{
    /**
     * Labels join file and line, empty without a file.
     *
     * @return void
     */
    public function testLabel(): void
    {
        $this->assertSame(
            'src/Controller/TasksController.php:42',
            CallsiteCapture::label(['file' => 'src/Controller/TasksController.php', 'line' => 42]),
        );
        $this->assertSame('', CallsiteCapture::label(['file' => null, 'line' => null]));
    }

    /**
     * Splits on the last colon so drive letters survive.
     *
     * @return void
     */
    public function testSplit(): void
    {
        $this->assertSame(
            ['file' => 'C:\\app\\Controller.php', 'line' => 123],
            CallsiteCapture::split('C:\\app\\Controller.php:123'),
        );
        $this->assertSame(
            ['file' => '/var/www/app/Controller.php', 'line' => 7],
            CallsiteCapture::split('/var/www/app/Controller.php:7'),
        );
        $this->assertSame(
            ['file' => 'C:\\app\\Controller.php', 'line' => null],
            CallsiteCapture::split('C:\\app\\Controller.php'),
        );
        $this->assertSame(['file' => '', 'line' => null], CallsiteCapture::split(''));
    }

    /**
     * Capture keeps application frames up to the configured limit.
     *
     * @return void
     */
    public function testCaptureRespectsFrameLimit(): void
    {
        $capture = CallsiteCapture::capture([__FILE__], 40, 2);

        $this->assertNotEmpty($capture['stack']);
        $this->assertLessThanOrEqual(2, count($capture['stack']));
    }

    /**
     * Disabled call sites return the empty shape.
     *
     * @return void
     */
    public function testCaptureDisabledReturnsEmptyShape(): void
    {
        Configure::write('DebugBar.call_sites', false);
        try {
            $capture = CallsiteCapture::capture([__FILE__]);
        } finally {
            Configure::delete('DebugBar.call_sites');
        }

        $this->assertSame(['file' => null, 'line' => null], $capture['callsite']);
        $this->assertSame([], $capture['stack']);
    }
}
