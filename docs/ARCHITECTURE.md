# WP Performance Doctor — Architecture

## Module responsibilities

| Module | Responsibility |
|--------|----------------|
| `Core` | Plugin lifecycle, bootstrapping, dependency container |
| `Contracts` | Interfaces shared across modules |
| `Collectors` | Raw diagnostic data gathering |
| `Diagnostics` | Rule-based evaluation of collected data |
| `Recommendations` | Converts diagnostic results into actionable advice |
| `Optimization` | Approved changes and rollback |
| `Reports` | Report storage and retrieval |
| `REST` | Secured endpoints for async operations |
| `Security` | Capability and nonce checks |
| `Database` | Schema definitions |
| `Support` | Logger and helpers |
| `admin` | Presentation and WordPress admin integration only |

## Diagnostic engine details

The `Diagnostics` module implements a deterministic rule engine:

- **DiagnosticRuleInterface**: Contract every rule must implement
- **AbstractRule**: Base class with shared helpers (metric access, formatting, result builders)
- **RuleRegistry**: Discovers, registers, enables/disables, and configures rules
- **DiagnosticContext**: Provides rules with latest normalized metrics and snapshot metadata
- **DiagnosticEngine**: Executes eligible rules with error isolation, required-metric validation, and deduplication
- **DiagnosticService**: Coordinates runs, persists findings, manages lifecycle (open/acknowledged/resolved/ignored)
- **Finding**: Structured result with severity, confidence, evidence, impact, remediation
- **HealthScoreCalculator**: Transparent 0–100 score weighted by severity and confidence
- **RecommendationProvider**: Converts findings into prioritized remediation advice

### Rule categories

| Category | Rules | Focus |
|----------|-------|-------|
| PHP & Server | 10 | Memory limit, execution time, upload/post size, OPcache, PHP version, extensions, disk space, load average |
| WordPress Configuration | 9 | Debug display, cron, permalinks, search visibility, memory limits, core updates, environment, multisite |
| Database | 8 | Autoloaded options (size/count), large tables, transients, revisions, table count, table engines, DB compatibility |
| Caching | 6 | Object cache, Redis, Memcached, OPcache, page cache, cache plugins |
| Plugins & Themes | 5 | Plugin count, missing versions, inactive plugins, active theme, plugin updates |
| PHP Profiling | 4 | Slow requests, high memory, sample count, request type |
| Frontend Assets | 4 | Excessive scripts/styles, external assets, blocking scripts, total assets |

### Severity levels

- `critical`: Potential site errors or outages
- `high`: Noticeable performance or reliability impact
- `medium`: Moderate performance or risk impact
- `low`: Minor optimization
- `info`: Observation without a problem

### Health score

Transparent 0–100 score derived from open findings, weighted by severity
(critical: 25, high: 15, medium: 8, low: 3, info: 0) and confidence
(high: 1.0, medium: 0.6, low: 0.3). Insufficient diagnostic coverage
displays "Insufficient data" instead of an arbitrary score.

## Dependency boundaries

- `Core` knows nothing about `admin`.
- `admin` depends on `Core`, `Security`, and page classes only.
- Diagnostic rules (`Diagnostics`) must never modify WordPress configuration or database state.
- Inspection and diagnosis are read-only.
- Optimization actions are isolated and require explicit administrator confirmation.

## Namespace

`WPPerformanceDoctor`

## Text domain

`wp-performance-doctor`
