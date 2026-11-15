# Task Table

[![CI](https://github.com/jomino151/task-table/actions/workflows/ci.yml/badge.svg)](https://github.com/jomino151/task-table/actions/workflows/ci.yml)
[![Latest version](https://img.shields.io/github/v/tag/jomino151/task-table?label=version&sort=semver)](CHANGELOG.md)
![Requires WordPress 6.7+](https://img.shields.io/badge/WordPress-6.7%2B-21759b)
![Requires PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](LICENSE)

> Publish open tasks, let volunteers sign up, and email subscribers when something new appears.

![Task table on the front end](docs/screenshots/frontend-table.png)

## Overview

Task Table is for organisations that hand out work to volunteers. Admins list open requests
in a table block, visitors sign up for them, and moderators approve or deny each signup.
It was built for a German non-profit that matches university requests (workshops, talks,
panels) with volunteer speakers, and it runs in production there.

### Features

- **Task table block**: rows are edited in the block editor and grouped by category. Archived rows are hidden.
- **Signups with moderation**: a signup modal on the front end and approve/deny in wp-admin. Moderators get an email for each signup, and volunteers get one with the decision.
- **Subscriptions**: double opt-in per category. Every notification has a signed one-click unsubscribe link.
- **Admin screens**: events, signups, subscribers, categories, editable email templates and Excel export.
- **Translatable**: German UI shipped, every string uses the `task-table` text domain.

| Signup modal | Events admin |
|---|---|
| ![Signup modal](docs/screenshots/frontend-signup-modal.png) | ![Events admin](docs/screenshots/admin-events.png) |
| **Signup moderation** | **Email templates** |
| ![Signups admin](docs/screenshots/admin-signups.png) | ![Email settings](docs/screenshots/admin-email-settings.png) |

## Requirements

- WordPress 6.7+
- PHP 7.4+, with `ZipArchive` for `.xlsx` export (it falls back to `.xls` without it)

## Installation

1. Get the plugin zip from the Releases page, or build it with `npm ci && npm run build && npm run plugin-zip`.
2. Upload it under **Plugins → Add New → Upload Plugin** and activate it.
3. Create categories under **Task Table → Categories**, then add the **Task-Tabelle** and
   **Neue Veranstaltungs-Benachrichtigung** blocks to a page.

## Frequently Asked Questions

### Can I rename the columns?

Yes. The labels come from the `task_table_column_labels` filter:

```php
add_filter( 'task_table_column_labels', function ( $labels ) {
	$labels['name_hochschule'] = 'Organisation';
	return $labels;
} );
```

The other filters and the REST API are listed in [docs/reference.md](docs/reference.md).

### What personal data does it store?

- **Signups:** name, email and an optional note.
- **Subscribers:** email address and chosen categories.

Unsubscribing deletes the subscriber's address. Deleting the plugin removes all of its tables
and options.

### Where do I report security bugs?

Privately, through GitHub's vulnerability reporting. See [SECURITY.md](SECURITY.md).

Versions `<= 1.1.0` let any logged-in user export all events (CWE-862). This is fixed in 1.2.0;
see the [changelog](CHANGELOG.md).

## Support Level

**Best effort:** maintained in spare time. Bug reports and security issues get a response, and
new features are unlikely.

## Development

The local stack (WordPress, MariaDB, Mailpit) runs in podman:

```bash
npm ci && npm run build
dev/up.sh              # http://localhost:8888 (admin/admin), mail at :8025
dev/tests/run.sh       # integration tests
dev/tests/upgrade.sh   # upgrade from v1.1.0 with production-like data
composer install && vendor/bin/phpcs   # WordPress coding standards
```

CI runs PHP 7.4/8.4 lint, PHPCS, a gitleaks history scan and both test suites on every pull request.

## Changelog

All notable changes are listed in [CHANGELOG.md](CHANGELOG.md).

## Contributing

Issues and pull requests are welcome. Please run `dev/tests/run.sh` and `vendor/bin/phpcs`
before opening a PR.

## License

[GPL-2.0-or-later](LICENSE)
