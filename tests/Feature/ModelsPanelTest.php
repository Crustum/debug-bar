<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the models inspector workspace.
 */
class ModelsPanelTest extends TestCase
{
    use DebugBarTestTrait;
    use IntegrationTestTrait;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetToolbarStore();
    }

    /**
     * Groups sort and the detail shows records/source tabs.
     *
     * @return void
     */
    public function testModelGroupsWithDetailTabs(): void
    {
        $this->seedRequest([
            'Models' => [
                'groups' => [
                    [
                        'table' => 'Articles',
                        'name' => 'ArticlesTable',
                        'model' => 'App\Model\Table\ArticlesTable',
                        'class' => 'App\Model\Table\ArticlesTable',
                        'connection' => 'test',
                        'retrieved' => 4,
                        'writes' => 1,
                        'reloads' => 2,
                        'unidentified' => 1,
                        'record_count' => 1,
                        'source_count' => 2,
                        'unidentified_load_count' => 1,
                        'records' => [
                            [
                                'identifier' => 'id:7',
                                'key' => 'id:7',
                                'retrieved' => 3,
                                'loads' => 3,
                                'source' => 'src/Controller/ArticlesController.php:10',
                                'source_app' => true,
                                'sources' => [
                                    [
                                        'source' => 'src/Controller/ArticlesController.php:10',
                                        'retrieval_count' => 2,
                                        'change_count' => 0,
                                    ],
                                    [
                                        'source' => 'src/Controller/ArticlesIndexController.php:5',
                                        'retrieval_count' => 1,
                                        'change_count' => 0,
                                    ],
                                ],
                                'source_count' => 2,
                                'unknown_sources' => 0,
                            ],
                        ],
                        'writes_list' => [
                            [
                                'operation' => 'Updated',
                                'event' => 'Updated',
                                'identifier' => 'id:7',
                                'key' => 'id:7',
                                'source' => 'src/Controller/ArticlesController.php:42',
                                'source_app' => true,
                                'changed' => ['title' => 'New title'],
                                'changes' => ['title' => 'New title'],
                                'changed_count' => 1,
                                'at_ms' => 12.5,
                            ],
                        ],
                        'sources' => [
                            [
                                'source' => 'C:\\app\\src\\Controller\\ArticlesController.php:10',
                                'source_app' => true,
                                'count' => 3,
                                'retrieval_count' => 3,
                                'change_count' => 0,
                                'chain' => ['ArticlesController.php:10', 'AppController.php:5'],
                            ],
                            [
                                'source' => 'src/Controller/ArticlesController.php:42',
                                'source_app' => true,
                                'count' => 1,
                                'retrieval_count' => 0,
                                'change_count' => 1,
                            ],
                        ],
                        'operations' => [
                            [
                                'op' => 'save',
                                'identifier' => 'id:7',
                                'changed' => ['title' => 'New title'],
                            ],
                        ],
                    ],
                ],
                'summary' => ['models' => 1, 'tables' => 1, 'retrieved' => 4, 'writes' => 1],
            ],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Models'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->response->getBody();

        $this->assertStringContainsString('data-dk-models-panel', $body);
        $this->assertStringContainsString('data-dk-model-entry', $body);
        $this->assertStringContainsString('data-dk-sort="models:model"', $body);
        $this->assertStringContainsString('data-dk-sort="models:reloads"', $body);
        $this->assertStringContainsString('data-dk-model-detail="model-1"', $body);
        $this->assertStringContainsString('data-dk-tab-panel="model-1:records"', $body);
        $this->assertStringContainsString('data-dk-tab-panel="model-1:source"', $body);
        $this->assertStringContainsString('How this model was loaded', $body);
        $this->assertStringContainsString('How this model changed', $body);
        $this->assertStringContainsString('Updated', $body);
        $this->assertStringContainsString('Record sources', $body);
        $this->assertStringContainsString('Changed fields', $body);
        $this->assertStringContainsString('From request start', $body);
        $this->assertStringContainsString('No models match this search', $body);
        $this->assertStringContainsString('New title', $body);
        // Windows paths split on the last colon, never `C:0`.
        $this->assertStringContainsString('ArticlesController.php:10', $body);
        $this->assertStringContainsString('AppController.php:5', $body);
        $this->assertStringNotContainsString('>C:0<', $body);
    }

    /**
     * @return void
     */
    public function testEmptyModelsExplainOptIn(): void
    {
        $this->seedRequest([
            'Models' => ['groups' => [], 'summary' => ['tables' => 0, 'retrieved' => 0, 'writes' => 0]],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Models'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $this->assertStringContainsString('No ORM operations were captured', (string)$this->response->getBody());
    }
}
