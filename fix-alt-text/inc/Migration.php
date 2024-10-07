<?php

namespace FixAltText;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\HelpersLibrary\Migration_Library as Migration_Library;

/**
 * Class Migration
 *
 * @package FixAltText
 * @since   1.2.0
 */
final class Migration extends Migration_Library {

	/**
	 * Runs all the migrations. This method is called on a loop through all sites in a multisite environment.
	 *
	 * @package FixAltText
	 * @since   1.2.0
	 *
	 * @param string $db_version
	 *
	 * @return void
	 */
	protected static function run_all( string $db_version ): void {

		if ( version_compare( '1.3.0', $db_version, '>' ) ) {
			self::v1_3_0();
		}
		if ( version_compare( '1.8.0', $db_version, '>' ) ) {
			self::v1_8_0();
		}
		if ( version_compare( '1.9.0', $db_version, '>' ) ) {
			self::v1_9_0();
		}

	}

	/**
	 * Runs the migration script for version 1.3.0
	 *
	 * @package FixAltText
	 * @since   1.9.0
	 *
	 * @return array
	 */
	public static function v1_9_0(): void {

		Debug::log( 'Migration v1.9.0' );

		// Upgrade settings for site to include all issues
		$settings = Settings::get_current_settings( true );

		$settings->reset_issues();
		$settings->save( false );

		if ( is_multisite() && is_main_site() ) {
			// Upgrade Network Settings
			$network_settings = Network_Settings::get_current_settings( true );

			$network_settings->reset_issues();
			$network_settings->save( false );
		}

		Notification::add_notification( [
			'message' => __( 'Fix Alt Text upgraded to version 1.9.0. There are new settings available under detect issues. Please review your settings and run a new scan. See Changelog for details.', FIXALTTEXT_SLUG ),
			'link_url' => admin_url( FIXALTTEXT_SETTINGS_URI ),
			'link_anchor_text' => __( 'View Settings', FIXALTTEXT_SLUG ),
			'alert_level' => 'notice',
		] );

	}

	/**
	 * Runs the migration script for version 1.3.0
	 *
	 * @package FixAltText
	 * @since   1.8.0
	 *
	 * @return array
	 */
	public static function v1_8_0(): void {

		global $wpdb;

		Debug::log( 'Migration v1.8.0' );

		// Upgrade settings for site to include all issues
		$settings = Settings::get_current_settings( true );

		$settings->reset_issues();
		$settings->save( false );

		$table_name = Get::table_name();

		if ( ! empty( $wpdb->get_results( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) ) ) {
			// Upgrade database to convert image_alt_text column to text type
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE `%1$s` MODIFY `image_alt_text` TEXT;', $table_name ) );
		}

		if ( is_multisite() && is_main_site() ) {
			// Upgrade Network Settings
			$network_settings = Network_Settings::get_current_settings( true );

			$network_settings->reset_issues();
			$network_settings->save( false );
		}

		Notification::add_notification( [
			'message' => __( 'Fix Alt Text upgraded to version 1.8.0. Now you have control over which issues are detected within settings. See Changelog for details.', FIXALTTEXT_SLUG ),
			'link_url' => admin_url( FIXALTTEXT_SETTINGS_URI ),
			'link_anchor_text' => __( 'View Settings', FIXALTTEXT_SLUG ),
			'alert_level' => 'notice',
		] );

	}

	/**
	 * Runs the migration script for version 1.3.0
	 *
	 * @package FixAltText
	 * @since   1.3.0
	 *
	 * @return array
	 */
	public static function v1_3_0(): void {

		Debug::log( 'Migration v1.3.0');

		/**
		 * Bug Fix: WP Crons did not have a consistent prefix
		 */
		Run::remove_crons();

		/**
		 * Cancel any scans that may be stuck
		 */
		Scan::stop_all_scans( __( 'All scans cancelled due to migration to Fix Alt Text version 1.2.0.', FIXALTTEXT_SLUG ) );

		/**
		 * Improvement: DB table columns tuned for performance
		 */
		$db_table_no_prefix = 'fixalttext_images';

		// delete temp table
		Run::drop_table( $db_table_no_prefix );

		// create new db table
		Run::create_table( GET::table( $db_table_no_prefix ) );

		$scan = Scans::get_current( true );
		$scan->set_needed( true );
		$scan->save();

		Notification::add_notification( [
			'message' => __( 'Fix Alt Text upgraded to version 1.3.0. Previous scan data cleared and a new scan is required to detect newly added issues.', FIXALTTEXT_SLUG ),
			'link_url' => admin_url( FIXALTTEXT_ADMIN_URI ),
			'link_anchor_text' => __( 'Start New Scan', FIXALTTEXT_SLUG ),
			'alert_level' => 'notice',
		] );
	}

}