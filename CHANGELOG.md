# RedirectMate Changelog

## 2.1.1 - 2026-09-01
### Fixed
- Fixed an issue where query string passthrough would keep the page parameter.

## 2.1.0 - 2026-07-04
### Fixed
- Fixed a SQL injection vulnerability where the requested URL was  interpolated into the redirect lookup's ORDER BY clause.
- Restricted controller actions to CP requests from users with  access to the RedirectMate utility.
- Escaped spreadsheet formulas in CSV exports to prevent CSV  formula injection.
- Fixed stored XSS in the control panel by sanitizing link URLs  and escaping untrusted 404 data.
- Hardened the URL status check against SSRF by allowing only  http(s) and limiting redirects.
- Validated redirect status code, match type and destination URL  on save.

## 2.0.2 - 2026-07-02
### Fixed
- Fixed an issue where trailing whitespace could result in database index collisions.

## 2.0.1 - 2026-01-15
### Fixed
- Fixed a JS error that would occur on Craft 5.8.22+

## 2.0.0 - 2025-03-10
### Fixed
- Fixed a bug where modals did not become visible in Craft 5.6+.
- Fixed an issue where it wasn't possible to change the site when editing an existing redirect
- Fixed an issue where a log item's "handled" status would not persist after checking it

## 2.0.0-beta.1 - 2024-02-24
### Added
- Added support for Craft 5
