<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Authorization\Identity;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the authorization inspector workspace.
 */
class AuthorizationPanelTest extends TestCase
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
     * Decisions filter by result with the combined detail flow.
     *
     * @return void
     */
    public function testDecisionsWithDetailFlow(): void
    {
        $this->seedRequest([
            'Authorization' => [
                'decisions' => [
                    [
                        'execution' => 1,
                        'key' => 'authorization-1',
                        'ability' => 'view',
                        'origin' => 'Policy check',
                        'result' => 'allowed',
                        'result_label' => 'Allowed',
                        'result_message' => null,
                        'result_code' => null,
                        'result_status' => null,
                        'user_label' => 'mara',
                        'user_type' => 'App\Model\Entity\User',
                        'user_identifier_name' => 'id',
                        'user_identifier' => 42,
                        'role' => 'admin',
                        'matched_rule_pretty' => '{"role": "admin"}',
                        'arguments' => [
                            [
                                'position' => 1,
                                'role_label' => 'Resource',
                                'kind' => 'model',
                                'type' => 'App\Model\Entity\Article',
                                'label' => 'Article 9',
                                'identity_label' => 'Identifier 9',
                            ],
                        ],
                        'argument_summary' => 'Article 9',
                        'handler_kind' => 'policy',
                        'handler_label' => 'Matched policy method',
                        'handler_available' => true,
                        'handler_name' => 'App\Policy\ArticlePolicy@canView',
                        'handler_source_label' => 'src/Policy/ArticlePolicy.php:27',
                        'callsite_label' => 'src/Controller/ArticlesController.php:41',
                        'stack' => [
                            [
                                'file' => 'src/Controller/ArticlesController.php',
                                'line' => 41,
                                'function' => Identity::class . '::can',
                                'app' => true,
                            ],
                        ],
                        'copy_evidence' => '{"result": "allowed"}',
                        'search' => 'view allowed mara article 9',
                    ],
                    [
                        'execution' => 2,
                        'key' => 'authorization-2',
                        'ability' => 'Articles/delete',
                        'origin' => 'Rbac rule',
                        'result' => 'denied',
                        'result_label' => 'Denied',
                        'result_message' => 'No permission rule matched',
                        'result_code' => null,
                        'result_status' => null,
                        'user_label' => 'Guest',
                        'user_type' => null,
                        'user_identifier_name' => null,
                        'user_identifier' => null,
                        'role' => 'user',
                        'matched_rule_pretty' => null,
                        'arguments' => [
                            [
                                'position' => 1,
                                'role_label' => 'Controller',
                                'kind' => 'value',
                                'type' => 'string',
                                'label' => 'Articles',
                                'identity_label' => null,
                            ],
                            [
                                'position' => 2,
                                'role_label' => 'Action',
                                'kind' => 'value',
                                'type' => 'string',
                                'label' => 'delete',
                                'identity_label' => null,
                            ],
                        ],
                        'argument_summary' => 'Articles and 1 more',
                        'handler_kind' => 'callback',
                        'handler_label' => 'Matched check callback',
                        'handler_available' => true,
                        'handler_name' => 'Rbac permission rule',
                        'handler_source_label' => null,
                        'callsite_label' => null,
                        'stack' => [],
                        'copy_evidence' => '{"result": "denied"}',
                        'search' => 'articles/delete denied guest',
                    ],
                ],
                'summary' => ['count' => 2, 'allowed' => 1, 'denied' => 1],
            ],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Authorization'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->response->getBody();

        $this->assertStringContainsString('data-dk-authorization-panel', $body);
        $this->assertStringContainsString('data-dk-authorization-entry', $body);
        $this->assertStringContainsString('data-dk-select="authorization-filter"', $body);
        $this->assertStringContainsString('data-dk-authorization-detail="authorization-1"', $body);
        $this->assertStringContainsString('2 decisions', $body);
        $this->assertStringContainsString('Denied (1)', $body);
        $this->assertStringContainsString('Allowed (1)', $body);
        $this->assertStringContainsString('ArticlePolicy@canView', $body);
        $this->assertStringContainsString('Policy check', $body);
        $this->assertStringContainsString('Rbac rule', $body);
        $this->assertStringContainsString('data-dk-authorization-origin', $body);
        $this->assertStringContainsString('data-dk-authorization-detail-role', $body);
        $this->assertStringContainsString('Matched rule', $body);
        $this->assertStringContainsString('Copy rule', $body);
        $this->assertStringContainsString('Authorization response', $body);
        $this->assertStringContainsString('No permission rule matched', $body);
        $this->assertStringContainsString('Authorization logic', $body);
        $this->assertStringContainsString('Copy evidence', $body);
        $this->assertStringContainsString('data-dk-authorization-detail-panel="combined"', $body);
        $this->assertStringContainsString('Policy before hooks', $body);
    }

    /**
     * @return void
     */
    public function testEmptyDecisionsRenderEmptyState(): void
    {
        $this->seedRequest([
            'Authorization' => [
                'decisions' => [],
                'summary' => ['count' => 0, 'allowed' => 0, 'denied' => 0],
            ],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Authorization'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $this->assertStringContainsString(
            'No authorization decisions were captured',
            (string)$this->response->getBody(),
        );
    }
}
