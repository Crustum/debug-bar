<?php
declare(strict_types=1);

namespace TestApp\Mcp;

use JsonSerializable;

/**
 * Self-referencing payload for walker cycle tests.
 */
class McpCircularNode implements JsonSerializable
{
    /**
     * @var array<string, mixed>
     */
    public array $ref = [];

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->ref;
    }
}
