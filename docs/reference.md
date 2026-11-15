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
| `task_table_column_labels` | German labels | Rename the table, email and export columns, e.g. `name_hochschule` → "Organisation" |
| `task_table_rate_limit` | signup 5 / 10 min per IP; subscribe 10 / h per IP and 3 mails / day per address | `($limit, $bucket)` → new limit; `0` disables |
| `task_table_client_ip` | `REMOTE_ADDR` | Return the real client IP behind a trusted reverse proxy |
| `task_table_confirmation_token_ttl` | 7 days | Lifetime of double opt-in links, in seconds |

Email subjects and bodies are editable under **Task Table → E-Mail-Einstellungen**. They support
the placeholders `{site_name}`, `{event_title}`, `{event_details}`, `{event_id}`, `{user_name}`,
`{user_email}`, `{confirmation_link}` and `{manage_link}`.

## Data and uninstall

Activation creates five tables (`*_task_table_events`, `_signups`, `_subscribers`,
`_subscriber_categories`, `_categories`). It also grants administrators `manage_task_table`.

Deleting the plugin from the Plugins screen runs `uninstall.php`. It drops the tables, which
contain all signups and subscriber addresses, and removes the options and capabilities.

## Translations

The front end uses German source strings. The admin uses English source strings, translated
in `languages/task-table-de_DE.po`. After editing a `.po` file, compile it:

```bash
msgfmt -o languages/task-table-de_DE.mo languages/task-table-de_DE.po
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
src/task-table/              table block (editor, render, signup modal)
src/new-event-signup/        subscription block
languages/                   translations
dev/                         local stack, tests, screenshots
```
