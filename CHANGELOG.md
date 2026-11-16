# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.3.0] - 2026-11-16

### Added
- On screens narrower than 768px, each task row is shown as a card of
  label and value pairs, with the "Sign up" button always visible. Wide
  screens keep the table.
- The subscribe form's email field has a label for screen readers.

### Changed
- The signup form is a native dialog: it can be used with the keyboard
  alone, keeps focus inside while open, closes with Escape or a click on
  the backdrop and returns focus to the button that opened it. Screen
  readers announce its title and the result of a signup.
- Buttons in the table and the dialog are at least 44px tall on touch
  screens.
- The default category "General" is shown translated (front end, emails,
  subscription page) as long as it has not been renamed.
- The admin screens load their styles and scripts from `admin/admin.css`
  and `admin/admin.js`, only on the plugin's own screens, instead of
  printing them inline on every request. They look the same as before.
- Adding a subscriber in wp-admin shows the new entry and the notice
  directly instead of reloading the page.
- English is now the source language everywhere. The front end, the email
  templates, the email settings screen and the block editor used German
  source strings; they are now English and the German moved into the
  `de_DE` and `de_DE_formal` translations. German sites see the same text
  as before.
- Default email templates are only affected on English sites. Templates that
  were saved in the email settings are stored as text and stay unchanged.

### Fixed
- Block editor and subscribe form scripts now load the plugin's bundled
  German translation (block scripts only looked in `wp-content/languages`).
- The "Sign up" button no longer overflows its table column.
- The front-end script no longer writes a message to the browser console
  on every page.
- The "choose at least one category" alert on the Email Settings screen shows
  quotes instead of `&quot;`.
- Exports (xlsx and XML) no longer become unreadable when event text contains
  control characters, e.g. a volunteer name with a pasted soft line break that
  was copied into the event on approval.

## [1.2.0] - 2026-11-15

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

[Unreleased]: https://github.com/jomino151/task-table/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/jomino151/task-table/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/jomino151/task-table/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/jomino151/task-table/releases/tag/v1.1.0
