<?php
namespace FixAltText\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\Debug;
use FixAltText\Migration;
use FixAltText\Settings;

/**
 * Class Plugin - Sets up the plugin
 *
 * @package FixAltText\HelpersLibrary
 * @since   1.1.0
 */
abstract class Plugin_Library {

	/**
	 * Initialize Plugin and Set Hooks
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	public static function init(): void {

		add_action( 'init', [
			static::class,
			'load_plugin_textdomain',
		] );

	}

	/**
	 * Checks to see if we need to run any version upgrade migration scripts
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	protected static function check_migrations(): void {

		// Check for migrations needed

		Debug::log( __( 'Check for migrations needed.', FIXALTTEXT_SLUG ) );

		$settings = Settings::get_current_settings( true );
		$db_version = $settings->get( 'db_version' );

		if ( FIXALTTEXT_VERSION != $db_version ) {

			// Detected that the current site needs migration ran
			require_once( FIXALTTEXT_INC_DIR . '/Migration.php' );

			Migration::init();

		}

	}

	/**
	 * Set the plugin's translation files location
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	public static function load_plugin_textdomain(): void {
		load_plugin_textdomain( FIXALTTEXT_SLUG, false, FIXALTTEXT_LANGUAGES_DIR );
	}

}