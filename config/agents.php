<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Agent Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default agent driver that will be used when
    | spawning agent runs. You may set this to any of the drivers defined
    | in the "drivers" array below.
    |
    */

    'default' => env('AGENT_DRIVER', 'claude-code'),

    /*
    |--------------------------------------------------------------------------
    | Agent Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the agent drivers for your application. Each
    | driver has its own configuration options. You can also add custom
    | drivers using the Agent::extend() method.
    |
    */

    'drivers' => [

        'claude-code' => [
            'binary' => env('CLAUDE_BINARY', 'claude'),
            'model' => env('CLAUDE_MODEL', 'sonnet'),
            'max_turns' => env('CLAUDE_MAX_TURNS', 100),
            'timeout' => env('CLAUDE_TIMEOUT', 3600),
            'work_dir' => env('CLAUDE_WORK_DIR'),
            'output_dir' => env('CLAUDE_OUTPUT_DIR', storage_path('agent-subprocess/output')),
            'log_dir' => env('CLAUDE_LOG_DIR', storage_path('agent-subprocess/logs')),
            'prompt_dir' => env('CLAUDE_PROMPT_DIR', storage_path('agent-subprocess/prompts')),
            'schema_dir' => env('CLAUDE_SCHEMA_DIR', storage_path('agent-subprocess/schemas')),
            'script_dir' => env('CLAUDE_SCRIPT_DIR', storage_path('agent-subprocess/scripts')),

            'verbose' => true,
            'include_partial_messages' => true,
            'setting_sources' => 'local',
            'disable_slash_commands' => true,
            'no_session_persistence' => true,

            'tools' => null,
            'allowed_tools' => null,
        ],

        'opencode' => [
            'binary' => env('OPENCODE_BINARY', 'opencode'),
            'provider' => env('OPENCODE_PROVIDER', 'anthropic'),
            'model' => env('OPENCODE_MODEL'),
            'timeout' => env('OPENCODE_TIMEOUT', 3600),
        ],

        'amp' => [
            'binary' => env('AMP_BINARY', 'amp'),
            'config_path' => env('AMP_CONFIG_PATH', base_path('agents/')),
            'timeout' => env('AMP_TIMEOUT', 3600),
        ],

        'null' => [
            // Testing driver with no configuration
        ],

    ],

];
