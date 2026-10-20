<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\RequestFacts;

/**
 * Covers inspector keys and panel badge counts.
 */
class RequestFactsTest extends TestCase
{
    /**
     * Titles slug to inspector keys.
     *
     * @return void
     */
    public function testInspectorKey(): void
    {
        $this->assertSame('queries', RequestFacts::inspectorKey('SqlLog'));
        $this->assertSame('queries', RequestFacts::inspectorKey('Sql Log'));
        $this->assertSame('request', RequestFacts::inspectorKey('Request'));
        $this->assertSame('request', RequestFacts::inspectorKey('Request Panel'));
        $this->assertSame('timer', RequestFacts::inspectorKey('Timer'));
        $this->assertSame('panel', RequestFacts::inspectorKey(''));
    }

    /**
     * Known panels count their badge numbers.
     *
     * @return void
     */
    public function testPanelCount(): void
    {
        $this->assertSame(2, RequestFacts::panelCount('SqlLog', ['records' => [['a'], ['b']]]));
        $this->assertSame(1, RequestFacts::panelCount('SqlLog', ['items' => [['a']]]));
        $this->assertSame(0, RequestFacts::panelCount('SqlLog', []));
        $this->assertSame(3, RequestFacts::panelCount('Cache', ['logs' => ['a', 'b', 'c']]));
        $this->assertSame(2, RequestFacts::panelCount('Mail', ['emails' => [['a'], ['b']]]));
        $this->assertSame(5, RequestFacts::panelCount('Timeline', ['retained' => 5]));
        $this->assertSame(2, RequestFacts::panelCount('Timeline', ['events' => [['a'], ['b']]]));
        $this->assertNull(RequestFacts::panelCount('Request', []));
        $this->assertNull(RequestFacts::panelCount('Unknown', ['a' => 1]));
        $this->assertNull(RequestFacts::panelCount('Routes', []));
        $this->assertNull(RequestFacts::panelCount('Session', []));
    }

    /**
     * Every list panel counts its badge number.
     *
     * @return void
     */
    public function testPanelCountLists(): void
    {
        $this->assertSame(2, RequestFacts::panelCount('Validation', ['errors' => [['a'], ['b']]]));
        $this->assertSame(0, RequestFacts::panelCount('Validation', []));
        $this->assertSame(3, RequestFacts::panelCount('Models', ['groups' => ['A' => [], 'B' => [], 'C' => []]]));
        $this->assertSame(4, RequestFacts::panelCount('HttpClient', ['summary' => ['count' => 4]]));
        $this->assertSame(1, RequestFacts::panelCount('HttpClient', ['requests' => [['a']]]));
        $this->assertSame(7, RequestFacts::panelCount('Findings', ['retained' => 7]));
        $this->assertSame(2, RequestFacts::panelCount('Findings', ['findings' => [['a'], ['b']]]));
        $this->assertSame(2, RequestFacts::panelCount('Exceptions', ['summary' => ['count' => 2]]));
        $this->assertSame(1, RequestFacts::panelCount('Exceptions', ['exceptions' => [['a']]]));
        $this->assertSame(5, RequestFacts::panelCount('Events', ['summary' => ['count' => 5]]));
        $this->assertSame(3, RequestFacts::panelCount('Events', ['events' => [['a'], ['b'], ['c']]]));
        $this->assertSame(2, RequestFacts::panelCount('Notifications', ['summary' => ['count' => 2]]));
        $this->assertSame(3, RequestFacts::panelCount('Notifications', ['summary' => ['count' => 3], 'groups' => [['a']]]));
        $this->assertNull(RequestFacts::panelCount('Notifications', ['groups' => [['a']]]));
        $this->assertSame(2, RequestFacts::panelCount('Views', ['summary' => ['count' => 2]]));
        $this->assertSame(1, RequestFacts::panelCount('Views', ['renders' => [['a']]]));
        $this->assertSame(2, RequestFacts::panelCount('Variables', ['variables' => ['a' => 1, 'b' => 2]]));
        $this->assertSame(3, RequestFacts::panelCount('Plugins', ['plugins' => ['a', 'b', 'c']]));
        $this->assertSame(3, RequestFacts::panelCount('Packages', ['packages' => ['a', 'b'], 'devPackages' => ['c']]));
        $this->assertSame(0, RequestFacts::panelCount('Packages', []));
        $this->assertSame(3, RequestFacts::panelCount('Attributes', ['total' => 3, 'items' => [['a']]]));
        $this->assertSame(2, RequestFacts::panelCount('Attributes', ['items' => [['a'], ['b']]]));
        $this->assertSame(0, RequestFacts::panelCount('Attributes', []));
    }

    /**
     * Deprecation buckets sum flat lists and grouped maps.
     *
     * @return void
     */
    public function testPanelCountDeprecations(): void
    {
        $this->assertSame(0, RequestFacts::panelCount('Deprecations', []));
        $this->assertSame(2, RequestFacts::panelCount('Deprecations', ['cake' => [['a'], ['b']]]));
        $this->assertSame(
            4,
            RequestFacts::panelCount('Deprecations', [
                'app' => [['a']],
                'plugins' => ['MyPlugin' => [['b'], ['c']], 'Other' => [['d']]],
            ]),
        );
    }

    /**
     * Built-in panels resolve a heading description, unknown panels stay empty.
     *
     * @return void
     */
    public function testInspectorDescription(): void
    {
        $this->assertSame(
            'Captured statements with bindings, source and EXPLAIN.',
            RequestFacts::inspectorDescription('SqlLog'),
        );
        $this->assertSame(
            'Cache operations with hit rate and raw log lines.',
            RequestFacts::inspectorDescription('Cache'),
        );
        $this->assertSame('', RequestFacts::inspectorDescription('Unknown'));
        $this->assertSame('', RequestFacts::inspectorDescription(''));
    }

    /**
     * Third-party panels register their description via Configure.
     *
     * @return void
     */
    public function testInspectorDescriptionThirdParty(): void
    {
        Configure::write('DebugBar.inspectorDescriptions', [
            'LiveWidget' => 'LiveWidget executions, fragments and HTMX requests.',
        ]);
        try {
            $this->assertSame(
                'LiveWidget executions, fragments and HTMX requests.',
                RequestFacts::inspectorDescription('LiveWidget'),
            );
            // Built-ins keep working alongside overrides.
            $this->assertSame(
                'Every captured event on one time scale.',
                RequestFacts::inspectorDescription('Timeline'),
            );
        } finally {
            Configure::delete('DebugBar.inspectorDescriptions');
        }

        $this->assertSame('', RequestFacts::inspectorDescription('LiveWidget'));
    }

    /**
     * Log levels flatten to one entry count.
     *
     * @return void
     */
    public function testPanelCountLog(): void
    {
        $this->assertSame(
            3,
            RequestFacts::panelCount('Log', ['logger' => ['error' => [['a'], ['b']], 'debug' => [['c']]]]),
        );
        $this->assertNull(RequestFacts::panelCount('Log', []));
        $this->assertNull(RequestFacts::panelCount('Log', ['logger' => null]));
    }
}
