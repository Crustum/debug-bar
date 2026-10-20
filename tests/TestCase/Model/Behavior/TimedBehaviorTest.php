<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Model\Behavior;

use Cake\TestSuite\TestCase;
use Crustum\DebugBar\DebugTimer;

/**
 * Class TimedBehaviorTestCase
 *
 * @ported-from \DebugKit\Test\TestCase\Model\Behavior\TimedBehaviorTest
 */
class TimedBehaviorTest extends TestCase
{
    /**
     * @var Table
     */
    protected $Article;

    /**
     * Start Test callback
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->Article = $this->fetchTable('Articles');
        $this->Article->addBehavior('Crustum/DebugBar.Timed');
    }

    /**
     * End a test
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->Article);
        DebugTimer::clear();
    }

    /**
     * Test find timers
     *
     * @return void
     */
    public function testFindTimers(): void
    {
        $timers = DebugTimer::getAll();
        $this->assertCount(1, $timers);

        $this->Article->find('all')->first();
        $result = DebugTimer::getAll();
        $this->assertCount(2, $result);

        $this->Article->find('all')->first();
        $result = DebugTimer::getAll();
        $this->assertCount(3, $result);
    }

    /**
     * Test save timers
     *
     * @return void
     */
    public function testSaveTimers(): void
    {
        $timers = DebugTimer::getAll();
        $this->assertCount(1, $timers);

        $article = $this->Article->newEntity(['user_id' => 1, 'title' => 'test', 'body' => 'test']);
        $this->Article->save($article);
        $result = DebugTimer::getAll();
        $this->assertCount(2, $result);
    }
}
