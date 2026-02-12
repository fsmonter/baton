# Baton

**Dispatch autonomous AI agents like you dispatch queue jobs.**

Baton is a Laravel package for orchestrating CLI agent runtimes — Claude Code, AMP, Codex, and others — as background workers in your application. Give an agent a workspace, a mission, and let it work.

---

## Why Baton?

The Laravel AI SDK is great for talking *to* LLMs — prompts, structured output, tool calls you define. But some tasks need more than a conversation. They need an **autonomous agent** with filesystem access, iterative reasoning, and the freedom to figure things out on its own.

You wouldn't rebuild Claude Code's tool loop from scratch with API calls. You'd just *use* Claude Code.

Baton lets you do that from Laravel.

```
// AI SDK: you control the tools, you get a response
$response = (new SalesCoach)->prompt('Analyze this transcript...');

// Baton: you dispatch a mission, the agent does the rest
$audit->dispatch('Review this codebase for security vulnerabilities.');
```

**AI SDK is for conversations. Baton is for jobs.**

---

## How It Works

Any Eloquent model can dispatch autonomous agent work:

```php
class Audit extends Model implements AgentRunnable
{
    use HasAgentRuns;

    public function getAgentPrompt(): string
    {
        return view('prompts.audit', ['audit' => $this]);
    }

    public function buildAgentConfig(): array
    {
        return [
            'model' => 'sonnet',
            'tools' => ['Read', 'Write', 'Bash'],
            'work_dir' => $this->repo_path,
        ];
    }
}

// Dispatch like a queue job
$audit->createAgentRun()->dispatch();

// Or use a specific agent runtime
$audit->createAgentRun()->dispatch(driver: 'amp');
```

Baton handles spawning, process lifecycle, output parsing, status tracking, and structured result extraction. Your app gets events when things happen:

```php
// In a listener
public function handle(AgentRunCompleted $event)
{
    $run = $event->agentRun;
    $results = $run->structured_output;

    // Do whatever you want with the results
    $run->runnable->update(['analysis' => $results]);
    $run->runnable->owner->notify(new AuditComplete($results));
}
```

---

## Use Cases

### Code

| Task | What the agent does |
|------|-------------------|
| **Codebase audits** | Reviews code for quality, security, patterns |
| **Test generation** | Writes and validates Playwright/PHPUnit tests |
| **Documentation** | Crawls codebase, generates structured docs |
| **Dependency upgrades** | Upgrades frameworks, runs tests after each change |
| **Refactoring campaigns** | Applies patterns across an entire codebase |
| **PR review** | Webhook triggers agent, posts structured feedback |
| **Incident investigation** | Reads logs + code, produces root cause analysis |

### Data & Analysis

| Task | What the agent does |
|------|-------------------|
| **Dataset cleaning** | Explores data, flags anomalies, produces clean output |
| **Research synthesis** | Reads documents, produces structured summaries |
| **Financial reconciliation** | Compares exports, identifies discrepancies |

### Business Operations

| Task | What the agent does |
|------|-------------------|
| **RFP responses** | Reads RFP + company docs, drafts proposals |
| **Compliance mapping** | Maps policies against regulatory frameworks |
| **Contract analysis** | Reviews against standard terms, flags deviations |
| **Application screening** | Scores and summarizes candidates |
| **Ticket investigation** | Researches issue, drafts resolution |

### Content

| Task | What the agent does |
|------|-------------------|
| **Documentation sites** | Scaffolds structure, cross-links, checks consistency |
| **Course generation** | Builds curricula with exercises from source material |

The pattern: **give the agent a workspace and a mission. Get structured results back.**

---

## Agent Runtimes

Baton uses Laravel's driver pattern. Ship with what exists, extend with what's next.

| Driver | Runtime | Status |
|--------|---------|--------|
| `claude-code` | [Claude Code](https://docs.anthropic.com/en/docs/claude-code) | ✅ Built |
| `amp` | [AMP](https://ampcode.com) | 🔜 Planned |
| `codex` | [OpenAI Codex](https://openai.com/codex) | 🔜 Planned |
| `null` | Testing | ✅ Built |

```php
// Default driver
$run->dispatch();

// Specific driver
$run->dispatch(driver: 'amp');

// Custom driver
Agent::extend('my-agent', function ($app, $config) {
    return new MyAgentDriver($config);
});
```

---

## Lifecycle

```
Model creates AgentRun
  → Job dispatched to queue
    → Driver spawns agent process
      → Agent works autonomously
        → Baton monitors, parses output, tracks progress
          → AgentRunCompleted / AgentRunFailed event
            → Your app reacts
```

Every run is tracked in the database with status, progress, output, and structured results. Query them like any Eloquent model:

```php
$audit->agentRuns()->latest()->first();
$audit->activeAgentRun();

AgentRun::where('status', 'running')->get();
```

---

## Complements Laravel AI SDK

They solve different problems and work well together:

```php
// 1. User uploads a contract
// 2. AI SDK: quick classification (instant)
$type = (new DocumentClassifier)->prompt(
    'Classify this document',
    attachments: [$file]
);

// 3. Baton: deep autonomous analysis (background)
$contract = Contract::create([
    'type' => $type['category'],
    'file_path' => $storedPath,
]);

$contract->createAgentRun([
    'prompt' => 'Analyze this contract against our standard terms...',
    'config' => ['work_dir' => $contract->workspace_path],
])->dispatch();

// 4. Later: AgentRunCompleted fires
// 5. AI SDK: summarize results for the user (instant)
$summary = (new Summarizer)->prompt(
    "Summarize these findings for a non-legal audience: {$run->result}"
);
```

| | AI SDK | Baton |
|---|--------|-------|
| **Model** | API calls | CLI agent runtimes |
| **Control** | You define every tool | Agent decides its own actions |
| **Duration** | Seconds | Minutes to hours |
| **Access** | What you pass in | Full workspace/filesystem |
| **Best for** | User-facing features | Background deep work |

---

## Installation

```bash
composer require vendor/baton

php artisan vendor:publish --provider="Baton\AgentServiceProvider"
php artisan migrate
```

---

## Requirements

- PHP 8.2+
- Laravel 11 / 12
- At least one agent runtime installed (e.g., `claude` CLI)
