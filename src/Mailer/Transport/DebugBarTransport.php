<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Mailer\Transport;

use ArrayObject;
use Cake\Core\App;
use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Message;
use Crustum\DebugBar\Service\CallsiteCapture;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use ReflectionClass;
use Throwable;

/**
 * Debug Transport class, useful for emulating the email sending process and inspecting
 * the resulting email message before actually sending it during development
 */
class DebugBarTransport extends AbstractTransport
{
    /**
     * Per-attachment body budget: bigger files keep metadata only.
     */
    protected const ATTACHMENT_MAX_BYTES = 1048576;

    /**
     * Total attachment body budget per message.
     */
    protected const ATTACHMENTS_TOTAL_MAX_BYTES = 2097152;

    /**
     * The transport object this class is decorating
     *
     * @var \Cake\Mailer\AbstractTransport|null
     */
    protected ?AbstractTransport $originalTransport = null;

    /**
     * A reference to the object were emails will be pushed to
     * for logging.
     *
     * @var \ArrayObject
     */
    protected ArrayObject $emailLog;

    /**
     * Constructor
     *
     * @param array $config Configuration options.
     * @param \Cake\Mailer\AbstractTransport|null $originalTransport The transport that is to be decorated
     */
    public function __construct(array $config = [], ?AbstractTransport $originalTransport = null)
    {
        if (!isset($config['debugKitLog']) || !$config['debugKitLog'] instanceof ArrayObject) {
            throw new InvalidArgumentException(
                'DebugBarTransport requires a `debugKitLog` config entry of type `ArrayObject`.',
            );
        }

        $this->emailLog = $config['debugKitLog'];

        if ($originalTransport instanceof AbstractTransport) {
            $this->originalTransport = $originalTransport;

            return;
        }

        $className = false;
        if (!empty($config['originalClassName'])) {
            /** @var class-string<\Cake\Mailer\AbstractTransport> $className */
            $className = App::className(
                $config['originalClassName'],
                'Mailer/Transport',
                'Transport',
            );
        }

        if ($className) {
            unset($config['originalClassName'], $config['debugKitLog']);
            $this->originalTransport = new $className($config);
        }
    }

    /**
     * Capture the message into the in-memory email log and optionally forward
     * to a wrapped real transport.
     *
     * Overrides the parent return shape: DebugBar stores the headers as an
     * associative array (so the panel can render rows) and splits the body
     * into text/html parts. Callers that consume this transport's return
     * value directly must account for this richer shape.
     *
     * @param \Cake\Mailer\Message $message The message to capture.
     * @return array
     * @phpstan-return array{headers: array<string, string>, message: array{text: string, html: string}, attachments: array<int, array{name: string, content_type: string, size_bytes: int|null, body_base64: string|null}>, attachments_omitted: int, status: string, duration_ms: float, transport: string, callsite: array{file: string|null, line: int|null}, stack: array<int, array{file: string|null, line: int|null, function: string, app: bool}>}|array<string, mixed>
     */
    public function send(Message $message): array
    {
        $headers = $message->getHeaders([
            'from', 'sender', 'replyTo', 'readReceipt', 'returnPath',
            'to', 'cc', 'bcc', 'date',
        ]);
        $headers['to'] = (string)($headers['To'] ?? '');
        $headers['cc'] = (string)($headers['Cc'] ?? '');
        $headers['bcc'] = (string)($headers['Bcc'] ?? '');
        $headers['from'] = (string)($headers['From'] ?? '');
        $headers['sender'] = (string)($headers['Sender'] ?? '');
        $headers['replyTo'] = (string)($headers['Reply-To'] ?? '');
        $headers['returnPath'] = (string)($headers['Return-Path'] ?? '');
        $headers['date'] = (string)($headers['Date'] ?? '');
        $parts = [
            'text' => $message->getBodyText(),
            'html' => $message->getBodyHtml(),
        ];

        $headers['Subject'] = $message->getOriginalSubject();
        $capture = CallsiteCapture::capture([__FILE__]);
        $attachmentCapture = $this->captureAttachments($message);
        $result = [
            'headers' => $headers,
            'message' => $parts,
            'attachments' => $attachmentCapture['items'],
            'attachments_omitted' => $attachmentCapture['omitted'],
            'status' => 'sent',
            'duration_ms' => 0.0,
            'transport' => $this->transportLabel(),
            'callsite' => $capture['callsite'],
            'stack' => $capture['stack'],
        ];
        $this->emailLog[] = $result;

        if ($this->originalTransport instanceof AbstractTransport) {
            $start = hrtime(true);
            $sent = $this->originalTransport->send($message);
            $result['duration_ms'] = round((hrtime(true) - $start) / 1000000, 2);
            $this->emailLog[count($this->emailLog) - 1] = $result;

            return $sent;
        }

        return $result;
    }

    /**
     * Captures attachment metadata, retaining bodies within budget.
     *
     * File contents never touch the log beyond the budgets; oversized or
     * unreadable files keep metadata with a null body and count as omitted.
     *
     * @param \Cake\Mailer\Message $message The message to capture.
     * @return array{items: array<int, array{name: string, content_type: string, size_bytes: int|null, body_base64: string|null}>, omitted: int}
     */
    protected function captureAttachments(Message $message): array
    {
        $items = [];
        $omitted = 0;
        $retainedBytes = 0;
        foreach ($message->getAttachments() as $name => $attachment) {
            $attachment = (array)$attachment;
            $label = $name !== '' ? $name : 'Attachment';
            $contentType = (string)($attachment['mimetype'] ?? 'application/octet-stream');
            [$size, $body] = $this->readAttachmentBody($attachment);
            if ($body === null) {
                $omitted++;
            } elseif (
                $size === null
                || $size > self::ATTACHMENT_MAX_BYTES
                || $retainedBytes + $size > self::ATTACHMENTS_TOTAL_MAX_BYTES
            ) {
                $body = null;
                $omitted++;
            } else {
                $retainedBytes += $size;
            }

            $items[] = [
                'name' => $label,
                'content_type' => $contentType !== '' ? $contentType : 'application/octet-stream',
                'size_bytes' => $size,
                'body_base64' => $body,
            ];
        }

        return ['items' => $items, 'omitted' => $omitted];
    }

    /**
     * Reads an attachment body, returning size and base64 (or null).
     *
     * @param array $attachment Normalized attachment entry.
     * @return array{int|null, string|null}
     */
    protected function readAttachmentBody(array $attachment): array
    {
        if (isset($attachment['data'])) {
            $raw = (string)preg_replace('/\s+/', '', (string)$attachment['data']);
            $decoded = base64_decode($raw, true);
            if ($decoded === false) {
                return [null, null];
            }

            return [strlen($decoded), base64_encode($decoded)];
        }

        $file = $attachment['file'] ?? null;
        if ($file instanceof UploadedFileInterface) {
            $size = $file->getSize();
            try {
                $contents = $file->getStream()->getContents();
            } catch (Throwable) {
                return [$size, null];
            }

            return [$size, base64_encode($contents)];
        }

        if (!is_string($file) || $file === '' || !is_readable($file)) {
            return [null, null];
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            return [null, null];
        }

        return [strlen($contents), base64_encode($contents)];
    }

    /**
     * Short class name of the wrapped transport, `Debug` when standalone.
     *
     * @return string
     */
    protected function transportLabel(): string
    {
        if (!$this->originalTransport instanceof AbstractTransport) {
            return 'Debug';
        }

        $reflection = new ReflectionClass($this->originalTransport);
        if ($reflection->isAnonymous()) {
            $parent = $reflection->getParentClass();
            if ($parent instanceof ReflectionClass) {
                return $parent->getShortName();
            }

            return 'Transport';
        }

        return $reflection->getShortName();
    }

    /**
     * Proxy unknown methods to the wrapped object
     *
     * @param string $method The method to call
     * @param array $args The args to call $method with.
     * @return mixed
     */
    public function __call(string $method, array $args): mixed
    {
        /** @var callable $callable */
        $callable = [$this->originalTransport, $method];

        return call_user_func_array($callable, $args);
    }

    /**
     * Proxy property reads to the wrapped object
     *
     * @param string $name The property to read.
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->originalTransport->{$name};
    }

    /**
     * Proxy property changes to the wrapped object
     *
     * @param string $name The property to read.
     * @param mixed $value The property value.
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->originalTransport->{$name} = $value;
    }

    /**
     * Proxy property changes to the wrapped object
     *
     * @param string $name The property to read.
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->originalTransport->{$name});
    }

    /**
     * Proxy property changes to the wrapped object
     *
     * @param string $name The property to delete.
     * @return void
     */
    public function __unset(string $name): void
    {
        unset($this->originalTransport->{$name});
    }
}
