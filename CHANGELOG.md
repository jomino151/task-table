# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [0.1.1] - unreleased

### Security
- **Missing authorization on the events export endpoint (CWE-862).**
  `GET /wp-json/task-table/v1/events/export` was available to every logged-in
  user, including the Subscriber role. Such users could download all events,
  archived ones included, together with internal fields and the names of
  approved volunteers. The endpoint now requires the `manage_task_table`
  capability. Affects versions `<= 0.1.0`.
- Subscribe endpoint: identical responses for new and existing addresses (no
  subscriber enumeration). Unauthenticated requests can no longer change a
  confirmed subscriber's categories. Rate limited per IP and per address.
- Double opt-in confirmation links expire after 7 days and return a uniform
  error for unknown addresses, wrong tokens and links that were already used.
- Signup endpoint: rate limited per IP, validates name and email, and rejects
  signups for archived events.
- Public error responses no longer include database error messages.

### Fixed
- The REST export returned the spreadsheet JSON-encoded, and the default XLSX
  format crashed with a fatal error (`wp_tempnam()` is not loaded on REST
  requests). It now returns a valid file.
- The admin script was enqueued from a non-existent path, so it never loaded
  and every admin page logged a PHP warning.
- The signup modal now uses `rest_url()` instead of a hard-coded `/wp-json`
  path, and it shows the error messages the server returns.

### Added
- Local development stack (`dev/up.sh`: WordPress, MariaDB and Mailpit through
  podman or docker) and an integration test suite (`dev/tests/run.sh`).
- Filters: `task_table_rate_limit`, `task_table_client_ip`,
  `task_table_confirmation_token_ttl`.

## [0.1.0]

- Initial version.
