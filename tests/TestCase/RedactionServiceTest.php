<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\RedactionService;

/**
 * Covers the redaction and bounding service.
 */
class RedactionServiceTest extends TestCase
{
    /**
     * Items beyond the maximum are reported, not silently dropped.
     *
     * @return void
     */
    public function testProcessItemsReportsOmissionCounts(): void
    {
        $service = new RedactionService(['collection' => ['max_items' => 2]]);
        $result = $service->processItems(['a', 'b', 'c']);

        $this->assertSame(['a', 'b'], array_values($result['items']));
        $this->assertSame(3, $result['retained']);
        $this->assertSame(1, $result['dropped']);
        $this->assertTrue($result['truncated']);
    }

    /**
     * Items within the maximum report a clean bill.
     *
     * @return void
     */
    public function testProcessItemsWithoutTruncation(): void
    {
        $service = new RedactionService(['collection' => ['max_items' => 5]]);
        $result = $service->processItems(['a']);

        $this->assertSame(1, $result['retained']);
        $this->assertSame(0, $result['dropped']);
        $this->assertFalse($result['truncated']);
    }

    /**
     * Depth, array size and string length are all bounded.
     *
     * @return void
     */
    public function testRedactValueBoundsNesting(): void
    {
        $service = new RedactionService(['collection' => [
            'max_items_per_array' => 2,
            'max_depth' => 1,
            'max_string_length' => 4,
        ]]);
        $result = $service->redactValue([
            'deep' => ['nested' => 'value'],
            'long' => 'abcdef',
            'extra' => 1,
            'dropped' => 2,
        ]);

        $this->assertSame('[depth limit]', $result['deep']);
        $this->assertSame('abcd', $result['long']);
        $this->assertArrayNotHasKey('dropped', $result);
    }

    /**
     * Non-array scalars pass through untouched.
     *
     * @return void
     */
    public function testRedactValueKeepsScalars(): void
    {
        $service = new RedactionService();

        $this->assertSame(7, $service->redactValue(7));
        $this->assertNull($service->redactValue(null));
        $this->assertTrue($service->redactValue(true));
    }

    /**
     * The full bindings policy keeps values, bounded.
     *
     * @return void
     */
    public function testRedactBindingsFullPolicy(): void
    {
        $service = new RedactionService(['query_bindings' => 'full']);

        $this->assertSame(['a' => 'secret', 'b' => 7], $service->redactBindings(['a' => 'secret', 'b' => 7]));
    }

    /**
     * The safe bindings policy masks strings only.
     *
     * @return void
     */
    public function testRedactBindingsSafePolicy(): void
    {
        $service = new RedactionService(['query_bindings' => 'safe']);

        $this->assertSame(['a' => '[redacted]', 'b' => 7], $service->redactBindings(['a' => 'secret', 'b' => 7]));
    }

    /**
     * The none bindings policy omits everything.
     *
     * @return void
     */
    public function testRedactBindingsNonePolicy(): void
    {
        $service = new RedactionService(['query_bindings' => 'none']);

        $this->assertSame([], $service->redactBindings(['a' => 'secret']));
    }

    /**
     * Password-like keys mask recursively, routing `pass` survives.
     *
     * @return void
     */
    public function testMaskSensitiveKeys(): void
    {
        $service = new RedactionService();

        $result = $service->maskSensitive([
            'username' => 'ann',
            'password' => 'hunter2',
            'password_set' => true,
            'api_token' => 'abc',
            'pass' => ['1'],
            'nested' => ['client_secret' => 'shh', 'page' => 2],
        ]);

        $this->assertSame('ann', $result['username']);
        $this->assertSame('********', $result['password']);
        $this->assertTrue($result['password_set']);
        $this->assertSame('********', $result['api_token']);
        $this->assertSame(['1'], $result['pass']);
        $this->assertSame('********', $result['nested']['client_secret']);
        $this->assertSame(2, $result['nested']['page']);
    }

    /**
     * Complete bindings survive the full policy untouched.
     *
     * @return void
     */
    public function testBindingsCompleteUnderFullPolicy(): void
    {
        $service = new RedactionService(['query_bindings' => 'full']);

        $this->assertSame('full', $service->queryBindingsPolicy());
        $this->assertTrue($service->bindingsComplete($service->redactBindings(['a' => 'secret', 'b' => 7])));
        $this->assertFalse($service->bindingsComplete(['a' => '[redacted]']));
        $this->assertFalse($service->bindingsComplete(['a' => ['b' => '[depth limit]']]));
    }

    /**
     * Masked policies never report complete bindings.
     *
     * @return void
     */
    public function testBindingsIncompleteUnderMaskedPolicies(): void
    {
        $safe = new RedactionService(['query_bindings' => 'safe']);

        $this->assertFalse($safe->bindingsComplete($safe->redactBindings(['a' => 'secret'])));

        $none = new RedactionService(['query_bindings' => 'none']);

        $this->assertFalse($none->bindingsComplete($none->redactBindings(['a' => 'secret'])));
    }

    /**
     * The hash key policy returns a stable digest.
     *
     * @return void
     */
    public function testRedactKeyHashPolicy(): void
    {
        $service = new RedactionService(['key_policy' => 'hash']);
        $first = $service->redactKey('session_user_7');
        $second = $service->redactKey('session_user_7');

        $this->assertSame($first, $second);
        $this->assertNotSame('session_user_7', $first);
        $this->assertSame(hash('sha256', 'session_user_7'), $first);
    }

    /**
     * The full key policy keeps the key readable.
     *
     * @return void
     */
    public function testRedactKeyFullPolicy(): void
    {
        $service = new RedactionService(['key_policy' => 'full']);

        $this->assertSame('session_user_7', $service->redactKey('session_user_7'));
    }
}
