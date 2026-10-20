<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Mailer;

/**
 * Represents the result of an already sent email
 *
 * @psalm-external-mutation-free
 * @ported-from \DebugKit\Mailer\SentMailResult
 */
class SentMailResult extends AbstractResult
{
    /**
     * Processes the mailer to extract the headers and parts
     *
     * @param array $headers The headers included in the email
     * @param array $parts The rendered parts in the email
     */
    public function __construct(array $headers, array $parts)
    {
        $this->headers = $headers;
        $this->parts = $parts;
    }
}
