<?php

namespace Crustum\DebugBar\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Panels fixture.
 *
 * Used to create schema for tests and at runtime.
 *
 * @ported-from \DebugKit\Test\Fixture\PanelsFixture
 */
class PanelsFixture extends TestFixture
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
    public string $table = 'panels';

    /**
     * Records
     *
     * @var array
     */
    public array $records = [];
}
