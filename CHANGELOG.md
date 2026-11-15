# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [1.2.0] - unreleased

### Security
- **Missing authorization on the events export endpoint (CWE-862).**
  `GET /wp-json/task-table/v1/events/export` was available to every logged-in
  user, including the Subscriber role. Such users could download all events,
  archived ones included, together with internal fields and the names of
  approved volunteers. The endpoint now requires the `manage_task_table`
  capability. Affects versions `<= 1.1.0`.
- Subscribe endpoint: identical responses for new and existing addresses (no
  subscriber enumeration). Unauthenticated requests can no longer change a
  confirmed subscriber's categories. Rate limited per IP and per address.
- Double opt-in confirmation links expire after 7 days and return a uniform
  error for unknown addresses, wrong tokens and links that were already used.
- Signup endpoint: rate limited per IP, validates name and email, and rejects
  signups for archived events.
- Public error responses no longer include database error messages.

### Added (privacy)
- Self-service subscription management: every new-event email contains an
  HMAC-signed link to change categories or unsubscribe (which deletes the
  address), plus RFC 8058 one-click `List-Unsubscribe` headers. Subscribing
  again with an already confirmed address emails that address its link.
- `uninstall.php` removes the plugin's tables (including all personal data),
  options, rate-limit transients and capabilities.

### Changed
- German translations ship as `languages/task-table-de_DE.po/.mo` loaded with
  `load_plugin_textdomain()`. They replace a runtime gettext filter that forced
  German on every site. Front-end, modal, block and email strings are
  translatable, and column labels can be changed with `task_table_column_labels`.
- The admin menu is called "Aufgabentabelle" in German instead of "Multi-Tabelle".
  Notification emails now use the column labels ("Name der Hochschule:"
  instead of "Hochschule:").
- The table block supports wide and full alignment.

### Fixed
- Email templates already stored with backslashes before quotes are cleaned up
  once on update (database version 1.7).
- Email template settings were saved without `wp_unslash()`, so quotes gained
  backslashes.
- The signup modal's backdrop only covered the content column in block themes.
- The signups admin table clipped the Actions column. It now scrolls.
- The REST export returned the spreadsheet JSON-encoded, and the default XLSX
  format crashed with a fatal error (`wp_tempnam()` is not loaded on REST
  requests). It now returns a valid file.
- The admin script was enqueued from a non-existent path, so it never loaded
  and every admin page logged a PHP warning.
- The signup modal now uses `rest_url()` instead of a hard-coded `/wp-json`
  path, and it shows the error messages the server returns.

### Added
- Local development stack (`dev/up.sh`: WordPress, MariaDB and Mailpit through
  podman), an integration test suite (`dev/tests/run.sh`) and an
  upgrade test from the previous release (`dev/tests/upgrade.sh`).
- Filters: `task_table_rate_limit`, `task_table_client_ip`,
  `task_table_confirmation_token_ttl`, `task_table_column_labels`.
- English README, SECURITY.md, LICENSE and screenshots.
- GitHub Actions CI: PHP 7.4/8.4 lint, PHPCS (WordPress-Extra, PHPCompatibilityWP),
  gitleaks history scan, integration and upgrade tests, plugin zip artifact.
  Dependabot for actions, Composer and npm.

### Changed (code style)
- The code base follows the WordPress coding standards, with late escaping at
  every output.

## [1.1.0]

- Last version before this changelog. The repository header said 0.1.0 while
  deployments reported 1.1.0; it's the same code.
