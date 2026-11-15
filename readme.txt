=== Task Table ===
Contributors:      jomino151
Tags:              volunteers, tasks, events, signup, block
Requires at least: 6.7
Tested up to:      7.1
Requires PHP:      7.4
Stable tag:        1.2.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Publish a table of open tasks or event requests, let volunteers sign up, and notify subscribers about new entries.

== Description ==

Task Table adds two blocks:

* **Task Table** shows open tasks or event requests per category. Visitors sign up for an entry in a modal, and moderators approve or deny the signup in wp-admin.
* **Subscribe to new events** is a double opt-in form. Subscribers get an email when a new entry is published in the categories they chose, and every email contains a link to change categories or unsubscribe.

Admin screens cover events (with archive and Excel export), signups, subscribers, categories and editable email templates.

All strings are English and use the `task-table` text domain. A German translation is included and is used automatically on German sites.

Source, development setup and security policy: https://github.com/jomino151/task-table

== Installation ==

1. Upload the plugin zip through Plugins → Add New → Upload Plugin, or copy the built plugin to `/wp-content/plugins/task-table`.
2. Activate it.
3. Create categories under Task Table → Categories and add the blocks to a page.

== Frequently Asked Questions ==

= Who can edit events? =

Users with the `manage_task_table` capability (administrators by default).

= How do subscribers unsubscribe? =

Every notification email contains a personal link to manage or cancel the subscription. Mail clients that support one-click unsubscribe (RFC 8058) show their own button as well.

= Can I rename the columns? =

Yes, with the `task_table_column_labels` filter.

== Changelog ==

= 1.2.0 =
* Security: the events export endpoint now requires the `manage_task_table` capability. Before, any logged-in user could download all events (CWE-862).
* Security: rate limits, input validation and no subscriber enumeration on the public endpoints. Confirmation links expire.
* New: self-service unsubscribe and category management links, with one-click List-Unsubscribe.
* New: German translation shipped as .po/.mo, translatable front-end strings, filterable column labels, uninstall cleanup.
* Fix: the REST export returns a valid file, the admin script loads, and email templates no longer gain backslashes.

= 1.1.0 =
* Initial release.
