# Baton — Design Plan v2

**Dispatch autonomous AI agents like you dispatch queue jobs.**

Baton is a thin Laravel package for spawning and managing CLI agent processes. No database, no migrations, no queue opinions. Just processes.

---

## Core API

```php
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

// Process handle — same API regardless of driver/sandbox
$process->isRunning();
$process->output();              // parsed output so far
$process->status();              // running, completed, failed
$process->result();              // final result when done
$process->result()->output;
$process->result()->structured;  // if schema provided
$process->result()->usage;       // tokens, cost, duration
$process->kill();

// Future: sandboxing
$process = Baton::prompt('Run the tests.')
    ->sandbox('docker', ['image' => 'node:22', 'network' => false])
    ->spawn();
```

---

## Architecture

### Layers

```
Baton Facade → PendingProcess (fluent builder) → Driver → Process (handle)
```

1. **Baton** — Facade entry point. `spawn()` shortcut and `prompt()` builder.
2. **PendingProcess** — Fluent builder. Collects config, calls driver on `spawn()`.
3. **Driver** — Spawns and manages the actual CLI process. Swappable.
4. **Process** — Opaque handle. Wraps a running agent regardless of where it runs.

### Driver Interface

```php
interface Driver
{
    public function spawn(PendingProcess $pending): Process;
    public function isRunning(Process $process): bool;
    public function terminate(Process $process): bool;
    public function readOutput(Process $process): OutputReader;
    public function getResult(Process $process): ?Result;
    public function capabilities(): array;
}
```

### Process Handle

```php
class Process
{
    public string $id;          // UUID — not tied to PID
    public string $driver;      // 'local', 'docker', 'e2b'
    public array $handle;       // driver-specific: ['pid' => 123] or ['containerId' => 'abc']
    public string $workDir;
    public Carbon $startedAt;

    // Convenience methods delegate to driver
    public function isRunning(): bool;
    public function status(): ProcessStatus;
    public function output(): OutputReader;
    public function result(): ?Result;
    public function kill(): bool;
}
```

### Result

```php
class Result
{
    public bool $success;
    public ?string $output;       // final text output
    public ?array $structured;    // structured output if schema was used
    public ?Usage $usage;         // tokens, cost
    public ?int $durationMs;
    public ?string $error;
}
```

### Output Parsing

Drivers provide an `OutputReader` that normalizes streaming output into events:

```php
interface OutputReader
{
    /** @return Generator<OutputEvent> */
    public function events(): Generator;
    public function tail(int $lines = 50): string;
}
```

Events are driver-agnostic:

```
OutputEvent (abstract)
├── MessageEvent      — agent text output
├── ToolUseEvent      — tool invocation
├── ToolResultEvent   — tool result
├── ProgressEvent     — progress update
├── ErrorEvent        — error
└── ResultEvent       — final result
```

---

## Drivers

| Driver | Runtime | Status |
|--------|---------|--------|
| `claude-code` | Claude Code CLI | ✅ First |
| `null` | Testing | ✅ First |
| `amp` | AMP | 🔜 Later |
| `codex` | OpenAI Codex | 🔜 Later |
| `docker` | Any agent in Docker | 🔜 Sandbox |
| `e2b` | E2B cloud sandbox | 🔜 Sandbox |

---

## What Baton Does NOT Do

- **No database/migrations.** No AgentRun model. Persistence is your app's concern.
- **No queue integration.** Wrap Baton in your own jobs if you want queued dispatch.
- **No polymorphic relationships.** No traits on your models required.
- **No opinions on your app structure.**

If you want persistence:

```php
// Your app, your job
class RunAgentJob implements ShouldQueue
{
    public function __construct(
        public Audit $audit,
        public string $prompt,
    ) {}

    public function handle(): void
    {
        $process = Baton::prompt($this->prompt)
            ->model('opus')
            ->inDir($this->audit->repo_path)
            ->spawn();

        // Poll or wait
        while ($process->isRunning()) {
            sleep(5);
        }

        // Store results however you want
        $this->audit->update([
            'result' => $process->result()->structured,
            'status' => $process->result()->success ? 'completed' : 'failed',
        ]);
    }
}
```

---

## Config

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

---

## Namespace & Package

- **Namespace:** `Baton\`
- **Composer:** `fsmonter/baton`
- **Facade:** `Baton`
- **Config:** `config/baton.php`

---

## Package Structure

```
src/
├── Baton.php                    # Facade
├── BatonManager.php             # Manager (driver resolution)
├── BatonServiceProvider.php     # Service provider
├── PendingProcess.php           # Fluent builder
├── Process.php                  # Running process handle
├── Result.php                   # Final result DTO
├── Usage.php                    # Token/cost tracking
├── ProcessStatus.php            # Enum: running, completed, failed
├── Contracts/
│   ├── Driver.php               # Driver interface
│   └── OutputReader.php         # Output reading interface
├── Drivers/
│   ├── ClaudeCodeDriver.php     # Claude Code implementation
│   └── NullDriver.php           # Testing driver
├── Output/
│   ├── OutputEvent.php          # Base event
│   ├── MessageEvent.php
│   ├── ToolUseEvent.php
│   ├── ToolResultEvent.php
│   ├── ProgressEvent.php
│   ├── ErrorEvent.php
│   └── ResultEvent.php
└── Parsers/
    ├── ClaudeStreamParser.php   # Claude JSONL parser
    └── JsonLinesParser.php      # Generic JSONL parser
config/
    └── baton.php
```

---

## Implementation Order

1. **Core:** PendingProcess, Process, Result, Usage, ProcessStatus
2. **Contracts:** Driver, OutputReader
3. **Manager:** BatonManager, Facade, ServiceProvider
4. **Drivers:** NullDriver (for tests), ClaudeCodeDriver
5. **Output:** Event classes, parsers
6. **Config:** baton.php
7. **Tests:** Unit + feature tests
8. **README:** Update to match new API

---

## Events (Laravel Events)

Baton fires Laravel events for integration:

```php
// Listen in your app
Event::listen(ProcessSpawned::class, fn ($e) => ...);
Event::listen(ProcessCompleted::class, fn ($e) => ...);
Event::listen(ProcessFailed::class, fn ($e) => ...);
```

These are optional — you don't have to listen to them.

---

## Open Questions

- Should `Baton::spawn($prompt)` be truly synchronous-start (process starts before returning), or should it support a deferred/lazy mode?
- Callback support? e.g. `->onComplete(fn ($result) => ...)` — or leave that to Laravel events?
- Should Process be serializable so it can survive across requests (e.g. stored in cache/session)?
