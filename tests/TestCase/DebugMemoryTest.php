<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\DebugBar\DebugMemory;

/**
 * Class DebugMemoryTest
 *
 * @ported-from \DebugKit\Test\TestCase\DebugMemoryTest
 */
class DebugMemoryTest extends TestCase
{
    /**
     * test memory usage
     *
     * @return void
     */
    public function testMemoryUsage(): void
    {
        $result = DebugMemory::getCurrent();
        $this->assertIsInt($result);

        $result = DebugMemory::getPeak();
        $this->assertIsInt($result);
    }

    /**
     * Test record() automatic naming
     *
     * @return void
     */
    public function testRecordNoKey(): void
    {
        DebugMemory::clear();
        DebugMemory::record();
        $result = DebugMemory::getAll(true);
        $this->assertCount(1, $result);
        $this->assertStringContainsString('DebugMemoryTest.php line ' . (__LINE__ - 3), array_keys($result)[0]);
    }

    /**
     * test making memory use markers.
     *
     * @return void
     */
    public function testMemorySettingAndGetting(): void
    {
        DebugMemory::clear();
        $result = DebugMemory::record('test marker');
        $this->assertTrue($result);

        $result = DebugMemory::getAll(true);
        $this->assertCount(1, $result);
        $this->assertTrue(isset($result['test marker']));
        $this->assertIsNumeric($result['test marker']);

        $result = DebugMemory::getAll();
        $this->assertEmpty($result);

        DebugMemory::record('test marker');
        DebugMemory::record('test marker');
        $result = DebugMemory::getAll();

        $this->assertCount(2, $result);
        $this->assertTrue(isset($result['test marker']));
        $this->assertTrue(isset($result['test marker #2']));
    }
}
