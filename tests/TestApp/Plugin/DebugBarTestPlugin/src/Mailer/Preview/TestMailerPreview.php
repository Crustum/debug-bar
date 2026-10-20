<?php
declare(strict_types=1);

namespace DebugBarTestPlugin\Mailer\Preview;

use Crustum\DebugBar\Mailer\MailPreview;

/**
 * @ported-from \DebugkitTestPlugin\Mailer\Preview\TestMailerPreview
 */

class TestMailerPreview extends MailPreview
{
    /**
     * Test email
     */
    public function test_email()
    {
        return $this->getMailer('DebugBarTestPlugin.Test')->test_email();
    }
}
