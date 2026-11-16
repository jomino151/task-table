# Design notes

Why some parts of the plugin work the way they do, and where to find them in the code. The
public endpoints are listed in [reference.md](reference.md).

## Double opt-in for subscriptions

Anyone can type any address into the subscribe form, so an address only receives new-event
emails after its owner has clicked a confirmation link.

- `task_table_add_subscriber()` in `src/api.php` stores the address with `confirmed = 0` and a
  random 32-character token (`wp_generate_password( 32, false, false )`), then mails the link
  built by `task_table_send_subscription_confirmation_email()` in `src/email-notifications.php`.
- The link goes to the front end (`?task_table_confirm_subscription=1&token=…&email=…`,
  handled in `task-table.php`). The REST route `GET /task-table/v1/subscribers/confirm` does the
  same. Both call `task_table_confirm_subscription_token()` in `src/api.php`.
- The token is compared with `hash_equals()`. It expires after 7 days, counted from the last time
  a link was sent (`updated_at`), and the `task_table_confirmation_token_ttl` filter changes the
  lifetime. On success the token is set to `NULL`, so a link works once.
- New-event emails only go to rows with `confirmed = 1` (the recipient queries in
  `src/email-notifications.php`).

## Signed manage and unsubscribe links

Every new-event email contains a personal link to change categories or unsubscribe. Subscribers
have no WordPress account, and a nonce would expire and break links in old emails. The link is
authenticated by an HMAC instead (`src/subscriptions.php`):

- `task_table_subscription_signature()` computes
  `hash_hmac( 'sha256', 'task-table-manage|<id>|<lowercased email>', wp_salt( 'auth' ) )`.
  The link carries the subscriber ID (`sid`) and the signature (`sig`), not the address.
- `task_table_get_subscriber_from_signed_request()` loads the row by ID and compares the
  signature with `hash_equals()`. A wrong signature, or a valid one used with another ID, gets a
  404. The integration tests check both cases.
- Because the address is part of the signed message, a link stops working once the row is
  deleted. Changing the site's auth salt invalidates all links.
- Changes only happen on POST. A GET shows a form, so mail scanners that prefetch links cannot
  unsubscribe anyone. The exception is the RFC 8058 one-click request
  (`List-Unsubscribe-Post: List-Unsubscribe=One-Click`), which mail clients send as a POST without
  user interaction. The headers come from `task_table_get_list_unsubscribe_headers()`.
- Unsubscribing deletes the row and its category assignments
  (`task_table_delete_subscriber()`); nothing is kept as "unsubscribed".
- The manage page sends `Cache-Control: no-cache` headers, `X-Robots-Tag: noindex`,
  `Referrer-Policy: no-referrer` and defines `DONOTCACHEPAGE`, so the signed URL does not end up
  in caches, search indexes or `Referer` headers.

## Uniform responses against subscriber enumeration

The subscribe and confirm endpoints are public. If their answers differed for known and unknown
addresses, anyone could test whether a given address is on the list.

- `POST /task-table/v1/subscribers` returns the same `200` body for a new address, an
  unconfirmed one, a confirmed one, and an address that hit its rate limit
  (`$generic_response` in `task_table_add_subscriber()`). Only a syntactically invalid address
  gets a `400`, which reveals nothing about the list.
- For an address that is already confirmed, the endpoint changes nothing: it does not touch
  the categories, and it mails that address its manage link instead. Before 1.2.0, an
  unauthenticated request could overwrite a confirmed subscriber's categories.
- `GET /task-table/v1/subscribers/confirm` returns the same `400` message for an unknown
  address, a wrong token, an expired token and a link that was already used.
- `dev/tests/run.sh` compares the response bodies byte for byte ("known and new address get
  identical responses", "unknown vs known email indistinguishable on confirm").

## Rate limiting

`task_table_is_rate_limited()` in `src/api.php` is a fixed-window counter stored in a
transient (`tt_rl_` + MD5 of bucket and key). The limits:

| Bucket | Key | Limit | Response when exceeded |
|---|---|---|---|
| `signup` | client IP | 5 per 10 minutes | `429` |
| `subscribe_ip` | client IP | 10 per hour | `429` |
| `subscribe_email` | email address | 3 per day | the normal `200` response, no email sent |

The per-address limit exists so the form cannot be used to flood someone's inbox with
confirmation emails. It answers with the normal response so it does not reveal anything either.

The client IP is `REMOTE_ADDR`. The plugin does not read `X-Forwarded-For`, because any client
can set that header. Sites behind a trusted reverse proxy can return the real IP through the
`task_table_client_ip` filter. `task_table_rate_limit` changes or disables (`0`) each limit.

Limits are per site and best effort: transients can be evicted, and the counter is not atomic,
so a burst of parallel requests can get a few more through than the limit.

## Upgrade test from the previous release

The plugin runs on a production site, and a release must not break existing subscribers,
pending signups or links in emails that were already sent. `dev/tests/upgrade.sh` checks this:

1. It picks the newest `v*` tag in `HEAD`'s history that is not on `HEAD` itself (so on a
   release tag it tests the upgrade from the release before), builds that version from
   `git archive` and starts it in its own podman stack on PHP 8.4, the production version.
2. With the old code, it creates data the way a production site has it: confirmed and pending
   subscribers with categories, a pending signup, email templates saved through the settings
   screen (older versions stored them with backslashes before quotes) and a queued new-event
   notification.
3. It swaps in the working-tree code with the database kept and checks that the schema
   version was raised, row counts are unchanged, templates were cleaned up, a confirmation
   link mailed before the update still works, the pending signup can be approved and its email
   has no stray backslashes, the queued notification goes out with a manage link, and existing
   subscribers can use their links. It also repeats the export authorization checks and fails
   on any PHP warning from the plugin.

CI runs it on every pull request (`.github/workflows/ci.yml` checks out the full history and
tags for this). The release workflow (`.github/workflows/release.yml`) runs the same CI on the
tag before it publishes the zip.

## Post-mortem: missing authorization on the export endpoint (CWE-862)

| | |
|---|---|
| Affected | `<= 1.1.0` |
| Fixed | `1.2.0`, commit `f6c10e3` |
| Impact | Any logged-in user could download all events |

**Impact.** `GET /wp-json/task-table/v1/events/export` returned every event, archived ones
included, with the internal fields and the names of approved volunteers. Any logged-in account
could call it, including the Subscriber role that WordPress gives out when registration is open.
The export did not contain signup or subscriber email addresses, and the endpoint could not
change data.

**Root cause.** The route's `permission_callback` was `is_user_logged_in()`, and the callback
`task_table_export_events_excel()` repeated that check. Being logged in is not a permission. All
other `/events` routes, and the export button on the admin screen (`admin-post.php`), check the
`manage_task_table` capability; the REST export did not.

**Detection.** Found in the plugin's own code, not reported from outside. Commit `2b38526` added a
local stack and `dev/repro-export-authz.sh`, which logs in as a Subscriber, calls the endpoint and
fails while it answers `200`. The fix followed in the next commit.

**Fix.** `f6c10e3` requires `current_user_can( 'manage_task_table' )` in the `permission_callback`
and again in the callback.

**Regression tests.** `dev/tests/run.sh` (section "Export authorization (CWE-862)") expects `401`
for anonymous requests, `403` for Subscriber and Editor, and `200` with a valid file for an
administrator. `dev/tests/upgrade.sh` repeats the `403` checks after upgrading from the previous
release. Both run in CI on every pull request.
