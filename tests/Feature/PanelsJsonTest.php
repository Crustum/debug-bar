<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the panel JSON representation.
 */
class PanelsJsonTest extends TestCase
{
    use DebugBarTestTrait;
    use IntegrationTestTrait;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetToolbarStore();
    }

    /**
     * HTML and JSON representations read the same stored row.
     *
     * @return void
     */
    public function testHtmlAndJsonShareOneSource(): void
    {
        $this->seedRequest(['Cache' => ['metrics' => [], 'logs' => []]]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Cache'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();

        $this->get('/debug-bar/panels/view/' . $panel->id . '.json');
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);

        $this->assertSame('Cache', $body['title']);
        $this->assertSame([], $body['data']['metrics']);
        $this->assertFalse($body['pagination']['has_more']);
    }

    /**
     * Unknown third-party panels need no changes for JSON.
     *
     * @return void
     */
    public function testThirdPartyPanelServesJsonUnchanged(): void
    {
        $this->seedRequest(['Acme.Widget' => ['greeting' => 'hello']]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Acme.Widget'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id . '.json');
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);

        $this->assertSame('Acme.Widget summary', $body['summary']);
        $this->assertSame('hello', $body['data']['greeting']);
        $this->assertArrayNotHasKey('decode_error', $body);
    }

    /**
     * Item lists page with a stable envelope.
     *
     * @return void
     */
    public function testItemsPageThroughCursorAndLimit(): void
    {
        $this->seedRequest(['Queue' => ['items' => [1, 2, 3, 4, 5, 6, 7], 'other' => 'x']]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Queue'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id . '.json?limit=3&cursor=2');
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);

        $this->assertSame([3, 4, 5], array_values($body['data']['items']));
        $this->assertSame(2, $body['pagination']['cursor']);
        $this->assertTrue($body['pagination']['has_more']);
        $this->assertSame(7, $body['pagination']['matching_count']);
        $this->assertSame('x', $body['data']['other']);
    }

    /**
     * The last page reports no further content.
     *
     * @return void
     */
    public function testLastPageClosesPagination(): void
    {
        $this->seedRequest(['Queue' => ['items' => [1, 2]]]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Queue'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id . '.json?limit=5');
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);

        $this->assertFalse($body['pagination']['has_more']);
        $this->assertSame(2, $body['pagination']['matching_count']);
    }

    /**
     * Missing panels stay missing.
     *
     * @return void
     */
    public function testMissingPanelIsNotFound(): void
    {
        $this->get('/debug-bar/panels/view/00000000-0000-4000-8000-000000000000.json');
        $this->assertResponseCode(404);
    }
}
