# PulseFlags Bundle

<div align="center">

**Production-ready Symfony Bundle for advanced feature flag management**

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/symfony-%5E7.3%20%7C%20%5E8.0-000000?style=flat&logo=symfony)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-green?style=flat)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-blue?style=flat)](https://phpstan.org/)
[![Code Style](https://img.shields.io/badge/code%20style-PSR--12-blue?style=flat)](https://www.php-fig.org/psr/psr-12/)

</div>

---

## Features

- **Advanced Rollout Strategies** - 10 powerful strategies for precise targeting
  - Percentage rollout with 0.001% precision (100,000 buckets)
  - User segments (reusable groups)
  - Custom attributes with 13 operators (equals, in, greater_than, contains, regex, etc.)
  - Progressive rollout (automated gradual releases)
  - IP/Geo targeting
- **Flexible Configuration** - Hash algorithms (CRC32, MD5, SHA256, MurmurHash3), stickiness, timezone support
- **Dual Storage Architecture**:
  - **Permanent Flags** - Read-only flags from YAML/PHP config files (version-controlled, critical features)
  - **Persistent Flags** - Runtime-mutable flags in database (MySQL, PostgreSQL, SQLite)
- **Twig Integration** - Built-in `is_feature_enabled()` function for templates
- **CLI Commands** - Complete command-line management and inspection tools
- **Configurable Logging** - Optional logging with custom channels, levels, and file output
- **Protection** - Permanent flags cannot be modified or deleted at runtime
- **Multi-Storage Support** - MySQL, PostgreSQL, SQLite, PHP arrays, YAML files
- **Type-Safe** - Full PHP type hints and strict types throughout

---

## Installation

```bash
composer require pulse/flags-bundle
```

**Requirements:** PHP 8.1+, Symfony 7.3+

### Manual Configuration

If Symfony Flex doesn't auto-configure the bundle, register it manually in `config/bundles.php`:

```php
return [
    // ...
    Pulse\Flags\Core\PulseFlagsBundle::class => ['all' => true],
];
```

---

## Quick Start

### 1. Configure Storage

Create `config/packages/pulse_flags.yaml`:

```yaml
pulse_flags:
    # Storage format for permanent (read-only) flags loaded from config files
    permanent_storage: yaml  # Options: yaml, php

    # Storage backend for persistent (runtime mutable) flags
    persistent_storage: db   # Options: db

    # Database configuration (for persistent storage)
    db:
        dsn: '%env(resolve:DATABASE_URL)%'
        table: 'pulse_feature_flags'
```

### 2. Initialize Persistent Storage

For database storage:

```bash
# Use built-in init command
php bin/console pulse:flags:init-storage

# Or use Doctrine migrations (if you prefer)
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
# Create a new flag
php bin/console pulse:flags:create experiments.beta_checkout

# Configure it with percentage rollout
php bin/console pulse:flags:enable experiments.beta_checkout --percentage=10
```

### 5. Use in Your Code

```php
use Pulse\Flags\Core\Service\FeatureFlagServiceInterface;

class ProductController
{
    public function __construct(
        private FeatureFlagServiceInterface $flags
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

---

## Core Concepts

### Permanent vs Persistent Flags

| Aspect | Permanent (Config) | Persistent (Database) |
|--------|-------------------|----------------------|
| **Location** | `config/packages/pulse_flags/*.yaml` | Database (MySQL, PostgreSQL, SQLite) |
| **Mutability** | Read-only at runtime | Fully mutable via CLI |
| **Use Cases** | Critical features, version-controlled toggles | A/B tests, gradual rollouts, experiments |
| **Modification** | Requires code deployment | Changed instantly via CLI |
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

---

## Activation Strategies

### 1. Simple Strategy

Basic on/off toggle:

```yaml
feature:
    enabled: true
    strategy: simple
```

### 2. Percentage Strategy

Gradual rollout with consistent user bucketing (100,000 buckets for high precision):

```yaml
# Standard rollout
new_checkout:
    enabled: true
    strategy: percentage
    percentage: 25  # 25% of users

# Fine-grained rollout (supports up to 3 decimal places)
early_adopters:
    enabled: true
    strategy: percentage
    percentage: 0.125  # 0.125% of users (125 out of 100,000)

# Custom hash algorithm and seed
experiment_v2:
    enabled: true
    strategy: percentage
    percentage: 50
    hash_algorithm: 'md5'  # Options: crc32 (default), md5, sha256
    hash_seed: 'exp-2025-q1'  # Optional seed for re-randomization

# B2B: Hash by company (all users in same company get same experience)
enterprise_feature:
    enabled: true
    strategy: percentage
    percentage: 25
    stickiness: 'company_id'  # Hash by company_id instead of user_id

# Stickiness with fallback chain
anonymous_feature:
    enabled: true
    strategy: percentage
    percentage: 10
    stickiness: ['user_id', 'session_id', 'device_id']  # Try in order
```

```php
$this->flags->isEnabled('experiments.new_checkout', [
    'user_id' => $userId,  // Required for consistent bucketing
]);

// For B2B features with company stickiness
$this->flags->isEnabled('enterprise_feature', [
    'company_id' => $companyId,  // All users in company get same experience
]);
```

**Important:** Same user always gets same result (consistent hash-based bucketing with 100,000 buckets).

### 3. User ID Strategy

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

### 4. Date Range Strategy

Time-bounded features:

```yaml
holiday_promo:
    enabled: true
    strategy: date_range
    start_date: '2025-12-01'
    end_date: '2025-12-31'
    timezone: 'America/New_York'  # Optional - ensures correct time for global apps
```

```php
$this->flags->isEnabled('promo.holiday_promo', [
    'current_date' => new \DateTime(),
]);
```

### 5. Composite Strategy

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

### 6. Segment Strategy

Define reusable user groups once, reference across multiple flags:

```yaml
# First, configure segments in pulse_flags configuration
pulse_flags:
    segments:
        premium_users:
            type: 'static'
            user_ids: ['1', '2', '3', '123', '456']

        internal_team:
            type: 'dynamic'
            condition: 'email_domain'
            value: 'company.com'

        us_users:
            type: 'dynamic'
            condition: 'country'
            value: 'US'

# Then use segments in flags
premium_feature:
    enabled: true
    strategy: segment
    segments: ['premium_users', 'internal_team']  # OR logic - user in ANY segment

regional_feature:
    enabled: true
    strategy: segment
    segments: ['us_users']
```

```php
$this->flags->isEnabled('premium_feature', [
    'user_id' => $userId,  // Required for all segments
    'email' => $email,     // Required for email_domain dynamic segments
    'country' => $country, // Required for country dynamic segments
]);
```

**Benefits:**
- Define user groups once, reuse everywhere
- Eliminates repetitive whitelist configurations
- Supports both static (explicit lists) and dynamic (rule-based) segments

### 7. Custom Attribute Strategy

Target users by any attribute with powerful operators:

```yaml
# Premium subscribers with account age > 30 days
premium_feature:
    enabled: true
    strategy: custom_attribute
    rules:
        - attribute: 'subscription_tier'
          operator: 'in'
          values: ['premium', 'enterprise']
        - attribute: 'account_age_days'
          operator: 'greater_than'
          value: 30

# Regional feature for specific countries
regional_feature:
    enabled: true
    strategy: custom_attribute
    rules:
        - attribute: 'country'
          operator: 'in'
          values: ['US', 'CA', 'GB']

# Educational institutions
edu_feature:
    enabled: true
    strategy: custom_attribute
    rules:
        - attribute: 'email'
          operator: 'ends_with'
          value: '.edu'

# Advanced regex matching
beta_program:
    enabled: true
    strategy: custom_attribute
    rules:
        - attribute: 'phone_number'
          operator: 'regex'
          value: '/^\+1/'
```

```php
$this->flags->isEnabled('premium_feature', [
    'subscription_tier' => 'premium',
    'account_age_days' => 45,
    'country' => 'US',
    'email' => 'user@example.com',
]);
```

**Available operators:**
- `equals`, `not_equals` - Exact comparison
- `in`, `not_in` - Array membership
- `greater_than`, `less_than`, `greater_than_or_equals`, `less_than_or_equals` - Numeric comparison
- `contains`, `not_contains` - Substring matching
- `starts_with`, `ends_with` - String prefix/suffix
- `regex` - Regular expression matching

**Logic:** All rules must pass (AND logic)

### 8. Progressive Rollout Strategy

Automate gradual rollouts with scheduled percentage increases:

```yaml
# Simple progressive rollout
new_feature:
    enabled: true
    strategy: progressive_rollout
    schedule:
        - { percentage: 1, start_date: '2025-01-01' }
        - { percentage: 5, start_date: '2025-01-03' }
        - { percentage: 25, start_date: '2025-01-07' }
        - { percentage: 50, start_date: '2025-01-10' }
        - { percentage: 100, start_date: '2025-01-15' }
    stickiness: 'user_id'

# Fine-grained rollout with timezone
critical_feature:
    enabled: true
    strategy: progressive_rollout
    schedule:
        - { percentage: 0.1, start_date: '2025-01-01 00:00:00' }
        - { percentage: 1, start_date: '2025-01-01 12:00:00' }
        - { percentage: 5, start_date: '2025-01-02 00:00:00' }
        - { percentage: 100, start_date: '2025-01-07 00:00:00' }
    stickiness: ['user_id', 'session_id']
    timezone: 'America/New_York'
```

```php
$this->flags->isEnabled('new_feature', [
    'user_id' => $userId,  // Required for consistent bucketing
]);
```

**Benefits:**
- Automate gradual rollouts without manual intervention
- Reduce risk by slowly increasing user exposure
- Monitor metrics at each stage before expanding
- Easy rollback by adjusting schedule

### 9. IP Strategy

Target users by IP address (supports CIDR notation):

```yaml
# Internal testing (office network)
internal_feature:
    enabled: true
    strategy: ip
    whitelist_ips: ['203.0.113.42', '198.51.100.10']

# Private networks (VPN, office)
vpn_feature:
    enabled: true
    strategy: ip
    ip_ranges: ['10.0.0.0/8', '192.168.0.0/16', '172.16.0.0/12']

# Mixed configuration
staging_access:
    enabled: true
    strategy: ip
    whitelist_ips: ['203.0.113.42']
    ip_ranges: ['10.0.0.0/8']
```

```php
$this->flags->isEnabled('internal_feature', [
    'ip_address' => $request->getClientIp(),
]);
```

**Supported formats:**
- IPv4: `192.168.1.1`
- IPv6: `2001:0db8:85a3:0000:0000:8a2e:0370:7334`
- CIDR: `192.168.0.0/16`, `2001:db8::/32`

### 10. Geo Strategy

Target users by geographic location:

```yaml
# Country-based targeting
eu_feature:
    enabled: true
    strategy: geo
    countries: ['DE', 'FR', 'GB', 'IT', 'ES', 'NL', 'BE']  # ISO 3166-1 alpha-2

# GDPR compliance (all EU countries)
gdpr_banner:
    enabled: true
    strategy: geo
    countries: [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ]

# Regional targeting
california_feature:
    enabled: true
    strategy: geo
    regions: ['CA']  # US state codes

# City-based targeting
metro_feature:
    enabled: true
    strategy: geo
    cities: ['New York', 'Los Angeles', 'Chicago']
```

```php
$this->flags->isEnabled('eu_feature', [
    'country' => 'DE',  // ISO 3166-1 alpha-2 code
    'region' => 'CA',   // Optional - state/region code
    'city' => 'Berlin', // Optional - city name
]);
```

**Note:** Geographic data must be provided in context. Common approaches:
- CDN edge functions (Cloudflare Workers, AWS Lambda@Edge)
- Application middleware using GeoIP2/MaxMind databases
- Reverse proxy headers (X-Country-Code)

---

## CLI Commands

### Flag Management

#### List All Flags

```bash
php bin/console pulse:flags:list

# Output shows both permanent and persistent flags with their status
```

#### Create Flag

```bash
php bin/console pulse:flags:create my_new_feature
```

#### Enable Flag

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

#### Disable Flag

```bash
# Disable (keeps config, sets enabled=false)
php bin/console pulse:flags:disable my_feature

# Remove completely
php bin/console pulse:flags:disable my_feature --remove
```

#### Check Flag Status

```bash
php bin/console pulse:flags:check my_feature

# With user context
php bin/console pulse:flags:check my_feature --user-id=123
```

#### Initialize Storage

```bash
# Create DB tables or initialize storage
php bin/console pulse:flags:init-storage

# Force re-initialization
php bin/console pulse:flags:init-storage --force
```

### Validation & Testing

#### Validate Flag Configuration

Validate flag configurations for errors and warnings:

```bash
# Validate single flag
php bin/console pulse:flags:validate experiments.new_feature

# Validate all flags
php bin/console pulse:flags:validate

# Exit codes:
#   0 - All flags valid
#   1 - Validation errors found
#   2 - Warnings found (non-blocking)
```

**Validation checks:**
- Required fields presence
- Value ranges and formats (percentage 0-100, dates, etc.)
- Strategy-specific rules (whitelist XOR blacklist, schedule order)
- Performance warnings (large user lists, past end dates)

#### Test Flag Evaluation

Test how a flag evaluates with specific context:

```bash
# Test with user ID
php bin/console pulse:flags:test experiments.new_feature --user-id=123

# Test with multiple context values
php bin/console pulse:flags:test geo.eu_feature --country=DE --email=user@example.com

# Test with custom attributes
php bin/console pulse:flags:test premium.feature \
  --context=subscription_tier=premium \
  --context=account_age_days=45

# Test progressive rollout
php bin/console pulse:flags:test rollout.gradual --user-id=123
```

**Output shows:**
- Final evaluation result (enabled/disabled)
- Flag configuration
- Applied context
- Strategy used

### Segment Management

#### List Segments

```bash
# List all segments
php bin/console pulse:flags:segments:list

# Filter by type
php bin/console pulse:flags:segments:list --type=static

# Show detailed information
php bin/console pulse:flags:segments:list --detailed
```

#### Create Segment

```bash
# Create static segment with user IDs
php bin/console pulse:flags:segments:create premium_users \
  --user-ids=1 --user-ids=2 --user-ids=3

# Create dynamic segment (email domain)
php bin/console pulse:flags:segments:create internal_team \
  --type=dynamic --condition=email_domain --value=company.com

# Create dynamic segment (country)
php bin/console pulse:flags:segments:create eu_users \
  --type=dynamic --condition=country --operator=in --value=DE,FR,GB
```

---

## Developer Experience

### Enhanced Logging

All flag evaluations are automatically logged with structured context for debugging and monitoring:

```php
// Every isEnabled() call logs:
[2025-01-15 10:30:45] app.INFO: Feature flag evaluated {
    "flag": "experiments.new_checkout",
    "flag_type": "Persistent",
    "strategy": "percentage",
    "result": true,
    "duration_ms": 1.23,
    "context_keys": ["user_id", "email"],
    "context_size": 2
}
```

**Log levels:**
- `INFO` - Successful flag evaluation, flag not found (returns false)
- `ERROR` - Strategy not found, configuration errors

**Configure logging in your PSR-3 logger:**

```yaml
# config/packages/monolog.yaml
monolog:
    handlers:
        feature_flags:
            type: stream
            path: "%kernel.logs_dir%/feature_flags.log"
            level: info
            channels: ["app"]
```

### Strategy Validation Framework

Comprehensive validation framework checks all strategy configurations for errors and warnings:

**Features:**
- **Automatic validation** - Validates all 10 strategies with strategy-specific rules
- **Early error detection** - Catch configuration errors before deployment
- **Performance warnings** - Warns about large user lists, past dates, etc.
- **CI/CD integration** - Use exit codes for automated testing

**Example validation errors:**

```bash
$ php bin/console pulse:flags:validate experiments.bad_config

Flag configuration has errors:
  • Percentage must be between 0 and 100 (got: 150)
  • Hash algorithm must be one of: crc32, md5, sha256, murmur3 (got: invalid)

Warnings:
  • Percentage < 0.001% may not work reliably with small user bases
```

**Validators for all strategies:**
- `PercentageStrategyValidator` - Validates percentage range, hash algorithm, stickiness
- `UserIdStrategyValidator` - Validates whitelist/blacklist (XOR), warns on large lists
- `DateRangeStrategyValidator` - Validates date formats, timezone, logical order
- `SegmentStrategyValidator` - Validates segment existence
- `CustomAttributeStrategyValidator` - Validates rules, operators, regex patterns
- `ProgressiveRolloutStrategyValidator` - Validates schedule order, percentages
- `IpStrategyValidator` - Validates IP addresses and CIDR notation
- `GeoStrategyValidator` - Validates country codes (ISO 3166-1 alpha-2)
- `CompositeStrategyValidator` - Recursively validates nested strategies

---

## Storage Backends

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

### YAML File Storage

For permanent flags (read-only):

```yaml
pulse_flags:
    permanent_storage: yaml
```

Flags are loaded from `config/packages/pulse_flags/*.yaml`

### PHP File Storage

For development/testing:

```yaml
pulse_flags:
    permanent_storage: php  # Flags stored in PHP files
```

---

## Testing

Use `PhpStorage` in memory mode for isolated tests:

```php
use Pulse\Flags\Core\Storage\PhpStorage;
use Pulse\Flags\Core\Service\PersistentFeatureFlagService;

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

---

## Security & Best Practices

### Protect Permanent Flags

Permanent flags (from YAML) cannot be modified at runtime:

```php
// This will throw RuntimeException
$flagService->enable('core.critical_feature');  // ❌ Error!
$flagService->remove('core.critical_feature');  // ❌ Error!
```

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

---

## Logging

PulseFlags includes configurable logging to help debug configuration issues and audit flag operations.

### Configuration

Configure logging behavior in `config/packages/pulse_flags.yaml`:

```yaml
pulse_flags:
    logging:
        enabled: true              # Enable/disable logging (default: true)
        channel: 'pulse_flags'     # Monolog channel name (default: 'pulse_flags')
        level: 'warning'           # Minimum log level (default: 'warning')
```

### Disable Logging

To completely disable logging:

```yaml
pulse_flags:
    logging:
        enabled: false
```

### Custom Log Channel with File Output

Create a dedicated log file for feature flags using Monolog:

```yaml
# config/packages/monolog.yaml
monolog:
    channels: ['pulse_flags']

    handlers:
        pulse_flags:
            type: stream
            path: '%kernel.logs_dir%/pulse_flags.log'
            level: info
            channels: ['pulse_flags']
```

```yaml
# config/packages/pulse_flags.yaml
pulse_flags:
    logging:
        enabled: true
        channel: 'pulse_flags'
        level: 'info'
```

### What Gets Logged

**Warning Level** (default):
- Missing or unknown strategies in flag configuration
- Composite strategy configuration errors

**Info Level**:
- Feature flag configured/enabled/disabled/removed (audit trail for persistent flags)

**Error Level**:
- Unknown strategy types in composite configurations

### Example Log Output

```
[2025-01-15 10:23:45] pulse_flags.INFO: [PulseFlags] Feature flag enabled {"flag":"new_checkout","options":{"strategy":"percentage","percentage":25}}
[2025-01-15 10:24:12] pulse_flags.WARNING: [PulseFlags] Strategy not found for flag {"flag":"beta_feature","strategy":"unknown_strategy","type":"Permanent"}
[2025-01-15 10:25:33] pulse_flags.ERROR: [PulseFlags] Unknown strategy in composite configuration {"strategy":"invalid","index":0,"available_strategies":["simple","percentage","user_id","date_range"]}
```

### Production Recommendations

For production environments:

```yaml
pulse_flags:
    logging:
        enabled: true
        level: 'warning'  # Only log errors and warnings
```

For development/debugging:

```yaml
pulse_flags:
    logging:
        enabled: true
        level: 'info'  # Log all flag operations
```

---

## Architecture

### Services

The bundle uses inheritance to eliminate code duplication:

- **`AbstractFeatureFlagService`** - Base service with shared logic for strategy management and flag evaluation
- **`PermanentFeatureFlagService`** - Read-only flags loaded from configuration files
- **`PersistentFeatureFlagService`** - Runtime-mutable flags stored in database

Both services extend the abstract base and implement `FeatureFlagServiceInterface`, providing consistent behavior across different storage backends.

### Commands

Commands are organized by purpose:

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

### Strategies

Ten activation strategies are available:

- **`SimpleStrategy`** - Basic on/off toggle
- **`PercentageStrategy`** - Gradual rollout with consistent bucketing (100,000 buckets)
- **`UserIdStrategy`** - Whitelist/blacklist specific users (O(1) hash set lookups)
- **`DateRangeStrategy`** - Time-bounded features with timezone support
- **`CompositeStrategy`** - Combine multiple strategies with AND/OR logic
- **`SegmentStrategy`** - Reusable user groups (static and dynamic segments)
- **`CustomAttributeStrategy`** - Rule-based targeting with 13 operators
- **`ProgressiveRolloutStrategy`** - Automated gradual rollout schedules
- **`IpStrategy`** - IP address targeting with CIDR support (IPv4/IPv6)
- **`GeoStrategy`** - Geographic targeting (country/region/city)

---

## Project Structure

```
config/
├── packages/pulse_flags/
│   ├── core.yaml          # Permanent flags (read-only)
│   ├── experiments.yaml   # More permanent flags
│   └── rollouts.yaml      # Even more permanent flags
├── routes.yaml
└── services.yaml

src/
├── Service/
│   ├── AbstractFeatureFlagServiceService.php
│   ├── FeatureFlagServiceInterface.php
│   ├── PermanentFeatureFlagService.php
│   └── PersistentFeatureFlagService.php
├── Storage/
│   ├── StorageInterface.php
│   ├── DbStorage.php             # Database backend
│   ├── PhpStorage.php            # PHP array storage
│   └── YamlStorage.php           # YAML file storage (read-only)
├── Strategy/
│   ├── StrategyInterface.php
│   ├── SimpleStrategy.php
│   ├── PercentageStrategy.php
│   ├── UserIdStrategy.php
│   ├── DateRangeStrategy.php
│   └── CompositeStrategy.php
├── Command/
│   ├── Flag/
│   │   ├── CreateFlagCommand.php
│   │   ├── EnableFlagCommand.php
│   │   ├── DisableFlagCommand.php
│   │   └── RemoveFlagCommand.php
│   ├── Query/
│   │   ├── CheckFlagCommand.php
│   │   └── ListFlagsCommand.php
│   └── Setup/
│       └── InitStorageCommand.php
├── Twig/
│   └── FeatureFlagExtension.php
├── DependencyInjection/
│   ├── Configuration.php
│   ├── PulseFlagsExtension.php
│   └── FlagsConfigurationLoader.php
└── PulseFlagsBundle.php
```

---

## API Reference

### FeatureFlagServiceInterface

Main interface for checking feature flags:

```php
interface FeatureFlagServiceInterface
{
    /**
     * Check if a feature flag is enabled
     *
     * @param string $name Flag name (e.g., 'core.new_ui')
     * @param array $context Context for strategy evaluation
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

### PersistentFeatureFlagService

Service for runtime-mutable flags with additional methods:

```php
class PersistentFeatureFlagService implements FeatureFlagServiceInterface
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

### Context Parameters

Context object passed to `isEnabled()` for strategy evaluation:

| Parameter | Type | Used By Strategy | Description |
|-----------|------|------------------|-------------|
| `user_id` | string\|int | percentage, user_id, segment | User identifier for consistent bucketing |
| `session_id` | string | percentage | Session identifier (fallback if no user_id) |
| `current_date` | DateTime | date_range | Current date for time-bounded features |
| `company_id` | string\|int | percentage (stickiness) | Company identifier for B2B features |
| `email` | string | segment (dynamic), custom_attribute | User email address |
| `country` | string | segment (dynamic), custom_attribute, geo | ISO 3166-1 alpha-2 country code |
| `region` | string | geo | State/region code or name |
| `city` | string | geo | City name |
| `ip_address` | string | ip | User's IP address (IPv4 or IPv6) |
| *custom* | mixed | custom_attribute | Any custom attribute for rule-based targeting |

**Example:**
```php
$context = [
    'user_id' => $user->getId(),
    'session_id' => $session->getId(),
    'current_date' => new \DateTime(),
    'email' => $user->getEmail(),
    'country' => 'US',
    'region' => 'CA',
    'city' => 'San Francisco',
    'ip_address' => $request->getClientIp(),
    'subscription_tier' => 'premium',
    'account_age_days' => 45,
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

---

## Troubleshooting

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

**Problem:** Getting an exception when trying to modify a flag via CLI

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

**Problem:** Flag changes don't take effect immediately

**Solution:**

1. **Cache Issue:** Clear Symfony cache: `php bin/console cache:clear`
2. **Opcache:** Restart PHP-FPM to clear opcache

---

## Uninstallation

### Step 1: Remove bundle registration

Edit `config/bundles.php` and remove the line:

```php
Pulse\Flags\Core\PulseFlagsBundle::class => ['all' => true],
```

### Step 2: Remove configuration files

```bash
rm -rf config/packages/pulse_flags.yaml
rm -rf config/packages/pulse_flags/
```

### Step 3: Clear cache

```bash
php bin/console cache:clear
# or
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

---

## Related Packages

- **[pulse/flags-admin-panel-bundle](https://github.com/pulse/flags-admin-panel-bundle)** - Web UI for managing feature flags

---

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This bundle is released under the MIT License. See the [LICENSE](LICENSE) file for details.
