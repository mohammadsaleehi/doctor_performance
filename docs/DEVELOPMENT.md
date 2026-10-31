# WP Performance Doctor — Development

## Composer installation

```bash
composer install
```

This installs development dependencies and generates `vendor/autoload.php`.

## Local WordPress testing

1. Install WordPress 6.5+ locally.
2. Copy this plugin into `wp-content/plugins/wp-performance-doctor/`.
3. Run `composer install` in the plugin directory.
4. Activate the plugin through the WordPress admin.

## Running PHPCS

```bash
composer require --dev wp-coding-standards/wpcs
vendor/bin/phpcs --standard=phpcs.xml.dist src admin wp-performance-doctor.php uninstall.php
```

## Running PHPStan

```bash
vendor/bin/phpstan analyse -c phpstan.neon.dist
```

## Running PHPUnit

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```

## Notes

- PHP_CodeSniffer, PHPStan, and PHPUnit are development-only and not required at runtime.
- The plugin loads correctly without `vendor/` present (autoloader is conditionally loaded).
