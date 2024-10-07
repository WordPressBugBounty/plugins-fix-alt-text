<?php

namespace FixAltText\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\Admin;
use FixAltText\Blog;
use FixAltText\Get;
use FixAltText\Notification;
use FixAltText\Scans;
use FixAltText\Settings;
use FixAltText\Network_Settings;

/**
 * Class Network_Settings_Library - Contains all the settings information
 *
 */
abstract class Network_Settings_Library extends Base_Library {

	protected string $option = FIXALTTEXT_NETWORK_OPTION; // Where we can find these settings in the site option table
	protected string $db_version = '';
	protected array $db_version_history = [];

	protected array $sites = []; // The sites in the multisite

	// Access
	protected array $access_tool_roles = []; // The roles that can have access to using this tool
	protected array $access_settings_roles = []; // The roles that can have access to modifying settings

	protected bool $debug = false; // If true, the debug tab will appear in the admin

	/**
	 * Set the default settings if nothing is in the database
	 */
	abstract protected function set_default(): void;

	/**
	 * Saves the current data that is loaded in the object to the database
	 *
	 * @param bool $display_notice Used to turn off admin notices when ran outside the admin context
	 */
	public function save( bool $display_notice = true ): Network_Settings {

		if ( ! defined( 'FIXALTTEXT_NETWORK_SAVING' ) ) {
			// Used to detect that we are in the process of saving. Prevents infinite loop scenarios
			define( 'FIXALTTEXT_NETWORK_SAVING', true );
		}

		$existing_settings = self::get_current_settings( true );

		if ( $this == $existing_settings && ! empty( get_site_option( $this->option ) ) ) {
			$response = 2;
		} else {

			$array = [];

			// Notify the sites that need to be scanned if scans are needed
			$this->are_scans_needed( $existing_settings );

			// Convert into an array before storing
			foreach ( $this as $property => $value ) {
				$array[ $property ] = $value;
			}

			// Save settings
			if ( $response = update_site_option( $this->option, $array ) ) {
				// Clear wp cache
				wp_cache_delete( '1:notoptions', 'site-options' );
			}

			$existing_settings = self::get_current_settings( true );

		}

		if ( $display_notice ) {
			// Display admin notices

			if ( $response ) {
				if ( 2 === $response ) {
					Admin::add_notice( [
						'message' => __( 'Network settings saved.', FIXALTTEXT_SLUG ),
						'alert_level' => 'success',
						'dismiss' => true,
					] );
				} else {
					Admin::add_notice( [
						'message' => __( 'Network settings have been saved.', FIXALTTEXT_SLUG ),
						'alert_level' => 'success',
						'dismiss' => true,
					] );
				}

			} else {
				Admin::add_notice( [
					'message' => __( 'Error: Network settings could not be saved.', FIXALTTEXT_SLUG ),
					'alert_level' => 'error',
					'dismiss' => true,
				] );
			}
		}

		return $existing_settings;

	}

	/**
	 * Gets the current network settings
	 *
	 * @param bool $from_db
	 */
	public static function get_current_settings( bool $from_db = false ): Network_Settings {

		// Grab plugin's global
		global $fixalttext;

		$network_settings = $fixalttext['network-settings'] ?? [];

		if ( $from_db || empty( $network_settings ) ) {
			$network_settings = new Network_Settings();
		}

		$fixalttext['network-settings'] = $network_settings;

		return $fixalttext['network-settings'];
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
			$scan_needed[3] = 'Scan Users';
		} elseif ( $this->scan_menus != $existing_settings->scan_menus ) {
			// Scan menus changed
			$scan_needed[4] = 'Scan Menus';
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
			4 => 'menus',
		];

		return $needed[ $key ] ?? '';
	}

	/**
	 * Determines the type of scans needed
	 *
	 * @param array $scan_needed
	 *
	 * @return array
	 */
	protected function get_type_scan_needed( array $scan_needed) : array {

		$scan_type = '';
		$scan_portion = '';
		$scan_sub_portions = [];

		if ( count( $scan_needed ) == 1 ) {
			// We only had 1 change, let's allow a partial scan

			$scan_type = 'portion';
			$sub_portions = [];

			// Get the portion and sub portions
			foreach( $scan_needed as $scan_needed_key => $sub_portions ){
				$scan_portion = self::get_needed_portion($scan_needed_key);
				break;
			}

			if ( is_array( $sub_portions ) && count( $sub_portions ) ) {
				$scan_type = 'sub_portion';
				$scan_sub_portions = $sub_portions;
			}

		} else {
			// Too many changes, require full scan
			$scan_type = 'full';
		}


		return [
			'type' => $scan_type,
			'portion' => $scan_portion,
			'sub_portions' => $scan_sub_portions,
		];
	}

	/**
	 * Compare the network settings against the current network settings to see if a new full scan is needed for the sites using the
	 *
	 * @param \HelpersLibrary\Network_Settings $existing_settings
	 *
	 * @return void
	 */
	private function are_scans_needed( Network_Settings $existing_settings ): void {

		// Sites affected by the network settings getting saved
		$sites_affected = [];

		$scan_needed = $this->check_differences( $existing_settings );

		// Grab previous and current sites affected
		$previous_sites = $existing_settings->get( 'sites' );
		$current_sites = $this->get( 'sites' );

		if ( $current_sites != $previous_sites ) {

			// Grab the difference between the two and only notify them to rescan
			$sites_affected = array_diff( $previous_sites, $current_sites );

			// Sites using network settings changed
			$scan_needed[10] = $sites_affected;

		}

		$all_sites_need_scanned = $scan_needed;
		unset( $all_sites_need_scanned[10] );

		$sites_to_scan_changed = $scan_needed[10] ?? [];

		// If anything other that sites changed, then force all sites to be rescanned
		if ( ! empty( $all_sites_need_scanned ) ) {
			$sites_affected = array_merge( $previous_sites, $current_sites, $sites_affected );
		}

		$sites_affected = array_unique( $sites_affected );

		if ( ! empty( $scan_needed ) ) {

			$codes = '';
			foreach ( $scan_needed as $code => $needed ) {
				$codes .= '[' . $code . ']';
			}

			$user = wp_get_current_user();

			$is_multisite = is_multisite();

			// Notify all the sites that are affected by the modification of network settings

			if ( ! empty( $sites_affected ) ) {

				// Notify each site
				foreach ( $sites_affected as $site_id ) {
					if ( $is_multisite ) {
						switch_to_blog( $site_id );
					}

					if ( is_plugin_active( FIXALTTEXT_PLUGIN ) ) {

						$scans_needed = self::get_type_scan_needed( $scan_needed );

						if ( in_array( $site_id, $sites_to_scan_changed ) ) {
							// This site was affected. Either it now uses network settings or it now uses local settings
							$scans_needed = [
								'type' => 'full',
								'portion' => '',
								'sub_portions' => [],
							];
						}

						$scans_needed_type = $scans_needed['type'] ?? '';
						$scans_needed_portion = $scans_needed['portion'] ?? '';
						$scans_needed_sub_portions = $scans_needed['sub_portions'] ?? [];

						$scan_settings = Scans::get_current();
						$scan_settings->set_needed( true, $scans_needed_type, $scans_needed_portion, $scans_needed_sub_portions );
						$scan_settings->save();

						Notification::add_notification( [
							'message' => sprintf( __( 'The scan settings have been changed by %s. A new %s scan will need to be run to reflect these settings. %s', FIXALTTEXT_SLUG ), $user->display_name, $scans_needed_type, $codes ),
							'link_url' => FIXALTTEXT_ADMIN_URL,
							'link_anchor_text' => __( 'View Scan Options on Dashboard', FIXALTTEXT_SLUG ),
							'alert_level' => 'notice',
						] );

					}

					if ( $is_multisite ) {
						restore_current_blog();
					}
				}
			}
		}

	}

	/**
	 * Converts the object Settings into an array
	 *
	 * @return array
	 */
	public static function get_array(): array {

		$settings = static::get_current_settings();

		$settings_array = [];
		foreach ( $settings as $key => $value ) {
			$settings_array[ $key ] = $value;
		}

		return $settings_array;
	}

	/**
	 * Tells you if the network settings are active
	 *
	 * @param $bid Blog ID
	 *
	 * @return bool
	 */
	public function using_network_settings( int $bid = FIXALTTEXT_CURRENT_SITE_ID ): bool {

		$active = false;

		if ( is_multisite() ) {
			if ( is_a( $this, 'Settings' ) ) {
				$network_settings = Network_Settings::get_current_settings();

				$active = $network_settings->using_network_settings( $bid );
			} else {
				$active = in_array( $bid, $this->sites );
			}
		}

		return $active;

	}

	/**
	 * Check to see if a user can access the tool
	 *
	 * @return bool
	 */
	public function can_user_access_settings(): bool {

		$current_user = wp_get_current_user();

		$roles = $current_user->roles;

		// Check all user roles
		foreach ( $roles as $role_slug ) {
			if ( $this->can_role_access_settings( $role_slug ) ) {
				return true;
			}
		}

		return false;

	}

	/**
	 * Check to see if a role can access the settings
	 *
	 * @param string $role_slug
	 *
	 * @return bool
	 */
	public function can_role_access_settings( string $role_slug = '' ): bool {

		$has_access = false;

		if ( 'administrator' === $role_slug ) {
			// Of course admin can
			$has_access = true;
		} else {

			$role = get_role( $role_slug );

			// Assume nothing: make sure they have the capability
			if ( $role->has_cap( Settings::get_user_access_capability() ) ) {
				$has_access = in_array( $role_slug, $this->access_settings_roles );
			}

		}

		return $has_access;
	}

	/**
	 * This is the capability needed so that a user can be an option to have access to the tool and settings
	 *
	 * @return string
	 */
	public static function get_user_access_capability(): string {

		return apply_filters( FIXALTTEXT_HOOK_PREFIX . 'user_access_capability', 'edit_pages' );

	}

	/**
	 * Check to see if a user can access the tool
	 *
	 * @return bool
	 */
	public function can_user_access_tool(): bool {

		$has_access = false;

		if ( current_user_can( Settings::get_user_access_capability() ) ) {

			$current_user = wp_get_current_user();

			$roles = $current_user->roles;

			// Check all user roles
			foreach ( $roles as $role_slug ) {
				if ( $this->can_role_access_tool( $role_slug ) ) {
					$has_access = true;
					break;
				}
			}
		}

		return $has_access;

	}

	/**
	 * Check to see if a role can access the tool
	 *
	 * @param string $role_slug
	 *
	 * @return bool
	 */
	public function can_role_access_tool( string $role_slug = '' ): bool {

		$has_access = false;

		if ( 'administrator' === $role_slug ) {
			// Of course admin can
			$has_access = true;
		} else if ( $this->can_role_access_settings( $role_slug ) ) {
			// If they can access settings, they can access the tool
			$has_access = true;
		} else {

			$role = get_role( $role_slug );

			// Assume nothing: make sure they have the capability
			if ( $role->has_cap( Settings::get_user_access_capability() ) ) {
				$has_access = in_array( $role_slug, $this->access_tool_roles );
			}

		}

		return $has_access;

	}

	/**
	 * Checks if the given post type can be scanned per the current settings
	 *
	 * @param $post_type
	 *
	 * @return bool
	 */
	public function can_scan_post_type( string $post_type ): bool {

		return in_array( $post_type, $this->scan_post_types );

	}

	/**
	 * Adds a blog ID to the array of sites using network settings
	 *
	 * @param int $bid Blog ID
	 *
	 * @return void
	 */
	public function add_site( int $bid ): void {

		$sites = $this->get( 'sites' );

		if ( ! in_array( $bid, $sites ) ) {
			$sites[] = $bid;
			$this->set( 'sites', $sites );
			$this->save( false );
		}

	}

	/**
	 * Sets the version history so we can use this for troubleshooting upgrade issues
	 *
	 * @param string $version New version
	 * @return void
	 */
	public function set_version( string $version ) {
		$current_version = $this->get( 'db_version' );
		$version_history = $this->get( 'db_version_history' );

		if ( ! isset( $version_history[ $current_version ] ) ) {
			// Just in case the current version didn't go into history
			$version_history[ $this->get( 'db_version' ) ] = wp_date( 'Y-m-d H:i:s' );
		}

		// A new version into history
		$version_history[ $version ] = wp_date( 'Y-m-d H:i:s' );
		$this->set( 'db_version_history', $version_history );
		$this->set( 'db_version', $version );
	}

}
