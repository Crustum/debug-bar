<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Service;

use Cake\Core\Configure;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\CliRecorder;

/**
 * Covers the generic CLI capture used by workers and commands.
 */
class CliRecorderTest extends TestCase
{
    /**
     * Tables to reset each test.
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Crustum/DebugBar.Requests',
        'plugin.Crustum/DebugBar.Panels',
    ];

    /**
     * Stashed host configuration.
     *
     * @var array<string, mixed>
     */
    protected array $oldConfig = [];

    /**
     * @var bool
     */
    protected bool $oldForce = false;

    /**
     * @var bool|null
     */
    protected ?bool $oldDebug = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $connection = ConnectionManager::get('test');
        $this->skipIf($connection->getDriver() instanceof Sqlite, 'CLI rows need a real database');

        $this->oldConfig = (array)Configure::read('DebugBar');
        $this->oldForce = (bool)($GLOBALS['FORCE_DEBUGBAR_TOOLBAR'] ?? false);
        $this->oldDebug = Configure::read('debug');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        CliRecorder::reset();
        Configure::write('DebugBar', $this->oldConfig);
        $GLOBALS['FORCE_DEBUGBAR_TOOLBAR'] = $this->oldForce;
        Configure::write('debug', $this->oldDebug);
        parent::tearDown();
    }

    /**
     * Disabled toolbar records nothing.
     *
     * @return void
     */
    public function testOpenReturnsFalseWhenDisabled(): void
    {
        Configure::write('debug', false);
        $GLOBALS['FORCE_DEBUGBAR_TOOLBAR'] = false;

        $this->assertFalse(CliRecorder::open('/console/test'));
        $this->assertFalse(CliRecorder::finish());
    }

    /**
     * CLI rows store without the web-only request panel.
     *
     * @return void
     */
    public function testCliRowHasNoRequestPanel(): void
    {
        $GLOBALS['FORCE_DEBUGBAR_TOOLBAR'] = true;

        $this->assertTrue(CliRecorder::open('/console/test'));
        $this->assertTrue(CliRecorder::finish());

        $requests = $this->getTableLocator()->get('Crustum/DebugBar.Requests');
        $row = $requests->find()
            ->orderBy(['Requests.requested_at' => 'DESC'])
            ->contain('Panels')
            ->firstOrFail();

        $this->assertSame('CLI', $row->method);
        $this->assertSame('console/test', $row->url);

        $names = [];
        foreach ((array)$row->panels as $panel) {
            $names[] = $panel->panel ?? null;
        }

        $this->assertNotContains('Request', $names);
        $this->assertContains('Environment', $names);
    }

    /**
     * CLI and job rows store bare names without a leading slash.
     *
     * @return void
     */
    public function testCliAndJobRowsStoreBareNames(): void
    {
        $GLOBALS['FORCE_DEBUGBAR_TOOLBAR'] = true;

        $this->assertTrue(CliRecorder::open('monitor-snapshot', 'CLI'));
        $this->assertTrue(CliRecorder::finish());

        $this->assertTrue(CliRecorder::open('DebugBarProbeJob', 'JOB'));
        $this->assertTrue(CliRecorder::finish());

        $requests = $this->getTableLocator()->get('Crustum/DebugBar.Requests');
        $rows = $requests->find()
            ->orderBy(['Requests.requested_at' => 'DESC'])
            ->limit(2)
            ->toArray();

        $urls = [];
        foreach ($rows as $row) {
            $urls[] = $row->method . ' ' . $row->url;
        }

        $this->assertContains('JOB DebugBarProbeJob', $urls);
        $this->assertContains('CLI monitor-snapshot', $urls);
    }
}
