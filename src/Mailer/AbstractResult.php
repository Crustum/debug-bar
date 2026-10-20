<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Mailer;

/**
 * A simple structure for representing the results of a sent email
 *
 * @psalm-external-mutation-free
 * @ported-from \DebugKit\Mailer\AbstractResult
 */
abstract class AbstractResult
{
    /**
     * The list of headers included in the email
     *
     * @var array
     */
    protected array $headers = [];

    /**
     * The rendered parts of the email (for example text and html)
     *
     * @var array
     */
    protected array $parts = [];

    /**
     * Returns the list of headers included in th email
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Returns the rendered parts in th email
     *
     * @return array
     */
    public function getParts(): array
    {
        return $this->parts;
    }
}
