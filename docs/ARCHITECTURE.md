# WP Performance Doctor — Architecture

## Module responsibilities

| Module | Responsibility |
|--------|----------------|
| `Core` | Plugin lifecycle, bootstrapping, dependency container |
| `Contracts` | Interfaces shared across modules |
| `Inspectors` | Read-only inspection of WordPress/server subsystems |
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
