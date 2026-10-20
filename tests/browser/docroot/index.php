<?php
declare(strict_types=1);

/**
 * Front controller for the browser test server.
 *
 * Boots the test application (same bootstrap as the PHPUnit suite) and
 * serves it over PHP's built-in server so a real browser can load toolbar
 * pages with middleware injection, panel endpoints, and plugin assets.
 * Fixture routes live in the test application and are added per milestone.
 */

require dirname(__DIR__, 2) . '/bootstrap.php';

use Cake\Core\Configure;
use Cake\Http\Server;
use TestApp\Application;

$host = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8765');
if (!str_contains($host, '://')) {
    $host = 'http://' . $host;
}

Configure::write('App.fullBaseUrl', $host);

$app = new Application(CONFIG);
$server = new Server($app);
$server->emit($server->run());
