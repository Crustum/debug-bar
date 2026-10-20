<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Log\Engine\DebugBarLog;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the logs inspector workspace.
 */
class LogsPanelTest extends TestCase
{
    use DebugBarTestTrait;
    use IntegrationTestTrait;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetToolbarStore();
    }

    /**
     * Repeated messages collapse into one group with a detail pane.
     *
     * @return void
     */
    public function testLogGroupsDedupWithDetail(): void
    {
        $logger = new DebugBarLog();
        $logger->log('error', 'Boom');
        $logger->log('error', 'Boom');
        $logger->log('info', 'Hello');

        $this->seedRequest(['Log' => ['logger' => $logger]]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Log'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('data-dk-logs-panel', $body);
        $this->assertStringContainsString('data-dk-workspace', $body);
        // Two identical errors collapse: one entry row with ×2 plus the info row.
        $this->assertStringContainsString('×2', $body);
        $this->assertStringContainsString('data-dk-log-detail="log-0"', $body);
        $this->assertStringContainsString('2 records in 1 entry', $body);
        $this->assertStringContainsString('Needs attention', $body);
        $this->assertStringContainsString('data-dk-select="logs-level"', $body);
        $this->assertStringContainsString('data-dk-search="logs"', $body);
        $this->assertStringContainsString('data-dk-list-empty', $body);
        $this->assertStringContainsString('data-dk-log-severity', $body);
        // No competing background on rows: setRowPressed swaps the indigo
        // markers, a lingering bg-transparent would win the cascade.
        $this->assertStringNotContainsString('dk:bg-transparent dk:hover', $body);
    }
}
