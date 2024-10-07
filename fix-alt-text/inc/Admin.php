<?php

namespace FixAltText;

// Prevent Direct Access

( defined( 'ABSPATH' ) ) || die;

use FixAltText\HelpersLibrary\REQUEST;
use FixAltText\HelpersLibrary\Admin_Library;

/**
 * Class Admin - Handles all the admin functionality
 *
 * @package FixAltText
 * @since   1.0.0
 */
final class Admin extends Admin_Library {

	/**
	 * Set Hooks and display errors
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public static function init(): void {

		parent::init();

		if ( ! wp_doing_ajax() ) {

			add_action( 'admin_enqueue_scripts', [
				self::class,
				'scripts',
			], 9 );

		}

		// Load the table of the screen so we have screen options
		add_action( "load-tools_page_" . FIXALTTEXT_SLUG, [ self::class, 'load_references_table'] );

	}

	/**
	 * Loads the references table
	 *
	 * @return void
	 */
	public static function load_references_table() {

		if( 'references' == REQUEST::text_field('tab') ) {

			global $References_Table;

			include( FIXALTTEXT_TABLES_DIR . '/References_Table.php' );

			$References_Table = new References_Table();

		}

	}

	/**
	 * Load Scripts
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public static function scripts(): void {

		global $pagenow;

		Debug::log( __( 'Loading Scripts', FIXALTTEXT_SLUG ) );

		$action = REQUEST::key('action');
		$page = REQUEST::key('page');
		$tab = self::get_current_tab();
		$post_id = REQUEST::int('post');

		// Only load on edit screen
		if ( ( 'post-new.php' == $pagenow || ( $action == 'edit' && $post_id ) ) && ! wp_doing_ajax() ) {

			$this_post = get_post( $post_id );
			$handle = FIXALTTEXT_SLUG;

			if ( 'attachment' === $this_post->post_type ) {
				// Editing Attachment

				$handle .= '-edit-attachment';

				// Load main plugin JS file
				wp_enqueue_script( $handle, FIXALTTEXT_ASSETS_URL . 'js/edit-attachment.js', [
					'jquery',
					'wp-i18n',
				], filemtime( FIXALTTEXT_ASSETS_DIR . '/js/edit-attachment.js' ), true );

			} else {
				// Editing Other Post Type

				$handle .= '-edit-post';

				// Load main plugin JS file
				wp_enqueue_script( $handle, FIXALTTEXT_ASSETS_URL . 'js/edit-post.js', [
					'jquery',
					'wp-i18n',
				], filemtime( FIXALTTEXT_ASSETS_DIR . '/js/edit-post.js' ), true );

			}

			if ( ! defined( $handle . '-js' ) ) {
				// Setting constant so that we'll know its already set to prevent double setting
				define( $handle . '-js', true );

				// Pass settings to JS
				wp_localize_script( $handle, 'FixAltTextSettings', Settings::get_array() );

				// Translation support
				wp_set_script_translations( $handle, FIXALTTEXT_SLUG );

			}

		}

		if ( $page == FIXALTTEXT_SLUG ) {

			define( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_SCRIPTS', true );

			wp_enqueue_script( FIXALTTEXT_SLUG . '-settings', FIXALTTEXT_ASSETS_URL . 'js/settings.js', [ 'jquery', 'wp-i18n' ], filemtime( FIXALTTEXT_ASSETS_DIR . '/js/settings.js' ), true );
			wp_localize_script( FIXALTTEXT_SLUG . '-settings', 'FixAltTextAjax', [ 'ajaxURL' => admin_url( 'admin-ajax.php' ) ] );
			wp_set_script_translations( FIXALTTEXT_SLUG . '-settings', FIXALTTEXT_SLUG );

			wp_enqueue_script( FIXALTTEXT_SLUG . '-table', FIXALTTEXT_ASSETS_URL . 'js/table.js', [ 'jquery', 'wp-i18n' ], filemtime( FIXALTTEXT_ASSETS_DIR . '/js/table.js' ), true );
			wp_localize_script( FIXALTTEXT_SLUG . '-table', 'FixAltTextAjax', [ 'ajaxURL' => admin_url( 'admin-ajax.php' ) ] );
			wp_set_script_translations( FIXALTTEXT_SLUG . '-table', FIXALTTEXT_SLUG );

			if ( ! is_network_admin() && ( '' == $tab || 'dashboard' == $tab ) ) {
				// Load dashboard

				define( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_DASHBOARD_SCRIPTS', true );
				wp_enqueue_script( FIXALTTEXT_SLUG . '-dashboard', FIXALTTEXT_ASSETS_URL . 'js/dashboard.js', [], filemtime( FIXALTTEXT_ASSETS_DIR . '/js/dashboard.js' ), true );
				wp_localize_script( FIXALTTEXT_SLUG . '-dashboard', 'FixAltTextAjax', [ 'ajaxURL' => admin_url( 'admin-ajax.php' ) ] );
				wp_set_script_translations( FIXALTTEXT_SLUG . '-dashboard', FIXALTTEXT_SLUG );

			}

		}

		if ( FIXALTTEXT_SLUG === $page || 'edit' == $action ) {

			if ( ! defined( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_STYLING' ) ) {
				// Add Helpers Library Styling
				define( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_STYLING', true );
			}

			wp_enqueue_style( FIXALTTEXT_SLUG . '-styles', FIXALTTEXT_ASSETS_URL . 'styles.css', [], filemtime( FIXALTTEXT_ASSETS_DIR . '/styles.css' ), 'all' );
		}

	}

}
