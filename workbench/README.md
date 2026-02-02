# Workbench

The workbench provides a fully functional Laravel application for developing and testing the Agent Subprocess package.

## Quick Start

```bash
# Serve the application
composer serve

# Visit http://localhost:8000
```

## Available Commands

### Serve the Application

```bash
composer serve
```

This builds the workbench (runs migrations, publishes assets) and starts the development server at `http://localhost:8000`.

### Run Artisan Commands

Use testbench as a drop-in replacement for `php artisan`:

```bash
./vendor/bin/testbench migrate
./vendor/bin/testbench tinker
./vendor/bin/testbench make:model Example
./vendor/bin/testbench route:list
```

### Run Tests

```bash
composer test
```

## Directory Structure

```
workbench/
├── app/
│   └── Providers/
│       └── WorkbenchServiceProvider.php  # Custom service provider
├── database/
│   ├── factories/                        # Model factories
│   ├── migrations/                       # Database migrations
│   └── seeders/
│       └── DatabaseSeeder.php            # Database seeder
├── resources/
│   └── views/                            # Blade views
├── routes/
│   ├── api.php                           # API routes
│   └── web.php                           # Web routes
└── README.md
```

## Adding Features

### Routes

Edit `workbench/routes/web.php` or `workbench/routes/api.php`:

```php
// workbench/routes/web.php
use AgentSubprocess\Facades\Agent;

Route::get('/run-agent', function () {
    $run = Agent::driver('claude-code')
        ->run('Hello, world!');

    return response()->json($run);
});
```

### Models

Create models in `workbench/app/Models/`:

```bash
./vendor/bin/testbench make:model Example -m
```

The model will be created in `workbench/app/Models/Example.php` and migration in `workbench/database/migrations/`.

### Views

Add Blade views to `workbench/resources/views/`:

```php
// workbench/routes/web.php
Route::get('/dashboard', function () {
    return view('dashboard');
});
```

### Migrations

Add migrations to `workbench/database/migrations/` or generate them:

```bash
./vendor/bin/testbench make:migration create_examples_table
```

Run migrations:

```bash
./vendor/bin/testbench migrate
```

### Factories

Add factories to `workbench/database/factories/`:

```php
// workbench/database/factories/ExampleFactory.php
namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Example;

class ExampleFactory extends Factory
{
    protected $model = Example::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
        ];
    }
}
```

### Seeders

Edit `workbench/database/seeders/DatabaseSeeder.php`:

```php
public function run(): void
{
    \Workbench\App\Models\Example::factory(10)->create();
}
```

Run seeders:

```bash
./vendor/bin/testbench db:seed
```

## Configuration

The workbench is configured via `testbench.yaml` at the project root:

```yaml
laravel: '@testbench'

providers:
  - AgentSubprocess\AgentServiceProvider
  - Workbench\App\Providers\WorkbenchServiceProvider

migrations:
  - workbench/database/migrations

workbench:
  start: '/'
  discovers:
    web: true
    api: true
    commands: true
    factories: true
    views: true
```

### Key Options

| Option | Description |
|--------|-------------|
| `providers` | Service providers to load |
| `migrations` | Paths to migration directories |
| `workbench.start` | Default route when serving |
| `workbench.discovers.web` | Auto-discover web routes |
| `workbench.discovers.api` | Auto-discover API routes |
| `workbench.discovers.commands` | Auto-discover Artisan commands |
| `workbench.discovers.factories` | Auto-discover model factories |
| `workbench.discovers.views` | Auto-discover Blade views |

## Using in Tests

The workbench configuration is automatically used in your Pest/PHPUnit tests:

```php
// tests/Feature/ExampleTest.php
it('can run an agent', function () {
    $run = Agent::driver('null')->run('test prompt');

    expect($run)->toBeInstanceOf(AgentRun::class);
});
```

## Troubleshooting

### Reset the Skeleton

```bash
./vendor/bin/testbench package:purge-skeleton
composer dump-autoload
```

### Clear Caches

```bash
./vendor/bin/testbench config:clear
./vendor/bin/testbench cache:clear
./vendor/bin/testbench view:clear
```

### Fresh Database

```bash
./vendor/bin/testbench migrate:fresh --seed
```


Baton abstraction layer:
┌─────────────────────────────────────────────────┐
│  Your Application (Laravel models, business     │
├─────────────────────────────────────────────────┤
│  Baton (orchestration, persistence, queues)     │
├──────────────┬──────────────┬───────────────────┤
│ ClaudeDriver │ OpenCodeDrv  │  CustomDriver     │
├──────────────┴──────────────┴───────────────────┤
│  SDKs / CLIs (claude-code, opencode, amp)       │
└─────────────────────────────────────────────────┘
