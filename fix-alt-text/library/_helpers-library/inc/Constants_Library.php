<?php
namespace FixAltText\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

final class Constants_Library {

	/**
	 * Defines all the constants for this plugin
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param string $namespace
	 * @param string $plugin_slug
	 * @param string $plugin_dir
	 *
	 * @return void
	 */
	final public static function setup_constants( string $namespace, string $plugin_slug, string $plugin_dir ): void {

		/**
		 * NOTICE: It is assumed that the following constant variables are already defined in the main plugin file
		 *
		 * $prefix . _SLUG - example-plugin
		 * $prefix . _NAME - Example Plugin
		 * $prefix . _VERSION - 1.0.0
		 * $prefix . _MIN_PHP - 5.6.0
		 * $prefix . _MIN_WP - 5.0.0
		 */
		$prefix = str_replace( '-', '', strtoupper( $plugin_slug ) );

		if ( ! defined( 'FIXALTTEXT_PREFIX' ) ) {

			// Grab the name of the plugin - Support plugin having different name than the slug
			$plugin_dir_name = explode( '/', $plugin_dir );
			$plugin_dir_name = end( $plugin_dir_name );

			// Setup Constants
			define( 'FIXALTTEXT_PREFIX', $prefix );

			$global = strtolower( $namespace );
			define( 'FIXALTTEXT_GLOBAL', $global );

			$option = str_replace( '-', '_', $plugin_slug );
			define( 'FIXALTTEXT_OPTION', $option );
			define( 'FIXALTTEXT_NETWORK_OPTION', $option . '_network' );
			define( 'FIXALTTEXT_SCAN_OPTION_LEGACY', $option . '_scan' );
			define( 'FIXALTTEXT_SCAN_OPTION', FIXALTTEXT_SCAN_OPTION_LEGACY . 's' );
			define( 'FIXALTTEXT_NOTIFICATIONS_OPTION', $option . '_notifications' );
			define( 'FIXALTTEXT_HOOK_PREFIX', $global . '_' );
			define( 'FIXALTTEXT_DIR', $plugin_dir );

			$file = $plugin_dir . '/' . $plugin_slug . '.php';
			define( 'FIXALTTEXT_FILE', $file );
			define( 'FIXALTTEXT_PLUGIN', $plugin_dir_name . '/' . $plugin_slug . '.php' );
			define( 'FIXALTTEXT_ASSETS_DIR', $plugin_dir . '/assets' );

			$inc_dir = $plugin_dir . '/inc';
			define( 'FIXALTTEXT_INC_DIR', $inc_dir );
			define( 'FIXALTTEXT_AJAX_DIR', $inc_dir . '/ajax' );
			define( 'FIXALTTEXT_TABLES_DIR', $inc_dir . '/tables' );
			define( 'FIXALTTEXT_LIBRARY_DIR', $plugin_dir . '/library' );
			define( 'FIXALTTEXT_TEMPLATES_DIR', $plugin_dir . '/templates' );
			define( 'FIXALTTEXT_QUEUE_DIR', $plugin_dir . '/queue' );
			define( 'FIXALTTEXT_LOGS_DIR', $plugin_dir . '/logs' );
			define( 'FIXALTTEXT_LANGUAGES_DIR', $plugin_dir . '/languages' );

			$url = plugin_dir_url( $file );
			define( 'FIXALTTEXT_URL', $url );
			define( 'FIXALTTEXT_ASSETS_URL', $url . 'assets/' );
			define( 'FIXALTTEXT_LIBRARY_JS_URL', $url . 'library/js/' );

			// Set current site
			$current_site_id = ( is_multisite() ) ? get_current_blog_id() : 1;
			define( 'FIXALTTEXT_CURRENT_SITE_ID', $current_site_id );

			// Admin URLs
			$admin_uri = 'tools.php?page=' . $plugin_slug;
			define( 'FIXALTTEXT_ADMIN_URI', $admin_uri );
			define( 'FIXALTTEXT_ADMIN_URL', admin_url( $admin_uri ) );

			// Setup the current admin page
			$admin_uri_current = $admin_uri . REQUEST::key( 'tab', '', '', '&tab=' );
			define( 'FIXALTTEXT_ADMIN_URI_CURRENT', $admin_uri_current );
			define( 'FIXALTTEXT_ADMIN_URL_CURRENT', admin_url( $admin_uri_current ) );

			// Settings URLs
			$settings_uri = $admin_uri . '&tab=settings';
			define( 'FIXALTTEXT_SETTINGS_URI', $settings_uri );
			define( 'FIXALTTEXT_SETTINGS_URL', admin_url( $settings_uri ) );

			$settings_network_uri = 'network/settings.php?page=' . $plugin_slug;
			define( 'FIXALTTEXT_SETTINGS_NETWORK_URI', $settings_network_uri );
			define( 'FIXALTTEXT_SETTINGS_NETWORK_URL', admin_url( $settings_network_uri ) );
		}

	}

}