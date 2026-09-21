# Implementation Plan: Croatian Anchor Price ("Sidrena cijena") Compliance System

**Branch**: `001-sidrena-cijena-compliance` | **Date**: 2026-09-21 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-sidrena-cijena-compliance/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command; its definition describes the execution workflow.

## Summary

Deliver a production-grade WordPress plugin that implements the Croatian additional ("sidrena") price obligation (Odluka NN 101/2026-1212), the daily machine-readable price-list publication obligation (Odluka NN 101/2026-1213), and the separate consumer-protection rule on the lowest price of the previous 30 days for special forms of sale.

The plugin records anchor prices as immutable historical data, displays current and anchor prices together on product pages, archives and promotional surfaces, calculates and displays the 30-day lowest price separately, validates compliance, publishes deterministic CSV/XML price lists per sales location with legally structured filenames, retains every publication for at least 30 days, and exposes an unauthenticated machine-readable price endpoint. WooCommerce is integrated through a dedicated adapter; a standalone catalog keeps the full compliance workflow usable without WooCommerce.

Technical approach: PHP 7.4+ (developed and tested on PHP 8.3), WordPress 6.8+ (target: latest stable 7.1), MySQL/MariaDB through WordPress APIs, no runtime third-party PHP libraries, a small custom PSR-4 autoloader, custom database tables for price history/publications, and dev-only Composer and npm tooling (PHPUnit, WPCS, PHPStan, `@wordpress/env`, Playwright).

## Technical Context

**Language/Version**: PHP 7.4+ (WordPress-supported floor; primary development and test runtime PHP 8.3, verified compatible through PHP 8.5); JavaScript ES2020 for admin assets, no build step required for runtime.

**Primary Dependencies**: WordPress 6.8+ (target latest stable, 7.1 at planning time); WooCommerce (latest stable) optional at runtime; PHPUnit, WordPress Coding Standards, PHPCompatibilityWP, PHPStan with `szepeviktor/phpstan-wordpress`, `@wordpress/env`, `@playwright/test`, `@wordpress/scripts` (lint/format only) as development dependencies. No runtime Composer dependencies.

**Storage**: MySQL 5.7+/MariaDB 10.4+ via `$wpdb`; five custom tables (price history, special sales, sales locations, publications, logs) plus WordPress options for settings/rule configuration; a private custom post type with taxonomies and post meta for the standalone catalog; generated files under `wp-content/uploads/sidrena-cijena/`; object cache/transients for lookups.

**Testing**: PHPUnit unit tests (pure domain logic), PHPUnit integration tests against the WordPress test suite inside wp-env, Playwright end-to-end tests against the wp-env development site, WP-CLI-driven fixtures for the required test products, PHPCS (WordPress + PHPCompatibilityWP) and PHPStan for static analysis.

**Target Platform**: WordPress 6.8+ on PHP 7.4–8.5, Apache/Nginx; WooCommerce 8.x+ when present; browser-based admin and storefront (modern evergreen browsers).

**Project Type**: Single project — a WordPress plugin (WooCommerce integration as an optional adapter module inside the plugin).

**Performance Goals**: anchor-price display adds no catalog-wide query per request (one indexed lookup per rendered item, cached); public price endpoint returns a 100-item page of a 10,000-item catalog in under 2 seconds; daily export of a 10,000-item catalog completes within a 5-minute batch window using streamed writes; admin dashboard aggregates complete in under 2 seconds.

**Constraints**: must never fatal when WooCommerce is absent, deactivated or uninstalled; must not modify WordPress/WooCommerce core or the theme; parameterized SQL only; append-only price history; deterministic, documented CSV/XML output; unauthenticated public price endpoint exposing price data only; minimum 30-day publication retention; PHP 7.4-compatible syntax (no PHP 8-only constructs); no customer personal data.

**Scale/Scope**: target 10,000-item catalogs (architecture scales to ~100,000 items via batching); up to dozens of sales locations; 5 custom tables; approximately 10 admin screens (dashboard, settings, locations, price history, anchor bulk entry, import, publications/archive, logs, compliance report, standalone catalog); REST namespace with 4–6 routes; 7 WP-CLI commands.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

The project constitution at `.specify/memory/constitution.md` is still the unratified template (placeholder principles only), so no project-specific constitutional gates exist. In their place, the plan applies the quality gates implied by the specification and the originating brief:

| Gate | Status | Evidence |
|------|--------|----------|
| Historical data integrity (append-only, auditable corrections) | PASS | FR-004/005/027–031; `data-model.md` defines append-only records and correction linkage |
| Legal-rule separation (anchor vs 30-day lowest price) | PASS | FR-019–025; separate tables, engines and labels |
| Security (capabilities, nonces, prepared SQL, secure files) | PASS | FR-078–080; security model in `research.md`; REST permission matrix in `contracts/rest-api.md` |
| WooCommerce-optional compatibility | PASS | FR-084/085; `CatalogProvider` abstraction in `research.md` and `data-model.md` |
| Testability (unit, integration, E2E) | PASS | FR-089; testing strategy in `research.md`; `quickstart.md` validation scenarios |
| Performance and scale | PASS | FR-082/083; indexed schema, caching, streamed exports |
| i18n and admin UX | PASS | FR-072/086; English source strings with shipped `hr_HR` translation |
| Lifecycle safety (deactivation/uninstall) | PASS | FR-077; uninstall policy in `research.md` |

No violations; Complexity Tracking is intentionally empty.

**Post-design re-check (2026-09-21, after Phase 1)**: All gates remain PASS. `data-model.md` implements append-only history with correction linkage and indexed lookups; `contracts/` fixes the CSV/XML/filename/REST/CLI surfaces and keeps the public endpoint price-only; the WooCommerce adapter and standalone catalog satisfy the optional-dependency gate; `quickstart.md` covers unit, integration and E2E validation. No new violations introduced by the design.

## Project Structure

### Documentation (this feature)

```text
specs/001-sidrena-cijena-compliance/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
│   ├── rest-api.md
│   ├── cli.md
│   ├── csv-format.md
│   ├── xml-format.md
│   ├── filename.md
│   └── wordpress-integration.md
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

The plugin root is the repository root (`sidrena-cijena/`).

```text
sidrena-cijena/
├── sidrena-cijena.php          # Plugin bootstrap: headers, guards, autoloader, activation hooks
├── uninstall.php               # Explicit, opt-in data deletion only
├── readme.txt                  # WordPress.org-style readme
├── README.md                   # Project overview (per brief)
├── composer.json               # Dev-only tooling (PHPUnit, WPCS, PHPStan, PHPCompatibilityWP)
├── package.json                # Dev-only tooling (wp-env, Playwright, lint scripts)
├── .wp-env.json                # Development environment (WordPress + WooCommerce + plugin mount)
├── .wp-env.test.json           # Test environment config (separate containers/database)
├── phpunit.xml.dist            # PHPUnit configuration (unit + integration suites)
├── playwright.config.js        # Playwright E2E configuration
├── phpcs.xml.dist              # WordPress Coding Standards + PHPCompatibilityWP
├── phpstan.neon.dist           # Static analysis configuration
├── src/
│   ├── Plugin.php              # Service container / bootstrap wiring
│   ├── Admin/                  # Screens, menus, Settings API, notices, list tables, assets
│   ├── Api/                    # REST controllers, public endpoint, pagination, caching
│   ├── Catalog/                # Catalog abstraction, standalone CPT catalog provider
│   ├── Cli/                    # WP-CLI command classes
│   ├── Compliance/             # Validator, findings, rule configuration, reference dates
│   ├── Cron/                   # Scheduler abstraction, daily/service jobs, cleanup
│   ├── Database/               # Schema, migrations, repositories, query builders
│   ├── Export/                 # Price-list builders, CSV/XML writers, filename generator, publisher
│   ├── Import/                 # CSV import, preview/dry-run/rollback, migration detection
│   ├── Logging/                # Structured logger and log repository
│   ├── Pricing/                # Anchor service, price history service, 30-day lowest-price engine
│   ├── Support/                # Autoloader, helpers, i18n, formatting, sanitization utilities
│   └── WooCommerce/            # Adapter: product source, price capture hooks, display integration
├── assets/
│   ├── css/                    # Frontend and admin styles
│   ├── js/                     # Admin and frontend scripts
│   └── images/
├── templates/                  # Overridable display templates (anchor price, 30-day price)
├── languages/                  # sidrena-cijena.pot, hr_HR.po/.mo, en_US.po
├── tests/
│   ├── unit/                   # Pure domain logic tests (no WordPress bootstrap)
│   ├── integration/            # WordPress/WooCommerce integration tests
│   ├── e2e/                    # Playwright specs and helpers
│   └── fixtures/               # CSV/XML examples, import fixtures, test-product definitions
└── docs/
    ├── development.md
    ├── legal-rules.md
    ├── price-history.md
    ├── price-list-format.md
    ├── woocommerce.md
    ├── testing.md
    └── troubleshooting.md
```

**Structure Decision**: Single WordPress plugin at the repository root, matching the structure suggested in the originating brief. The compliance core (`Pricing`, `Compliance`, `Database`, `Export`, `Import`) has no WooCommerce dependency; `src/WooCommerce/` contains the adapter only, and `src/Catalog/` provides the standalone provider used when WooCommerce is absent. Admin, API, CLI and Cron layers depend on the core through interfaces.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

No violations. No entries required.
