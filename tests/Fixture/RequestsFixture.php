<?php

namespace Crustum\DebugBar\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Requests fixture.
 *
 * Used to create schema for tests and at runtime.
 *
 * @ported-from \DebugKit\Test\Fixture\RequestsFixture
 */
class RequestsFixture extends TestFixture
{
    /**
     * Connection name.
     *
     * @var string
     */
    public string $connection = 'test_debug_bar';

    /**
     * table property
     *
     * This is necessary to prevent userland inflections from causing issues.
     *
     * @var string
     */
    public string $table = 'requests';

    /**
     * Records
     *
     * @var array
     */
    public array $records = [];
}
