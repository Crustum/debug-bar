<?php
declare(strict_types=1);

namespace TestApp\Panel;

/**
 * Old-style panel fixture for the legacy adapter.
 *
 * Deliberately does not extend the current base class: it mimics panels
 * written against the previous toolbar generation.
 */
class LegacyStubPanel
{
    /**
     * @var string
     */
    public string $plugin = 'TestApp';

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Legacy Stub';
    }

    /**
     * @return string
     */
    public function elementName(): string
    {
        return 'TestApp.legacy_stub_panel';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return ['stub' => true];
    }

    /**
     * @return string
     */
    public function summary(): string
    {
        return 'stub';
    }

    /**
     * @return void
     */
    public function initialize(): void
    {
    }
}
