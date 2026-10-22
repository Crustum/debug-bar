<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Doctrine\SqlFormatter\HtmlHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Throwable;

/**
 * Multiline SQL formatting for query evidence.
 *
 * The inspector evidence block shows the bound statement pretty-printed
 * (one clause per line, indented) while copy/EXPLAIN keep the raw runnable
 * SQL. Formatting returns plain text: `SqlFormatter` HTML output is
 * stripped of tags so the shell highlight.js pass owns all colorizing and
 * no inline styles leak into panel markup. Anything unformattable falls
 * back to the input unchanged.
 */
class QueryFormatter
{
    /**
     * Formats SQL across multiple lines.
     *
     * @param string $sql Raw or bound SQL.
     * @return string Multiline plain-text SQL, or the input when empty.
     */
    public static function format(string $sql): string
    {
        if (trim($sql) === '') {
            return $sql;
        }

        try {
            $html = new SqlFormatter(new HtmlHighlighter())->format($sql);
        } catch (Throwable) {
            return $sql;
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim($text, "\n");

        return $text !== '' ? $text : $sql;
    }
}
