<?php
declare(strict_types=1);

namespace TestApp\Authorization;

/**
 * Test resource for policy resolution.
 */
class TestResource
{
    /**
     * @param int $id Resource identifier.
     * @param bool $owned Whether the check passes.
     */
    public function __construct(
        public int $id,
        public bool $owned,
    ) {
    }
}
