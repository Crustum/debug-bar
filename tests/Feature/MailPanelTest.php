<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the mail inspector split: rows, detail tabs, preview links and
 * attachment downloads served from the transport capture.
 */
class MailPanelTest extends TestCase
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
     * Seeds one request carrying two captured emails.
     *
     * @return string The request id.
     */
    protected function seedMail(): string
    {
        $request = $this->seedRequest([
            'Mail' => [
                'emails' => [
                    [
                        'headers' => [
                            'Subject' => 'Welcome aboard',
                            'from' => 'sender@example.com',
                            'to' => ['to@example.com', 'second@example.com'],
                            'cc' => [],
                            'bcc' => [],
                            'replyTo' => [],
                            'date' => 'Tue, 13 Oct 2026 12:00:00 +0300',
                        ],
                        'message' => [
                            'text' => 'Hello text',
                            'html' => '<p>Hello html</p>',
                        ],
                        'attachments' => [
                            [
                                'name' => 'notes.txt',
                                'content_type' => 'text/plain',
                                'size_bytes' => 11,
                                'body_base64' => base64_encode('hello-bytes'),
                            ],
                        ],
                        'attachments_omitted' => 1,
                        'status' => 'sent',
                        'duration_ms' => 12.5,
                        'transport' => 'Debug',
                        'callsite' => ['file' => 'src/Mailer/WelcomeMailer.php', 'line' => 42],
                        'stack' => [
                            [
                                'file' => 'src/Mailer/WelcomeMailer.php',
                                'line' => 42,
                                'function' => 'WelcomeMailer::send',
                                'app' => true,
                            ],
                        ],
                    ],
                    [
                        'headers' => [
                            'Subject' => 'Plain notice',
                            'from' => 'sender@example.com',
                            'to' => 'other@example.com',
                        ],
                        'message' => ['text' => 'Just text', 'html' => ''],
                        'attachments' => [],
                        'attachments_omitted' => 0,
                        'status' => 'sent',
                        'duration_ms' => 1.0,
                        'transport' => 'Debug',
                        'callsite' => ['file' => null, 'line' => null],
                        'stack' => [],
                    ],
                ],
            ],
        ]);

        return (string)$request->id;
    }

    /**
     * @return void
     */
    public function testMailRendersSplitWithDetail(): void
    {
        $id = $this->seedMail();
        $this->get('/debug-bar/toolbar/' . $id);
        $this->assertResponseOk();

        $body = (string)$this->response->getBody();
        $this->assertStringContainsString('data-dk-mail-panel', $body);
        $this->assertStringContainsString('data-dk-mail-entry', $body);
        $this->assertStringContainsString('data-dk-mail-detail', $body);
        $this->assertStringContainsString('mail-filter', $body);
        $this->assertStringContainsString('Attachments (1)', $body);
        $this->assertStringContainsString('Welcome aboard', $body);
        $this->assertStringContainsString('To to@example.com', $body);
        $this->assertStringContainsString('?part=html', $body);
        $this->assertStringContainsString('?part=text', $body);
        $this->assertStringContainsString('notes.txt', $body);
        $this->assertStringContainsString('not retained because', $body);
        $this->assertStringContainsString('WelcomeMailer.php', $body);
        $this->assertStringContainsString('Delivery details', $body);
        // Transport-normalized string recipients render as well as lists.
        $this->assertStringContainsString('other@example.com', $body);
    }

    /**
     * @return void
     */
    public function testAttachmentDownloadServesRetainedBody(): void
    {
        $this->seedMail();
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Mail'])->firstOrFail();

        $this->get('/debug-bar/mail-preview/sent/' . $panel->id . '/0/attachment/0');
        $this->assertResponseOk();
        $this->assertSame('hello-bytes', (string)$this->response->getBody());
        $this->assertStringContainsString(
            'text/plain',
            $this->response->getHeaderLine('Content-Type'),
        );
        $this->assertStringContainsString(
            'notes.txt',
            $this->response->getHeaderLine('Content-Disposition'),
        );
    }

    /**
     * @return void
     */
    public function testAttachmentDownloadMissesUnknownIndex(): void
    {
        $this->seedMail();
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Mail'])->firstOrFail();

        $this->get('/debug-bar/mail-preview/sent/' . $panel->id . '/0/attachment/9');
        $this->assertResponseCode(404);
    }
}
