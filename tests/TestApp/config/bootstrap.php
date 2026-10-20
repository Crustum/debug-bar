<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Cake\Mailer\TransportFactory;

/**
 * Test application bootstrap for Crustum/DebugBar.
 *
 * Registers fixture-only services captured by panel hooks that run
 * before controllers: a file cache profile for cache activity and a
 * debug mail transport that never sends.
 */

ensureDirectoryExists(CACHE . 'dbb_fixtures' . DS);

Cache::setConfig('dbb_fixtures', [
    'className' => 'File',
    'path' => CACHE . 'dbb_fixtures' . DS,
    'prefix' => 'dbb_browser_',
    'duration' => '+10 seconds',
    'serialize' => true,
]);

TransportFactory::setConfig('dbb', ['className' => 'Debug']);
