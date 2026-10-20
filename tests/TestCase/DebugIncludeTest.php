<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\DebugBar\DebugInclude;

/**
 * Class DebugInclude test
 *
 * @ported-from \DebugKit\Test\TestCase\DebugIncludeTest
 */
class DebugIncludeTest extends TestCase
{
    public function testIncludePaths(): void
    {
        $include = new DebugInclude();
        $result = $include->includePaths();
        $this->assertIsArray($result);
        $this->assertStringContainsString($result[0], get_include_path());
    }

    public function testIsCakeFile(): void
    {
        $include = new DebugInclude();

        $path = CAKE . 'Controller/Controller.php';
        $this->assertTrue($include->isCakeFile($path));

        $this->assertFalse($include->isCakeFile(__FILE__));
        $this->assertFalse($include->isCakeFile(TMP));
    }

    public function testIsAppFile(): void
    {
        $include = new DebugInclude();

        $path = APP . 'Application.php';
        $this->assertTrue($include->isAppFile($path));

        $this->assertFalse($include->isAppFile(__FILE__));
        $this->assertFalse($include->isAppFile(TMP));
    }

    public function testGetPluginName(): void
    {
        $include = new DebugInclude();

        $this->assertSame('Crustum/DebugBar', $include->getPluginName(__FILE__));
        $this->assertFalse($include->getPluginName(sys_get_temp_dir()));
    }

    public function testGetComposerPackageName(): void
    {
        $include = new DebugInclude();

        $path = CAKE . 'Controller/Controller.php';
        $this->assertSame('cakephp/cakephp', $include->getComposerPackageName($path));
    }

    public function testNiceFileName(): void
    {
        $include = new DebugInclude();

        $this->assertSame(
            'CAKE/Controller/Controller.php',
            $include->niceFileName(CAKE . 'Controller/Controller.php', 'cake'),
        );

        $this->assertSame(
            'APP/Application.php',
            $include->niceFileName(APP . 'Application.php', 'app'),
        );

        $this->assertSame(
            'ROOT/tests/bootstrap.php',
            $include->niceFileName(ROOT . '/tests/bootstrap.php', 'root'),
        );

        $this->assertSame(
            'Crustum/DebugBar/tests/bootstrap.php',
            $include->niceFileName(ROOT . '/tests/bootstrap.php', 'plugin', 'Crustum/DebugBar'),
        );

        $this->assertSame(
            'src/Controller/Controller.php',
            $include->niceFileName(CAKE . 'Controller/Controller.php', 'vendor', 'cakephp/cakephp'),
        );
    }

    public function testGetFileType(): void
    {
        $include = new DebugInclude();

        $this->assertSame('Controller', $include->getFileType(CAKE . 'Controller/Controller.php'));
        $this->assertSame('Component', $include->getFileType(CAKE . 'Controller/Component/FlashComponent.php'));
        $this->assertSame('Console', $include->getFileType(CAKE . 'Console/Shell.php'));
    }
}
