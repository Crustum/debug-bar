<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test;

use Cake\Datasource\ConnectionManager;
use Cake\Utility\Text;
use Crustum\DebugBar\Model\Entity\Request;

/**
 * Seeds toolbar rows into an in-memory store for feature tests.
 */
trait DebugBarTestTrait
{
    /**
     * Resets the toolbar store to a fresh in-memory database.
     *
     * @return void
     */
    protected function resetToolbarStore(): void
    {
        if (ConnectionManager::getConfig('test_debug_bar')) {
            ConnectionManager::drop('test_debug_bar');
        }

        ConnectionManager::setConfig('test_debug_bar', ['url' => 'sqlite:///:memory:']);
    }

    /**
     * Stores one request row with the given panels.
     *
     * @param array<string, array<string, mixed>> $panels Panel name to stored data.
     * @param array<string, mixed> $requestData Request row overrides.
     * @return \Crustum\DebugBar\Model\Entity\Request
     */
    protected function seedRequest(array $panels, array $requestData = []): Request
    {
        $requests = $this->fetchTable('Crustum/DebugBar.Requests');
        $request = $requests->newEntity(array_merge([
            'url' => '/debug-bar-test',
            'content_type' => 'text/html',
            'method' => 'GET',
            'status_code' => 200,
        ], $requestData));
        $requests->saveOrFail($request);

        foreach ($panels as $name => $data) {
            $panel = $requests->Panels->newEntity([
                'id' => Text::uuid(),
                'request_id' => $request->id,
                'panel' => $name,
                'title' => $name,
                'element' => $this->panelElement($name),
                'summary' => $name . ' summary',
                'content' => serialize($data),
            ]);
            $requests->Panels->saveOrFail($panel);
        }

        return $request;
    }

    /**
     * Resolves the element name for a seeded panel.
     *
     * @param string $name Panel name.
     * @return string
     */
    protected function panelElement(string $name): string
    {
        $short = $name;
        $position = strrpos($name, '.');
        if ($position !== false) {
            $short = substr($name, $position + 1);
        }

        $snake = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $short));

        return 'Crustum/DebugBar.' . $snake . '_panel';
    }
}
