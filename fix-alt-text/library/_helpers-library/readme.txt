=== Helpers Library ===
Contributors: stevenayers63, jdorner
Requires PHP: 7.4.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==

The Helpers Library is an add-on dependency for custom WordPress plugins. This library provides the code to easily do the following:

* Admin: Header with menu
* Admin: Settings pages
* Admin: Dashboard
* Admin: Notifications
* Admin: Debug area
* Scanning: Background scanning capabilities
* Security: Retrieved sanitized $_GET, $_POST, $_REQUEST, and $_SERVER data
* Code: Automatically creates PHP constants for reference

== Install Dependency Into Plugin ==

Notice: This plugin relies on your plugin having a PHP namespace so that the install will be as a sub namespace.

1. Download the latest version of Helpers Library from https://gitlab.com/sovdeveloping/helpers-library
2. Place the unzipped files in /wp-content/plugins/your-plugin/library/helpers-library
3. Read install instructions: /wp-content/plugins/your-plugin/library/helpers-library/install.txt

== Changelog ==

= Versions Key (Major.Minor.Patch) =
* Major - 1.x.x increase involves major changes to the visual or functional aspects of the plugin, or removing functionality that has been previously deprecated. (higher risk of breaking changes)
* Minor - x.1.x increase introduces new features, improvements to existing features, or introduces deprecations. (low risk of breaking changes)
* Patch - x.x.1 increase is a bug fix, security fix, or minor improvement and does not introduce new features. (non-breaking changes)

= Version 1.7.1 =
*Release Date - 28 Aug 2024

* Minor Improvement: Allow width of admin page content-body to be as wide as the viewable area so that tables on larger screens can see more data
* Minor Improvement: Allow no ajax on top menu links with class "no-ajax"
* Bug Fix: Table_Library was breaking the Screen Options from displaying properly
* BUg Fix: The method print_column_headers() on Table_Library was outdated and not compatible with new screen options functionality within core

= Version 1.7.0 =
*Release Date - 16 Aug 2024*

* New Feature: Added constant PLUGINNAME_IS_HEARTBEAT so that plugins can determine if the request is a heartbeat. They may want to not load the plugin (while also checking ! wp_doing_cron() ) if they do not want to conflict with XDebug for heartbeat requests
* Improvement: Added ability to set default value and attributes on Settings_Display::input
* Improvement: Added ability to append html to a checkbox on Settings_Display::checkboxes
* Bug Fix: The constant PLUGINNAME_PLUGIN had the incorrect value if the host plugin's directory name did not match the plugin slug
* Bug Fix: When saving settings during a migration, the settings were not being refreshed and would subsequently save and override (undo) the new settings added or modified by the migration script.

= Version 1.6.0 =
*Release Date - 25 Mar 2024*

* New Feature: created install script to automatically make the library in scope with the plugin using it making the plugin more efficient and without conflict with same library in another plugin.
* New Feature: added textarea_field() to REQUEST class
* Improvement: Refactored the way that scan data is stored and retrieved.
* Improvement: Renamed classes to have Library suffix to make it easier to distinguish classes for plugins using the library
* Improvement: Removed usage of get_constant_value() for a performance increase.
* Improvement: Created Scans_Library.php class
* Minor Improvement: Fixed PHP Deprecated: trim(): Passing null to parameter #1 ($string) of type string is deprecated
* Minor Improvement: Various styling
* Bug Fix: The scan was exceeding the default memory limit when running the background process causing the scan the stall until the user cancelled the scan. This was preventing the plugin to fully scan the site.
* Bug Fix: The debug log was getting cleared unexpectedly when enabled.

= Version 1.5.0 =
*Release Date - 21 Jul 2023*

* New Feature: Plugin dependency can now be loaded into a plugin as a sub namespace to prevent conflicts with other plugins that use older or newer versions of this dependency
* New Feature: Force proper installation of dependency
* Improvement: Added dependency install instructions
* Improvement: added method get_scan_types() so that we can easily modify the available scan types and have a single source of truth
* Minor Improvement: grammar improvements
* Minor Improvement: code formatting
* Minor Improvement: removed some unnecessary code
* Bug Fix: The status code cache wasn't getting cleared if a scan was cancelled
