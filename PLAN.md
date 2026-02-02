# Laravel Agent Subprocess Package Design

## Problem Statement

You've identified a recurring pattern across `code-audits-poc` and `qa-e2e` projects:
- Domain parent entities (Audit, Project) spawn agent subprocess runs
- Runs track status, progress, process IDs, and results
- Settings/context inherit from parent → child → runtime
- Steps within runs have individual results

This can be abstracted into a reusable, **agent-agnostic** Laravel package that supports any subprocess-based agent (Claude Code, OpenCode, AMP, Agent SDK, etc.).

---

## Core Pattern Identified

```
Domain Parent (Audit, Project)
    ↓ provides context/settings
Runnable Entity (Category+Audit, Scenario)
    ↓ spawns via AgentDriver
AgentRun (AgentExecution, Run)
    ↓ has many
Steps/Results (AnalysisStep, StepResult)
```

### Current Implementations

| Aspect | code-audits-poc | qa-e2e |
|--------|-----------------|--------|
| Parent | Audit | Project |
| Runnable | Category + Audit | Scenario |
| Run Model | AgentExecution | Run |
| Steps | AnalysisStep | StepResult |
| Process Service | ClaudeCodeAgentService | (not implemented) |
| Settings | Category → Audit → Execution | Project.context → Scenario.context |

---

## Proposed Package: `laravel-agent-subprocess`

### Design Philosophy

Uses Laravel's **Driver Pattern** (like Cache, Queue, Filesystem, Mail) to support multiple agent backends:
- **Claude Code** - Anthropic's CLI agent
- **OpenCode** - Open-source alternative
- **AMP** - Agent Management Platform
- **Agent SDK** - Custom agent implementations
- **Null** - Testing driver

### Package Structure

```
packages/agent-subprocess/
├── src/
│   ├── Contracts/
│   │   ├── AgentDriver.php         # Core driver interface
│   │   ├── OutputParser.php        # Output parsing interface
│   │   ├── AgentRunnable.php       # Interface for models that can spawn runs
│   │   └── HasAgentContext.php     # Interface for context composition
│   ├── Drivers/
│   │   ├── ClaudeCodeDriver.php    # Claude Code implementation
│   │   ├── OpenCodeDriver.php      # OpenCode implementation
│   │   ├── AmpDriver.php           # AMP implementation
│   │   └── NullDriver.php          # Testing driver
│   ├── Parsers/
│   │   ├── ClaudeStreamParser.php  # Claude streaming output
│   │   ├── JsonLinesParser.php     # Generic JSON lines
│   │   └── NdjsonParser.php        # Newline-delimited JSON
│   ├── Models/
│   │   ├── AgentRun.php            # Polymorphic run model
│   │   └── AgentStep.php           # Step tracking within runs
│   ├── Concerns/
│   │   ├── HasAgentRuns.php        # Trait: agentRuns() relationship + helpers
│   │   └── ComposesContext.php     # Trait: context inheritance chain
│   ├── Enums/
│   │   ├── RunStatus.php           # pending, queued, running, completed, failed
│   │   └── StepStatus.php          # pending, running, completed, failed, skipped
│   ├── DTOs/
│   │   ├── Process.php             # { pid, driver, command, workDir, outputPath, ... }
│   │   ├── AgentConfig.php         # { name, model, tools, allowedCommands }
│   │   └── ContextLayer.php        # { content, priority, label, metadata }
│   ├── Events/
│   │   ├── Output/                 # Normalized output events
│   │   │   ├── OutputEvent.php     # Base class
│   │   │   ├── ProgressEvent.php   # Progress updates
│   │   │   ├── ToolUseEvent.php    # Tool invocations
│   │   │   ├── MessageEvent.php    # Agent messages
│   │   │   └── ErrorEvent.php      # Errors
│   │   ├── AgentRunStarted.php
│   │   ├── AgentRunCompleted.php
│   │   └── AgentRunFailed.php
│   ├── Jobs/
│   │   ├── ExecuteAgentRun.php     # Spawn process via driver
│   │   └── MonitorAgentProcess.php # Poll until completion
│   ├── AgentManager.php            # Manager class (like CacheManager)
│   ├── Facades/
│   │   └── Agent.php               # Facade for AgentManager
│   └── AgentServiceProvider.php
├── config/agents.php
└── database/migrations/
    ├── create_agent_runs_table.php
    └── create_agent_steps_table.php
```

---

## Key Abstractions

### 1. AgentDriver Interface (Core Abstraction)

```php
interface AgentDriver
{
    public function spawn(AgentRun $run): Process;
    public function isRunning(Process $process): bool;
    public function terminate(Process $process): bool;
    public function parseOutput(Process $process): Generator; // yields OutputEvent
    public function getCapabilities(): array;
}
```

### 2. AgentManager (Laravel Manager Pattern)

```php
class AgentManager
{
    public function driver(?string $name = null): AgentDriver;
    public function getDefaultDriver(): string;
    public function extend(string $driver, Closure $callback): self;
}

// Facade usage:
Agent::spawn($run);                      // Use default driver
Agent::driver('opencode')->spawn($run);  // Use specific driver
```

### 3. Driver Implementations

```php
class ClaudeCodeDriver implements AgentDriver
{
    public function spawn(AgentRun $run): Process
    {
        $command = [
            $this->config['binary'],
            '--print',
            '--model', $run->config['model'] ?? $this->config['model'],
            '--allowedTools', json_encode($run->config['tools'] ?? []),
            $run->prompt,
        ];

        // Spawn subprocess and return Process DTO
    }
}

class OpenCodeDriver implements AgentDriver { /* ... */ }
class AmpDriver implements AgentDriver { /* ... */ }
class NullDriver implements AgentDriver { /* For testing */ }
```

### 4. Output Parsing & Events

```php
interface OutputParser
{
    public function parse(string $line): ?OutputEvent;
    public function supportsStreaming(): bool;
}

// Normalized output events (driver-agnostic)
abstract class OutputEvent
{
    public Carbon $timestamp;
    public string $raw;
}

class ProgressEvent extends OutputEvent
{
    public int $percent;
    public ?string $activity;
}

class ToolUseEvent extends OutputEvent
{
    public string $tool;
    public array $input;
    public mixed $output;
}

class MessageEvent extends OutputEvent
{
    public string $content;
    public string $role;
}

class ErrorEvent extends OutputEvent
{
    public string $message;
    public ?int $code;
}
```

### 5. Process DTO

```php
class Process
{
    public function __construct(
        public ?int $pid,
        public string $driver,
        public array $command,
        public string $workDir,
        public ?string $outputPath,
        public ?string $logPath,
        public ?Carbon $startedAt,
        public array $meta = [],
    ) {}

    public function isStarted(): bool;
    public function toArray(): array;
    public static function fromArray(array $data): self;
}
```

### 6. AgentRun Model (Polymorphic)

```php
Schema::create('agent_runs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuidMorphs('runnable');           // Links to Audit, Scenario, any model
    $table->string('driver');                 // claude-code, opencode, amp, etc.
    $table->string('status')->default('pending');
    $table->unsignedTinyInteger('progress_percent')->default(0);
    $table->string('current_activity')->nullable();
    $table->json('process')->nullable();      // Process DTO as JSON
    $table->json('composed_context')->nullable();
    $table->json('config')->nullable();
    $table->text('prompt')->nullable();
    $table->json('result')->nullable();
    $table->text('error')->nullable();
    $table->timestamps();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
});
```

### 7. HasAgentRuns Trait

```php
trait HasAgentRuns
{
    public function agentRuns(): MorphMany
    {
        return $this->morphMany(AgentRun::class, 'runnable');
    }

    public function createAgentRun(array $attributes = []): AgentRun;
    public function latestAgentRun(): ?AgentRun;
    public function activeAgentRun(): ?AgentRun;
}
```

### 8. Context Composition

```php
interface HasAgentContext
{
    public function getAgentContext(): ContextLayer;
    public function getContextParent(): ?HasAgentContext;
}

// Usage: Project implements HasAgentContext (base context)
// Scenario implements HasAgentContext, getContextParent() returns Project
// Context stacks: Project.context → Scenario.context → runtime data
```

---

## Configuration

```php
// config/agents.php
return [
    'default' => env('AGENT_DRIVER', 'claude-code'),

    'drivers' => [
        'claude-code' => [
            'binary' => env('CLAUDE_BINARY', 'claude'),
            'model' => env('CLAUDE_MODEL', 'sonnet'),
            'max_turns' => 100,
            'timeout' => 3600,
        ],

        'opencode' => [
            'binary' => env('OPENCODE_BINARY', 'opencode'),
            'provider' => env('OPENCODE_PROVIDER', 'anthropic'),
        ],

        'amp' => [
            'binary' => env('AMP_BINARY', 'amp'),
            'config_path' => base_path('agents/'),
        ],

        'null' => [], // For testing
    ],
];
```

---

## Usage Example

### Domain Model Integration

```php
// app/Models/Scenario.php
class Scenario extends Model implements AgentRunnable, HasAgentContext
{
    use HasAgentRuns, ComposesContext;

    public function getAgentContext(): ContextLayer
    {
        return new ContextLayer(
            content: $this->context,
            priority: 10,
            metadata: ['db_setup' => $this->db_setup]
        );
    }

    public function getContextParent(): ?HasAgentContext
    {
        return $this->project; // Inherits Project.context
    }

    public function getAgentDriver(): ?string
    {
        return 'claude-code'; // Or null for default
    }

    public function getAgentPrompt(): string
    {
        return view('prompts.scenario', ['scenario' => $this]);
    }

    public function buildAgentConfig(): array
    {
        return [
            'model' => 'opus',
            'tools' => ['Bash', 'Read', 'Write'],
        ];
    }
}
```

### Creating and Running

```php
use Vendor\AgentSubprocess\Facades\Agent;

$scenario = Scenario::find($id);

// Create run with default driver
$run = $scenario->createAgentRun([
    'prompt' => $scenario->getAgentPrompt(),
    'config' => $scenario->buildAgentConfig(),
]);

// Spawn using default driver
Agent::spawn($run);

// Or use specific driver
Agent::driver('opencode')->spawn($run);

// With steps
$run = $scenario->newAgentRun()
    ->withSteps([
        ['name' => 'Navigate to login'],
        ['name' => 'Enter credentials'],
        ['name' => 'Verify dashboard'],
    ])
    ->dispatch('agents-queue')
    ->create();

// Events fire: AgentRunStarted, AgentRunCompleted, AgentRunFailed
```

### Extending with Custom Drivers

```php
// In a service provider
use Vendor\AgentSubprocess\Facades\Agent;

Agent::extend('custom-agent', function ($app, $config) {
    return new CustomAgentDriver($config);
});

// Usage
Agent::driver('custom-agent')->spawn($run);
```

---

## Migration Path

### code-audits-poc
1. Install package
2. Have `Audit` implement `AgentRunnable`, `HasAgentContext`
3. Replace `AgentExecution` with package's `AgentRun` (morphable)
4. Replace `ClaudeCodeAgentService` with `Agent::driver('claude-code')`
5. Keep domain-specific logic (Questions, Answers, Categories)

### qa-e2e
1. Install package
2. Have `Scenario` implement `AgentRunnable`, `HasAgentContext`
3. Use package's `AgentRun` instead of custom `Run`
4. Implement `ExecuteScenarioRun` job using `Agent` facade

---

## Trade-offs

| Approach | Pros | Cons |
|----------|------|------|
| **Driver Pattern** (chosen) | Agent-agnostic, extensible, Laravel-idiomatic | Slightly more abstraction |
| **Traits + Polymorphic** (chosen) | Max flexibility, no forced inheritance | More boilerplate per model |
| Single hardcoded agent | Simpler | Locked to one agent, no flexibility |
| Abstract Base Model | Less boilerplate | Forces inheritance, conflicts with existing bases |

---

## Implementation Steps

1. **Create package scaffold** in `packages/agent-subprocess/`
2. **Implement contracts**: AgentDriver, OutputParser, AgentRunnable, HasAgentContext
3. **Implement AgentManager**: Driver resolution, extension support
4. **Implement drivers**: ClaudeCodeDriver, NullDriver (others later)
5. **Implement parsers**: ClaudeStreamParser, JsonLinesParser
6. **Implement output events**: ProgressEvent, ToolUseEvent, MessageEvent, ErrorEvent
7. **Implement core models**: AgentRun, AgentStep with polymorphic relations
8. **Implement traits**: HasAgentRuns, ComposesContext
9. **Implement DTOs**: Process, AgentConfig, ContextLayer
10. **Implement jobs**: ExecuteAgentRun, MonitorAgentProcess
11. **Add lifecycle events**: RunStarted, RunCompleted, RunFailed
12. **Write migrations** with UUIDs and proper indexes
13. **Create config** with driver definitions
14. **Test with code-audits-poc** as first integration
15. **Add additional drivers**: OpenCodeDriver, AmpDriver

---

## Verification

1. Run existing code-audits-poc tests after integration
2. Create new AgentRun via `Audit->createAgentRun()`
3. Verify `Agent::spawn($run)` works with default driver
4. Verify `Agent::driver('claude-code')->spawn($run)` works
5. Verify `Agent::driver('null')->spawn($run)` works for testing
6. Verify output events are normalized across drivers
7. Verify context composition chains correctly
8. Verify custom driver extension via `Agent::extend()`
9. Test qa-e2e integration with the same package

---

## Class Diagram (ASCII)

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                              DRIVER PATTERN                                             │
└─────────────────────────────────────────────────────────────────────────────────────────┘

                              ┌─────────────────────────────┐
                              │       AgentManager          │
                              │       «Facade: Agent»       │
                              ├─────────────────────────────┤
                              │ +driver(?name): AgentDriver │
                              │ +extend(name, callback)     │
                              │ +getDefaultDriver(): string │
                              └──────────────┬──────────────┘
                                             │ resolves
                                             ▼
                              ┌─────────────────────────────┐
                              │    «interface»              │
                              │    AgentDriver              │
                              ├─────────────────────────────┤
                              │ +spawn(AgentRun): Process   │
                              │ +isRunning(Process): bool   │
                              │ +terminate(Process): bool   │
                              │ +parseOutput(Process):      │
                              │     Generator<OutputEvent>  │
                              │ +getCapabilities(): array   │
                              └──────────────┬──────────────┘
                                             │
                 ┌───────────────────────────┼───────────────────────────┐
                 │                           │                           │
                 ▼                           ▼                           ▼
┌─────────────────────────┐  ┌─────────────────────────┐  ┌─────────────────────────┐
│    ClaudeCodeDriver     │  │     OpenCodeDriver      │  │       NullDriver        │
│    «implements»         │  │     «implements»        │  │     «implements»        │
└─────────────────────────┘  └─────────────────────────┘  └─────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                              MODEL RELATIONSHIPS                                        │
└─────────────────────────────────────────────────────────────────────────────────────────┘

┌───────────────────────┐         ┌───────────────────────┐
│   «interface»         │         │   «interface»         │
│   AgentRunnable       │         │   HasAgentContext     │
├───────────────────────┤         ├───────────────────────┤
│ +getAgentDriver()     │         │ +getAgentContext()    │
│ +getAgentPrompt()     │         │ +getContextParent()   │
│ +buildAgentConfig()   │         └───────────┬───────────┘
└───────────┬───────────┘                     │
            │ implements                      │ implements
            └──────────────┬──────────────────┘
                           ▼
              ┌─────────────────────────┐
              │  Domain Model           │
              │  (Scenario, Audit)      │
              ├─────────────────────────┤
              │ uses HasAgentRuns       │
              │ uses ComposesContext    │
              └────────────┬────────────┘
                           │ morphMany
                           ▼
              ┌─────────────────────────┐       ┌─────────────────────────┐
              │      AgentRun           │ 1───* │      AgentStep          │
              │      «Model»            │       │      «Model»            │
              ├─────────────────────────┤       ├─────────────────────────┤
              │ -driver: string         │       │ -name: string           │
              │ -status: RunStatus      │       │ -status: StepStatus     │
              │ -process: Process       │       │ -result: json           │
              │ -result: json           │       └─────────────────────────┘
              └─────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                              OUTPUT EVENTS                                              │
└─────────────────────────────────────────────────────────────────────────────────────────┘

                              ┌─────────────────────────┐
                              │    «abstract»           │
                              │    OutputEvent          │
                              ├─────────────────────────┤
                              │ +timestamp: Carbon      │
                              │ +raw: string            │
                              └───────────┬─────────────┘
                                          │ extends
           ┌──────────────┬───────────────┼───────────────┬──────────────┐
           ▼              ▼               ▼               ▼              ▼
    ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌─────────────┐ ┌────────────┐
    │ Progress   │ │ ToolUse    │ │ Message    │ │ StepComplete│ │ Error      │
    │ Event      │ │ Event      │ │ Event      │ │ Event       │ │ Event      │
    └────────────┘ └────────────┘ └────────────┘ └─────────────┘ └────────────┘
```
