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
class MailPanel extends DebugPanel
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
}
