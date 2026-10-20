<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Database\Log;

use Cake\Database\Log\LoggedQuery;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Database\Log\DebugLog;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * DebugLog test case
 *
 * @ported-from \DebugKit\Test\TestCase\Database\Log\DebugLogTest
 */
class DebugLogTest extends TestCase
{
    /**
     * @var DebugLog
     */
    protected $logger;

    /**
     * setup
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new DebugLog(null, 'test');
    }

    /**
     * Test logs being stored.
     *
     * @return void
     */
    public function testLog(): void
    {
        $query = new LoggedQuery();
        $query->setContext([
            'query' => 'SELECT * FROM posts',
            'took' => 10,
            'numRows' => 5,
        ]);

        $this->assertCount(0, $this->logger->queries());

        $this->logger->log(LogLevel::DEBUG, (string)$query, ['query' => $query]);
        $this->assertCount(1, $this->logger->queries());
        $this->assertSame(10.0, $this->logger->totalTime());

        $this->logger->log(LogLevel::DEBUG, (string)$query, ['query' => $query]);
        $this->assertCount(2, $this->logger->queries());
        $this->assertSame(20.0, $this->logger->totalTime());
    }

    /**
     * Test logs being stored.
     *
     * @return void
     */
    public function testLogElastic(): void
    {
        $this->assertCount(0, $this->logger->queries());

        $this->logger->log(LogLevel::DEBUG, 'title:Good', [
            'query' => 'title:Good',
            'request' => [
                'method' => 'GET',
                'path' => '/articles/_search',
                'data' => '',
            ],
            'response' => [
                'took' => 10,
                'hits' => [
                    'total' => 15,
                ],
            ],
        ]);
        $this->assertCount(1, $this->logger->queries());
        $this->assertEquals(10, $this->logger->totalTime());

        $this->logger->log(LogLevel::DEBUG, 'title:Best', [
            'query' => 'title:Best',
            'request' => [
                'method' => 'GET',
                'path' => '/articles/_search',
                'data' => '',
            ],
            'response' => [
                'lol' => 'nope',
            ],
        ]);
        $this->assertCount(2, $this->logger->queries());
        $this->assertEquals(10, $this->logger->totalTime());
    }

    /**
     * Test log ignores schema reflection
     *
     * @return void
     */
    #[DataProvider('schemaQueryProvider')]
    public function testLogIgnoreReflection(string $sql): void
    {
        $query = new LoggedQuery();
        $query->setContext([
            'query' => $sql,
            'took' => 10,
            'numRows' => 5,
        ]);

        $this->assertCount(0, $this->logger->queries());

        $this->logger->log(LogLevel::DEBUG, (string)$query, ['query' => $query]);
        $this->assertCount(0, $this->logger->queries());
    }

    /**
     * Test config setting turns off schema ignores
     *
     * @return void
     */
    #[DataProvider('schemaQueryProvider')]
    public function testLogIgnoreReflectionDisabled(string $sql): void
    {
        $query = new LoggedQuery();
        $query->setContext([
            'query' => $sql,
            'took' => 10,
            'numRows' => 5,
        ]);

        $logger = new DebugLog(null, 'test', true);
        $this->assertCount(0, $logger->queries());

        $logger->log(LogLevel::DEBUG, (string)$query, ['query' => $query]);
        $this->assertCount(1, $logger->queries());
    }

    public static function schemaQueryProvider(): array
    {
        return [
            // MySQL
            ['SHOW TABLES FROM database'],
            ['SHOW FULL COLUMNS FROM database.articles'],
            // general
            ['SELECT * FROM information_schema'],
            // sqlserver
            ['SELECT I.[name] FROM sys.[tables]'],
            ['SELECT [name] FROM sys.foreign_keys'],
            ['SELECT [name] FROM INFORMATION_SCHEMA.TABLES'],
            // sqlite
            ['PRAGMA index_info()'],
        ];
    }

    /**
     * Test decoration of logger.
     *
     * @return void
     */
    public function testLogDecorates(): void
    {
        $orig = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $orig->expects($this->once())
            ->method('log');

        $query = new LoggedQuery();
        $logger = new DebugLog($orig, 'test');
        $logger->log(LogLevel::DEBUG, (string)$query, ['query' => $query]);
    }

    /**
     * Captured callsites point at application code, never the logger chain.
     *
     * @return void
     */
    public function testLogCapturesAppCallsite(): void
    {
        $query = new LoggedQuery();
        $query->setContext([
            'query' => 'SELECT * FROM posts',
            'took' => 10,
            'numRows' => 5,
        ]);

        $this->logger->log(LogLevel::DEBUG, (string)$query, ['query' => $query]);
        $queries = $this->logger->queries();
        $this->assertCount(1, $queries);

        $callsite = $queries[0]['callsite'];
        $this->assertSame(__FILE__, $callsite['file']);
        $this->assertIsInt($callsite['line']);

        $stack = $queries[0]['stack'];
        $this->assertNotEmpty($stack);
        foreach ($stack as $frame) {
            $this->assertTrue($frame['app']);
            $this->assertStringNotContainsString('vendor', str_replace('\\', '/', (string)$frame['file']));
        }
    }
}
