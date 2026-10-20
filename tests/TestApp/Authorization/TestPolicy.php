<?php
declare(strict_types=1);

namespace TestApp\Authorization;

use Authorization\Policy\Result;
use Authorization\Policy\ResultInterface;

/**
 * Test policy with an allow/deny branch.
 */
class TestPolicy
{
    /**
     * @param mixed $user Checked identity.
     * @param \TestApp\Authorization\TestResource $resource Checked resource.
     * @return \Authorization\Policy\ResultInterface
     */
    public function canView(mixed $user, TestResource $resource): ResultInterface
    {
        return $resource->owned ? new Result(true) : new Result(false, 'Not the owner.');
    }
}
