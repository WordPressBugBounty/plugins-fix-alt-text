<?php
namespace FixAltText\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\Scan_Process;
use FixAltText\Admin;
use FixAltText\Settings;
use FixAltText\Scan;
use FixAltText\Scans;
use FixAltText\Get;
use FixAltText\Debug;

/**
 * Class Scan
 *
 * @package FixAltText\HelpersLibrary
 * @since   1.1.0
 *
 * @TODO    : make scanner check inside of certain blocks for other blocks like
 */
abstract class Scan_Library extends Base_Library {

	protected string $type = ''; // full, portion, sub_portion, or specific
	protected string $portion = ''; // The portion of a full scan to scan: all, post-types, users, taxonomies, status, statuses, menus, etc
	protected array $sub_portions = []; // An array of post types or taxonomy slugs
	protected int $specific = 0; // The specific ID of a portion (user ID, post ID, menu ID, term ID)
	protected string $start_date = ''; // Date when the full scan started
	protected string $end_date = ''; // Date when the full scan started
	protected string $pause_date = ''; // Date when the scan was paused
	protected string $currently = ''; // What is currently being scanned?
	protected int $progress = 0; // Number of posts scanned
	protected int $progress_total = 0;  // Total amount of items being scanned
	protected int $cancelled = 0; // user ID of the user that cancelled the scan
	protected int $started = 0; // user ID of user who started scan or -1 for wp_cron
	protected string $notes = ''; // Details about the scan

	function __construct( $data = [] ) {

		if ( ! empty( $data ) ) {
			// Load provided data
			parent::__construct( $data );
		}

	}

	/**
	 * Registers all hooks needed by the plugin
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	abstract public static function init(): void;

	/**
	 * Displays the start scan button
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param string $class
	 */
	final public static function display_start_scan_button( string $class = 'initial' ): void {

		$settings = Settings::get_current_settings();

		if ( $settings->can_user_access_settings() ) {
			$current_user_id = get_current_user_id();
			$nonce = wp_create_nonce( FIXALTTEXT_SLUG . '-start-scan-' . $current_user_id );

			$scans = Scans::get_current();
			$scan_types = Scans::get_types();
			$scan_portions = Scans::get_portions();

			$needed = $scans->get( 'needed' );

			if ( 'initial' == $class ) {
				$class .= ( $needed ) ? ' scan-needed' : '';

				if ( in_array( 'full', $scan_types ) ) {
					echo '<p style="text-align: center;" class="scan-controls">';
					echo '<a href="#scan" data-scan-type="full" data-scan-portion="" data-scan-sub-portions="" data-scan-specific="0" class="scan-link start dashicons dashicons-controls-play" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html__( 'Initial', FIXALTTEXT_SLUG ) . '<br>' .  esc_html('Full Scan', FIXALTTEXT_SLUG ) . '</a>';
					echo '</p>';
				}
				echo '<p style="text-align: center"><span class="warning-text">' . esc_html__( 'Warning:', FIXALTTEXT_SLUG ) . '</span> ' . esc_html__( 'Please review the scan settings before you start an initial scan. Reference data is only accurate if a full scan is completed.', FIXALTTEXT_SLUG ) . '</p>';
			} else {

				echo '<p style="text-align: center;" class="scan-controls">';

				if ( in_array( 'full', $scan_types ) ) {
					echo '<a href="#scan" data-scan-type="full" data-scan-portion="" data-scan-sub-portions="" data-scan-specific="0" class="scan-link start dashicons dashicons-controls-play" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html__( 'Start', FIXALTTEXT_SLUG ) . '<br>' .  esc_html('Full Scan', FIXALTTEXT_SLUG ) . '</a>';
				}

				if ( in_array( 'portion', $scan_types ) && in_array( 'statuses', $scan_portions ) && Scans::has_full_scan_ran() ) {
					echo '<a href="#" data-scan-type="portion" data-scan-portion="statuses" data-scan-sub-portions="" data-scan-specific="0" class="scan-link start dashicons dashicons-admin-links" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html__( 'Check', FIXALTTEXT_SLUG ) . '<br>' .  esc_html('Status Codes', FIXALTTEXT_SLUG ) . '</a>';
				}

				echo '</p>';

				echo '<p style="text-align: center"><span class="notice-text">' . esc_html__( 'Notice:', FIXALTTEXT_SLUG ) . '</span> ' . esc_html__( 'Running a new scan may take a few minutes depending on your settings. Reference data is only accurate if a full scan is completed.', FIXALTTEXT_SLUG ) . '</p>';
			}

		}

	}

	/**
	 * Display the lastest scan stats
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	final public static function display_scan_stats(): void {

		$scan = Scans::get_recent_scan();

		$scan_start_date = $scan->get( 'start_date' );
		$scan_end_date = $scan->get( 'end_date' );
		$scan_pause_date = $scan->get( 'pause_date' );

		$scan_is_paused = $scan->is_paused();

		if ( ! $scan_is_paused ) {
			// Duration
			$start = date_create( $scan_start_date );
			$end = date_create( $scan_end_date );
			$difference = date_diff( $start, $end );
			$scan_duration_days = (int) $difference->format( '%d' );
			$scan_duration_hours = (int) $difference->format( '%h' );
			$scan_duration_minutes = (int) $difference->format( '%i' );
			$scan_duration_seconds = (int) $difference->format( '%s' );

			$scan_duration = ( $scan_duration_days ) ? $scan_duration_days . ' ' . __( 'Days', FIXALTTEXT_SLUG ) . ' ' : '';
			$scan_duration .= ( $scan_duration_hours ) ? $scan_duration_hours . ' ' . __( 'Hours', FIXALTTEXT_SLUG ) . ' ' : '';
			$scan_duration .= ( $scan_duration_minutes ) ? $scan_duration_minutes . ' ' . __( 'Minutes', FIXALTTEXT_SLUG ) . ' ' : '';
			$scan_duration .= ( $scan_duration_seconds ) ? $scan_duration_seconds . ' ' . __( 'Seconds', FIXALTTEXT_SLUG ) . ' ' : '';
		}

		$progress = $scan->get( 'progress' );
		$total = $scan->get( 'progress_total' );

		$percentage = 0;
		if ( $progress ) {
			$percentage = round( ( $progress / $total ) * 100, 1 );
		}

		$cancelled = ( $scan->get( 'cancelled' ) ) ? __( 'Yes', FIXALTTEXT_SLUG ) : __( 'No', FIXALTTEXT_SLUG );

		echo '<h3>' . esc_html__( 'The last scan details:', FIXALTTEXT_SLUG ) . '</h3>
		<ul>
		<li><b>' . esc_html__( 'Type', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $scan->get( 'type' ) ) . '</li>
		<li><b>' . esc_html__( 'Started By', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $scan->get_started_by() ) . '</li>
		<li><b>' . esc_html__( 'Start Date', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $scan_start_date ) . '</li>';

		if ( $scan_is_paused ) {
			echo '<li class="notice-text"><b>' . esc_html__( 'Pause Date', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $scan_pause_date ) . '</li>';
		} else {
			echo '<li><b>' . esc_html__( 'End Date', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $scan_end_date ) . '</li>';
		}

		if ( $scan->get( 'type' ) == 'portion' && $scan->get( 'portion' ) == 'statuses' ) {
			echo '<li><b>' . esc_html__( 'Total Unique URLs Checked', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $progress ) . '</li>';
		} else {
			if ( ! $scan_is_paused ) {
				echo '<li><b>' . esc_html__( 'Scanned', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $progress ) . '</li>';
			}
		}

		if ( ! $scan_is_paused ) {
			$status_class = ( 100 == $percentage ) ? 'success-text' : 'error-text';
			echo '<li class="' . esc_attr( $status_class ) . '"><b>' . esc_html__( 'Progress', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $percentage ) . '%</li>';

			echo '<li><b>' . esc_html__( 'Total Duration', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $scan_duration ) . '</li>';

			$status_class = ( 'Yes' == $cancelled ) ? 'warning-text' : 'success-text';
			echo '<li class="' . esc_attr( $status_class ) . '"><b>' . esc_html__( 'Cancelled', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $cancelled ) . '</li>';

			if ( $scan->get( 'cancelled' ) ) {
				echo '<li><b>' . esc_html__( 'Canceled By', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $scan->get_cancelled_by() ) . '</li>';
			}
		}

		echo '</ul> ';

		if ( $scan_is_paused || $scan->is_running() ) {
			Scan_Process::display_progress_bar( $scan );
		}
	}

	/**
	 * Converts the started ID into a display name
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return string
	 */
	final public function get_started_by(): string {

		$id = $this->get( 'started' );

		return Get::convert_id_to_name( $id );

	}

	/**
	 * Converts the cancelled ID into a display name
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return string
	 */
	final public function get_cancelled_by(): string {

		$id = $this->get( 'cancelled' );

		return Get::convert_id_to_name( $id );

	}

	/**
	 * Set scan_start_date from outside the method
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 */
	final public function set_start_date( bool $reset = false ): void {

		if ( $reset ) {
			$this->set( 'start_date', '' );
		} else {
			$this->set( 'start_date', wp_date( 'Y-m-d H:i:s' ) );
		}

	}

	/**
	 * Set scan_end_date from outside the method
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	final public function set_end_date( bool $reset = false ): void {

		if ( $reset ) {
			$this->set( 'end_date', '' );
		} else {
			$this->set( 'end_date', wp_date( 'Y-m-d H:i:s' ) );
		}

	}

	/**
	 * Sets scan type
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param string $value
	 *
	 * @return void
	 */
	final public function set_type( string $value ): void {

		$this->set( 'type', $value );

	}

	/**
	 * Sets the portion property
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.6.0
	 *
	 * @return void
	 */
	final public function set_portion( string $value ): void {

		$this->set( 'portion', $value );

	}

	/**
	 * Sets the sub_portions property
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.6.0
	 *
	 * @return void
	 */
	final public function set_sub_portions( array $value ): void {

		$this->set( 'sub_portions', $value );

	}

	/**
	 * Combine sub portions into a colon delimited string
	 *
	 * @param array $sub_portions
	 *
	 * @return string
	 */
	final public static function convert_sub_portions_to_string(array $sub_portions = [], string $default = 'all'): string {

		$sub_portions_string = $default;
		if ( ! empty( $sub_portions ) ) {
			$sub_portions_string = implode( ':', $sub_portions );
		}

		return trim( $sub_portions_string ?? '' );

	}

	/**
	 * Convert a sub_portions string back into an array
	 *
	 * @param string $sub_portions_string
	 *
	 * @return string
	 */
	final public static function convert_sub_portions_to_array( string $sub_portions_string = '' ): array {

		$sub_portions = [];
		if ( ! empty( $sub_portions_string ) ) {
			$sub_portions = explode( ':', $sub_portions_string );
		}

		return $sub_portions;

	}

	/**
	 * Sets the specific property
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.6.0
	 *
	 * @return void
	 */
	final public function set_specific( int $value ): void {

		$this->set( 'specific', $value );

	}

	/**
	 * Sets the scan as cancelled by current user
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	final public function set_cancelled( ?int $id = null ): void {

		if ( $id == null ) {
			$this->set( 'cancelled', get_current_user_id() );
		} else {
			$this->set( 'cancelled', $id );
		}

		$this->set( 'pause_date', '' );

	}

	/**
	 * Adds a message to the notes property
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param string $notes
	 *
	 * @return void
	 */
	final public function set_notes( string $notes = '' ): void {

		$this->set( 'notes', $this->notes . ' ' . $notes );

	}

	/**
	 * Updates progress
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param int $count
	 *
	 * @return void
	 */
	final public function update_progress( int $queue_count ): void {

		$total = $this->get( 'progress_total' );
		$progress = $total - $queue_count;
		$this->set_progress( $progress );

		Debug::log( 'update_progress: ' . $total . ' - ' . $queue_count . ' = ' . $progress );

		$this->save();

	}

	/**
	 * Sets progress count
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	public function set_progress( int $value ): void {

		$this->set( 'progress', $value );

	}

	/**
	 * Sets progress total count
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	public function set_progress_total( int $value ): void {

		$this->set( 'progress_total', $value );

	}

	/**
	 * Sets started ID
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	public function set_started( int $value ): void {

		$this->set( 'started', $value );

	}

	/**
	 * Sets the value of property "currently" which lets us know what is currently being scanned
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param string $value
	 *
	 * @return void
	 */
	public function set_currently( string $value ): void {

		$this->set( 'currently', $value );

	}

	/**
	 * Determine is the current site's scan is running
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return bool
	 */
	public function is_running(): bool {

		$is_running = false;

		if ( $this->get( 'start_date' ) && ! $this->get( 'end_date' ) && ! $this->get( 'cancelled' ) && ! $this->is_paused() ) {
			$is_running = true;
		}

		return $is_running;

	}

	/**
	 * Is the scan complete?
	 *
	 * @return bool
	 */
	public function is_complete(): bool  {

		$is_complete = false;

		if (
			! $this->is_running() &&
			$this->get( 'end_date' ) &&
			! $this->get( 'cancelled' ) &&
			! $this->is_paused()
		) {
			$is_complete = true;
		}

		return $is_complete;
	}

	/**
	 * Pauses this scan
	 *
	 * @return void
	 */
	public function pause( string $notes = '' ): void {

		if ( $this->is_running() ) {

			$current_site_id = ( is_multisite() ) ? get_current_blog_id() : 1;

			if ( FIXALTTEXT_HELPERSLIBRARY_CURRENT_SITE_ID == $current_site_id ) {
				// Only delete the lock if we are on the current site

				// Grab plugin's global
				global $fixalttext;

				if ( isset( $fixalttext['scan-process'] ) ) {
					if ( ! $fixalttext['scan-process']->can_we_start() ) {
						// Delete scan locks
						$fixalttext['scan-process']->pause_process();
					}
				}

				// Mark a scan as needed
				$scans = Scans::get_current( true );

				// Mark this specific kind of scan as needed
				$scans->set_needed( true, $this->get('type'), $this->get('portion'), $this->get('sub_portions') );

				// Set pause date in local time
				$this->set( 'pause_date', wp_date( 'Y-m-d H:i:s' ) );
				$this->set_notes( $notes );
				$this->save();

			}

		}

	}

	/**
	 * Determine if this scan is paused
	 *
	 * @return bool
	 */
	public function is_paused() : bool {

		// True if no pause date detected
		return ( $this->get( 'pause_date' ) != '' );

	}

	/**
	 * Resume this scan
	 *
	 * @return bool
	 */
	public function resume( string $notes = '' ): bool {

		global $fixalttext;

		$resume = false;

		if ( isset( $fixalttext['scan-process'] ) ) {
			if ( $fixalttext['scan-process']->can_we_start() && $this->is_paused() ) {

				$this->set( 'pause_date', '' );
				$this->save();

				$resume = true;

				// Restart the background process
				$fixalttext['scan-process']->dispatch();
			} else {
				Debug::log('Error: Cannot resume scan.');
			}
		}

		return $resume;

	}

	/**
	 * Displays the message sorry no results available since the scan hasn't fully ran
	 *
	 * @return void
	 */
	public static function display_results_not_available( bool $warning = false ): void {

		$settings = Settings::get_current_settings();
		$can_user_access_settings = $settings->can_user_access_settings();
		$slug = FIXALTTEXT_SLUG;

		$link_url = FIXALTTEXT_ADMIN_URL . '#scan';
		$link_anchor_text = __( 'Start Scan', $slug );

		$is_running = Scans::is_full_scan_running();

		if ( $is_running ) {
			$message = esc_html__( 'Results will be displayed when the full scan is finished scanning.', FIXALTTEXT_SLUG );
		} else {
			// Scan is needed
			if ( $can_user_access_settings ) {
				$message = esc_html__( 'A full scan needs to be completed before accurate results are available.', $slug );
			} else {
				$message = esc_html__( 'A full scan needs to be completed before results are available.', $slug );
				$message .= esc_html__( 'Contact your site administrator to perform a full scan.', $slug );
			}
		}

		if ( $warning ) {
			Admin::add_notice( [
				'message' => $message,
				'link_url' => $link_url,
				'link_anchor_text' => $link_anchor_text,
				'alert_level' => 'warning',
			] );
		} else {
			echo '<p>' . esc_html( $message );
			if ( $can_user_access_settings && ! $is_running ) {
				echo ' <a href="' . esc_url( $link_url ) . '" class="dashboard-start-scan">' . esc_html( $link_anchor_text ) . '</a>';
			}
			echo '</p>';
		}

	}

	/**
	 * Cancels the current scan
	 *
	 * @param string $notes
	 *
	 * @return void
	 */
	public function cancel( string $notes = '' ): void {

		if ( $this->is_paused() || $this->is_running() ){

			$current_site_id = ( is_multisite() ) ? get_current_blog_id() : 1;

			if ( FIXALTTEXT_HELPERSLIBRARY_CURRENT_SITE_ID == $current_site_id ) {
				// Only delete the lock if we are on the current site

				// Grab plugin's global
				global $fixalttext;

				if ( isset( $fixalttext['scan-process'] ) ) {
					if ( ! $fixalttext['scan-process']->can_we_start() ) {
						// Delete scan locks
						$fixalttext['scan-process']->cancel_process();
					}
				}
			}

			// Mark a scan as needed
			$scans = Scans::get_current( true );

			// Mark this specific kind of scan as needed
			$scans->set_needed( true, $this->get('type'), $this->get('portion'), $this->get('sub_portions') );

			$this->set_cancelled();
			$this->set_notes( $notes );

			// End date and time
			$this->set_end_date();

			// Saves this specific scan and the current state of the Scan Settings
			$this->save();
		}

	}

	/**
	 * Saves the values of this specific scan
	 *
	 * @return void
	 */
	public function save() : void {

		// Get the Scan Settings
		$scans = Scans::get_current();

		$scans->update_scan( $this );
	}

}