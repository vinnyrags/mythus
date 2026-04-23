# Mythus

WordPress platform framework. Installed as a must-use plugin, Mythus provides the provider pattern, dependency injection container (PHP-DI 7), and the support managers that consuming themes compose to register features, hooks, blocks, patterns, assets, ACF field groups, and REST endpoints.

Mythus is **theme-agnostic** — no Timber, Twig, or template coupling. Themes wrap Mythus with their own bridge (e.g., the IX parent theme extends `Mythus\Provider` to add Timber support).

Named after one of the playable characters in Honkai: Star Rail.

## Stack

- PHP 8.4+ with strict types
- [PHP-DI 7](https://php-di.org/) (autowiring-first)
- PHPUnit 9 + [WorDBless](https://github.com/Automattic/wordbless) for testing

## Install

Mythus installs into `wp-content/mu-plugins/mythus/` via Composer:

```json
{
  "require": {
    "vincentragosta/mythus": "dev-main"
  },
  "extra": {
    "installer-paths": {
      "wp-content/mu-plugins/{$name}/": ["type:wordpress-muplugin"]
    }
  }
}
```

A loader at `wp-content/mu-plugins/mythus-loader.php` is responsible for bootstrapping Mythus. If its vendor directory isn't present, the loader calls `wp_die()` with install instructions.

## What's Inside

```
src/
├── Provider.php              # Abstract base provider (theme-agnostic)
├── Contracts/
│   ├── Registrable.php       # Base marker interface
│   ├── Feature.php           # Toggleable capabilities (opt-out via => false)
│   ├── Hook.php              # Always-active hooks (additive only)
│   └── Routable.php          # REST-addressable classes
├── Support/
│   ├── AbstractRegistry.php
│   ├── Acf/AcfManager.php
│   ├── Asset/AssetManager.php
│   ├── Block/BlockManager.php
│   ├── Feature/FeatureManager.php
│   ├── Pattern/PatternManager.php
│   └── Rest/{RestManager,Endpoint}.php
└── Hooks/
    └── BlockStyles.php       # Abstract declarative block style registration
```

## The Provider Pattern

A provider is a self-contained domain — it owns its PHP classes, assets, blocks, config, and tests. Providers compose managers rather than inheriting them. The `Provider::setup()` method is idempotent and deferred; managers are only instantiated when needed.

```
Registrable (interface)
  ├── Feature (marker) — toggleable, $features array, opt-out via => false
  ├── Hook (marker) — always-active, $hooks array, additive only
  └── Provider (abstract base)
```

Consuming themes extend `Mythus\Provider` and add their own bridge layer. For example, the [IX parent theme](https://github.com/vinnyrags/IX) adds a `Provider` subclass that wires in Timber/Twig template resolution.

## Testing

```bash
composer install
composer test
```

Tests use **WorDBless** to load WordPress without a database, ensuring fast and isolated execution. Tests mirror the source tree: `tests/Unit/Support/Asset/AssetManagerTest.php` ↔ `src/Support/Asset/AssetManager.php`.

## Philosophy

- **Theme-agnostic** — no Timber, no Twig, no template rendering in Mythus. Themes bridge to their rendering layer.
- **Autowiring-first** — PHP-DI resolves most classes automatically. Explicit container definitions are only added when autowiring can't figure it out.
- **Composition over inheritance** — providers compose managers; managers are not in the inheritance chain.
- **Lazy initialization** — `setup()` is idempotent and deferred. Multiple calls are safe.
- **Silent failure at asset boundaries** — missing `dist/` CSS/JS files are skipped silently, so a provider with PHP logic but no compiled assets doesn't break the site.

## Context

Mythus is part of the [vincentragosta.io](https://github.com/vinnyrags/vincentragosta.io) WordPress site. It was extracted into this standalone package so it can evolve as a reusable framework across projects. The IX parent theme is the reference consumer.
