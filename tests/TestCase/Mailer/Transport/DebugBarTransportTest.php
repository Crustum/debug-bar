<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Mailer\Transport;

use ArrayObject;
use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Message;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Mailer\Transport\DebugBarTransport;
use InvalidArgumentException;

class DebugBarTransportTest extends TestCase
{
    protected ArrayObject $log;

    protected DebugBarTransport $transport;

    protected $wrapped;

    protected function setUp(): void
    {
        parent::setUp();
        $this->log = new ArrayObject();
        $this->wrapped = new class extends AbstractTransport {
            public string $property;

            public function send(Message $message): array
            {
                return [];
            }

            public function customMethod(): string
            {
                return 'bloop';
            }
        };
        $this->transport = new DebugBarTransport(
            ['debugKitLog' => $this->log],
            $this->wrapped,
        );
    }

    public function testPropertyProxies(): void
    {
        $this->wrapped->property = 'value';
        $this->assertTrue(isset($this->transport->property));
        $this->assertSame('value', $this->transport->property);

        $this->transport->property = 'new value';
        $this->assertSame('new value', $this->wrapped->property);
        unset($this->transport->property);
        $this->assertFalse(isset($this->wrapped->property));
    }

    public function testMethodProxy(): void
    {
        $this->assertSame('bloop', $this->transport->customMethod());
    }

    public function testConstructorRejectsMissingDebugBarLog(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('debugKitLog');

        new DebugBarTransport([]);
    }

    public function testConstructorRejectsWrongDebugBarLogType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DebugBarTransport(['debugKitLog' => 'not-an-arrayobject']);
    }

    public function testEmailCapture(): void
    {
        $message = new Message();
        $message->setSubject('Testing 123')
            ->setFrom('sender@example.com')
            ->setTo('to@example.com');
        $this->transport->send($message);
        $this->assertCount(1, $this->log);

        $result = $this->log[0];
        $this->assertArrayHasKey('headers', $result);
        $this->assertArrayHasKey('message', $result);
    }

    /**
     * Wire-cased core headers normalize to the panel contract.
     *
     * @return void
     */
    public function testCaptureNormalizesHeaderKeys(): void
    {
        $message = new Message();
        $message->setSubject('UI Test')
            ->setTo('a@b.com')
            ->setEmailFormat('html');
        $this->transport->send($message);

        $headers = $this->log[0]['headers'];
        $this->assertSame('a@b.com', $headers['to']);
        $this->assertSame('UI Test', $headers['Subject']);
        $this->assertSame('', $headers['from']);
    }

    public function testCaptureCarriesDeliveryFacts(): void
    {
        $message = new Message();
        $message->setSubject('Facts')
            ->setFrom('sender@example.com')
            ->setTo('to@example.com')
            ->setBcc('bcc@example.com');
        $this->transport->send($message);

        $result = $this->log[0];
        $this->assertSame('sent', $result['status']);
        $this->assertSame('AbstractTransport', $result['transport']);
        $this->assertIsFloat($result['duration_ms']);
        $this->assertSame([], $result['attachments']);
        $this->assertSame(0, $result['attachments_omitted']);
        $this->assertArrayHasKey('callsite', $result);
        $this->assertArrayHasKey('stack', $result);
    }

    public function testStandaloneTransportLabelsDebug(): void
    {
        $transport = new DebugBarTransport(['debugKitLog' => $this->log]);
        $message = new Message();
        $message->setSubject('Solo')
            ->setFrom('sender@example.com')
            ->setTo('to@example.com');
        $transport->send($message);

        $this->assertSame('Debug', $this->log[0]['transport']);
        $this->assertSame(0.0, $this->log[0]['duration_ms']);
    }

    public function testCaptureRetainsAttachmentBodies(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'dk-mail-');
        file_put_contents($file, 'attachment-bytes');
        try {
            $message = new Message();
            $message->setSubject('Files')
                ->setFrom('sender@example.com')
                ->setTo('to@example.com')
                ->addAttachment($file, 'notes.txt', 'text/plain');
            $this->transport->send($message);
        } finally {
            unlink($file);
        }

        $attachments = $this->log[0]['attachments'];
        $this->assertCount(1, $attachments);
        $this->assertSame('notes.txt', $attachments[0]['name']);
        $this->assertSame('text/plain', $attachments[0]['content_type']);
        $this->assertSame(16, $attachments[0]['size_bytes']);
        $this->assertSame('attachment-bytes', base64_decode($attachments[0]['body_base64']));
        $this->assertSame(0, $this->log[0]['attachments_omitted']);
    }
}
