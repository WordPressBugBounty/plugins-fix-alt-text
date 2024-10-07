<?php
namespace FixAltText\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

$this_version = '1.7.0';
$min_php_version = '7.4.0';
/**
 * Helpers Library
 *
 * @package FixAltText\HelpersLibrary
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * Plugin Name: Helpers Library
 * Plugin URI: https://gitlab.com/sovdeveloping/helpers-library
 * Version: 1.7.0
 * Description: This library is an add-on for plugins so the visual WordPress admin interface can be easily  implemented for a plugin.
 * Author: Steven Ayers
 * Author URI: https://profiles.wordpress.org/stevenayers63/
 * Text Domain: helpers-library
 * License: GPL v3
 *
 * This software is provided "as is" and any express or implied warranties, including, but not limited to, the
 * implied warranties of merchantibility and fitness for a particular purpose are disclaimed. In no event shall
 * the copyright owner or contributors be liable for any direct, indirect, incidental, special, exemplary, or
 * consequential damages(including, but not limited to, procurement of substitute goods or services; loss of
 * use, data, or profits; or business interruption) however caused and on any theory of liability, whether in
 * contract, strict liability, or tort(including negligence or otherwise) arising in any way out of the use of
 * this software, even if advised of the possibility of such damage.
 *
 * For full license details see license.txt
 */

;

// Define which plugin is loading this library
$this_plugin = basename( dirname( __DIR__, 2 ) );

if ( ! defined( 'FIXALTTEXT_HELPERSLIBRARY_VERSION' ) ) {

	// Set current site
	$current_site_id = ( is_multisite() ) ? get_current_blog_id() : 1;
	define( 'FIXALTTEXT_HELPERSLIBRARY_CURRENT_SITE_ID', $current_site_id );

	// Load the plugin
	define( 'FIXALTTEXT_HELPERSLIBRARY_VERSION', $this_version );
	define( 'FIXALTTEXT_HELPERSLIBRARY_MIN_PHP', $min_php_version );
	define( 'FIXALTTEXT_HELPERSLIBRARY_SLUG', 'helpers-library' );
	define( 'FIXALTTEXT_HELPERSLIBRARY_PLUGIN_LOADED', $this_plugin );
	define( 'FIXALTTEXT_HELPERSLIBRARY_DIR', __DIR__ );
	define( 'FIXALTTEXT_HELPERSLIBRARY_INC_DIR', FIXALTTEXT_HELPERSLIBRARY_DIR . '/inc' );
	define( 'FIXALTTEXT_HELPERSLIBRARY_LIBRARY_DIR', FIXALTTEXT_HELPERSLIBRARY_DIR . '/library' );
	define( 'FIXALTTEXT_HELPERSLIBRARY_TABLES_DIR', FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/tables' );

	define( 'FIXALTTEXT_HELPERSLIBRARY_ASSETS_DIR', FIXALTTEXT_HELPERSLIBRARY_DIR . '/assets' );
	define( 'FIXALTTEXT_HELPERSLIBRARY_URL', plugin_dir_url( __FILE__ ) );
	define( 'FIXALTTEXT_HELPERSLIBRARY_ASSETS_URL', FIXALTTEXT_HELPERSLIBRARY_URL . 'assets' );

	// Must be loaded before defining admin URLs
	require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/REQUEST.php' );

	// Detect heartbeat requests
	$is_heartbeat = ( wp_doing_ajax() && 'heartbeat' === REQUEST::text_field('action') );
	define( 'FIXALTTEXT_IS_HEARTBEAT', $is_heartbeat );

	define( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_FOLDER', str_replace( str_replace( ['https://', 'http://'], '', site_url() ) . '/', '', str_replace( ['https://', 'http://'], '', admin_url() ) ) ); // e.g. wp-admin/
	define( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_DIR', substr_replace( ABSPATH . FIXALTTEXT_HELPERSLIBRARY_ADMIN_FOLDER, "", - 1 ) );  // e.g. /var/www/wp-admin

	// Admin URLs
	define( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_URI', REQUEST::key( 'page', '', '', 'tools.php?page=' ) );
	define( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_URL', admin_url( FIXALTTEXT_HELPERSLIBRARY_ADMIN_URI ) );

	// Setup the current admin page
	define( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_URI_CURRENT', FIXALTTEXT_HELPERSLIBRARY_ADMIN_URI . REQUEST::key( 'tab', '', '', '&tab=' ) );
	define( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_URL_CURRENT', admin_url( FIXALTTEXT_HELPERSLIBRARY_ADMIN_URI_CURRENT ) );

	// DB Connection
	require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Constants_Library.php' );
	require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Plugin_Library.php' );
	require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Base_Library.php' );
	require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Debug_Library.php' );
	require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Get_Library.php' );
	require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Run_Library.php' );
	require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Network_Settings_Library.php' );
	require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Migration_Library.php' );
	require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Notification_Library.php' );
	require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Menu_Library.php' );
	require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Scans_Library.php' );
	require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Scan_Library.php' );

	// Load needed WP functions
	include_once( FIXALTTEXT_HELPERSLIBRARY_ADMIN_DIR . '/includes/plugin.php' );

	if ( is_admin() || is_network_admin() ) {
		require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Admin_Library.php' );
		require( FIXALTTEXT_HELPERSLIBRARY_TABLES_DIR . '/Table_Library.php' );
		require( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Settings_Display_Library.php' );
	}

} else {
	// The library is already loaded

	// Check to see if it's the same version already loaded
	if ( FIXALTTEXT_HELPERSLIBRARY_VERSION !== $this_version ) {

		// We have a conflict in version. Set constant so that the main plugin can use this as a flag to prevent the plugin from loading.
		define( 'FIXALTTEXT_HELPERSLIBRARY_CONFLICT', sprintf( __( 'There is a dependency conflict between plugins %s and %s, so we are not loading %s. Please make sure both plugins are updated and using the latest versions available. Also, notify both plugin authors to install the Helpers Library as a sub namespace of their plugin per the install instructions in readme.txt to avoid conflicts like this in the future.', $this_plugin ), $this_plugin, FIXALTTEXT_HELPERSLIBRARY_PLUGIN_LOADED, $this_plugin ) );

	}

}