# Task Table

A WordPress plugin for publishing open tasks or event requests, letting volunteers
sign up for them, and notifying subscribers when new entries appear. It was built
for a non-profit that lists requests (workshops, talks, panels) and has volunteers
claim them. Admins moderate the signups and keep an archive.

The interface ships in German (source strings) with English admin strings translated
to German via `languages/`. Everything uses the `task-table` text domain, so it can
be translated to other languages too.

![Task table on the front end](docs/screenshots/frontend-table.png)

## Features

- **Task table block** (`task-table/task-table`): edit rows in the block editor.
  They're stored in custom tables and rendered server-side, filtered by category.
  Archived rows are hidden.
- **Signup modal**: visitors sign up for a row with name, email and an optional
  note. Moderators get an email. If someone is already confirmed, the signup
  counts as a substitute.
- **Moderation**: approve or deny signups in wp-admin. The person gets an email,
  and the approved name appears in the table.
- **Subscriptions block** (`task-table/new-event-signup`): visitors subscribe to
  new entries per category, with double opt-in. Every notification carries a
  signed link to change categories or unsubscribe (with RFC 8058 one-click
  `List-Unsubscribe`).
- **Admin screens**: events (search, category filter, archive), signups,
  subscribers, categories, editable email templates, and per-category
  moderator recipients.
- **Export**: events as `.xlsx` or SpreadsheetML `.xls`, filtered by status,
  category and keyword.

| Signup modal | Admin dashboard |
|---|---|
| ![Signup modal](docs/screenshots/frontend-signup-modal.png) | ![Events admin](docs/screenshots/admin-events.png) |
| **Signup moderation** | **Email templates** |
| ![Signups admin](docs/screenshots/admin-signups.png) | ![Email settings](docs/screenshots/admin-email-settings.png) |

## Requirements

- WordPress 6.7+
- PHP 7.4+ (`ZipArchive` for `.xlsx` export; otherwise it falls back to `.xls`)
- Node 20+ only for building from source

## Installation

From a release zip: **Plugins → Add New → Upload Plugin**, then activate.

From source:

```bash
git clone https://github.com/jomino151/task-table.git
cd task-table
npm ci
npm run build          # compiles blocks and copies PHP into build/
npm run plugin-zip     # optional: task-table.zip for upload
```

The plugin loads its PHP from `build/`, so it won't run from a checkout that
hasn't been built.

Activation creates the database tables and grants administrators the
`manage_task_table` capability. Then add the **Task Table** and **Subscribe to new
events** blocks to a page and create categories under **Task Table → Categories**.

Deleting the plugin from the Plugins screen runs `uninstall.php`, which drops the
plugin's tables (including all signups and subscriber addresses) and options.

## REST API

| Method & route | Access | Purpose |
|---|---|---|
| `GET /wp-json/task-table/v1/categories` | public | List categories |
| `POST /wp-json/event-signups/v1/submit` | public, rate limited | Sign up for an open event: `event_id`, `user_name`, `user_email`, `free_text` |
| `POST /wp-json/task-table/v1/subscribers` | public, rate limited | Start double opt-in: `email`, `categories[]` |
| `GET /wp-json/task-table/v1/subscribers/confirm` | public | Confirm a subscription: `email`, `token` |
| `GET /wp-json/task-table/v1/events` | `manage_task_table` | List events (`archived`, `category_id`, `category`) |
| `POST /wp-json/task-table/v1/events` | `manage_task_table` | Create an event |
| `PUT /wp-json/task-table/v1/events/{id}` | `manage_task_table` | Update an event |
| `DELETE /wp-json/task-table/v1/events/{id}` | `manage_task_table` | Delete an event and its signups |
| `POST /wp-json/task-table/v1/events/{id}/archive` | `manage_task_table` | Archive or restore (`archived: 0/1`) |
| `GET /wp-json/task-table/v1/events/export` | `manage_task_table` | Download events (`archived=0/1/all`, `format=xlsx/xml`, `tt_categories[]`, `tt_search`) |

Authenticated routes use the standard WordPress cookie + `X-WP-Nonce` (or
application passwords). Public front-end links: `?task_table_confirm_subscription=1`
(double opt-in) and `?task_table_subscription=manage|unsubscribe&sid=…&sig=…`
(manage or cancel a subscription).

## Customizing

| Filter | Default | Use |
|---|---|---|
| `task_table_column_labels` | German labels | Rename the table/email/export columns, e.g. `name_hochschule` → "Organisation" |
| `task_table_rate_limit` | signup 5 / 10 min per IP; subscribe 10 / h per IP and 3 mails / day per address | `($limit, $bucket)` → new limit, `0` disables |
| `task_table_client_ip` | `REMOTE_ADDR` | Return the real client IP behind a trusted reverse proxy |
| `task_table_confirmation_token_ttl` | 7 days | Lifetime of double opt-in links in seconds |

Email subjects and bodies are editable under **Task Table → E-Mail-Einstellungen**
and support placeholders such as `{event_title}`, `{user_name}`,
`{confirmation_link}` and `{manage_link}`.

## Security

- All write and admin routes require `manage_task_table`, and admin actions verify nonces.
- Public endpoints validate input and are rate limited. The subscribe and
  confirm endpoints give identical answers for known and unknown addresses, so
  they can't be used to check who is subscribed.
- Subscription management links are HMAC-signed with the site's auth salt, and
  changes only happen on POST, so mail scanners that prefetch links can't
  unsubscribe anyone.
- Event text is filtered with a narrow `wp_kses` allowlist on save and on output.
- Exports write every cell as a string, so spreadsheet formulas aren't evaluated.

**Advisory:** versions `<= 1.1.0` let any logged-in user, including the
Subscriber role, download all events through the export endpoint (CWE-862, fixed
in 1.2.0). See [CHANGELOG.md](CHANGELOG.md) and the repository's security advisories.

To report a vulnerability, see [SECURITY.md](SECURITY.md).

## Development

A self-contained stack runs on podman or docker:

```bash
npm ci && npm run build
dev/up.sh                 # WordPress :8888, Mailpit :8025, seeded fictional data
npm run start             # optional: rebuild on change (the repo is mounted into WordPress)
dev/wp.sh plugin list     # wp-cli inside the stack
dev/tests/run.sh          # integration tests (authorization, validation, rate limits, opt-in, unsubscribe)
dev/tests/upgrade.sh      # installs the previous release with production-like data, upgrades, re-checks
node dev/screenshots.mjs  # regenerate docs/screenshots (needs Chrome/Chromium)
dev/down.sh               # remove containers and volumes
```

Logins: `admin/admin`, `editor/editor`, `subscriber/subscriber`. All mail is
caught by Mailpit at http://localhost:8025. `dev/repro-export-authz.sh` is the
standalone reproduction for the 1.1.0 export vulnerability.

### Translations

German translations live in `languages/task-table-de_DE.po`. After editing a `.po`
file, compile it with `msgfmt -o languages/task-table-de_DE.mo languages/task-table-de_DE.po`
(or `wp i18n make-mo languages`).

### Project layout

```
task-table.php            bootstrap, block registration, column labels, text domain
uninstall.php             removes tables, options and capabilities
src/api.php               REST routes, signup/subscribe handling, export
src/subscriptions.php     signed manage/unsubscribe links (RFC 8058)
src/email-notifications.php  templates and sending
src/admin-pages.php       wp-admin screens
src/database.php          schema, migrations, categories
src/task-table/           table block (editor, render, signup modal script)
src/new-event-signup/     subscription block
languages/                translations
dev/                      local stack, tests, screenshots
```

## License

GPL-2.0-or-later, see [LICENSE](LICENSE).
