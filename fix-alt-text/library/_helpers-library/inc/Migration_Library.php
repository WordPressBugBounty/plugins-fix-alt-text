<?php
namespace FixAltText\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\Get;
use FixAltText\Debug;
use FixAltText\Network_Settings;
use FixAltText\Settings;

/**
 * Class Migration
 *
 * @package FixAltText\HelpersLibrary
 * @since 1.1.0
 */
abstract class Migration_Library {

	/**
	 * Initiates migration process
	 *
	 * @return void
	 */
	public final static function init(): void {

		// Start series of upgrade migrations

		Debug::log( __( 'Start series of upgrade migrations.', FIXALTTEXT_SLUG ) );

		$sites = Get::sites();

		// Run for all sites
		foreach ( $sites as $site ) {

			if ( is_multisite() ) {
				switch_to_blog( $site->blog_id );
			}

			// Update All Settings
			self::update_settings();

			if ( is_plugin_active( FIXALTTEXT_PLUGIN ) ) {

				// Refresh settings data
				$settings = Settings::get_current_settings( true );
				$db_version = $settings->get( 'db_version' );

				static::run_all( $db_version );

				// Refresh settings data
				$settings = Settings::get_current_settings( true );

				// Update the db settings version
				$settings->set_version( FIXALTTEXT_VERSION );
				$settings->save( false );

				// Update the network db settings version
				if ( is_multisite() && is_main_site() ) {
					$network_settings = Network_Settings::get_current_settings( true );
					$network_settings->set_version( FIXALTTEXT_VERSION );
					$network_settings->save( false );
				}

			}

			if ( is_multisite() ) {
				restore_current_blog();
			}
		}

	}

	/**
	 * Make sure all legacy settings are migrated
	 *
	 * @return void
	 */
	private static function update_settings() : void {

		// Clear out legacy settings cache
		wp_cache_delete( FIXALTTEXT_SCAN_OPTION_LEGACY, 'options' );

		// Grab the legacy data
		$scans = get_option( FIXALTTEXT_SCAN_OPTION_LEGACY );

		if ( ! empty( $scans ) ) {
			$result = update_option( FIXALTTEXT_SCAN_OPTION, $scans, false );

			if ( $result ) {
				// Clear out settings cache
				wp_cache_delete( FIXALTTEXT_SCAN_OPTION, 'options' );;
			}
		}

		// Remove legacy data from DB
		delete_option( FIXALTTEXT_SCAN_OPTION_LEGACY );

	}

	/**
	 * Runs all the migrations
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param string $db_version
	 *
	 * @return void
	 */
	abstract protected static function run_all( string $db_version ): void;

}