# Reference

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

Authenticated routes use the WordPress cookie plus `X-WP-Nonce`, or application passwords.

Front-end links:
- `?task_table_confirm_subscription=1&email=…&token=…` confirms a double opt-in.
- `?task_table_subscription=manage|unsubscribe&sid=…&sig=…` manages or cancels a subscription (HMAC-signed).

## Filters

| Filter | Default | Use |
|---|---|---|
| `task_table_column_labels` | English labels, translated | Rename the table, email and export columns, e.g. `name_hochschule` → "Organisation" |
| `task_table_rate_limit` | signup 5 / 10 min per IP; subscribe 10 / h per IP and 3 mails / day per address | `($limit, $bucket)` → new limit; `0` disables |
| `task_table_client_ip` | `REMOTE_ADDR` | Return the real client IP behind a trusted reverse proxy |
| `task_table_confirmation_token_ttl` | 7 days | Lifetime of double opt-in links, in seconds |

Email subjects and bodies are editable under **Task Table → Email Settings**. They support
the placeholders `{site_name}`, `{event_title}`, `{event_details}`, `{event_id}`, `{user_name}`,
`{user_email}`, `{confirmation_link}` and `{manage_link}`.

## Field names

The plugin was first built for a German site, so the event columns and API fields kept their
German names. Renaming them would mean a schema migration on live data and new keys for the
`task_table_column_labels` filter, so they stay. The labels users see are English and
translated.

| Column (REST `PUT`, export, filter key) | Editor/`POST` field | Label |
|---|---|---|
| `was_angefragt` | `wasAngefragt` | What is requested? |
| `kontext` | `kontext` | Context |
| `name_hochschule` | `nameHochschule` | University name |
| `tn_anzahl` | `tnAnzahl` | Participants |
| `zeitpunkt` | `zeitpunkt` | Planned date |
| `auftragdetails` | `auftragdetails` | Request details |
| `wer_macht` | `werMacht` | Who's doing it? / Sign up |

## Data and uninstall

Activation creates five tables (`*_task_table_events`, `_signups`, `_subscribers`,
`_subscriber_categories`, `_categories`). It also grants administrators `manage_task_table`.

Deleting the plugin from the Plugins screen runs `uninstall.php`. It drops the tables, which
contain all signups and subscriber addresses, and removes the options and capabilities.

## Translations

All source strings are English. German is in `languages/task-table-de_DE.po` and
`languages/task-table-de_DE_formal.po`. After editing them, rebuild the `.mo` files and the JSON
files the block scripts load:

```bash
python3 dev/make-translations.py
```

## Project layout

```
task-table.php               bootstrap, block registration, column labels, text domain
uninstall.php                removes tables, options and capabilities
src/api.php                  REST routes, signup/subscribe handling, export
src/subscriptions.php        signed manage/unsubscribe links (RFC 8058)
src/email-notifications.php  templates and sending
src/admin/                   wp-admin screens: loader.php (menu, assets), one file per screen
admin/                       admin screen stylesheet and script
src/database.php             schema, migrations, categories
src/task-table/              table block (editor, render, front-end script view.js)
src/templates/               signup dialog markup
src/new-event-signup/        subscription block
languages/                   translations
dev/                         local stack, tests, screenshots
docs/                        reference, design notes, screenshots, Playground blueprint
```
