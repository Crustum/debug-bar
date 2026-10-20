<?php
declare(strict_types=1);

/**
 * DebugBar Plugin Configuration
 *
 * Host applications copy settings into `config/debug_bar.php` and load via
 * `Configure::load('debug_bar')`. Keys are read as `Configure::read('DebugBar.*')`.
 */
return [
    'DebugBar' => [
        'theme' => 'system',
        'forceEnable' => false,
        'safeTld' => [],
        'ignorePathsPattern' => null,
        // Noise URLs never stored (glob masks against the request path,
        // e.g. '/notifications/*', '/health'). A trailing `/*` also covers
        // the bare prefix (`/notifications` itself). Unlike
        // `ignorePathsPattern` (regex, 2xx only) these apply to every status.
        'ignored_url_masks' => [],
        'ignoreAuthorization' => false,
        'slow_query_ms' => 100,
        'slow_http_request_ms' => 250,
        'slow_request_ms' => 1000,
        'requestCount' => 100,
        'maxDepth' => 10,
        'variablesPanelMaxDepth' => 5,
        'includeSchemaReflection' => false,
        'findings' => [
            'minimum_cache_operations' => 5,
            'high_cache_miss_rate' => 0.8,
            'max_findings' => 50,
        ],
        'storage' => [
            'max_requests' => 100,
        ],
        'mcp' => [
            'max_items' => 50,
            'max_bytes' => 100000,
        ],
        'collection' => [
            'max_items' => 500,
            'max_items_per_array' => 100,
            'max_depth' => 5,
            'max_string_length' => 2000,
        ],
        'query_bindings' => 'full',
        'key_policy' => 'full',
        // Editor links for stack traces and sources. Disabled by default;
        // enable and pick an editor (or a custom `{file}`/`{line}` link
        // template) to open frames in the IDE instead of copying paths:
        // 'links' => ['enabled' => true, 'editor' => 'cursor'],
        // 'links' => [
        //     'enabled' => true,
        //     'link' => 'cursor://file/{file}:{line}',
        // ],
        'links' => [
            'enabled' => false,
            'editor' => null,
            'link' => null,
        ],
        // Application call sites: `call_sites` records the first application
        // frame plus a short stack per captured event. `call_site_frames`
        // keeps this many useful application frames (12 matches the
        // established capture behavior; upstream uses 5).
        'call_sites' => true,
        'call_site_frames' => 12,
        'exception_frames' => [
            'application' => 12,
            'vendor' => 12,
            'source_context_lines' => 9,
        ],
    ],
];
