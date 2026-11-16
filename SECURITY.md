# Security Policy

## Supported versions

| Version  | Supported |
|----------|-----------|
| 1.3.x (latest release) | Yes |
| 1.2.x    | No, please update |
| < 1.2.0  | No, contains a known vulnerability ([CHANGELOG](CHANGELOG.md)) |

Only the latest release receives security fixes. Please update before reporting.

## Reporting a vulnerability

Please **do not open a public issue** for security problems.

Report it privately through GitHub's
[private vulnerability reporting](https://docs.github.com/en/code-security/security-advisories/guidance-on-reporting-and-writing-information-about-vulnerabilities/privately-reporting-a-security-vulnerability):
open the repository's **Security** tab and choose **Report a vulnerability**.

Please include:

- the affected version and endpoint, file or screen
- the WordPress role you used (anonymous, Subscriber, Editor, …)
- steps to reproduce, ideally against the local stack in `dev/` (see the README)
- the impact as you understand it

## What to expect

- **Acknowledgement** within 5 working days.
- **Assessment** (confirmed or not, severity) within 14 days.
- **Fix** for confirmed issues as soon as practical, usually within 30 days for
  high or critical severity. A GitHub Security Advisory is published together
  with the fixed release, crediting the reporter unless they ask not to be named.

This is a small project maintained in spare time, so there is no bug bounty.

## Scope

In scope: the plugin code in this repository (PHP, block JavaScript, REST
endpoints, emails it sends).

Out of scope: WordPress core, themes and other plugins, findings that need an
administrator account (administrators can already run arbitrary code in
WordPress), and missing rate limits on endpoints other than the public ones
listed in the README.

## Past advisories

| ID | Summary | Affected | Fixed |
|----|---------|----------|-------|
| [GHSA-g7rx-773p-f9m6](https://github.com/jomino151/task-table/security/advisories/GHSA-g7rx-773p-f9m6) | Missing authorization on `GET /task-table/v1/events/export` (CWE-862): any logged-in user could export all events | `<= 1.1.0` | `1.2.0` |
