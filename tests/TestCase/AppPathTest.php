<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\Core\BasePlugin;
use Cake\Core\Plugin;
use Cake\Core\PluginCollection;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\AppPath;

/**
 * Covers slash-agnostic application vs vendor path checks.
 */
class AppPathTest extends TestCase
{
    /**
     * Unix vendor paths are vendor.
     *
     * @return void
     */
    public function testUnixVendorPath(): void
    {
        $this->assertTrue(AppPath::isVendor('/var/www/app/vendor/cakephp/cakephp/src/ORM/Table.php'));
        $this->assertFalse(AppPath::isApp('/var/www/app/vendor/cakephp/cakephp/src/ORM/Table.php'));
    }

    /**
     * Windows vendor paths use backslashes and must still match.
     *
     * @return void
     */
    public function testWindowsVendorPath(): void
    {
        $this->assertTrue(AppPath::isVendor('C:\\projects\\app\\vendor\\psr\\log\\src\\LoggerTrait.php'));
        $this->assertFalse(AppPath::isApp('C:\\projects\\app\\vendor\\psr\\log\\src\\LoggerTrait.php'));
    }

    /**
     * Application paths on either separator style stay app.
     *
     * @return void
     */
    public function testAppPaths(): void
    {
        $this->assertTrue(AppPath::isApp('/var/www/app/src/Model/Table/UsersTable.php'));
        $this->assertTrue(AppPath::isApp('C:\\projects\\app\\src\\Model\\Table\\UsersTable.php'));
        $this->assertFalse(AppPath::isVendor('/var/www/app/src/Model/Table/UsersTable.php'));
        $this->assertFalse(AppPath::isVendor('C:\\projects\\app\\src\\Model\\Table\\UsersTable.php'));
    }

    /**
     * Empty and missing paths are never vendor.
     *
     * @return void
     */
    public function testEmptyPaths(): void
    {
        $this->assertFalse(AppPath::isVendor(null));
        $this->assertFalse(AppPath::isVendor(''));
        $this->assertFalse(AppPath::isApp(null));
        $this->assertFalse(AppPath::isApp(''));
    }

    /**
     * Plugin files report plugin — vendor location or not.
     *
     * @return void
     */
    public function testPluginOrigin(): void
    {
        $previous = Plugin::getCollection();
        $collection = new PluginCollection();
        $collection->add(new BasePlugin([
            'name' => 'Admin',
            'path' => '/var/www/app/vendor/cakedc/admin/',
        ]));
        $collection->add(new BasePlugin([
            'name' => 'NotificationUI',
            'path' => 'C:\\projects\\app\\plugins\\NotificationUI\\',
        ]));
        Plugin::setCollection($collection);

        try {
            $this->assertSame(
                AppPath::ORIGIN_PLUGIN,
                AppPath::origin('/var/www/app/vendor/cakedc/admin/templates/element/view.php'),
            );
            $this->assertSame(
                AppPath::ORIGIN_PLUGIN,
                AppPath::origin('C:\\projects\\app\\plugins\\NotificationUI\\templates\\element\\bell.php'),
            );
            $this->assertSame(
                'Admin',
                AppPath::pluginName('/var/www/app/vendor/cakedc/admin/templates/element/view.php'),
            );
            $this->assertSame(
                'NotificationUI',
                AppPath::pluginName('C:\\projects\\app\\plugins\\NotificationUI\\templates\\element\\bell.php'),
            );
            $this->assertNull(
                AppPath::pluginName('/var/www/app/templates/Pages/home.php'),
            );
            $this->assertSame(
                AppPath::ORIGIN_FRAMEWORK,
                AppPath::origin('/var/www/app/vendor/cakephp/cakephp/templates/layout/default.php'),
            );
            $this->assertSame(
                AppPath::ORIGIN_APPLICATION,
                AppPath::origin('/var/www/app/templates/Pages/home.php'),
            );
        } finally {
            Plugin::setCollection($previous);
        }
    }
}
