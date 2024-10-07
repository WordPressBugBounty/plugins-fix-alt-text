=== Fix Alt Text ===
Contributors: stevenayers63, jdorner
Tags: accessibility, alt text, image alt text, image seo, accessible
Requires at least: 5.3
Requires PHP: 7.4
Tested up to: 6.6.1
Stable tag: 1.9.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Manage alt text site-wide easily with Fix Alt Text. You can also force users to use alt text when adding images in Gutenberg and Classic editors.

== Description ==

It is increasingly important for you to make your images meet accessibility standards. Images must have alternative text (alt text) added so that the visually impaired can use screen readers to understand the purpose and context of the image displayed on the screen.

In addition to making your site more accessible, using alt text helps your site rank better in search engines. This is an added SEO benefit for marketers.

Unfortunately, as a site grows, it becomes difficult and time-consuming to edit and maintain image alt text. Fix Alt Text plugin can help you discover where alt text is needed, quickly fix issues, and even force users to input alt text as images are used throughout the site in the future.

=== Features: ===
* Find Usage: Easily find all uses of alt text by scanning the site
* Find keyword usage: Search for specific keywords used in alt text
* Inline Editing: Instantly edit alt text without manually editing and searching through the content of a post, attachment, or custom post type
* Detect Issues: Quickly identify issues with your alt text to assist you in ADA compliance
* Toggle Features: Control which features are active for maximum flexibility
* Tool Access: Control which user roles have access to the plugin
* Settings Access: Control which user roles have access to changing settings
* Global Settings: In a multisite setup, save time by using global settings and select which sites will inherit those settings
* Fully Multisite Compatible: Force sites within the network to use global network settings for easy management

== Bonus Features ==
* Privacy: It does not use any 3rd-party tracking
* No Sales Pitches: It does not sell or promote any pro plugins
* Free: It's currently and will continue to be a Free plugin!
* No Littering: It cleans up after itself if uninstalled or when a blog is removed from a multisite network.

=== Compatible With ===
* Multisite Installations
* WP Gutenberg Editor
* WP Classic Editor
* PHP Versions 7.4, 8.0, 8.1, 8.2
* [WhereUsed](https://wordpress.org/plugins/where-used/)
* [Advanced Custom Fields - ACF](https://wordpress.org/plugins/advanced-custom-fields/)
* [Network Media Library](https://github.com/humanmade/network-media-library)

== Language Support ==

* English (default)
* [Translate this plugin in your language.](https://translate.wordpress.org/projects/wp-plugins/fix-alt-text)

== Screenshots ==

1. Dashboard: Initial scan is needed
2. Site Settings: Overwritten by network settings
3. Network Settings: Edit network settings and see scan status of all sites
4. Dashboard: Running full scan
5. Dashboard: Scan complete. Displaying issues and notifications
6. Alt Text: Found references involving alt text
7. Alt Text: Preview image and inline editing
8. Gutenberg Editor: Forcing Alt Text
9. Media Library: Forcing Alt Text

== Changelog ==

= Versions Key (Major.Minor.Patch) =
* Major - 1.x.x increase involves major changes to the visual or functional aspects of the plugin, or removing functionality that has been previously deprecated. (higher risk of breaking changes)
* Minor - x.1.x increase introduces new features, improvements to existing features, or introduces deprecations. (low risk of breaking changes)
* Patch - x.x.1 increase is a bug fix, security fix, or minor improvement and does not introduce new features. (non-breaking changes)

NOTICE: Release detail should be arranged by category per this order: New Feature, Improvement, Minor Improvement, Bug Fix, Security, Note

= Version 1.9.0 =
*Release Date - 31 Aug 2024

* New Feature: Detects whether the alt text contains backslash characters.
* New Feature: Added ability to hide columns in the Alt Text references table using the screen options tab at the top-left of the screen.
* Minor Improvement: Updated Helpers Library 1.7.1
* Minor Improvement: Removed max-width on table showing all the alt text references. This will allow users to view on larger screens to accommodate checking alt text with longer values
* Bug Fix: When alt text was being displayed within the table as a preview, it was being truncated to 255 character and gave the false impression that the real alt text was also truncated.
* Bug Fix: When a term description was updated and involved an image with alt text, the term was not getting rescanned and displaying updated info in plugin
* Bug Fix: When a term was deleted, the alt text data was not being removed from the database
* Bug Fix: When saving a post, the alt text within the content of that post was showing escaping slashes in the value when inline editing the alt text. Thank you @avisato

= Version 1.8.1 =
*Release Date - 16 Aug 2024

* Minor Improvement: Updates to the changelog

= Version 1.8.0 =
*Release Date - 16 Aug 2024*

* New Feature: Issues can now be toggled on/off within local and network settings (multisite)
* New Feature: Minimum words for alt text can be modified within local and network settings (multisite)
* New Feature: Maximum characters for alt text can be modified within local and network settings (multisite)
* New Feature: Added filter fixalttext_get_issues to allow developers the ability to add their own custom issues
* Improvement: Updated Helpers Library 1.7.0
* Improvement: Removed sorting for the alt text column in the references table. It was not very helpful to sort these values.
* Minor Improvement: On the settings page, converted the text notifying the user that the network settings are being used into an actual admin notice box.
* Bug Fix: Alt text length was unintentionally being truncated in specific scenarios to a length of 256 characters if the value was longer. Thank you @avisato
* Bug Fix: Migration script for version 1.3.0 was referencing the incorrect URL in the upgrade notice.
* Bug Fix: While using XDebug to troubleshoot issues in PHP, the wp heartbeat would interrupt the process.cd trunk
* Deprecated Filter: fixalttext_issues_color_key has been deprecated. Please use replacement fixalttext_get_issues filter to adjust the color scheme of issues on the issues pie chart.
* Note: Tested up to: 6.6.1

= Version 1.7.0 =
*Release Date - 25 Mar 2024*

* New Feature: A Scan can be paused and resumed
* Minor Improvement: Updated Helpers Library 1.6.0
* Minor Improvement: Added JPE file extension as a valid mime type
* Bug Fix: While using the block editor in other languages, alt text was not validating and prompting the user to input alt text for images. Thank you @wmaas.
* Bug Fix: File extensions were sometimes including GET variables during a scan (run a new full scan to fix)
* Bug Fix: Dropdown file types filter contain invalid file types due to GET variables (run a new full scan to fix)
* Bug Fix: Invalid file types in issues discovered due to GET variables (run a new full scan to fix)
* Note: Tested up to: 6.4.3

= Version 1.6.1 =
*Release Date - 21 Sep 2023*

* Bug Fix: The scan was exceeding the default memory limit when running the background process causing the scan the stall until the user cancelled the scan. This was preventing the plugin to fully scan the site.
* Note: Tested up to: 6.3.1

= Version 1.6.0 =
*Release Date - 28 Jul 2023*

* Improvement: Converted Helpers Library to be under the plugin's namespace to prevent conflicts with other plugins using the same dependency
* Minor Improvement: Optimized the uninstall cleanup script
* Bug fix: custom db tables were not being removed when the plugin was deleted from the site.
* Bug Fix: custom db tables were not being created in various scenarios

= Version 1.5.0 =
*Release Date - 13 Jul 2023*

* Improvement: Added compatibility with [Network Media Library plugin for WordPress](https://github.com/humanmade/network-media-library)
* Minor Improvement: Updated Helpers Library 1.5.0
* Bug Fix: Error encountered when inline editing alt text of an image in the media library. Only occurs in a multisite environment when using the Network Shared Media Library plugin and the image was uploaded from a non-primary site.

= Version 1.4.4 =
*Release Date - 31 May 2023*

* Minor Improvement: Updated Helpers Library 1.4.1
* Bug fix: Javascript error occurs when selecting a PDF in media libary modal
* Note: Tested up to: 6.2.2

= Version 1.4.3 =
*Release Date - 17 May 2023*

* Improvement: switch the keyup listener to use input for detecting changes of alt text input fields to be more reliable
* Bug Fix: After sending a post to the trash, references associated with the post still appear in References table
* Bug Fix: Attachment references were not getting removed from references table when deleted.
* Bug Fix: Force alt text alert was causing false positives when the media library modal is accessed, closed, then accessed again on the post edit screen int he admin.

= Version 1.4.2 =
*Release Date - 21 Apr 2023*

* Bug Fix: Inline editing was encountering an error in specific scenarios: image_url_mismatch

= Version 1.4.1 =
*Release Date - 14 Apr 2023*

* Minor Improvement: Updated Helpers Library 1.4.0
* Security: Improved sanitation of data

= Version 1.4.0 =
*Release Date - 05 Apr 2023*

* Improvement: Search now includes the title of the attachment or post in the "from" column
* Minor Improvement: Updated Helpers Library 1.3.7
* Bug Fix: Deprecated PHP notice - PHP 8.2 deprecated sending null to trim()
* Bug Fix: PHP Fatal error occurred if mbstring non-default extension was not installed. Fallback implemented.
* Note: Tested up to: 6.2.0

= Version 1.3.9 =
*Release Date - 10 Mar 2023*

* Minor Improvement: Updated Helpers Library 1.3.6
* Bug Fix: The plugin directory constant values were incorrect if site_url() and admin_url() do not have the same HTTP protocol.
* Bug Fix: Prevent update of plugin if it is under local git control

= Version 1.3.8 =
*Release Date - 9 Mar 2023*

* Minor Improvement: Updated Helpers Library 1.3.5
* Bug Fix: Fixed fatal error related to multisite installations. Issue occurs when a previous blog has been deleted and a new blog is created. The new blog's IDs increments and no longer matches the assumed corresponding index within the full array of blogs.
* Bug Fix: The database tables were not getting created on plugin activation for a multisite.

= Version 1.3.7 =
*Release Date - 3 Mar 2023*

* Bug Fix: JavaScript error when the issues chart attempts to display when there are no issues found.
* Bug Fix: Inline editing did not work for images directly located in the media library
* Bug Fix: When uploading an attachment, the attachment was not automatically scanned into the system

= Version 1.3.6 =
*Release Date - 3 Mar 2023*

* Bug Fix: Activating plugin on a multisite caused fatal error

= Version 1.3.5 =
*Release Date - 19 Jan 2023*

* Bug Fix: Inline edit feature was not compatible with post meta that was stored as an array or an object.
* Bug Fix: Sometimes alt text would be marked as having code in it if a quote mark was in the alt text.

= Version 1.3.4 =
*Release Date - 29 Nov 2022*

* Minor Improvement: Updated Helpers Library dependency
* Bug Fix: Scan duration details should not display 0 days, 0 hours, etc.
* Bug Fix: Scan is showing a message that a scan is needed when it is actually not
* Note: Tested up to: 6.1.1

= Version 1.3.3 =
*Release Date - 09 Nov 2022*

* Minor Improvement: Updated Helpers Library dependency
* Bug Fix: Migration script wasn't single site compatible
* Bug Fix: When scan menus option changed in settings, the user was not being notified that a new scan was needed.
* Bug Fix: Headers sent prematurely when displaying admin notice if updating plugin while another plugin is using an older version of Helpers Library
* Bug Fix: Scans involving thousands of users, or terms would run out of memory due to meta caching
* Bug Fix: There was a rare possibility that a post gets queued and then the post is deleted before the background scan processes it, which could cause the progress bar to stall on the dashboard.
* Note: Tested up to: 6.1.0

= Version 1.3.1 =
*Release Date - 27 Oct 2022*

* Minor Improvement: Updated Helpers Library dependency
* Security: Removed usernames from the scan users queue file

= Version 1.3.0 =
*Release Date - 26 Oct 2022*

* New Feature: Filter "fixalttext_excluded_post_types" has been added to allow modification of post types excluded from scans
* New Feature: Filter "fixalttext_excluded_taxonomies" has been added to allow modification of post types excluded from scans
* Improvement: Debug logging is more efficient
* Improvement: Scan was revised to now handle very large databases and use less resources
* Improvement: Updated recommended taxonomies and post types to scan. Please review your settings.
* Bug Fix: Uninstall script fixed
* Bug Fix: The plugin was initializing on the init hook, but activation hooks do not work on the init hook and were causing loading issues when plugin was activated.
* Bug Fix: Retrieving settings in a single site install were failing
* Bug Fix: Scan would run out of memory when trying to queue 400K posts
* Bug Fix: Post was not getting rescanned after it has been updated
* Security: Minor XSS Escaping within a user protected area

= Version 1.2.0 =
*Release Date - 20 Oct 2022*

* New Feature: Debug area for troubleshooting scan issues. Debug mode must be turned on in settings before it will appear.
* New Feature: Detects invalid image src URLs and invalid image types
* New Feature: Records plugin version history to assist troubleshooting issues
* New Feature: Detects image alt text located in post excerpts
* Improvement: Converted all AJAX requests to JSON responses
* Improvement: Upgraded to Chart.js v3.8.0
* Improvement: Implemented standard security class to handle consistent sanitization
* Improvement: Styling and code standardization to make maintenance easier
* Improvement: Added Helpers_Library 1.1.0 dependency
* Improvement: Added upgrade migration scripts
* Improvement: On multisite, you can now navigate to the network settings from the site settings tab
* Improvement: DB table columns tuned for performance
* Improvement: Renamed issue names to be more descriptive (rescan required on update)
* Improvement: Moved filters into the column headings and now applied automatically on change
* Improvement: Filters are highlighted blue when applied
* Improvement: Converted inline edit text input to a textarea field for better ease of editing. Thanks Rick Barley!
* Improvement: Added auto focus on the inline edit field to save a click. Thanks Rick Barley!
* Minor Improvement: Refactored compatibility checks
* Bug Fix: Uninstall script didn't remove scheduled crons
* Bug Fix: Conditional empty checks did not exist before iterating over various settings. Could result in PHP error.
* Bug Fix: Plugin options cache was not being cleared properly
* Bug Fix: Missing </tr> tag in settings DOM
* Bug Fix: WP Crons did not have a consistent prefix
* Bug Fix: Updated links to # to include an actual target to stop jumping to the top of the screen
* Bug Fix: JS and CSS assets had a double slash in the URL. Thanks @amundsan
* Bug Fix: Scan complete notification had a broken link to scan details
* Bug Fix: Scan was picking up attachments in the Media Library that were not images
* Bug Fix: When uninstalling the plugin it threw an error
* Bug Fix: Could not start a new scan if a previous scan was interrupted by deactivating the plugin or some other unexpected action.
* Note: Increased Minimum WP Version to 5.3
* Note: Tested up to: 6.0.3

= Version 1.1.4 =
*Release Date - 15 Sep 2022*

* Minor Improvement: Added consistency with how constants were defined
* Minor Improvement: Updated version release key in changelog
* Minor Improvement: Code formatting
* Bug Fix: User could save draft without being prompted to add alt text
* Bug Fix: hardcoded wp-admin references were breaking Bedrock compatibility. Thanks @amundsan
* Note: Tested up to 6.0.2

= Version 1.1.3 =
*Release Date - 09 Aug 2022*

* Minor Improvement: Added versions key to Changelog
* Bug Fix: JS assets were not loaded when adding a new post.

= Version 1.1.2 =
*Release Date - 25 Mar 2022*

* Bug Fix: non-static function called statically caused a fatal error

= Version 1.1.1 =
*Release Date - 25 Mar 2022*

* Bug Fix: delete_post hook was sending 1 parameter instead of 2 and caused a fatal error

= Version 1.1.0 =
*Release Date - 24 Mar 2022*

* New Feature: Scans now detect image alt text associated with taxonomies and users
* New Feature: Settings include ability to select which taxonomies to scan
* New Feature: Settings include ability to toggle on/off user scanning
* Improvement: Added labels to post types to be scanned in settings area
* Improvement: Minor styling adjustments
* Improvement: Minor performance tuning
* Improvement: Accessibility - Missing labels for Notification checkboxes
* Bug Fix: The notification that a new scan is needed after saving settings was not displayed to the user immediately
* Bug Fix: Search highlighting would cause a fatal error if the text being searched included a percent sign
* Bug Fix: Iterating over a null value when setting checkboxes to checked if no settings initially existed. PHP Warning thrown.
* Bug Fix: On new scan, the database would get cleared out even if the scan failed. Functionality moved to run when confirmed that the scan is going to run.
* Bug Fix: Settings page background would flicker due to css hover effect on tables.
* Note: Tested up to 5.9.2

= Version 1.0.2 =
*Release Date - 11 Feb 2022*

* Improvement: Accessibility - Dropdown menus on Alt Text screen did not have labels
* Improvement: Accessibility - Table Row actions & inline edit link don't become visible on :focus
* Improvement: Accessibility - User Access settings did not have labels
* Bug Fix: Multiple links were opening in new tabs unintentionally.
* Note: Thank you Joe Dolson (@joedolson) for reviewing the plugin and providing great feedback!

= Version 1.0.1 =
*Release Date - 10 Feb 2022*

* Minor Improvement: Code formatting and miscellaneous changes
* Bug Fix: JS error when defining jQuery constant for shorthand

= Version 1.0.0 =
*Release Date - 01 Feb 2022*

* Note: Initial Release

