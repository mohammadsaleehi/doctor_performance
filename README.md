# WP Performance Doctor

Measure, diagnose, fix, and verify WordPress performance.

## Purpose

WP Performance Doctor is a WordPress plugin designed to help website owners
measure website performance, inspect WordPress/PHP/database/server/caching
configurations, identify bottlenecks, diagnose issues using a deterministic
rule-based engine, and provide actionable optimization recommendations.

## Main capabilities (planned)

- Measure performance metrics
- Inspect WordPress, PHP, database, server, and caching configuration
- Deterministic rule-based diagnostics
- Actionable optimization recommendations
- Safe optimizations with explicit administrator approval
- Verification of improvements after changes

## Requirements

- WordPress 6.5+
- PHP 8.1+

## Installation for local development

1. Clone this repository into `wp-content/plugins/wp-performance-doctor/`.
2. Run `composer install` to install dependencies and generate the autoloader.
3. Activate the plugin through the WordPress admin.
4. Visit Performance Doctor in the admin menu.

## Architecture

The plugin follows a modular, layered architecture:

- `Core` — plugin lifecycle and bootstrapping
- `Contracts` — shared interfaces
- `Inspectors` — read-only subsystem inspection
- `Collectors` — raw diagnostic data gathering
- `Diagnostics` — rule-based evaluation
- `Recommendations` — actionable advice
- `Optimization` — approved changes and rollback
- `Reports` — report storage and retrieval
- `REST` — secured endpoints
- `Security` — capability and nonce checks
- `admin` — presentation and admin integration only

See `docs/ARCHITECTURE.md` for module responsibilities and dependency boundaries.

## Current development status

Phase 1: Project foundation and plugin bootstrap (complete).

The plugin loads correctly and registers a top-level admin menu with placeholder
pages. No actual scanning, diagnostic rules, or optimizations are implemented yet.

## Security

See `docs/SECURITY.md`.

## Development

See `docs/DEVELOPMENT.md`.

## Roadmap

See `docs/ROADMAP.md`.
