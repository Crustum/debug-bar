<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use ArrayObject;
use Cake\Core\App;
use Cake\Mailer\TransportFactory;
use Crustum\DebugBar\DebugPanel;
use Crustum\DebugBar\Mailer\Transport\DebugBarTransport;
use ReflectionClass;

/**
 * Provides debug information on the Emails sent during the request
 *
 * @ported-from \DebugKit\Panel\MailPanel
 */
class MailPanel extends DebugPanel implements TimelineSourceInterface
{
    /**
     * The list of emails produced during the request
     *
     * @var \ArrayObject|null
     */
    protected ?ArrayObject $emailLog = null;

    /**
     * Initialize hook - configures the email transport.
     *
     * @return void
     */
    public function initialize(): void
    {
        $reflection = new ReflectionClass(TransportFactory::class);
        $property = $reflection->getProperty('config');
        /** @var array<\Cake\Mailer\AbstractTransport|array> $configs */
        $configs = $property->getValue();
        $log = new ArrayObject();
        $this->emailLog = $log;

        foreach ($configs as $name => $transport) {
            if (is_object($transport)) {
                if (!$transport instanceof DebugBarTransport) {
                    $configs[$name] = new DebugBarTransport(['debugKitLog' => $log], $transport);
                }

                continue;
            }

            $className = App::className($transport['className'], 'Mailer/Transport', 'Transport');
            if (!$className || $className === DebugBarTransport::class) {
                continue;
            }

            $transport['originalClassName'] = $transport['className'];
            $transport['className'] = 'Crustum/DebugBar.DebugBar';
            $transport['debugKitLog'] = $log;

            $configs[$name] = $transport;
        }

        $reflection->setStaticPropertyValue('config', $configs);
    }

    /**
     * Get the data this panel wants to store.
     *
     * @return array
     */
    public function data(): array
    {
        return [
            'emails' => isset($this->emailLog) ? $this->emailLog->getArrayCopy() : [],
        ];
    }

    /**
     * Get summary data from the queries run.
     *
     * @return string
     */
    public function summary(): string
    {
        if (!$this->emailLog instanceof ArrayObject) {
            return '';
        }

        return (string)count($this->emailLog);
    }

    /**
     * Builds sent-mail rows for the waterfall.
     *
     * @param array<string, mixed> $data Mail panel data.
     * @param float $requestStart Request start as microtime(true).
     * @return list<array{at: float, duration_ms: float, type: string, label: string, panel: string, source: array{file: string, line: int}|null, ref: mixed}>
     */
    public function timelineEvents(array $data, float $requestStart): array
    {
        $events = [];
        foreach (array_values((array)($data['emails'] ?? [])) as $index => $email) {
            $email = (array)$email;
            $at = (float)($email['at'] ?? 0);
            if ($at <= 0) {
                continue;
            }

            $headers = (array)($email['headers'] ?? []);
            $subject = (string)($headers['Subject'] ?? '');
            $label = trim('Mail sent' . ($subject !== '' ? ' ' . $subject : ''));
            $callsite = (array)($email['callsite'] ?? []);
            $file = (string)($callsite['file'] ?? '');
            $events[] = [
                'at' => max(0.0, ($at - $requestStart) * 1000),
                'duration_ms' => max(0.0, (float)($email['duration_ms'] ?? 0)),
                'type' => 'mail',
                'label' => mb_strimwidth($label, 0, 120, '…'),
                'panel' => 'mail',
                'source' => $file !== '' ? ['file' => $file, 'line' => (int)($callsite['line'] ?? 0)] : null,
                'ref' => 'mail-' . $index,
            ];
        }

        return $events;
    }
}
