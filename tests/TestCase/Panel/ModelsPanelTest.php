<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Panel\ModelsPanel;

/**
 * Covers model operation capture and grouping.
 */
class ModelsPanelTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        ModelsPanel::clear();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        ModelsPanel::clear();
        parent::tearDown();
    }

    /**
     * Global listeners catch every table with no behavior attached.
     *
     * The event manager merges global listeners into each table-local
     * dispatch, so plain locator tables are captured as-is.
     *
     * @return void
     */
    public function testGlobalListenersCaptureFind(): void
    {
        $panel = new ModelsPanel();
        $panel->initialize();

        try {
            $articles = $this->fetchTable('Articles');
            $articles->find()->first();
        } finally {
            EventManager::instance()->off('Model.beforeFind', $panel->captureFind(...));
            EventManager::instance()->off('Model.beforeSave', $panel->captureSave(...));
            EventManager::instance()->off('Model.beforeDelete', $panel->captureDelete(...));
        }

        $data = $panel->data();

        $this->assertCount(1, $data['groups']);
        $this->assertSame('Articles', $data['groups'][0]['table']);
        $this->assertGreaterThanOrEqual(1, $data['groups'][0]['retrieved']);
        $this->assertSame(0, $data['groups'][0]['writes']);
    }

    /**
     * Primary-key equality in the query becomes the record identifier.
     *
     * @return void
     */
    public function testCaptureFindReadsPkFromWhere(): void
    {
        $panel = new ModelsPanel();
        $panel->initialize();

        try {
            $articles = $this->fetchTable('Articles');
            $articles->find()->where(['Articles.id' => 3])->first();
            $articles->find()->first();
        } finally {
            EventManager::instance()->off('Model.beforeFind', $panel->captureFind(...));
            EventManager::instance()->off('Model.beforeSave', $panel->captureSave(...));
            EventManager::instance()->off('Model.beforeDelete', $panel->captureDelete(...));
        }

        $data = $panel->data();

        $this->assertCount(1, $data['groups']);
        $this->assertSame(2, $data['groups'][0]['retrieved']);
        $this->assertSame(1, $data['groups'][0]['unidentified']);
        $this->assertCount(1, $data['groups'][0]['records']);
        $this->assertSame('id:3', $data['groups'][0]['records'][0]['identifier']);
        $this->assertSame(1, $data['groups'][0]['records'][0]['loads']);
        // The source keeps its application call chain for the Sources tab.
        $this->assertNotEmpty($data['groups'][0]['sources']);
        $this->assertNotEmpty($data['groups'][0]['sources'][0]['chain']);
    }

    /**
     * Writes group per class with logical labels and sources.
     *
     * @return void
     */
    public function testRecordGroupsWritesWithChanged(): void
    {
        ModelsPanel::record([
            'op' => 'save',
            'table' => 'Articles',
            'class' => 'App\Model\Table\ArticlesTable',
            'connection' => 'test',
            'identifier' => 'id:7',
            'changed' => ['title' => 'New title'],
            'is_new' => false,
            'source' => 'src/Controller/ArticlesController.php:42',
            'source_app' => true,
        ]);

        $panel = new ModelsPanel();
        $data = $panel->data();

        $this->assertCount(1, $data['groups']);
        $this->assertSame('App\Model\Table\ArticlesTable', $data['groups'][0]['class']);
        $this->assertSame('ArticlesTable', $data['groups'][0]['name']);
        $this->assertSame(0, $data['groups'][0]['retrieved']);
        $this->assertSame(1, $data['groups'][0]['writes']);
        $this->assertSame('Updated', $data['groups'][0]['writes_list'][0]['operation']);
        $this->assertSame(['title' => 'New title'], $data['groups'][0]['writes_list'][0]['changed']);
        $this->assertSame(
            'src/Controller/ArticlesController.php:42',
            $data['groups'][0]['writes_list'][0]['source'],
        );
        $this->assertSame(1, $data['summary']['writes']);
    }

    /**
     * Sibling observer models never reach the panel.
     *
     * Speculum's bookkeeping tables fire the same model events as host
     * tables; recording them duplicates the SQL log volume in model
     * shape, so classes under the ignored namespaces are dropped.
     *
     * @return void
     */
    public function testRecordSkipsIgnoredNamespaces(): void
    {
        $previous = Configure::read('DebugBar.ignored_model_namespaces');
        Configure::write('DebugBar.ignored_model_namespaces', ['Crustum\\Speculum\\']);

        try {
            ModelsPanel::record([
                'op' => 'find',
                'table' => 'SpeculumEntries',
                'class' => 'Crustum\\Speculum\\Model\\Table\\SpeculumEntriesTable',
                'connection' => 'test',
                'identifier' => 'id:1',
                'changed' => [],
                'source' => 'src/Controller/PostsController.php:73',
                'source_app' => true,
            ]);
            ModelsPanel::record([
                'op' => 'find',
                'table' => 'Articles',
                'class' => 'App\\Model\\Table\\ArticlesTable',
                'connection' => 'test',
                'identifier' => 'id:3',
                'changed' => [],
                'source' => 'src/Controller/ArticlesController.php:10',
                'source_app' => true,
            ]);

            $panel = new ModelsPanel();
            $data = $panel->data();

            $this->assertCount(1, $data['groups']);
            $this->assertSame('App\\Model\\Table\\ArticlesTable', $data['groups'][0]['class']);
        } finally {
            Configure::write('DebugBar.ignored_model_namespaces', $previous);
        }
    }

    /**
     * Re-reads of a known identity count as reloads with per-record rows.
     *
     * @return void
     */
    public function testRepeatedReadsCountAsReloads(): void
    {
        foreach ([1, 2] as $ignored) {
            ModelsPanel::record([
                'op' => 'find',
                'table' => 'Articles',
                'class' => 'App\Model\Table\ArticlesTable',
                'connection' => 'test',
                'identifier' => 'id:3',
                'changed' => [],
                'source' => 'src/Controller/ArticlesController.php:10',
                'source_app' => true,
            ]);
        }

        $panel = new ModelsPanel();
        $data = $panel->data();

        $this->assertSame(2, $data['groups'][0]['retrieved']);
        $this->assertSame(1, $data['groups'][0]['reloads']);
        $this->assertCount(1, $data['groups'][0]['records']);
        $this->assertSame('id:3', $data['groups'][0]['records'][0]['identifier']);
        $this->assertSame(2, $data['groups'][0]['records'][0]['retrieved']);
        $this->assertCount(1, $data['groups'][0]['sources']);
    }

    /**
     * New entities map to the Created logical write label.
     *
     * @return void
     */
    public function testNewEntityMapsToCreatedLabel(): void
    {
        ModelsPanel::record([
            'op' => 'save',
            'table' => 'Articles',
            'class' => 'App\Model\Table\ArticlesTable',
            'connection' => 'test',
            'identifier' => '',
            'changed' => ['title' => 'Fresh'],
            'is_new' => true,
            'source' => '',
            'source_app' => false,
        ]);

        $panel = new ModelsPanel();
        $data = $panel->data();

        $this->assertSame('Created', $data['groups'][0]['writes_list'][0]['operation']);
    }

    /**
     * Deletes map to the Deleted logical write label.
     *
     * @return void
     */
    public function testDeleteMapsToDeletedLabel(): void
    {
        ModelsPanel::record([
            'op' => 'delete',
            'table' => 'Articles',
            'class' => 'App\Model\Table\ArticlesTable',
            'connection' => 'test',
            'identifier' => 'id:9',
            'changed' => [],
            'source' => '',
            'source_app' => false,
        ]);

        $panel = new ModelsPanel();
        $data = $panel->data();

        $this->assertSame('Deleted', $data['groups'][0]['writes_list'][0]['operation']);
    }

    /**
     * Collection drains the inbox so workers never bleed across requests.
     *
     * @return void
     */
    public function testDataDrainsInbox(): void
    {
        ModelsPanel::record([
            'op' => 'find',
            'table' => 'Articles',
            'class' => 'App\Model\Table\ArticlesTable',
            'connection' => 'test',
            'identifier' => '',
            'changed' => [],
        ]);

        $panel = new ModelsPanel();
        $this->assertCount(1, $panel->data()['groups']);
        $this->assertSame([], $panel->data()['groups']);
    }
}
