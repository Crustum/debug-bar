<?php
declare(strict_types=1);

/**
 * DebugBar Plugin Configuration
 *
 * Host applications keep a full copy of this file in `config/debug_bar.php`
 * and load it via `Configure::load('debug_bar')`. Keys are read as
 * `Configure::read('DebugBar.*')`. All scalar settings can be overridden
 * via environment variables.
 */
return [
    'DebugBar' => [
        /**
         * Color theme for the toolbar shell: system|light|dark.
         */
        'theme' => env('DEBUGBAR_THEME', 'system'),

        /**
         * Default host corner for the 40x40 mini icon.
         *
         * One of: top-left, top-right, middle-left, middle-right,
         * bottom-left, bottom-right. The bar itself always stays
         * bottom-docked; only the icon moves. A palette pin stored in
         * the browser wins until this value changes.
         */
        'miniPosition' => env('DEBUGBAR_MINI_POSITION', 'bottom-right'),

        /**
         * What a click on the mini icon opens: bar|inspector.
         */
        'miniAction' => env('DEBUGBAR_MINI_ACTION', 'inspector'),

        /**
         * Enable the toolbar even when debug mode is off.
         */
        'forceEnable' => filter_var(env('DEBUGBAR_FORCE_ENABLE', false), FILTER_VALIDATE_BOOLEAN),

        /**
         * Extra top-level domains treated as safe local development hosts.
         */
        'safeTld' => [],

        /**
         * PCRE pattern for request paths that must never be recorded.
         * Applies to 2xx responses only. Null disables it.
         */
        'ignorePathsPattern' => env('DEBUGBAR_IGNORE_PATHS_PATTERN'),

        /**
         * Noise URLs never stored (glob masks against the request path,
         * e.g. '/notifications/*', '/health'). A trailing `/*` also covers
         * the bare prefix (`/notifications` itself). Unlike
         * `ignorePathsPattern` (regex, 2xx only) these apply to every status.
         */
        'ignored_url_masks' => [],

        /**
         * Skip authorization checks on the toolbar endpoints.
         */
        'ignoreAuthorization' => filter_var(env('DEBUGBAR_IGNORE_AUTHORIZATION', false), FILTER_VALIDATE_BOOLEAN),

        /**
         * Queries slower than this (milliseconds) are flagged as slow.
         */
        'slow_query_ms' => (int)env('DEBUGBAR_SLOW_QUERY_MS', 100),

        /**
         * HTTP client requests slower than this (milliseconds) are flagged as slow.
         */
        'slow_http_request_ms' => (int)env('DEBUGBAR_SLOW_HTTP_REQUEST_MS', 250),

        /**
         * Requests slower than this (milliseconds) are flagged as slow in findings.
         */
        'slow_request_ms' => (int)env('DEBUGBAR_SLOW_REQUEST_MS', 1000),

        /**
         * How many requests the request list query returns.
         */
        'requestCount' => (int)env('DEBUGBAR_REQUEST_COUNT', 100),

        /**
         * Export depth for dumped view variables and request data.
         */
        'maxDepth' => (int)env('DEBUGBAR_MAX_DEPTH', 10),

        /**
         * Export depth for the Variables panel (can be lower than maxDepth).
         */
        'variablesPanelMaxDepth' => (int)env('DEBUGBAR_VARIABLES_PANEL_MAX_DEPTH', 5),

        /**
         * Capture schema reflection data alongside SQL logging.
         */
        'includeSchemaReflection' => filter_var(env('DEBUGBAR_INCLUDE_SCHEMA_REFLECTION', false), FILTER_VALIDATE_BOOLEAN),

        /**
         * Thresholds for the Findings panel intelligence.
         */
        'findings' => [
            /**
             * Cache panels with fewer operations than this stay quiet.
             */
            'minimum_cache_operations' => (int)env('DEBUGBAR_MIN_CACHE_OPERATIONS', 5),

            /**
             * Miss ratio (0-1) above which a cache panel is flagged as high-miss.
             */
            'high_cache_miss_rate' => (float)env('DEBUGBAR_HIGH_CACHE_MISS_RATE', 0.8),

            /**
             * Maximum findings kept per request.
             */
            'max_findings' => (int)env('DEBUGBAR_MAX_FINDINGS', 50),
        ],

        /**
         * Stored request window for the shell request switcher.
         */
        'storage' => [
            /**
             * Maximum recent requests available for switching.
             */
            'max_requests' => (int)env('DEBUGBAR_STORAGE_MAX_REQUESTS', 100),
        ],

        /**
         * Caps for the read-only MCP tools.
         */
        'mcp' => [
            /**
             * Maximum items returned per tool call.
             */
            'max_items' => (int)env('DEBUGBAR_MCP_MAX_ITEMS', 50),

            /**
             * Maximum payload bytes returned per tool call.
             */
            'max_bytes' => (int)env('DEBUGBAR_MCP_MAX_BYTES', 100000),
        ],

        /**
         * Capture-time limits applied before panels store their data.
         */
        'collection' => [
            /**
             * Maximum items captured per collection.
             */
            'max_items' => (int)env('DEBUGBAR_COLLECTION_MAX_ITEMS', 500),

            /**
             * Maximum items captured per nested array.
             */
            'max_items_per_array' => (int)env('DEBUGBAR_COLLECTION_MAX_ITEMS_PER_ARRAY', 100),

            /**
             * Maximum nesting depth captured.
             */
            'max_depth' => (int)env('DEBUGBAR_COLLECTION_MAX_DEPTH', 5),

            /**
             * Maximum string length captured before truncation.
             */
            'max_string_length' => (int)env('DEBUGBAR_COLLECTION_MAX_STRING_LENGTH', 2000),
        ],

        /**
         * Query bindings policy: full|safe|none.
         *
         * `safe` masks string bindings, `none` drops them entirely.
         * Anything but `full` also disables runnable SQL.
         */
        'query_bindings' => env('DEBUGBAR_QUERY_BINDINGS', 'full'),

        /**
         * Cache/key policy: full|hash. `hash` stores sha256 digests
         * instead of raw cache keys.
         */
        'key_policy' => env('DEBUGBAR_KEY_POLICY', 'full'),

        /**
         * Editor links for stack traces and sources. Disabled by default;
         * enable and pick an editor (or a custom `{file}`/`{line}` link
         * template) to open frames in the IDE instead of copying paths:
         * 'links' => ['enabled' => true, 'editor' => 'cursor'],
         * 'links' => [
         *     'enabled' => true,
         *     'link' => 'cursor://file/{file}:{line}',
         * ],
         */
        'links' => [
            /**
             * Render IDE links instead of copy path affordances.
             */
            'enabled' => filter_var(env('DEBUGBAR_LINKS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

            /**
             * Editor preset name (e.g. cursor, vscode, phpstorm).
             */
            'editor' => env('DEBUGBAR_LINKS_EDITOR'),

            /**
             * Custom link template with `{file}` and `{line}` placeholders.
             */
            'link' => env('DEBUGBAR_LINKS_TEMPLATE'),
        ],

        /**
         * Record the first application frame plus a short stack per captured event.
         */
        'call_sites' => filter_var(env('DEBUGBAR_CALL_SITES', true), FILTER_VALIDATE_BOOLEAN),

        /**
         * How many useful application frames to keep per call site.
         */
        'call_site_frames' => (int)env('DEBUGBAR_CALL_SITE_FRAMES', 12),

        /**
         * Frame capture for the Exceptions panel.
         */
        'exception_frames' => [
            /**
             * Application frames kept per throwable.
             */
            'application' => (int)env('DEBUGBAR_EXCEPTION_FRAMES_APPLICATION', 12),

            /**
             * Vendor frames kept per throwable.
             */
            'vendor' => (int)env('DEBUGBAR_EXCEPTION_FRAMES_VENDOR', 12),

            /**
             * Source context lines shown around each frame.
             */
            'source_context_lines' => (int)env('DEBUGBAR_EXCEPTION_FRAMES_SOURCE_CONTEXT_LINES', 9),
        ],
    ],
];
