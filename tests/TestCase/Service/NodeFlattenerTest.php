<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Service;

use Cake\Error\Debug\ScalarNode;
use Cake\Error\Debugger;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\NodeFlattener;

/**
 * Covers debug-node flattening for the request detail tables.
 */
class NodeFlattenerTest extends TestCase
{
    /**
     * Scalars survive the round trip untouched.
     *
     * @return void
     */
    public function testScalarNodes(): void
    {
        $this->assertSame('Tasks', NodeFlattener::toPlain(Debugger::exportVarAsNodes('Tasks')));
        $this->assertSame(200, NodeFlattener::toPlain(Debugger::exportVarAsNodes(200)));
        $this->assertTrue(NodeFlattener::toPlain(Debugger::exportVarAsNodes(true)));
        $this->assertNull(NodeFlattener::toPlain(Debugger::exportVarAsNodes(null)));
    }

    /**
     * Arrays flatten recursively with plain keys.
     *
     * @return void
     */
    public function testArrayNodes(): void
    {
        $plain = NodeFlattener::toPlain(Debugger::exportVarAsNodes([
            'controller' => 'Tasks',
            'query' => ['page' => 2, 'active' => true],
        ]));

        $this->assertSame('Tasks', $plain['controller']);
        $this->assertSame(['page' => 2, 'active' => true], $plain['query']);
    }

    /**
     * Deep graphs truncate instead of exploding.
     *
     * @return void
     */
    public function testDepthCap(): void
    {
        $plain = NodeFlattener::toPlain(Debugger::exportVarAsNodes(['a' => ['b' => ['c' => 1]]]), 1);

        $this->assertSame('…', $plain['a']);
    }

    /**
     * Objects flatten to their class plus every property (no bare markers).
     *
     * @return void
     */
    public function testClassNodeProperties(): void
    {
        $plain = NodeFlattener::toPlain(Debugger::exportVarAsNodes(
            new Entity(['name' => 'Ann', 'role' => ['admin' => true]], ['markClean' => true]),
        ));

        $this->assertSame(Entity::class, $plain['__class']);
        $this->assertSame('Ann', $plain['name']);
        $this->assertSame(['admin' => true], $plain['role']);
    }

    /**
     * Enums and resources degrade to JSON-safe markers, never blank output.
     *
     * @return void
     */
    public function testExoticScalars(): void
    {
        $this->assertSame('H', NodeFlattener::toPlain(new ScalarNode('enum', NodeFlattenerSuit::Hearts)));
        $this->assertSame('Hearts', NodeFlattener::toPlain(new ScalarNode('enum', NodeFlattenerPlainSuit::Hearts)));

        $stream = fopen('php://memory', 'r');
        $this->assertSame(
            'resource(' . get_resource_type($stream) . ')',
            NodeFlattener::toPlain(new ScalarNode('resource', $stream)),
        );
        fclose($stream);

        // A single unencodable value degrades per key instead of blanking.
        $json = NodeFlattener::jsonPretty(['a' => 1, 'b' => INF]);
        $this->assertNotNull($json);
        $this->assertStringContainsString('"a": 1', $json);
        $this->assertStringContainsString('unencodable', $json);
    }

    /**
     * Named node maps flatten by key, skipping non-nodes.
     *
     * @return void
     */
    public function testMap(): void
    {
        $plain = NodeFlattener::map([
            'method' => Debugger::exportVarAsNodes('POST'),
            'junk' => 'not-a-node',
        ]);

        $this->assertSame(['method' => 'POST'], $plain);
    }

    /**
     * Secret leaves mask by name, structures stay visible.
     *
     * @return void
     */
    public function testMaskSensitive(): void
    {
        $node = NodeFlattener::maskSensitive(Debugger::exportVarAsNodes([
            'username' => 'ann',
            'password' => 'hunter2',
            'password_set' => true,
            'nested' => ['token' => 'abc', 'page' => 2],
        ]));

        $plain = NodeFlattener::toPlain($node);
        $this->assertSame('ann', $plain['username']);
        $this->assertSame('********', $plain['password']);
        $this->assertTrue($plain['password_set']);
        $this->assertSame('********', $plain['nested']['token']);
        $this->assertSame(2, $plain['nested']['page']);
    }

    /**
     * Object properties mask by name, the class stays visible.
     *
     * @return void
     */
    public function testMaskSensitiveObject(): void
    {
        $node = NodeFlattener::maskSensitive(
            Debugger::exportVarAsNodes(new Entity(['name' => 'Ann', 'api_token' => 'abc'])),
        );

        $plain = NodeFlattener::toPlain($node);
        $this->assertSame('Ann', $plain['name']);
        $this->assertSame('********', $plain['api_token']);
    }
}
