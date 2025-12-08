# 🚀 PulseFlags Bundle

Production-ready Symfony Bundle for feature flag management with percentage-based traffic splitting, multiple activation strategies, and dual storage architecture.

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/symfony-%5E7.3-black)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

## ✨ Features

- 🎯 **Percentage Rollout** - Gradual feature deployment to percentage of users with consistent bucketing
- 👥 **Multiple Strategies** - Simple, percentage, user ID whitelist/blacklist, date range, composite
- 💾 **Dual Storage Architecture**:
  - **Permanent Flags** - Read-only flags from YAML config files (version-controlled, critical features)
  - **Persistent Flags** - Writable flags in database (MySQL, PostgreSQL, SQLite) (runtime-configurable, A/B tests)
- 🎨 **Twig Integration** - Easy template usage with `is_feature_enabled()` helper
- 🔧 **CLI Commands** - Full command-line management and inspection
- 🖥️ **Admin Panel** - Beautiful web UI for managing dynamic flags at `/admin/pulse-flags`
- 🔒 **Protection** - Permanent flags cannot be modified or deleted at runtime
- 🌐 **Multi-Storage** - MySQL, PostgreSQL, SQLite, in-memory array

## 📦 Installation

```bash
composer require pulse/flags-bundle
```

**Requirements:** PHP 8.1+, Symfony 7.3+

### Install Public Assets

The bundle includes a web admin panel with CSS/JS assets. Install them to your public directory:

```bash
php bin/console assets:install
```

Or with symlinks (faster, recommended for development):

```bash
php bin/console assets:install --symlink --relative
```

This copies/links bundle assets from `vendor/pulse/flags-bundle/public/` to your `public/bundles/pulseflags/` directory.

## 🗑️ Uninstallation

Due to Symfony Flex behavior (known issue in Symfony 6.1+), you must manually remove bundle registration before uninstalling:

### Step 1: Remove bundle registration

Edit `config/bundles.php` and remove the line:

```php
Pulse\FlagsBundle\PulseFlagsBundle::class => ['all' => true],
```

### Step 2: Remove configuration files

```bash
rm -rf config/packages/pulse_flags.yaml
rm -rf config/packages/pulse_flags/
rm -rf config/routes/pulse_flags.yaml
```

### Step 3: Clear cache

```bash
rm -rf var/cache/*
```

### Step 4: Remove the package

```bash
composer remove pulse/flags-bundle
```

### Step 5 (Optional): Drop database table

```bash
php bin/console doctrine:query:sql "DROP TABLE pulse_feature_flags"
# Or create a migration to remove the table
```

**Why manual removal is needed:** Symfony Flex may not automatically remove the bundle registration from `config/bundles.php`. While the bundle uses lazy loading (so it won't crash during container compilation even without database), Symfony still tries to load the bundle class, which fails if the package files are already removed from `vendor/`.

## 🚀 Quick Start

### 1. Configure Storage

Create or update `config/packages/pulse_flags.yaml`:

```yaml
pulse_flags:
    # Storage format for permanent (read-only) flags loaded from config files
    permanent_storage: yaml  # Options: yaml, php

    # Storage backend for persistent (runtime mutable) flags
    persistent_storage: db  # Database storage (MySQL, PostgreSQL, SQLite)

    # Database configuration
    db:
        dsn: '%env(resolve:DATABASE_URL)%'  # Credentials extracted from DSN
        table: 'pulse_feature_flags'
```

### 2. Initialize Persistent Storage

For database storage:

```bash
# Option 1: Use built-in init command
php bin/console pulse:flags:init-storage

# Option 2: Use Doctrine migrations (if you prefer)
php bin/console doctrine:migrations:migrate
```

### 3. Define Permanent Flags (Optional)

Create `config/packages/pulse_flags/core.yaml` for version-controlled flags:

```yaml
# All flags defined in YAML are automatically permanent (read-only)
# Use persistent storage (database) for runtime-modifiable flags

new_ui:
    enabled: true
    strategy: simple
    description: 'New user interface redesign'

api_v2:
    enabled: true
    strategy: simple
    description: 'API version 2 endpoints'

maintenance_mode:
    enabled: false
    strategy: simple
    description: 'Global maintenance mode'
```

**Note:** Flags are namespaced by filename: `core.yaml` → `core.new_ui`, `core.api_v2`, etc.

### 4. Create Dynamic Flags

For flags that need runtime modification (A/B tests, gradual rollouts):

```bash
# Via CLI
php bin/console pulse:flags:create experiments.beta_checkout

# Then configure it
php bin/console pulse:flags:enable experiments.beta_checkout --percentage=10
```

Or use the admin panel at `/admin/pulse-flags`

### 5. Use in Your Code

```php
use Pulse\FlagsBundle\Service\FeatureFlagServiceInterface;
// Or use specific services:
// use Pulse\FlagsBundle\Service\PermanentFeatureFlagService;
// use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;

class ProductController
{
    public function __construct(
        private FeatureFlagServiceInterface $flags  // Will use PersistentFeatureFlagService by default
    ) {}

    public function checkout(): Response
    {
        // Simple check (works for both permanent and persistent flags)
        if ($this->flags->isEnabled('core.new_ui')) {
            return $this->render('product/checkout_new.html.twig');
        }

        // Percentage-based with consistent user bucketing
        if ($this->flags->isEnabled('experiments.beta_checkout', [
            'user_id' => $this->getUser()?->getId(),
        ])) {
            return $this->render('product/checkout_beta.html.twig');
        }

        return $this->render('product/checkout.html.twig');
    }
}
```

### 6. Use in Twig Templates

```twig
{% if is_feature_enabled('core.new_ui') %}
    <div class="new-design">...</div>
{% else %}
    <div class="old-design">...</div>
{% endif %}

{# With context for percentage strategy #}
{% if is_feature_enabled('experiments.beta_checkout', {'user_id': app.user.id}) %}
    {# Beta checkout flow #}
{% endif %}
```

## 🏗️ Architecture

The bundle uses a feature-based directory structure for better organization and maintainability:

### Commands

Commands are organized by purpose for easy navigation:

- **`Command/Flag/`** - CRUD operations for managing flags
  - `CreateFlagCommand` - Create new persistent flags
  - `EnableFlagCommand` - Enable flags with strategy configuration
  - `DisableFlagCommand` - Disable flags
  - `RemoveFlagCommand` - Delete flags

- **`Command/Query/`** - Information and inspection commands
  - `CheckFlagCommand` - Test flag evaluation with context
  - `ListFlagsCommand` - Display all flags with pagination

- **`Command/Setup/`** - Maintenance and setup commands
  - `InitStorageCommand` - Initialize database storage

### Services

The bundle uses inheritance to eliminate code duplication:

- **`AbstractFeatureFlagService`** - Base service with shared logic for strategy management and flag evaluation
- **`PermanentFeatureFlagService`** - Read-only flags loaded from configuration files
- **`PersistentFeatureFlagService`** - Runtime-mutable flags stored in database

Both services extend the abstract base and implement the `FeatureFlagInterface`, providing consistent behavior across different storage backends.

### Admin Panel

Admin resources are consolidated in a single location:

- **`Admin/Controller/`** - Admin panel controller with both UI and API endpoints
- **`Admin/Resources/views/`** - Twig templates for the admin interface
- **`Resources/public/admin/`** - CSS and JavaScript assets

This structure makes the admin panel self-contained and easy to maintain.

### Strategies

Five activation strategies are available:

- **`SimpleStrategy`** - Basic on/off toggle
- **`PercentageStrategy`** - Gradual rollout with consistent bucketing
- **`UserIdStrategy`** - Whitelist/blacklist specific users
- **`DateRangeStrategy`** - Time-bounded features
- **`CompositeStrategy`** - Combine multiple strategies with AND/OR logic

## 📖 Core Concepts

### Permanent vs Persistent Flags

| Aspect | Permanent (Config) | Persistent (Database) |
|--------|-------------------|----------------------|
| **Location** | `config/packages/pulse_flags/*.yaml` | Database (MySQL, PostgreSQL, SQLite) |
| **Mutability** | Read-only at runtime | Fully mutable via API/CLI/Admin |
| **Use Cases** | Critical features, version-controlled toggles | A/B tests, gradual rollouts, experiments |
| **Modification** | Requires code deployment | Changed instantly via admin panel |
| **Protection** | Cannot be modified/deleted at runtime | Can be freely modified |
| **Examples** | `core.api_v2`, `core.maintenance_mode` | `experiments.beta_feature`, `rollout.new_checkout` |

**Best Practice:** Use permanent flags for critical features that should be version-controlled. Use persistent flags for dynamic experiments and gradual rollouts.

### Flag Namespacing

Flags are automatically namespaced by their config file name:

```
config/packages/pulse_flags/
├── core.yaml        → core.new_ui, core.api_v2
├── experiments.yaml → experiments.beta_feature
└── rollouts.yaml    → rollouts.premium_tier
```

### Activation Strategies

#### 1. Simple Strategy

Basic on/off toggle:

```yaml
feature:
    enabled: true
    strategy: simple
```

#### 2. Percentage Strategy

Gradual rollout with consistent user bucketing:

```yaml
new_checkout:
    enabled: true
    strategy: percentage
    percentage: 25  # 25% of users
```

```php
$this->flags->isEnabled('experiments.new_checkout', [
    'user_id' => $userId,  // Required for consistent bucketing
]);
```

**Important:** Same user always gets same result (uses CRC32 hash bucketing).

#### 3. User ID Strategy

Whitelist/blacklist specific users:

```yaml
premium_features:
    enabled: true
    strategy: user_id
    whitelist: ['123', '456', '789']
    # OR
    blacklist: ['999']
```

```php
$this->flags->isEnabled('core.premium_features', [
    'user_id' => $userId,
]);
```

#### 4. Date Range Strategy

Time-bounded features:

```yaml
holiday_promo:
    enabled: true
    strategy: date_range
    start_date: '2025-12-01'
    end_date: '2025-12-31'
```

```php
$this->flags->isEnabled('promo.holiday_promo', [
    'current_date' => new \DateTime(),
]);
```

#### 5. Composite Strategy

Combine multiple strategies:

```yaml
complex_feature:
    enabled: true
    strategy: composite
    strategies:
        - type: percentage
          percentage: 50
        - type: date_range
          start_date: '2025-01-01'
    operator: AND  # or OR
```

## 🔧 CLI Commands

### List All Flags

```bash
php bin/console pulse:flags:list

# Output shows both permanent and persistent flags with their status
```

### Create Flag

```bash
php bin/console pulse:flags:create my_new_feature
```

### Enable Flag

```bash
# Simple enable
php bin/console pulse:flags:enable my_feature

# Percentage rollout
php bin/console pulse:flags:enable my_feature --percentage=25

# User whitelist
php bin/console pulse:flags:enable my_feature --whitelist=123 --whitelist=456

# Date range
php bin/console pulse:flags:enable my_feature --start-date=2025-01-01 --end-date=2025-12-31
```

### Disable Flag

```bash
# Disable (keeps config, sets enabled=false)
php bin/console pulse:flags:disable my_feature

# Remove completely
php bin/console pulse:flags:disable my_feature --remove
```

### Check Flag Status

```bash
php bin/console pulse:flags:check my_feature

# With user context
php bin/console pulse:flags:check my_feature --user-id=123
```

### Initialize Storage

```bash
# Create DB tables or initialize storage
php bin/console pulse:flags:init-storage

# Force re-initialization
php bin/console pulse:flags:init-storage --force
```

## 🖥️ Admin Panel

Access the web UI at `/admin/pulse-flags`

Features:
- View all flags (permanent and persistent)
- Toggle flags on/off
- Edit flag configuration (percentage, dates, whitelists)
- Create new persistent flags
- Delete persistent flags
- **Protection:** Permanent flags are read-only (shown with lock icon)

### Routing

Add to `config/routes/pulse_flags.yaml` (auto-generated by Symfony Flex):

```yaml
pulse_flags_admin:
    resource: '@PulseFlagsBundle/Resources/config/routes/pulse_flags.yaml'
    prefix: /admin/pulse-flags
```

## 🗄️ Storage Backends

### Database (MySQL, PostgreSQL, SQLite)

```yaml
pulse_flags:
    persistent_storage: db
    db:
        dsn: '%env(DATABASE_URL)%'
        table: 'pulse_feature_flags'
```

Supports:
- MySQL (JSON column)
- PostgreSQL (JSONB column)
- SQLite (TEXT column)

### PHP File Storage

For development/testing, you can use PHP file storage:

```yaml
pulse_flags:
    permanent_storage: php  # Flags stored in PHP files
```

## 🧪 Testing

Use PhpStorage in memory mode for isolated tests:

```php
use Pulse\FlagsBundle\Storage\PhpStorage;
use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;

class MyTest extends TestCase
{
    public function testFeatureFlag(): void
    {
        // PhpStorage without file path = in-memory mode
        $storage = new PhpStorage();
        $storage->set('test.feature', [
            'enabled' => true,
            'strategy' => 'simple',
        ]);

        $flagService = new PersistentFeatureFlagService($storage);

        $this->assertTrue($flagService->isEnabled('test.feature'));
    }
}
```

## 🔒 Security & Best Practices

### Protect Permanent Flags

Permanent flags (from YAML) cannot be modified at runtime:

```php
// This will throw RuntimeException
$flagService->enable('core.critical_feature');  // ❌ Error!
$flagService->remove('core.critical_feature');  // ❌ Error!
```

Admin panel shows permanent flags as read-only.

### Consistent Percentage Bucketing

Always pass `user_id` or `session_id` for percentage strategies:

```php
// ✅ Good - consistent results for same user
$this->flags->isEnabled('experiments.feature', [
    'user_id' => $userId
]);

// ❌ Bad - random results on each call
$this->flags->isEnabled('experiments.feature');
```

### Flag Naming Convention

Use descriptive, namespaced names:

```
✅ Good:
- core.api_v2
- experiments.new_checkout
- rollout.premium_tier_2025

❌ Bad:
- flag1
- test
- new_feature
```

## 📁 Project Structure

```
config/packages/pulse_flags/
├── core.yaml          # Permanent flags (read-only)
├── experiments.yaml   # More permanent flags
└── rollouts.yaml      # Even more permanent flags

src/
├── Controller/
│   └── AdminController.php       # Admin panel
├── Service/
│   └── FeatureFlagService.php    # Main service
├── Storage/
│   ├── StorageInterface.php
│   ├── DbStorage.php             # Database backend
│   ├── PhpStorage.php            # PHP file storage
│   └── YamlStorage.php           # YAML file storage (read-only)
├── Strategy/
│   ├── StrategyInterface.php
│   ├── SimpleStrategy.php
│   ├── PercentageStrategy.php
│   ├── UserIdStrategy.php
│   ├── DateRangeStrategy.php
│   └── CompositeStrategy.php
└── Command/
    ├── ListFlagsCommand.php
    ├── CreateFlagCommand.php
    ├── EnableFlagCommand.php
    ├── DisableFlagCommand.php
    ├── CheckFlagCommand.php
    └── InitStorageCommand.php
```

## 🔧 Troubleshooting

### Database Table Doesn't Exist

**Problem:** "Table 'pulse_feature_flags' doesn't exist"

**Solution:**

```bash
# Option 1: Use init command
php bin/console pulse:flags:init-storage --force

# Option 2: Use Doctrine migrations
php bin/console doctrine:migrations:migrate
```

### Cannot Modify Permanent Flag

**Problem:** Getting 403 Forbidden when trying to modify a flag via admin panel or CLI

**Solution:** The flag is defined in a YAML config file, making it permanent (read-only). Either:

1. Modify the YAML file directly in `config/packages/pulse_flags/`
2. Deploy code changes to update the flag
3. Create a new persistent flag with a different name for runtime modification

### Percentage Strategy Always Returns False

**Problem:** Percentage-based flags always return `false`

**Solution:** You must pass `user_id` or `session_id` in context for consistent bucketing:

```php
// ❌ Wrong - no context
$this->flags->isEnabled('experiments.feature');

// ✅ Correct - with user context
$this->flags->isEnabled('experiments.feature', [
    'user_id' => $this->getUser()?->getId()
]);
```

### Flag Changes Not Visible

**Problem:** Flag changes in admin panel don't take effect immediately

**Solution:**

1. **Cache Issue:** Clear Symfony cache: `php bin/console cache:clear`
2. **Opcache:** Restart PHP-FPM to clear opcache

### Admin Panel Not Loading (404)

**Problem:** `/admin/pulse-flags` returns 404 Not Found

**Solution:** Ensure routes are imported in `config/routes/pulse_flags.yaml`:

```yaml
pulse_flags_admin:
    resource: '@PulseFlagsBundle/Resources/config/routes/pulse_flags.yaml'
    prefix: /admin/pulse-flags
```

Then clear cache: `php bin/console cache:clear`

## 📚 API Reference

### Core Services

#### FeatureFlagInterface

Main interface for checking feature flags:

```php
interface FeatureFlagInterface
{
    /**
     * Check if a feature flag is enabled
     *
     * @param string $name Flag name
     * @param array $context Context for strategy evaluation (user_id, session_id, current_date)
     * @return bool True if enabled
     */
    public function isEnabled(string $name, array $context = []): bool;

    /**
     * Get flag configuration
     *
     * @param string $name Flag name
     * @return array|null Configuration or null if not found
     */
    public function getConfig(string $name): ?array;

    /**
     * Check if flag exists
     *
     * @param string $name Flag name
     * @return bool True if exists
     */
    public function exists(string $name): bool;

    /**
     * Get all flags
     *
     * @return array<string, array> All flags keyed by name
     */
    public function all(): array;
}
```

#### PersistentFeatureFlagService

Service for runtime-mutable flags with additional methods:

```php
class PersistentFeatureFlagService implements FeatureFlagInterface
{
    /** Configure or update a flag */
    public function configure(string $name, array $config): void;

    /** Enable a flag (sets enabled=true) */
    public function enable(string $name): void;

    /** Disable a flag (sets enabled=false) */
    public function disable(string $name): void;

    /** Remove a flag completely */
    public function remove(string $name): void;
}
```

### Admin Panel REST API

Base URL: `/admin/pulse-flags/api`

#### List All Flags

```
GET /api/flags
```

**Response:**
```json
[
    {
        "name": "core.new_ui",
        "config": {"enabled": true, "strategy": "simple"},
        "readonly": true
    },
    {
        "name": "experiments.beta",
        "config": {"enabled": true, "strategy": "percentage", "percentage": 25},
        "readonly": false
    }
]
```

#### Get Flag Details

```
GET /api/flag/{name}
```

**Response:**
```json
{
    "name": "experiments.beta",
    "config": {"enabled": true, "strategy": "percentage", "percentage": 25},
    "readonly": false
}
```

#### Toggle Flag

```
POST /api/flag/{name}/toggle
```

**Response:**
```json
{
    "success": true,
    "name": "experiments.beta",
    "enabled": false
}
```

**Error (403):** Cannot toggle permanent flags

#### Update Flag Configuration

```
PUT /api/flag/{name}
Content-Type: application/json

{
    "enabled": true,
    "strategy": "percentage",
    "percentage": 50
}
```

**Response:**
```json
{
    "success": true,
    "name": "experiments.beta"
}
```

**Error (403):** Cannot update permanent flags

#### Create Flag

```
POST /api/flag
Content-Type: application/json

{
    "name": "new.feature",
    "enabled": false,
    "strategy": "simple"
}
```

**Response (201):**
```json
{
    "success": true,
    "name": "new.feature"
}
```

**Error (409):** Flag already exists

#### Delete Flag

```
DELETE /api/flag/{name}
```

**Response:**
```json
{
    "success": true,
    "name": "experiments.beta"
}
```

**Error (403):** Cannot delete permanent flags
**Error (404):** Flag not found

### Context Parameters

Context object passed to `isEnabled()` for strategy evaluation:

| Parameter | Type | Used By Strategy | Description |
|-----------|------|------------------|-------------|
| `user_id` | string\|int | percentage, user_id | User identifier for consistent bucketing |
| `session_id` | string | percentage | Session identifier (fallback if no user_id) |
| `current_date` | DateTime | date_range | Current date for time-bounded features |

**Example:**
```php
$context = [
    'user_id' => $user->getId(),
    'session_id' => $session->getId(),
    'current_date' => new \DateTime(),
];

$isEnabled = $flagService->isEnabled('my_feature', $context);
```

### Strategy Configuration Reference

#### Simple Strategy
```yaml
my_feature:
    enabled: true
    strategy: simple
```

#### Percentage Strategy
```yaml
my_feature:
    enabled: true
    strategy: percentage
    percentage: 25  # 0-100
```

#### User ID Strategy
```yaml
my_feature:
    enabled: true
    strategy: user_id
    whitelist: ['123', '456']  # Allow only these users
    # OR
    blacklist: ['999']  # Block these users
```

#### Date Range Strategy
```yaml
my_feature:
    enabled: true
    strategy: date_range
    start_date: '2025-01-01'  # YYYY-MM-DD
    end_date: '2025-12-31'    # Optional
```

#### Composite Strategy
```yaml
my_feature:
    enabled: true
    strategy: composite
    operator: AND  # or OR
    strategies:
        - type: percentage
          percentage: 50
        - type: date_range
          start_date: '2025-01-01'
```

## 🔒 Security

### CSRF Protection

All state-changing admin panel endpoints are protected against CSRF attacks:
- POST `/admin/pulse-flags/flags/{name}/toggle`
- PUT/PATCH `/admin/pulse-flags/flags/{name}`
- POST `/admin/pulse-flags/flags`
- DELETE `/admin/pulse-flags/flags/{name}`

CSRF tokens are automatically generated and validated. The JavaScript client includes the token in all API requests via the `X-CSRF-Token` header.

### Recommended Security Practices

1. **Enable Authentication**: Protect admin routes with Symfony Security:
   ```yaml
   # config/packages/security.yaml
   access_control:
       - { path: ^/admin/pulse-flags, roles: ROLE_ADMIN }
   ```

2. **Use HTTPS**: Always use HTTPS in production to protect CSRF tokens and flag data.

3. **Validate Table Names**: If allowing user-configured table names, use only alphanumeric characters and underscores.

4. **Monitor Changes**: Enable audit logging for flag modifications in production environments.

5. **Review Security Audit**: See [SECURITY_AUDIT.md](SECURITY_AUDIT.md) for detailed security findings and recommendations.

### Reporting Security Issues

If you discover a security vulnerability, please email security@example.com instead of using the issue tracker.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This bundle is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.

## 🙏 Credits

Developed with ❤️ by the Pulse team.
