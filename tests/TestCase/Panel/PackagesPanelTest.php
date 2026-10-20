<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Panel\PackagesPanel;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Class PackagesPanelTest
 *
 * @ported-from \DebugKit\Test\TestCase\Panel\PackagesPanelTest
 */
class PackagesPanelTest extends TestCase
{
    /**
     * @var PackagesPanel
     */
    protected $panel;

    /**
     * set up
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->panel = new PackagesPanel();
    }

    /**
     * Packages view variables provider
     *
     * @return array
     */
    public static function packagesProvider(): array
    {
        return [
            'requirements' => ['packages'],
            'dev requirements' => ['devPackages'],
        ];
    }

    /**
     * test data
     *
     * @return void
     */
    #[DataProvider('packagesProvider')]
    public function testData(string $package): void
    {
        $data = $this->panel->data();
        $this->assertArrayHasKey($package, $data);

        $singlePackage = current($data[$package]);
        $this->assertArrayHasKey('name', $singlePackage);
        $this->assertArrayHasKey('description', $singlePackage);
        $this->assertArrayHasKey('version', $singlePackage);
    }
}
