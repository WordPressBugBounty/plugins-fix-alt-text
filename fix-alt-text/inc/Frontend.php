<?php

namespace FixAltText;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Class Admin - Handles all the admin functionality
 *
 * @package FixAltText
 * @since   1.0.0
 */
final class Frontend {

	/**
	 * Set Hooks and display errors
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public static function init(): void {

		add_action( 'wp_enqueue_media', [
			self::class,
			'wp_enqueue_media',
		], 9 );

	}

	/**
	 * Load JS Scripts Needed when the WordPress Editor is loaded on the frontend
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	public static function wp_enqueue_media( string $html ): string {

		// Editing Other Post Type

		$handle = FIXALTTEXT_SLUG . '-edit-post';

		if ( ! defined( $handle . '-js' ) ) {
			// Setting constant so that we'll know its already set to prevent double setting
			define( $handle . '-js', true );

			// Load main plugin JS file
			wp_enqueue_script( $handle, FIXALTTEXT_ASSETS_URL . 'js/edit-post.js', [
				'jquery',
				'wp-i18n',
			], filemtime( FIXALTTEXT_ASSETS_DIR . '/js/edit-post.js' ), true );

			// Pass settings to JS
			wp_localize_script( $handle, 'FixAltTextSettings', Settings::get_array() );

			// Translation support
			wp_set_script_translations( $handle, FIXALTTEXT_SLUG );
		}

		// We have to return html or the editor will not be shown
		return $html;

	}

}
