<?php
declare(strict_types=1);

namespace TestApp\Authorization;

/**
 * Stand-in superuser policy for source reflection.
 */
class TestSuperuserPolicy
{
    /**
     * @param mixed $identity User identity.
     * @param mixed $resource Server request.
     * @return bool
     */
    public function canAccess(mixed $identity, mixed $resource): bool
    {
        return true;
    }
}
