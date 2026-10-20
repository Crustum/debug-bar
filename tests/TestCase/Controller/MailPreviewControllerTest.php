<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\TestCase\FixtureFactoryTrait;

/**
 * Mail preview controller test
 *
 * @ported-from \DebugKit\Test\TestCase\Controller\MailPreviewControllerTest
 */
class MailPreviewControllerTest extends TestCase
{
    use FixtureFactoryTrait;
    use IntegrationTestTrait;

    /**
     * Test that plugin is passed to the view in email action
     *
     * @return void
     */
    public function testEmailPluginPassedToView(): void
    {
        $this->get('/debug-bar/mail-preview/preview/TestMailerPreview/test_email?plugin=DebugBarTestPlugin');

        $this->assertResponseOk();
        $this->assertResponseContains('src="?part=html&plugin=DebugBarTestPlugin');
    }

    /**
     * Test that invalid classnames are rejected
     *
     * @return void
     */
    public function testEmailRejectInvalidClassName(): void
    {
        $this->get('/debug-bar/mail-preview/preview/Cake\Utility\Inflector/slug');
        $this->assertResponseCode(404);

        $this->get('/debug-bar/mail-preview/preview/Invalid/hello');
        $this->assertResponseCode(404);
    }

    /** Test email template content
     *
     * @return void
     */
    public function testEmailPartTextContent(): void
    {
        $this->get('/debug-bar/mail-preview/preview/TestMailerPreview/test_email?part=text&plugin=DebugBarTestPlugin');

        $this->assertResponseOk();
        $this->assertResponseContains('Testing email action.');
        $this->assertResponseContains('/users/verify/token', 'Should contain URL from app context');
    }

    /**
     * Test that onChange js function passes plugin to iframe
     *
     * @return void
     */
    public function testOnChangeJsPluginPassedToview(): void
    {
        $this->get('/debug-bar/mail-preview/preview/TestMailerPreview/test_email?plugin=DebugBarTestPlugin');

        $this->assertResponseContains("iframe.contentWindow.location.replace('?part=' + part_name + '&plugin=DebugBarTestPlugin');");
    }

    /**
     * Test sent() with invalid data.
     *
     * @return void
     */
    public function testSentInvalidData(): void
    {
        $this->get('/debug-bar/mail-preview/sent/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa/0');
        $this->assertResponseCode(404);
    }

    /**
     * Test sent() with valid data.
     *
     * @return void
     */
    public function testSentValidData(): void
    {
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $request = $this->makeRequest();
        $panel = $panels->newEntity(['request_id' => $request->id]);
        $data = [
            'emails' => [
                [
                    'headers' => ['To' => 'test@example.com'],
                    'message' => ['html' => '<h1>Hi</h1>', 'text' => 'Hi'],
                ],
            ],
        ];
        $panel->content = serialize($data);
        $panels->save($panel);

        $this->get("/debug-bar/mail-preview/sent/{$panel->id}/0");
        $this->assertResponseCode(200);
        $this->assertResponseContains('test@example.com');
        $this->assertResponseContains('<iframe');
    }

    /**
     * Test sent() with valid data rendering a part
     *
     * @return void
     */
    public function testSentValidDataRenderPart(): void
    {
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $request = $this->makeRequest();
        $panel = $panels->newEntity(['request_id' => $request->id]);
        $data = [
            'emails' => [
                [
                    'headers' => ['To' => 'test@example.com'],
                    'message' => ['html' => '<h1>Hi</h1>', 'text' => 'Hi'],
                ],
            ],
        ];
        $panel->content = serialize($data);
        $panels->save($panel);

        $this->get("/debug-bar/mail-preview/sent/{$panel->id}/0?part=html");
        $this->assertResponseCode(200);
        $this->assertResponseContains('<h1>Hi</h1>');
    }
}
