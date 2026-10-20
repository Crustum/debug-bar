<?php
declare(strict_types=1);

namespace TestApp\Stub;

use Crustum\DebugBar\DebugSql;

/**
 * @ported-from \DebugKit\TestApp\Stub\DebugSqlStub
 */

class DebugSqlStub extends DebugSql
{
    public static $isCli = true;

    protected static function isCli(): bool
    {
        return static::$isCli;
    }
}
