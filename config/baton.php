<?php

return [
    'default' => env('BATON_DRIVER', 'claude-code'),

    /*
    |--------------------------------------------------------------------------
    | Process Store
    |--------------------------------------------------------------------------
    |
    | Tracks spawned processes so they survive PHP restarts. The filesystem
    | driver stores JSON files — no database required. Implement the
    | ProcessStore contract for custom backends (Redis, database, etc).
    |
    */

    'store' => [
        'driver' => env('BATON_STORE', 'filesystem'),
        'path' => storage_path('baton/processes'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    */

    'drivers' => [
        'claude-code' => [
            'binary' => env('BATON_CLAUDE_BINARY', 'claude'),
            'model' => env('BATON_CLAUDE_MODEL', 'sonnet'),
            'max_turns' => env('BATON_CLAUDE_MAX_TURNS', 100),
            'timeout' => env('BATON_CLAUDE_TIMEOUT', 3600),
        ],

        'null' => [],
    ],
];
