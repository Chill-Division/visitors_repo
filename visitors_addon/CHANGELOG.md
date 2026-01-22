## 1.0.1

- Fix: Moved SQLite database to `/data` partition to ensure data persists across add-on restarts.
- Feature: Added UI warning when using the default admin password.
- Docs: Added comments to codebase warning about ephemeral directories.

## 1.0.0

- **Major Release**: Stable release with robust configuration handling.
- Fix: Solved admin password integration issues. Password is now reliably pulled from Home Assistant configuration.
- Feature: Added Admin interface for managing sign-in options (add, edit, remove) with SQLite persistence.
- Cleanup: Optimized logging for production use and removed debug artifacts.

## 0.9.5.1

- Bugfixes and improved user log-out selection

## 0.9.5

- Added admin_password configuration option in addon settings
- Improved admin interface styling to match main page:
  - Implemented Pico CSS framework for consistent theming
  - Enhanced table styling with proper borders and hover effects
  - Added Material Icons for better visual hierarchy
  - Improved responsive layout and visual separation
  - Fixed dark mode compatibility
- Updated password handling to read from addon configuration
- Improved error message styling and form layout

## 0.9.4.1

- Revert overzealous header changes

## 0.9.4

- Bugfixes (spaces in names etc)
- Security improvements
- UI improvements, especially to admin

## 0.9.3

- Fixed duplicate insertion of Terms
- Fixed panel icon

## 0.9.2

- Rollback admin page
- TODO: Fix Admin page so it works with dark-mode

## 0.9.1

- Fixed sqlite3 not needed
- Fixed icons / naming
- TODO: Accept an admin password from the add-on configuration options

## 0.9

- Initial release
- Needs the Admin Password to be user-configurable
