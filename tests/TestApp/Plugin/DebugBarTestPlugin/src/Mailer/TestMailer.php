<?php
declare(strict_types=1);

namespace DebugBarTestPlugin\Mailer;

use Cake\Mailer\Mailer;
use Cake\Mailer\TransportFactory;

/**
 * @ported-from \DebugkitTestPlugin\Mailer\TestMailer
 */

class TestMailer extends Mailer
{
    /**
     * Test email method
     */
    public function test_email()
    {
        if (!TransportFactory::getConfig('default')) {
            TransportFactory::setConfig(['default' => ['className' => 'Debug']]);
        }

        return $this;
    }
}
