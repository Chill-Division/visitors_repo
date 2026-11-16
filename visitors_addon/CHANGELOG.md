## 1.1.0

A fresh look to match the GRACe Portal, in light or dark, plus some important fixes for the admin page. Update when you can.

### What's new

- **A new look, in light or dark.** The sign-in and admin pages now look like the GRACe Portal, in Chill Division's colours and type. Tap the moon button in the menu bar for the dark theme, and the sun to switch back. Each device remembers its choice.
- **Signing in and out on one screen.** On a tablet the sign-in form and sign-out search sit side by side. On a phone, a "Leaving? Sign out" button jumps straight to signing out.
- **No more pop-ups to tap away.** "You're signed in" and "You're signed out" now show as a short message at the bottom of the screen.
- **Works with no internet.** The pages no longer load anything from the internet. Before, a Home Assistant box that was offline showed an unstyled page that couldn't save sign-ins.
- **A quick count on the admin page.** How many visitors came today, how many haven't signed out yet, and how many came in the last 30 days.

### Important fixes

- **Admin logins stick when you use http://.** If you open Home Assistant at an address starting with http:// (not https://), the admin page logged you out again on the next tap, so checklist changes never saved. Fixed.
- **The admin password can't be downloaded any more.** Anyone who could open the add-on in Home Assistant (a kiosk tablet, say) could load the add-on's settings file, admin password included, by typing its address. It's blocked now. If you think someone might have seen your password, change it.
- **Phone numbers stay private at the kiosk.** Searching for your name to sign out showed each match's phone number to whoever was at the screen. It now shows the name, company and time they signed in. Phone numbers are only on the admin page.

### Changes

- Checklist items with an apostrophe or ampersand (like "don't") now show and save properly. Before, they came out garbled on the admin page.
- Admin passwords with symbols in them (& < > " and ') now work.
- Deleting a checklist item asks you to confirm in the add-on's own pop-up.
- Visitor times show as "15 Nov 2026" and "9:42 am".

### Minor bug fixes and reliability improvements

- Tapping your name twice no longer changes your sign-out time, and refreshing the admin page after a change doesn't make it twice.
- Signing in always needs a name, phone number and who you're visiting, even if the page is skipped.

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
