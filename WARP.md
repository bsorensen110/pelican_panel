# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

Pelican Panel is a Laravel-based game server management panel using Filament for the admin interface. It communicates with Wings (the daemon) to manage Docker-based game servers. The architecture follows a multi-panel approach with three distinct panels: Admin, App, and Server.

## Development Commands

### Setup
```powershell
# Initial setup
composer install
php artisan p:environment:setup
php artisan p:environment:database
php artisan migrate --seed --force
php artisan p:user:make

# Install frontend dependencies
npm install
```

### Building
```powershell
# Development build with hot reload
npm run dev

# Production build
npm run build
```

### Code Quality
```powershell
# Lint and format PHP code (Laravel Pint)
composer pint
# or: .\vendor\bin\pint

# Static analysis (PHPStan via Larastan)
composer phpstan
# or: .\vendor\bin\phpstan --memory-limit=-1
```

### Testing
```powershell
# Run all tests using Pest
php artisan test

# Run specific test suite
php artisan test --testsuite=Integration
php artisan test --testsuite=Unit

# Run tests in a specific directory
php artisan test tests/Feature/
php artisan test tests/Filament/

# Run specific test file
php artisan test tests/Feature/SomeTest.php
```

### Laravel Artisan Commands
```powershell
# Start development server (if using artisan serve)
php artisan serve

# View application info (includes Pelican version)
php artisan about

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# View logs in real-time
php artisan pail

# Pelican-specific commands (use p: prefix)
php artisan p:user:make            # Create admin user
php artisan p:environment:setup    # Configure environment
php artisan p:environment:database # Configure database
```

## Architecture

### Multi-Panel Structure
The application uses three Filament panels, each serving different purposes:

1. **Admin Panel** (`app/Filament/Admin/`): Full administrative interface for managing nodes, eggs, servers, users, roles, etc.
2. **App Panel** (`app/Filament/App/`): User-facing panel for server owners to manage their servers
3. **Server Panel** (`app/Filament/Server/`): Server-specific management interface for files, databases, backups, schedules, etc.

Panel providers are registered in `bootstrap/providers.php` and configure each panel independently.

### Core Components

#### Models (`app/Models/`)
Primary domain models include:
- **Server**: Represents game server instances with resource limits, status, and configuration
- **Node**: Physical/virtual machines running Wings daemon that host servers
- **Egg**: Server templates defining Docker images, startup commands, and variables
- **User**: Users with role-based permissions (uses Spatie Laravel Permission)
- **Allocation**: IP:Port combinations assigned to servers

All models use morph maps defined in `AppServiceProvider` for polymorphic relationships.

#### Services (`app/Services/`)
Business logic layer organized by domain:
- **Servers/ServerCreationService**: Handles server provisioning with deployment logic
- **Deployment/**: Node selection and allocation assignment for new servers
- **Backups/**: Backup lifecycle management
- **Databases/**: Database creation and deployment to Wings

Services coordinate between repositories and handle complex workflows.

#### Repositories (`app/Repositories/Daemon/`)
Communication layer with Wings daemon:
- **DaemonRepository**: Base class with HTTP client setup using `Http::daemon()` macro
- **DaemonServerRepository**: Server operations on Wings
- **DaemonConfigurationRepository**: Node configuration sync
- **DaemonFileRepository**: File management operations

All daemon communication uses bearer token authentication and validates token_id in responses.

#### Filament Resources
CRUD interfaces following Filament's Resource pattern:
- Resources define form schemas, table columns, and pages
- Each resource has corresponding Pages (List, Create, Edit)
- RelationManagers handle nested resource relationships
- Resources filter accessible records based on user permissions (e.g., `accessibleNodes()`)

#### API Layer
Three separate API groups defined in `routes/`:
- `api-application.php`: Administrative API (Scramble docs at `/docs/api/application`)
- `api-client.php`: Client API for server owners (Scramble docs at `/docs/api/client`)
- `api-remote.php`: Wings daemon callbacks

Uses Laravel Sanctum with custom ApiKey model for authentication.

### Frontend Architecture

#### Asset Pipeline
- **Vite** for bundling (config: `vite.config.js`)
- **TailwindCSS v4** with Filament integration
- Assets in `resources/css/` and `resources/js/` are auto-discovered via glob patterns
- Livewire components for dynamic UI

#### Xterm.js Integration
Terminal functionality uses xterm with addons:
- `@xterm/xterm`: Core terminal
- `@xterm/addon-fit`, `@xterm/addon-search`, `@xterm/addon-webgl`, `@xterm/addon-web-links`
- `xterm-addon-search-bar`

### Database
- SQLite for testing (configured in `phpunit.xml`)
- MySQL/MariaDB/PostgreSQL for production
- Migrations in `database/migrations/`
- Factories in `database/Factories/` (note capital F)
- Seeders in `database/Seeders/` (note capital S)

### Configuration
Key configuration files:
- `config/panel.php`: Pelican-specific settings
- Service providers in `bootstrap/providers.php` include extension providers for avatars, captchas, features, and OAuth

### Extensions System
Plugin-like architecture in `app/Extensions/`:
- **Avatar/**: Custom avatar providers
- **Captcha/**: Captcha implementations
- **Features/**: Feature flags
- **OAuth/**: OAuth provider integrations

Each has a corresponding service provider that registers the extension.

### Health Checks
System health monitoring using Spatie Laravel Health:
- Checks registered in `AppServiceProvider::boot()`
- Includes debug mode, cache, database, scheduler, disk space, and version checks
- Custom checks in `app/Checks/`

## Testing Guidelines

### Test Organization
- **Integration tests**: `tests/Integration/` - Use `IntegrationTestCase`
- **Unit tests**: `tests/Unit/`
- **Filament tests**: `tests/Filament/`

### Helper Functions (`tests/Pest.php`)
- `createServerModel($attributes)`: Create fully-configured test server
- `generateTestAccount($permissions)`: Create user with optional subuser permissions
- `cloneEggAndVariables($egg)`: Clone egg for isolated testing
- `getBungeecordEgg()`: Default egg for most tests

### Custom Expectations
- `expect($value)->toLogActivities($count)`: Verify activity logging

## Code Quality Standards

### Linting (Pint)
Laravel preset with custom rules in `pint.json`:
- Allows flexible concat spacing
- Anonymous classes without parentheses
- No spacing after `not` operator
- No spacing enforcement on single-line comments

Run before committing: `composer pint`

### Static Analysis (PHPStan)
Level 6 with Larastan extension (`phpstan.neon`):
- Enforces type safety across app
- Custom rule: `ForbiddenGlobalFunctionsRule` restricts certain global functions
- Exceptions for environment variable access in specific command/settings files
- Run before committing: `composer phpstan`

### Validation
Models implement `App\Contracts\Validatable` and use `HasValidation` trait with static validation rules arrays.

## Important Patterns

### Daemon Communication
Always use the `Http::daemon()` macro for Wings communication:
```php
Http::daemon($node)->get('/api/system');
```
This macro handles authentication, SSL verification (production only), timeouts, and base URL.

### Morph Maps
Use string keys when querying polymorphic relationships:
```php
'server', 'node', 'user', 'allocation', 'database', etc.
```
Maps defined in `AppServiceProvider`.

### User Permissions
- Root admins bypass all gates (checked in `Gate::before()`)
- Use `user()->accessibleNodes()` for node-scoped queries
- Spatie Permission package manages roles and permissions

### Activity Logging
Activity logs are tracked automatically. Use descriptive log descriptions and include relevant subjects/properties.

### Environment Setup
Prefer using `php artisan p:environment:*` commands over manual `.env` editing for initial setup.

## Development Workflow

1. Make changes to code
2. Run `composer pint` to format PHP code
3. Run `composer phpstan` to check for type errors
4. Run `php artisan test` to verify tests pass
5. Test manually in browser with `npm run dev` running
6. Commit changes (do not include builds)

## Contributing Notes

Per `contributing.md`:
- Fork and work on feature branches (not `main`)
- One pull request per feature/fix
- Translation strings: only add to English (`lang/en/`), others via Crowdin
- Mark PRs as Draft if incomplete or need help
- IDEs: VS Code (free) or PhpStorm (paid) recommended
- Laravel Herd suggested for easy PHP/webserver setup on Windows/macOS
