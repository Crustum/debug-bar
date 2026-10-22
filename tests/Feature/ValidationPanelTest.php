<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the validation inspector body, static by design.
 */
class ValidationPanelTest extends TestCase
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
     * @return void
     */
    public function testEmptyValidationRendersSuccessState(): void
    {
        $this->seedRequest(['Validation' => ['errors' => []]]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Validation'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $this->assertStringContainsString('No validation errors.', (string)$this->response->getBody());
    }

    /**
     * @return void
     */
    public function testValidationErrorsRenderStackedArticles(): void
    {
        $this->seedRequest([
            'Validation' => ['errors' => [
                [
                    'field' => 'email',
                    'message' => 'Must be valid',
                    'bag' => 'default',
                    'status' => 422,
                    'redirect' => '/register',
                    'failed_rules' => ['email', 'unique'],
                ],
            ]],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Validation'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->response->getBody();

        $this->assertStringContainsString('data-dk-validation-entry', $body);
        $this->assertStringContainsString('email', $body);
        $this->assertStringContainsString('Failed rules', $body);
    }
}
