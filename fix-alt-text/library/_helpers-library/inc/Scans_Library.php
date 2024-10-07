<?php
namespace FixAltText\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\Admin;
use FixAltText\Settings;
use FixAltText\Scan;
use FixAltText\Scans;
use FixAltText\Get;
use FixAltText\Debug;

/**
 * Class Scans_Library
 *
 * @package FixAltText\HelpersLibrary
 * @since   1.6.0
 */
abstract class Scans_Library extends Base_Library {

	// Scan Details - only available to Settings()
	protected bool $needed = true; // Is turned to false after a full scan is complete
	protected string $type = 'full'; // full, portion, sub_portion, or specific
	protected string $portion = ''; // The portion of a full scan to scan: post-types, users, taxonomies, statuses, menus, etc
	protected array $sub_portions = []; // An array of post type or taxonomy slugs
	protected int $specific = 0; // The specific ID of a portion (user ID, post ID, menu ID, term ID)
	protected array $history = []; // List of historical scans

	function __construct( $data = [] ) {

		if ( empty( $data ) ) {

			// Clear cache
			wp_cache_delete( FIXALTTEXT_SCAN_OPTION, 'options' );

			$db_data = get_option( FIXALTTEXT_SCAN_OPTION );

			// Load the data from the database
			parent::__construct( $db_data );

		} else {
			// Load provided data
			parent::__construct( $data );
		}

		$this->convert_history_to_objects();

	}

	/**
	 * Converts the history of scans array into an array of Scan objects
	 *
	 * @return void
	 */
	private function convert_history_to_objects() : void {

		if ( ! empty( $this->history ) ) {
			foreach ( $this->history as $scan_key => $scan_array ) {

				if ( is_array( $scan_array ) ) {
					$this->history[ $scan_key ] = new Scan( $scan_array );
				}

			}
		}

	}

	/**
	 * Converts the history of scans array into an array of arrays
	 *
	 * @return void
	 */
	private function convert_history_to_arrays() : void {

		if ( ! empty( $this->history ) ) {
			foreach ( $this->history as $scan_key => $scan_object ) {

				if ( is_object( $scan_object ) && method_exists( $scan_object, 'return_array' ) ) {
					$this->history[ $scan_key ] = $scan_object->return_array();
				}
			}
		}

	}

	/**
	 * Resets all the values to default, except for history
	 *
	 * @return void
	 */
	public function reset(): void {

		$this->needed = true;
		$this->type = 'full';
		$this->portion = '';
		$this->sub_portions = [];
		$this->specific = 0;

	}

	/**
	 * Clears the values of the Scans object
	 *
	 * @return void
	 */
	public function clear(): void {

		$this->needed = false;
		$this->type = '';
		$this->portion = '';
		$this->sub_portions = [];
		$this->specific = 0;

	}

	/**
	 * Gets the current scans
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 */
	final public static function get_current( bool $from_db = false ): Scans {

		// Grab plugin's global
		global $fixalttext;

		$current_site_id = get_current_blog_id();

		if ( $from_db || empty( $fixalttext['scans'] ) ) {
			$fixalttext[ $current_site_id ]['scans'] = new static();
		}

		return $fixalttext[ $current_site_id ]['scans'];
	}

	/**
	 * Retrieves most recent scan of specific type
	 *
	 * @return object|\FixAltText\Scan
	 */
	public static function get_recent_scan( string $scan_type = '', string $scan_portion = 'all', array $scan_sub_portions = [], int $scan_specific = 0, int $nth = 1 ): object {

		// Grab most recent scan regardless of type
		$scans = static::get_current(true);
		$all_scans = $scans->get('history');
		$recent_scan = [];

		$scan_type = ( '' == $scan_type ) ? 'full' : $scan_type;
		$scan_portion = ( 'all' == $scan_portion ) ? '' : $scan_portion;
		$scan_sub_portions = ( 'all' == $scan_sub_portions ) ? '' : $scan_sub_portions;

		Debug::log('get_recent_scan params: ' . $scan_type . ' ' . $scan_portion . ' ' . print_r($scan_sub_portions, true) . ' ' . $scan_specific );

		//Debug::log('$all_scans: ' . print_r($all_scans, true));

		$nth_count = 0;

		if ( ! empty( $all_scans ) ) {
			// We have at least one scan to check

			foreach ( $all_scans as $scan ) {

				// Let's check to make sure we are getting the correct scan
				$this_type = $scan->get( 'type' );
				$this_portion = $scan->get( 'portion' );
				$this_sub_portions = $scan->get( 'sub_portions' );
				$this_specific = $scan->get( 'specific' );

				Debug::log( '$scan details: ' . $this_type . ' ' . $this_portion . ' ' . print_r( $this_sub_portions, true ) . ' ' . $this_specific );

				if ( $scan_type == $this_type && $scan_portion == $this_portion && $scan_sub_portions == $this_sub_portions && $scan_specific == $this_specific ) {
					// We found the specific scan
					Debug::log( 'We found the specific scan' );
					$nth_count ++;
				}

				if ( $nth_count == $nth ){
					// We found the nth scan type
					$recent_scan = $scan;
					break;
				}
			}
		}

		//Debug::log('$recent_scan 1: ' . print_r($recent_scan, true));

		// Make sure we are dealing with an object
		if ( is_array( $recent_scan ) ) {
			$recent_scan = new Scan( $recent_scan );
		}

		//Debug::log('$recent_scan 2: ' . print_r($recent_scan, true));

		return $recent_scan;
	}

	/**
	 * Retrieves most recent scan of specific type
	 **
	 * @param bool $from_db
	 *
	 * @return null|object
	 */
	public static function get_active_scan( bool $from_db = false ): ?object {

		// Grab most recent scan regardless of type
		$scans = static::get_current( $from_db );
		$scan_history = $scans->history ?? [];
		$active_scan = null;

		if ( ! empty( $scan_history ) ) {
			// Search the array of scans

			foreach ( $scan_history as $scan ) {

				// Convert to class Scan
				$scan = new Scan( $scan );

				if ( $scan->is_running() ) {
					// We found the scan that is running
					$active_scan = $scan;
					break;
				}

			}
		}

		if ( ! is_null( $active_scan ) ) {
			Debug::log( '(' . __LINE__ . ') Active Scan Progress: ' . $active_scan->get( 'progress' ) );
		}

		return $active_scan;
	}

	/**
	 * Retrieves paused scan
	 **
	 * @param bool $from_db
	 *
	 * @return null|object
	 */
	public static function get_paused_scan( bool $from_db = false ): ?object {

		// Grab most recent scan regardless of type
		$scans = static::get_current( $from_db );
		$scan_history = $scans->history ?? [];
		$paused_scan = null;

		if ( ! empty( $scan_history ) ) {
			// Search the array of scans

			foreach ( $scan_history as $scan ) {

				// Convert to class Scan
				$scan = new Scan( $scan );

				if ( $scan->is_paused() ) {
					// We found the paused scan
					$paused_scan = $scan;
					break;
				}

			}
		}

		if ( ! is_null( $paused_scan ) ) {
			Debug::log( '(' . __LINE__ . ') Paused Scan Progress: ' . $paused_scan->get( 'progress' ) );
		}

		return $paused_scan;
	}

	/**
	 * Has a full scan ran and completed and a new scan is not needed?
	 *
	 * @return bool
	 */
	static function has_full_scan_ran(): bool {

		$ran_and_complete = false;

		$recent_scan = self::get_recent_scan( 'full' );

		if ( ! empty( $recent_scan ) && $recent_scan->is_complete() && ! $recent_scan->get('needed') ) {
			$ran_and_complete = true;
		}

		return $ran_and_complete;
	}

	/**
	 * Is a full scan running?
	 *
	 * @return bool
	 */
	static function is_full_scan_running(): bool {

		$running = false;

		$recent_scan = self::get_recent_scan( 'full' );

		if ( ! empty( $recent_scan ) && $recent_scan->is_running() ) {
			$running = true;
		}

		return $running;
	}

	/**
	 * Stops all currently running scans
	 *
	 * @return void
	 */
	static function stop_all_scans( string $notes = '' ): void {

		$sites = Get::sites();

		foreach ( $sites as $site ) {

			if ( is_multisite() ) {
				switch_to_blog( $site->blog_id );
			}

			if ( is_plugin_active( FIXALTTEXT_PLUGIN ) ) {
				Scan::cancel( $notes );
			}

			if ( is_multisite() ) {
				restore_current_blog();
			}
		}

	}

	/**
	 * The type of scans that are allowed
	 *
	 * @return array
	 */
	public static function get_types() : array {

		return [
			'full',
			'portion',
			'specific',
		];

	}

	/**
	 * All the portions of a full scan that could be specifically scanned
	 *
	 * @return array
	 */
	public static function get_portions() : array {

		return [
			'all',
			'menus',
			'users',
			'post-types',
			'taxonomies',
			'statuses',
			'status',
		];

	}

	/**
	 * Saves the scan details as an array in the database
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return bool
	 */
	public function save(): bool {

		// Grab plugin's global
		global $fixalttext;

		$array = [];

		$current_site_id = get_current_blog_id();

		// Update the local cache of the scan
		$fixalttext[ $current_site_id ]['scans'] = $this;

		// Convert history of scans from array of objects to array of arrays
		$this->convert_history_to_arrays();

		// Convert first-level object into an array before storing
		foreach ( $this as $property => $value ) {
			$array[ $property ] = $value;
		}

		Debug::log( 'Saving Scans:' . print_r( $array, true ) );

		$result = update_option( FIXALTTEXT_SCAN_OPTION, $array, false );

		if ( $result ) {
			// Clear cache
			wp_cache_delete( FIXALTTEXT_SCAN_OPTION, 'options' );
		}

		return $result;
	}

	/**
	 * Sets whether a scan is needed
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 */
	final public function set_needed( bool $needed, string $type = '', string $portion = '', array $sub_portions = [] ): void {

		if ( $needed ) {
			$this->set( 'type', $type );
			$this->set( 'portion', $portion );
			$this->set( 'sub_portions', $sub_portions );
		} else {
			// Mark as not needed
			$this->reset();
		}

		$this->set( 'needed', $needed );

	}

	/**
	 * Sets the history of scans
	 */
	final public function set_scans( array $scans ): void {

		$this->set( 'history', $scans );

		$this->convert_history_to_objects();

	}

	/**
	 * Add a new scan to the history of scans
	 *
	 * @return void
	 */
	final public function add_scan( Scan $scan ) : void {

		Debug::log('add_scan');

		// Add scan to the beginning of history array
		$all_scans = [];
		$all_scans[] = $scan;

		// Max number of scans we retain
		$max_scans = 10;

		if ( ! empty( $this->history ) ) {
			foreach ( $this->history as $key => $old_scan ) {

				if ( count( $all_scans ) < $max_scans ) {
					$all_scans[] = $old_scan;
				} else {
					break;
				}

			}
		}

		$this->set_scans( $all_scans );

		// Save Scan settings
		$this->save();

	}

	/**
	 * Updates a specific scan or adds it
	 */
	final public function update_scan( object $this_scan ) : void {

		$update_scan = false;
		$add_scan = true;

		if ( ! empty( $this->history ) ) {
			// We have a history of scans

			foreach( $this->history as $key => $scan ) {

				if ( is_array( $scan ) ) {
					$scan = new Scan( $scan );

					// Convert history to use objects
					$this->history[ $key ] = $scan;

					$update_scan = true;
				}
				if (
					$scan->get( 'start_date' ) == $this_scan->get( 'start_date' ) &&
					$scan->get( 'type' ) == $this_scan->get( 'type' ) &&
					$scan->get( 'portion' ) == $this_scan->get( 'portion' ) &&
					$scan->get( 'sub_portions' ) == $this_scan->get( 'sub_portions' ) &&
					$scan->get( 'specific' ) == $this_scan->get( 'specific' )
				) {
					// Update the specific scan
					$this->history[ $key ] = $this_scan;
					$add_scan = false;
					$update_scan = true;
				}
			}

		}

		if ( $add_scan ) {
			// We didn't find this specific scan OR
			// No history of scans; let's add this one.
			$this->add_scan( $this_scan );
		} else {
			// We detected an update is needed

			if( $update_scan ){
				Debug::log('$update_scan');
				$this->save();
			}
		}

	}
}