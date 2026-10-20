<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\QueryFormatter;

/**
 * Covers multiline SQL formatting for query evidence.
 */
class QueryFormatterTest extends TestCase
{
    /**
     * @return void
     */
    public function testFormatBreaksClausesAcrossLines(): void
    {
        $formatted = QueryFormatter::format('SELECT * FROM bookings WHERE day_id = 3 ORDER BY id');

        $this->assertStringContainsString("\n", $formatted);
        $this->assertStringContainsString('SELECT', $formatted);
        $this->assertStringContainsString('day_id = 3', $formatted);
    }

    /**
     * Formatted output is plain text for the shell highlighter.
     *
     * @return void
     */
    public function testFormatStripsFormatterHtml(): void
    {
        $formatted = QueryFormatter::format("SELECT id FROM users WHERE name = 'adm'");

        $this->assertStringNotContainsString('<', $formatted);
        $this->assertStringNotContainsString('style=', $formatted);
    }

    /**
     * Empty input passes through untouched.
     *
     * @return void
     */
    public function testFormatKeepsEmptyInput(): void
    {
        $this->assertSame('', QueryFormatter::format(''));
        $this->assertSame('   ', QueryFormatter::format('   '));
    }
}
