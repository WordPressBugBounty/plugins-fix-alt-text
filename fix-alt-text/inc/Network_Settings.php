<?php

namespace FixAltText;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\HelpersLibrary\Network_Settings_Library;

/**
 * Class Settings - Contains all the settings information
 *
 * @package FixAltText
 * @since   1.0.0
 */
class Network_Settings extends Network_Settings_Library {

	protected array $blocks = []; // Blocks to force alt text
	protected array $others = []; // Other areas to force alt text

	// Scan
	protected array $scan_post_types = []; // Post types to scan
	protected array $scan_taxonomies = []; // Taxonomies to scan
	protected bool $scan_users = true; // Scan users
	protected array $scan_issues = []; // Issues to detect during scan
	protected int $scan_issues_min_words = 3; //Minimum words for alt text
	protected int $scan_issues_max_characters = 150; //Minimum words for alt text

	/**
	 * Constructs the Settings object with provided data or gets the data from database
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @param array | object $settings
	 */
	public function __construct( $settings = [] ) {

		if ( empty( $settings ) ) {
			// Load from database

			// Clear cache
			wp_cache_delete( '1:notoptions', 'site-options' );

			// Get from the DB
			$db_settings = get_site_option( $this->option );

			if ( ! empty( $db_settings ) ) {
				// We already have settings in DB

				$this->overwrite( $db_settings );

				/**
				 * Add forward compatibility for scan_taxonomies
				 *
				 * @package FixAltText
				 * @since   1.1.0
				 */
				if ( ! isset( $db_settings['scan_taxonomies'] ) ) {
					$this->set( 'scan_taxonomies', Get::recommended_taxonomies() );
				}
			} else {
				// No settings in DB

				$this->set_default();

				if ( ! defined( 'FIXALTTEXT_NETWORK_SAVING' ) ) {
					// Save Default Settings
					$this->save( false );
				}
			}

		} else {
			// Load from provided data
			$this->load( $settings );
		}

		if ( ! in_array( 'image-url-not-valid', $this->scan_issues ) ) {
			/**
			 * Force this setting to be active
			 *
			 * @package FixAltText
			 * @since   1.8.0
			 **/
			$this->scan_issues[] = 'image-url-not-valid';
		}
		if ( ! in_array( 'image-type-not-valid', $this->scan_issues ) ) {
			/**
			 * Force this setting to be active
			 *
			 * @package FixAltText
			 * @since   1.8.0
			 **/
			$this->scan_issues[] = 'image-type-not-valid';
		}

	}

	/**
	 * Set the default settings if nothing is in the database
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 */
	protected function set_default(): void {

		$this->set( 'blocks', Get::blocks() );
		$this->set( 'others', Get::others() );

		$active_sites = [];
		if ( is_multisite() ) {
			$sites = get_sites();
			if ( ! empty( $sites ) ) {
				foreach ( $sites as $site ) {
					if ( is_plugin_active( FIXALTTEXT_PLUGIN ) ) {
						// This plugin is active on this site: set to use network settings by default
						$active_sites[] = $site->blog_id;
					}
				}
			}
		}

		$this->set( 'sites', $active_sites );
		$this->set( 'scan_post_types', Get::recommended_post_types() );
		$this->set( 'scan_taxonomies', Get::recommended_taxonomies() );
		$this->set( 'scan_users', true );
		$this->reset_issues();
		$this->set( 'db_version', FIXALTTEXT_VERSION );

	}

	/**
	 * Sets the settings for issues back to default
	 *
	 * @since 1.8.0
	 *
	 * @param $issues
	 *
	 * @return void
	 */
	public function reset_issues(): void {

		$this->set( 'scan_issues', Get::issues(true) );
		$this->set( 'scan_issues_min_words', 3 );
		$this->set( 'scan_issues_max_characters', 150 );

	}

	/**
	 * Checks the differences of current and previous settings to determine if a scan is needed.
	 *
	 * @param object $existing_settings
	 *
	 * @return array
	 */
	protected function check_differences( object $existing_settings ): array {

		$scan_needed = [];

		if ( ! empty( $post_types = array_diff( $this->scan_post_types, $existing_settings->scan_post_types ) ) ) {
			// Scan post types changed
			$scan_needed[1] = $post_types;
		} elseif ( ! empty( $taxonomies = array_diff( $this->scan_taxonomies, $existing_settings->scan_taxonomies ) ) ) {
			// Scan taxonomy terms changed
			$scan_needed[2] = $taxonomies;
		} elseif ( $this->scan_users != $existing_settings->scan_users ) {
			// Scan users changed
			$scan_needed[3] = 3;
		}

		return $scan_needed;
	}

	/**
	 * Gets the needed portion based on the key provided by check_differences()
	 *
	 * @param int $key
	 *
	 * @return string
	 */
	public static function get_needed_portion( int $key ): string {

		$needed = [
			1 => 'post types',
			2 => 'taxonomies',
			3 => 'users',
		];

		return $needed[ $key ] ?? '';
	}

}