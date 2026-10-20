<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Panel\ExceptionsPanel;
use Exception;

/**
 * Covers exception normalization.
 */
class ExceptionsPanelTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        ExceptionsPanel::clear();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        ExceptionsPanel::clear();
        parent::tearDown();
    }

    /**
     * Frames split into app and vendor with configured caps.
     *
     * @return void
     */
    public function testNormalizeSplitsFramesWithCaps(): void
    {
        try {
            throw new Exception('Outer failure');
        } catch (Exception $exception) {
            $normalized = ExceptionsPanel::normalize($exception, [
                'application' => 12,
                'vendor' => 12,
                'source_context_lines' => 9,
            ],);
        }

        $this->assertSame(Exception::class, $normalized['class']);
        $this->assertSame('Outer failure', $normalized['message']);
        $this->assertNotEmpty($normalized['frames']);
        $this->assertSame([], $normalized['causes']);
        $appFrames = array_filter(
            $normalized['frames'],
            static fn(array $frame): bool => empty($frame['vendor']),
        );
        $this->assertLessThanOrEqual(12, count($appFrames));
        $vendorFrames = array_filter(
            $normalized['frames'],
            static fn(array $frame): bool => !empty($frame['vendor']),
        );
        $this->assertLessThanOrEqual(12, count($vendorFrames));
    }

    /**
     * Chained causes cap at five with messages truncated.
     *
     * @return void
     */
    public function testNormalizeCapsCauses(): void
    {
        $error = new Exception('level 0');
        for ($level = 1; $level <= 7; $level++) {
            $error = new Exception("level {$level}", 0, $error);
        }

        $normalized = ExceptionsPanel::normalize($error);

        $this->assertSame('level 7', $normalized['message']);
        $this->assertCount(5, $normalized['causes']);
        $this->assertSame('level 6', $normalized['causes'][0]['message']);
    }

    /**
     * Collection drains the inbox so workers never bleed across requests.
     *
     * @return void
     */
    public function testDataDrainsInbox(): void
    {
        ExceptionsPanel::record(new Exception('Boom'));

        $panel = new ExceptionsPanel();
        $data = $panel->data();

        $this->assertCount(1, $data['exceptions']);
        $this->assertSame(1, $data['summary']['count']);
        $this->assertSame([], $panel->data()['exceptions']);
    }
}
