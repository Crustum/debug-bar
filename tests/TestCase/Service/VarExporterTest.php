<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Service;

use Cake\Error\Debugger;
use Cake\I18n\DateTime;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\VarExporter;
use RuntimeException;

/**
 * Covers the one-line var export for panel authors.
 */
class VarExporterTest extends TestCase
{
    /**
     * Plain arrays survive untouched and stay JSON-safe.
     *
     * @return void
     */
    public function testPlainArrays(): void
    {
        $plain = VarExporter::export(['post_id' => 9, 'tags' => ['a', 'b']]);

        $this->assertSame(['post_id' => 9, 'tags' => ['a', 'b']], $plain);
        $this->assertSame($plain, json_decode((string)json_encode($plain), true));
    }

    /**
     * Scalars wrap into a value key instead of vanishing.
     *
     * @return void
     */
    public function testScalarsWrap(): void
    {
        $this->assertSame(['value' => 'req-1'], VarExporter::export('req-1'));
        $this->assertSame(['value' => 200], VarExporter::export(200));
    }

    /**
     * Entities expand to class plus properties, not bare markers.
     *
     * @return void
     */
    public function testEntitiesExpand(): void
    {
        $plain = VarExporter::export(new Entity(['id' => 42, 'type' => 'posts'], ['markNew' => false]));

        $this->assertSame(Entity::class, $plain['__class']);
        $this->assertSame(42, $plain['id']);
        $this->assertSame('posts', $plain['type']);
    }

    /**
     * Dates render their fields instead of a bare class name.
     *
     * @return void
     */
    public function testDatesExpand(): void
    {
        $plain = VarExporter::export(['created' => new DateTime('2026-01-02 03:04:05')]);

        $this->assertIsArray($plain['created']);
        $this->assertSame(DateTime::class, $plain['created']['__class']);
    }

    /**
     * Nested data keeps inner objects expandable.
     *
     * @return void
     */
    public function testNestedObjects(): void
    {
        $plain = VarExporter::export([
            'record' => 'Notification',
            'data' => [
                'title' => 'New Post',
                'created' => new DateTime('2026-01-02 03:04:05'),
            ],
        ]);

        $this->assertSame('Notification', $plain['record']);
        $this->assertSame('New Post', $plain['data']['title']);
        $this->assertIsArray($plain['data']['created']);
    }

    /**
     * Sensitive leaves mask by default and stay visible on opt-out.
     *
     * Cake core already masks datasource keys (`password`, `login`);
     * the flag below controls the wider RedactionService layer.
     *
     * @return void
     */
    public function testMasking(): void
    {
        $this->assertSame('********', VarExporter::export(['password' => 's3cret'])['password']);
        $this->assertSame(
            '********',
            VarExporter::export(['client_secret' => 's3cret'])['client_secret'],
        );
        $this->assertSame(
            's3cret',
            VarExporter::export(['client_secret' => 's3cret'], 5, false)['client_secret'],
        );
    }

    /**
     * Depth budget truncates through the core marker instead of exploding.
     *
     * @return void
     */
    public function testDepthCap(): void
    {
        $plain = VarExporter::export(['a' => ['b' => ['c' => 1]]], 2, false);

        $this->assertSame(['' => '[maximum depth reached]'], $plain['a']['b']);
    }

    /**
     * Throwables export without throwing.
     *
     * @return void
     */
    public function testThrowables(): void
    {
        $plain = VarExporter::export(new RuntimeException('connection refused'));

        $this->assertSame(RuntimeException::class, $plain['__class']);
    }

    /**
     * Stored node trees flatten to the same shape.
     *
     * @return void
     */
    public function testFlatten(): void
    {
        $node = Debugger::exportVarAsNodes(['post_id' => 3]);

        $this->assertSame(['post_id' => 3], VarExporter::flatten($node));
        $this->assertSame(['value' => 'x'], VarExporter::flatten(Debugger::exportVarAsNodes('x')));
    }
}
