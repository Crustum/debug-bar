<?php
declare(strict_types=1);

namespace TestApp\Authorization;

/**
 * Duck-typed identity stub (the panel reads methods, not the interface).
 */
class TestIdentity
{
    /**
     * @param int $id Identifier.
     * @param array<string, mixed> $data Original data.
     */
    public function __construct(
        protected int $id,
        protected array $data,
    ) {
    }

    /**
     * @return int
     */
    public function getIdentifier(): int
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOriginalData(): array
    {
        return $this->data;
    }
}
