# WP Performance Doctor — Security

## Security and data collection principles

- Never execute expensive diagnostics on every frontend request.
- Diagnostics run manually or through scheduled/background jobs (future).
- Never collect passwords, API secrets, payment information, or unnecessary personal data.
- Sanitize input and escape output.
- Validate capabilities and nonces for administrative actions.
- Use prepared database queries.
- Avoid exposing server paths or sensitive configuration values in reports.
- Do not automatically modify `.htaccess`, `wp-config.php`, PHP settings, or database tables.
- Avoid external network requests during plugin initialization.
- Do not install or activate caching mechanisms automatically.
- Uninstall behavior is conservative; never delete user data without an explicit documented setting.

## Capabilities

All administrative pages require the `manage_options` capability.

## Nonces

Nonce creation and verification are centralized in `WPPerformanceDoctor\Security\NonceManager`.
