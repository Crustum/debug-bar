<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Mcp;

use ArrayObject;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Mcp\McpDataWalker;
use DateTimeImmutable;
use TestApp\Mcp\McpCircularNode;

/**
 * Covers the MCP data walker: sanitize, pointer reads, pagination.
 */
class McpDataWalkerTest extends TestCase
{
    /**
     * Objects collapse to class markers, cycles and depth cap hold.
     *
     * @return void
     */
    public function testSanitizeCollapsesObjects(): void
    {
        $walker = new McpDataWalker();
        $node = new McpCircularNode();
        $node->ref = ['self' => $node];

        $clean = $walker->sanitize([
            'logger' => new ArrayObject([1]),
            'when' => new DateTimeImmutable('2026-10-14 10:00:00+00:00'),
            'deep' => ['a' => ['b' => ['c' => 'value']]],
            'cycle' => $node,
            'scalar' => 7,
        ]);

        $this->assertSame('ArrayObject', $clean['logger']['__class']);
        $this->assertSame('2026-10-14T10:00:00+00:00', $clean['when']);
        $this->assertSame('value', $clean['deep']['a']['b']['c']);
        $this->assertSame(
            McpCircularNode::class,
            $clean['cycle']['self']['__circular'],
        );
        $this->assertSame(7, $clean['scalar']);
    }

    /**
     * @return void
     */
    public function testLocateFollowsPointers(): void
    {
        $walker = new McpDataWalker();
        $document = ['panels' => ['SqlLog' => ['records' => [['sql' => 'SELECT 1']]]]];

        $this->assertSame(
            'SELECT 1',
            $walker->locate($document, '/panels/SqlLog/records/0/sql')['value'],
        );
        $this->assertFalse($walker->locate($document, '/panels/Missing')['found']);
        $this->assertFalse($walker->locate($document, '/panels/SqlLog/records/9')['found']);
        $this->assertTrue($walker->locate($document, '')['found']);
    }

    /**
     * Long strings chunk with omitted bytes instead of dumping.
     *
     * @return void
     */
    public function testLongStringsChunk(): void
    {
        $walker = new McpDataWalker(50, 10);

        $view = $walker->render(str_repeat('a', 60), '', 0, 10);

        $this->assertTrue($view['chunked']);
        $this->assertSame(60, $view['length_bytes']);
        $this->assertCount(5, $view['chunks']);
        $this->assertSame(10, $view['omitted_due_to_bytes']);
        $this->assertSame('short', $walker->render('short', '', 0, 10)['value']);
    }

    /**
     * Lists paginate with cursors and child paths.
     *
     * @return void
     */
    public function testListsPaginate(): void
    {
        $walker = new McpDataWalker(50, 100000);

        $view = $walker->render([['sql' => 'a'], ['sql' => 'b'], ['sql' => 'c']], '/panels/X', 1, 1);

        $this->assertSame('list', $view['type']);
        $this->assertSame(3, $view['count']);
        $this->assertCount(1, $view['entries']);
        $this->assertSame('/panels/X/1', $view['entries'][0]['path']);
        $this->assertSame(2, $view['pagination']['next_cursor']);
        $this->assertTrue($view['pagination']['truncated']);
    }
}
