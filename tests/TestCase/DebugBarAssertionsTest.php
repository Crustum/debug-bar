<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Panel\ExceptionsPanel;
use Crustum\DebugBar\TestSuite\DebugBarAssertionsTrait;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Throwable;

/**
 * Covers the DebugBar assertions trait.
 */
class DebugBarAssertionsTest extends TestCase
{
    use DebugBarAssertionsTrait;

    /**
     * Schema lives at class scope: `#[Before]` trait hooks run before
     * `setUp()`, so per-test DDL would leak into the measured window.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (!ConnectionManager::getConfig('test_debug_bar')) {
            ConnectionManager::setConfig('test_debug_bar', ['url' => 'sqlite:///:memory:']);
        }

        ConnectionManager::get('test_debug_bar')->execute(
            'CREATE TABLE IF NOT EXISTS assert_items (id INTEGER PRIMARY KEY, name VARCHAR(50))',
        );
    }

    /**
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        try {
            ConnectionManager::get('test_debug_bar')->execute('DROP TABLE IF EXISTS assert_items');
        } catch (Throwable) {
        }

        parent::tearDownAfterClass();
    }

    /**
     * @return void
     */
    public function testQueryCountPassesWithinLimit(): void
    {
        ConnectionManager::get('test_debug_bar')->execute('SELECT * FROM assert_items');

        $this->assertQueryCountAtMost(5, 'test_debug_bar');
        $this->assertSame(1, count($this->debugBarNewQueries('test_debug_bar')));
    }

    /**
     * @return void
     */
    public function testQueryCountFailsOverLimit(): void
    {
        ConnectionManager::get('test_debug_bar')->execute('SELECT * FROM assert_items');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('at most 0 queries');
        $this->assertQueryCountAtMost(0, 'test_debug_bar');
    }

    /**
     * @return void
     */
    public function testQueryTimeStaysWithinBudget(): void
    {
        ConnectionManager::get('test_debug_bar')->execute('SELECT * FROM assert_items');

        $this->assertQueryTimeAtMost(5000.0, 'test_debug_bar');
    }

    /**
     * @return void
     */
    public function testRepeatedShapeTripsNPlusOne(): void
    {
        $connection = ConnectionManager::get('test_debug_bar');
        for ($i = 0; $i < 6; $i++) {
            $connection->execute("SELECT * FROM assert_items WHERE id = {$i}");
        }

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Possible N+1');
        $this->assertNoNPlusOne(5, 'test_debug_bar');
    }

    /**
     * @return void
     */
    public function testFewRepeatsPassNPlusOne(): void
    {
        $connection = ConnectionManager::get('test_debug_bar');
        for ($i = 0; $i < 2; $i++) {
            $connection->execute("SELECT * FROM assert_items WHERE id = {$i}");
        }

        $this->assertNoNPlusOne(5, 'test_debug_bar');
    }

    /**
     * @return void
     */
    public function testDurationMeasuresCallable(): void
    {
        $result = $this->assertDurationAtMost(5000.0, static fn(): string => 'done');

        $this->assertSame('done', $result);

        $this->expectException(AssertionFailedError::class);
        $this->assertDurationAtMost(0.0, static function (): void {
            usleep(2000);
        });
    }

    /**
     * @return void
     */
    public function testNoErrorsPassesClean(): void
    {
        $this->assertNoErrors();
    }

    /**
     * @return void
     */
    public function testRecordedErrorFailsNoErrors(): void
    {
        ExceptionsPanel::record(new RuntimeException('boom'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('RuntimeException');
        $this->assertNoErrors();
    }

    /**
     * Only queries logged after the setup count toward assertions.
     *
     * @return void
     */
    public function testOffsetsIsolateTests(): void
    {
        $this->assertQueryCountAtMost(0, 'test_debug_bar');
    }
}
