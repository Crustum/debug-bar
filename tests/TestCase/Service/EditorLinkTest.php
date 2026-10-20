<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Service;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\EditorLink;

/**
 * Covers the centralized editor-link resolution (links config).
 */
class EditorLinkTest extends TestCase
{
    /**
     * @var mixed
     */
    protected $previousLinks;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->previousLinks = Configure::read('DebugBar.links');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Configure::write('DebugBar.links', $this->previousLinks);
        parent::tearDown();
    }

    /**
     * Disabled by default: stacks render copy affordances, never links.
     *
     * @return void
     */
    public function testDisabledByDefault(): void
    {
        Configure::write('DebugBar.links', ['enabled' => false]);

        $this->assertNull(EditorLink::url('src/Controller/AppController.php', 10));
    }

    /**
     * Custom link templates substitute file and line.
     *
     * @return void
     */
    public function testCustomLinkTemplate(): void
    {
        Configure::write('DebugBar.links', [
            'enabled' => true,
            'link' => 'cursor://file/{file}:{line}',
        ]);

        $this->assertSame(
            'cursor://file/src/Controller/AppController.php:10',
            EditorLink::url('src/Controller/AppController.php', 10),
        );
    }

    /**
     * Known editors map by name without a custom template.
     *
     * @return void
     */
    public function testKnownEditorNames(): void
    {
        Configure::write('DebugBar.links', ['enabled' => true, 'editor' => 'cursor']);

        $this->assertSame(
            'cursor://file/src/Controller/AppController.php:10',
            EditorLink::url('src/Controller/AppController.php', 10),
        );

        Configure::write('DebugBar.links', ['enabled' => true, 'editor' => 'phpstorm']);

        $this->assertSame(
            'phpstorm://open?file=src/Controller/AppController.php&line=10',
            EditorLink::url('src/Controller/AppController.php', 10),
        );
    }

    /**
     * Unknown editors and file-less frames never link.
     *
     * @return void
     */
    public function testUnknownEditorAndEmptyFile(): void
    {
        Configure::write('DebugBar.links', ['enabled' => true, 'editor' => 'notepad']);

        $this->assertNull(EditorLink::url('src/Controller/AppController.php', 10));
        $this->assertNull(EditorLink::url('', 10));
        $this->assertNull(EditorLink::url(null, 10));
    }
}
