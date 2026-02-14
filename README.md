# Baton

**Dispatch autonomous AI agents like you dispatch queue jobs.**

Baton is a thin Laravel package for spawning and managing CLI agent processes.

## Installation

```bash
composer require fsmonter/baton
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag=baton-config
```

## Quick Start

```php
use Baton\Baton;

// Simple — starts immediately
$process = Baton::spawn('Fix the failing tests.');

// Configured
$process = Baton::prompt('Audit this codebase.')
    ->model('opus')
    ->tools(['Read', 'Write', 'Bash'])
    ->inDir('/path/to/repo')
    ->via('claude-code')
    ->maxTurns(50)
    ->timeout(3600)
    ->schema($jsonSchema)
    ->spawn();
```

## Process Handle

```php
$process->isRunning();    // bool
$process->status();       // ProcessStatus::Running|Completed|Failed
$process->output();       // OutputReader — iterate events or tail
$process->result();       // Result DTO (null while running)
$process->kill();         // terminate the process
```

## Result

```php
$result = $process->result();

$result->success;       // bool
$result->output;        // final text output
$result->structured;    // structured output if schema was used
$result->usage;         // Usage DTO (inputTokens, outputTokens, cost)
$result->durationMs;    // execution time in ms
$result->error;         // error message if failed
```

## Output Events

Stream parsed output events from the agent:

```php
foreach ($process->output()->events() as $event) {
    match (true) {
        $event instanceof \Baton\Output\MessageEvent => /* text output */,
        $event instanceof \Baton\Output\ToolUseEvent => /* tool invocation */,
        $event instanceof \Baton\Output\ToolResultEvent => /* tool result */,
        $event instanceof \Baton\Output\ErrorEvent => /* error */,
        $event instanceof \Baton\Output\ResultEvent => /* final result */,
    };
}

// Or just get recent output
echo $process->output()->tail(50);
```

## Laravel Events

Baton fires Laravel events for integration:

```php
use Baton\Events\ProcessSpawned;
use Baton\Events\ProcessCompleted;
use Baton\Events\ProcessFailed;

Event::listen(ProcessSpawned::class, fn ($e) => /* ... */);
```

## Drivers

| Driver | Runtime | Status |
|--------|---------|--------|
| `claude-code` | Claude Code CLI | ✅ |
| `null` | Testing | ✅ |
| `docker` | Any agent in Docker | 🔜 |
| `e2b` | E2B cloud sandbox | 🔜 |

### Custom Drivers

```php
use Baton\Baton;

Baton::extend('my-driver', function (array $config) {
    return new MyCustomDriver($config);
});
```

## Configuration

```php
// config/baton.php
return [
    'default' => env('BATON_DRIVER', 'claude-code'),

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
```

## Testing

Use the `null` driver in tests:

```php
use Baton\Baton;
use Baton\Drivers\NullDriver;

// Set null as default in your test
config(['baton.default' => 'null']);

$process = Baton::spawn('Test prompt');
$driver = Baton::driver('null');

// Simulate completion
$driver->complete($process, 'Task done!');

expect($process->result()->success)->toBeTrue();
expect($process->result()->output)->toBe('Task done!');
```

## What Baton Does NOT Do

- **No database/migrations.** Persistence is your app's concern.
- **No queue integration.** Wrap Baton in your own jobs if you want queued dispatch.
- **No polymorphic relationships.** No traits on your models required.

## License

MIT
