# RedirectMate Changelog

## Unreleased
### Improved
- RedirectMate is now smarter about how conflicting redirects are handled when saving a new redirect, or editing an existing one.

## 1.0.3 - 2024-03-03
### Fixed
- Fixed an issue where it wasn't possible to change the site when editing an existing redirect  
- Fixed an issue where a log item's "handled" status would not persist after checking it  

## 1.0.2 - 2024-01-10
### Fixed
- Fixes a PDO exception for 404 requests where the source URL was empty  

## 1.0.1 - 2023-09-06
### Fixed  
- Fixes issues on Craft 4.5+ where query params would interfere w/ RedirectMate's queries

## 1.0.0 - 2023-06-02
### Added
- Initial release
