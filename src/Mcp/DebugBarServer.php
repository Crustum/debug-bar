<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Mcp;

use Crustum\DebugBar\Mcp\Tools\GetFindingsTool;
use Crustum\DebugBar\Mcp\Tools\GetPanelDataTool;
use Crustum\DebugBar\Mcp\Tools\InspectQueriesTool;
use Crustum\DebugBar\Mcp\Tools\ListRequestsTool;
use Crustum\Mcp\Server;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Instructions;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Attributes\Version;

#[Name('DebugBar')]
#[Version('1.0.0')]
#[Description('Read bounded CakePHP DebugBar captures. List recent requests, read one panel, inspect findings and query evidence. Every tool answers with a version/status/data envelope; use GetPanelDataTool with JSON Pointer paths when deeper evidence is needed.')]
#[Instructions('Read bounded CakePHP DebugBar captures. List recent requests, read one panel, inspect findings and query evidence. Every tool answers with a version/status/data envelope; use GetPanelDataTool with JSON Pointer paths when deeper evidence is needed.')]
class DebugBarServer extends Server
{
    /**
     * Registered diagnostic tools.
     *
     * @var array<int, class-string<\Crustum\Mcp\Server\Tool>>
     */
    protected array $tools = [
        ListRequestsTool::class,
        GetPanelDataTool::class,
        GetFindingsTool::class,
        InspectQueriesTool::class,
    ];

    /**
     * No MCP resources served by this server.
     *
     * @var array<int, class-string<\Crustum\Mcp\Server\Resource>>
     */
    protected array $resources = [];

    /**
     * No MCP prompts served by this server.
     *
     * @var array<int, class-string<\Crustum\Mcp\Server\Prompt>>
     */
    protected array $prompts = [];
}
