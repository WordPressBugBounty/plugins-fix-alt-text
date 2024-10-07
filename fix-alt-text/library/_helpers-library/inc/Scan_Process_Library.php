<?php
namespace FixAltText\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\Debug;
use FixAltText\Get;
use FixAltText\Menu;
use FixAltText\Run;
use FixAltText\Settings;
use FixAltText\Scan;
use FixAltText\Scans;
use FixAltText\Notification;
use FixAltText\Scan_Process;

/**
 * Class Scan_Process_Library
 *
 * Credits: This class was built from the plugin "WP Background Processing" by Delicious Brains Inc.
 * Due to the amount of code that was eventually overwritten, we decided remove the dependency and combine all the code into this one class.
 *
 * @link    https://github.com/A5hleyRich/wp-background-processing
 *
 * @package FixAltText\HelpersLibrary
 * @since   1.3.0
 */
class Scan_Process_Library {

	// Prefix for hooks
	protected $identifier;

	//These variables are going to be the classes in the scope of the plugin
	protected object $scan;

	/*
	 * The Scan ID is a MD5 hash of the details of the scan. We use this ID as a prefix for file names so that we can pull all the scan queue files easily
	 */
	protected string $scan_id;

	protected string $scan_type;
	protected string $scan_portion;
	protected array $scan_sub_portions;
	protected int $scan_specific;

	private string $queue_file_name;
	private string $queue_file_path;

	// Manage resource usage
	protected $start_time = 0;
	protected bool $time_exceeded = false;
	protected bool $memory_exceeded = false;

	// the queue broken into groups
	protected array $groups;

	// Store cache in options table temporarily
	public string $cache_status_codes_option;

	protected $cron_hook_identifier;
	protected $cron_interval_identifier;

	/**
	 * Initiate new background process
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	public function __construct() {

		$this->identifier = FIXALTTEXT_HOOK_PREFIX . 'scan';
		$this->cron_hook_identifier = $this->identifier . '_cron';
		$this->cron_interval_identifier = $this->cron_hook_identifier . '_interval';
		$this->cache_status_codes_option = $this->identifier . '_cache_status_codes';

		// Setting scope so that we can use the plugin's classes
		$scan = Scans::get_active_scan(true);

		if ( ! is_null( $scan ) ) {
			$this->scan = $scan;
		} else {
			$this->scan = Scans::get_recent_scan();
		}

		$this->set_scan_details();

		$this->set_hooks();
	}

	/**
	 * This caches a few scan values for convenience and speed
	 *
	 * @return void
	 */
	public function set_scan_details() : void {

		$this->scan_type = $this->scan->get('type') ?: 'full';
		$this->scan_portion = $this->scan->get('portion') ?: '';
		$this->scan_sub_portions = $this->scan->get('sub_portions') ?: [];
		$this->scan_specific = $this->scan->get('specific') ?: 0;

	}

	/**
	 * Setup background process: which always need to be running so that crons can be registered properly
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	public static function init(): void {

		// Grab plugin's global
		global $fixalttext;

		Debug::log( 'Scan_Process init' );
		//die();

		$fixalttext['scan-process'] = new Scan_Process();

		Debug::log( 'Scan_Process init done' );

	}

	/**
	 * Set the hooks
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	protected function set_hooks(): void {

		// start scan
		add_action( 'wp_ajax_' . $this->identifier . '_start', [
			$this,
			'try_to_start',
		], 10, 4 );

		// cancel scan
		add_action( 'wp_ajax_' . $this->identifier . '_cancel', [
			$this,
			'cancel',
		], 10, 0 );

		// pause scan
		add_action( 'wp_ajax_' . $this->identifier . '_pause', [
			$this,
			'pause',
		], 10, 0 );

		// resume scan
		add_action( 'wp_ajax_' . $this->identifier . '_resume', [
			$this,
			'resume',
		], 10, 0 );

		// display progress bar
		add_action( 'wp_ajax_' . $this->identifier . '_progress_bar', [
			static::class,
			'display_progress_bar',
		], 10, 0 );

		add_action( 'wp_ajax_' . $this->identifier, [
			$this,
			'maybe_handle',
		] );
		add_action( 'wp_ajax_nopriv_' . $this->identifier, [
			$this,
			'maybe_handle',
		] );

		// Crons
		add_action( $this->cron_hook_identifier, [
			$this,
			'handle_cron_healthcheck',
		] );
		add_filter( 'cron_schedules', [
			$this,
			'schedule_cron_healthcheck',
		] );

	}

	/**
	 * The interval of minutes that the cron runs
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	public function get_cron_interval(): int {

		// Grab the current time limit (default 20 seconds)
		$seconds = $this->get_time_limit();

		// Add 30 seconds to allow the server to breathe
		// 20 + 30 = 50 seconds
		$seconds += 30;

		// Convert to minutes
		// ceil( 50 / 60 ) = 1  minute
		$minutes = (int)ceil( $seconds / 60 );

		return $minutes;

	}

	/**
	 * Update content of the queue file
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	public function update( array $group ): void {

		$write_to_file = false;
		$file_path = $this->get_queue_file_path();

		if ( file_exists( $file_path ) ) {
			// Open the file for reading;
			$fh = fopen( $file_path, 'r+' );

			// Handle failure
			if ( $fh === false ) {
				Debug::log( 'Could not open file: ' . $file_path );
			} else {

				foreach ( $group as $line ) {
					$current_line = stream_get_line( $fh, 10240, self::get_end_marker() );

					// Strip extra spaces
					$current_line = trim( $current_line ?? '' );

					if ( $current_line == $line || $current_line . self::get_end_marker() == $line ) {
						// Move the pointer down one
						fgets( $fh );
						$write_to_file = true;
					} else {
						Debug::log( 'Could not find line: ' . $line . '!=' . $current_line );
					}
				}

				if ( $write_to_file ) {
					// Overwrite the file with everything after the current position of the pointer
					file_put_contents( $file_path, stream_get_contents( $fh ) );
				}

				// Close the file handle; when you are done using
				if ( fclose( $fh ) === false ) {
					Debug::log( 'Could not close file: ' . $file_path );
				}

			}
		}

	}

	/**
	 * Delete queue file for current scan
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	public function delete(): void {

		// Clear groups
		$this->groups = [];

		$file = $this->get_queue_file_path();

		Debug::log('deleting queue file: ' . $file);

		if ( file_exists( $file ) ) {
			// Delete queue file
			unlink( $file );
		} else {
			Debug::log('queue file does not exist: ' . $file);
		}

		// Clear file cache
		clearstatcache();

	}

	/**
	 * Retrieves an array of queue file paths located in the queue directory
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	public function get_queue_files(): array {

		$queue_dir = FIXALTTEXT_QUEUE_DIR;

		$queue_files = [];

		if ( $queue_dir && file_exists( $queue_dir ) ) {
			// Find all queue files
			$files = scandir( $queue_dir );

			Debug::log( print_r( $files, true ) );

			if ( ! empty( $files ) ) {
				foreach ( $files as $file ) {
					if ( '.' !== $file && '..' !== $file ){
						$queue_files[] = $queue_dir . '/' . $file;
					}
				}
			}
		}

		return $queue_files;
	}

	/**
	 * Gets the filename of the queue file
	 */
	public function get_queue_file_name() : string {

		if ( ! isset( $this->queue_file_name ) ) {

			$scan_type = $this->scan_type ?: 'full';
			$scan_portion = $this->scan_portion ?: 'all';

			// example template: siteID__type__portion__subportion:subportion__specificID.txt
			// example filename: 1__full__post-types__all__0.txt
			// example filename: 1__portion__post-types__posts:cpt_faq__0.txt
			$filename = [];
			$filename[] = FIXALTTEXT_HELPERSLIBRARY_CURRENT_SITE_ID;
			$filename[] = $scan_type;
			$filename[] = $scan_portion;
			$filename[] = Scan::convert_sub_portions_to_string( $this->scan_sub_portions);
			$filename[] = $this->scan_specific;

			// NOTICE: must use __ as delimiter as some CPT's use underscores and some use hyphens
			$this->queue_file_name = implode( '__', $filename ) . '.txt';

		}

		return $this->queue_file_name;

	}

	/**
	 * The path location of the queue file
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	public function get_queue_file_path(): string {

		if ( ! isset( $this->queue_file_path ) ) {
			$this->queue_file_path = FIXALTTEXT_QUEUE_DIR . '/' . $this->get_queue_file_name();
		}

		return $this->queue_file_path;

	}

	/**
	 * Is queue empty for this scan?
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	public function is_queue_empty(): bool {

		return ! $this->get_queue_count();

	}

	/**
	 * Starts an admin session, so we have proper access to scan everything
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.1
	 */
	private function start_admin_session(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			// Get admin users
			$admin_users = get_users( [ 'capability__in' => [ 'manage_options' ] ] );

			if ( ! empty( $admin_users ) ) {

				// Check If First Admin User Exists
				if ( ! empty( $admin_users[0]->ID ) ) {

					// 1 hour expiration
					$expiration = time() + 3600;

					// Include class WP_Session_Tokens
					require_once( ABSPATH . '/wp-includes/class-wp-session-tokens.php' );

					// Create token
					$manager = \WP_Session_Tokens::get_instance( $admin_users[0]->ID );
					$token = $manager->create( $expiration );

					// Set login cookies
					$_COOKIE['wordpress_test_cookie'] = 'WP Cookie check';
					$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $admin_users[0]->ID, $expiration, 'logged_in', $token );

				} else {
					Debug::log( 'this should not happen - no admin user' );
				}
			}
		}

	}

	/**
	 * Handle
	 *
	 * Pass each queue item to the task handler, while remaining
	 * within server memory and time limit constraints.
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	public function handle(): void {

		// Prevent object caching by WP
		Run::prevent_caching(true);

		// Increase the default memory limit to use WP_MAX_MEMORY_LIMIT
		wp_raise_memory_limit( 'admin' );

		Debug::log( __( 'Running Background Scan', FIXALTTEXT_SLUG ) );

		$this->lock_process();

		// Ensure we have admin access
		$this->start_admin_session();

		if ( ! $this->is_queue_empty() ) {

			// Process everything

			if ( ! $this->scan_type || ! in_array( $this->scan_type, Scans::get_types() ) ) {
				Debug::log( __( 'ERROR: Aborting scan. Scan type not valid: ' . $this->scan_type, FIXALTTEXT_SLUG ) );

				return;
			}

			if ( $this->scan_portion && ! in_array( $this->scan_portion, Scans::get_portions() ) ) {
				Debug::log( __( 'ERROR: Aborting scan. Scan portion not valid: ' . $this->scan_portion, FIXALTTEXT_SLUG ) );

				return;
			}

			if ( file_exists( $this->get_queue_file_path() ) ) {

				if( 'full' === $this->scan_type ){

					$portions = Scans::get_portions();

					// Flag used to check to see if we need to stop midway in a full scan
					$continue = true;

					foreach ( $portions as $portion ) {

						if ( ! in_array( $portion, [ 'all', 'status'] ) ) {
							if ( $continue ) {
								$continue = $this->process( $portion );
							} else {
								Debug::log( 'Stopping Scan...' );
							}
						}

					}
				} elseif( 'portion' === $this->scan_type ){

					$this->process( $this->scan_portion );

				} else {

					// Assuming Specific Scan: process all statuses that have been marked for updating in the database
					$this->process( 'statuses' );
				}

			} else {

				Debug::log( __( 'ERROR: Queue file not found: ' . $this->get_queue_file_path(), FIXALTTEXT_SLUG ) );

			}

		}

		Debug::log( __( 'Unlocking Scan', FIXALTTEXT_SLUG ) );

		$this->unlock_process();

		if ( ! $this->time_exceeded() && ! $this->memory_exceeded() && ! $this->scan->is_paused() ) {

			// Start next batch or complete process.
			if ( ! $this->is_queue_empty() ) {
				Debug::log( __( 'Continuing Background Scan', FIXALTTEXT_SLUG ) );
				$this->dispatch();
			} else {
				Debug::log( __( 'Scan: Queue is empty. [1]', FIXALTTEXT_SLUG ) );
				$this->complete();
			}

		} else {

			if ( ! $this->scan->is_paused() ) {
				// Ran out of resources, let's check to see if we actually finished

				Debug::log( __( 'Scan: Out of resources.', FIXALTTEXT_SLUG ) );

				if ( $this->is_queue_empty() ) {
					Debug::log( __( 'Scan: Queue is empty. [2]', FIXALTTEXT_SLUG ) );
					$this->complete();
				}
			}

		}

		wp_die();
	}

	/**
	 * Gets the group size for a specific portion
	 */
	public function get_group_size( string $scan_portion ): int {

		$group_sizes = [
			'post-types' => 5,
			'users' => 10,
			'taxonomies' => 10,
			'status' => 1,
			'statuses' => 5,
			'menus' => 1,
		];

		$group_size = isset( $group_sizes[ $scan_portion ] ) ? $group_sizes[ $scan_portion ] : 5;

		/**
		 * Modify the size of the group. The greater the group the fewer DB queries, BUT it will take longer to run and risk possibly timing out with PHP.
		 * WARNING: Modifying these group sizes could potentially cause high server load. Make sure you know what you are doing.
		 */
		$group_size = apply_filters( $this->identifier . '_group_size', $group_size, $scan_portion );

		// Governor to prevent people from being reckless
		if ( $group_size > 50 ) {
			// Limit group size to 50
			$group_size = 50;
		}

		return $group_size;
	}

	public static function get_end_marker() : string {

		return '````';

	}

	public static function get_delimiter_marker() : string {

		return '```';

	}

	/**
	 * Groups the array into chunks for better efficiency of SQL queries and memory usage
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.3
	 */
	public function get_group( string $portion ): array {

		$group = [];

		// Queue File path
		$file_path = $this->get_queue_file_path();

		// Grab the first X lines of the queue file
		if ( file_exists( $file_path ) ) {

			$group_size = static::get_group_size( $portion );

			Debug::log( 'Group size: ' . $group_size . ' - Queue File: ' . $file_path );

			// Open the file for reading;
			$handle = fopen( $file_path, 'r+' );

			// Handle failure
			if ( $handle === false ) {
				Debug::log( 'Could not open file: ' . $file_path );
			} else {
				$num = 1;
				$flag = $this->get_prepend_flag( $portion );

				// Grab the current line of the queue file
				$current_line = stream_get_line( $handle, 10240, self::get_end_marker() );

				// Strip extra spaces
				$current_line = trim( $current_line ?? '' );

				if ( $current_line . self::get_end_marker() == $flag ) {
					// Placeholder goes into group
					$group[] = $flag;
				} else {
					// Build group based on group size
					while ( $num <= $group_size && $current_line ) {

						/**
						 * $line[0] = ID or URL
						 * $line[1] = portion: post-types, users, menus, taxonomies...etc.
						 * $line[2] = post_type (optional)
						 */
						$line = explode(self::get_delimiter_marker(), $current_line);

						// Strip extra spaces
						$line[1] = trim( $line[1] ?? '' );

						if ( $portion === $line[1] ) {

							// Strip extra spaces
							$current_line = trim( $current_line ?? '' );

							$group[] = $current_line;

							// move pointer
							fgets( $handle );

							// Grab the current line of the queue file
							$current_line = stream_get_line( $handle, 10240, self::get_end_marker() );

						}

						++ $num;
					}
				}

				fclose( $handle );

				if ( ! $current_line ) {
					// Reached end of queue file. Delete file.
					if ( file_exists( $file_path ) ) {
						// Delete queue file
						unlink( $file_path );
					}
				}

			}
		} else {
			Debug::log( 'File does not exist!: ' . $file_path );
		}

		Debug::log( 'Group: ' . print_r( $group, true ) );

		return $group;

	}

	/**
	 * Process the queue
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.3
	 */
	protected function process( string $portion ): bool {

		global $wpdb;

		Debug::log( sprintf( __( 'Scan: Processing %s', FIXALTTEXT_SLUG ), $this->scan_type . ' ' . $portion ) );

		if ( $this->time_exceeded() || $this->memory_exceeded() ) {
			Debug::log( sprintf( __( 'Scan: Resources exceeded. Aborting %s scan.', FIXALTTEXT_SLUG ), $this->scan_type ) );

			// Tell next process to NOT continue
			return false;
		}

		$group = $this->get_group( $portion );

		// Skip if we have no groups
		if ( empty( $group ) ) {
			Debug::log( sprintf( __( 'Scan: Nothing to scan. Aborting %s scan.', FIXALTTEXT_SLUG ), $this->scan_type ) );

			// Tell next process to continue
			return true;
		}

		$tables = [
			'post-types' => $wpdb->prefix . 'posts',
			'taxonomies' => $wpdb->prefix . 'term_taxonomy',
			'users' => $wpdb->prefix . 'users',
			'menus' => $wpdb->prefix . 'term_taxonomy',
			'statuses' => Get::table_name()
		];

		$table = $tables[ $portion ] ?? '';

		$flag = $this->get_prepend_flag( $portion );

		while ( ! empty( $group ) ) {

			$sql = '';
			$first_queue = $group[0] ?? '';

			if ( $first_queue !== $flag ) {

				Debug::log( 'Process Group: ' . $first_queue . ' != ' . $flag);

				if ( 'menus' !== $portion && 'statuses' !== $portion ) {
					// Create a SQL query

					$values = [];
					$values[] = $table;

					$placeholders = [];

					foreach ( $group as $value ) {
						// Grab the queue ID or URL
						$queue = strstr( $value, '```', true );

						if ( false === $queue ) {
							// Didn't find delimiter
							$queue = $value ?? '';

							// Strip extra spaces
							$queue = trim( $queue ?? '' );

						}

						$values[] = $queue;
						$placeholders[] = '%' . count( $values ) . '$s';
					}

					// Grab terms from DB - Use a custom query so that it's faster and uses less memory
					if ( 'post-types' == $portion ) {
						$sql = $wpdb->prepare( 'SELECT * FROM `%1$s` WHERE `ID` IN ( ' . implode( ',', $placeholders ) . ');', $values );
					} elseif ( 'taxonomies' == $portion ) {
						$sql = $wpdb->prepare( 'SELECT `term_id`, `taxonomy`, `description` FROM `%1$s` WHERE `term_id` IN ( ' . implode( ',', $placeholders ) . ')', $values );
					} elseif ( 'users' == $portion ) {
						$sql = $wpdb->prepare( 'SELECT * FROM `%1$s` WHERE `ID` IN (' . implode( ",", $placeholders ) . ');', $values );
					} else {
						$sql = apply_filters( $this->identifier . '_process_' . $portion . '_sql', false );
					}

					if ( $sql ) {
						// Grab results via mysqli
						$mysqli = Get::db_connection();
						$result = $mysqli->query( $sql );
					} else {
						Debug::log( 'Error!: We do not have an SQL query for portion: ' . $portion );
					}
				} else {
					// The group is the results
					$result = $group;
				}

				// Process each object
				if ( $result ) {

					// Set the object
					if ( $sql ) {
						// Grab next row
						$object = $result->fetch_object();
					} else {
						// Grab next array entry
						$object = array_shift( $result );
					}

					while ( $object ) {

						// Return false on this filter if you want to skip this particular object
						if ( apply_filters( $this->identifier . '_process_' . $portion, $object ) ) {

							if ( 'taxonomies' == $portion ) {
								Debug::log( sprintf( __( 'Scanning term ID: %d', FIXALTTEXT_SLUG ), $object->term_id ) );
								//Debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
								$this->scan::scan_term( $object, FIXALTTEXT_HELPERSLIBRARY_CURRENT_SITE_ID, $this->cache_status_codes_option );
								Debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
							} elseif ( 'post-types' == $portion ) {
								Debug::log( sprintf( __( 'Scanning post ID: %d', FIXALTTEXT_SLUG ), $object->ID ) );
								//Debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
								$this->scan::scan_post( $object, FIXALTTEXT_HELPERSLIBRARY_CURRENT_SITE_ID, $this->cache_status_codes_option );
								Debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
							} elseif ( 'users' == $portion ) {
								Debug::log( sprintf( __( 'Scanning user ID: %d', FIXALTTEXT_SLUG ), $object->ID ) );
								//Debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
								$this->scan::scan_user( $object, FIXALTTEXT_HELPERSLIBRARY_CURRENT_SITE_ID, $this->cache_status_codes_option );
								Debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
							} elseif ( 'statuses' == $portion ) {
								if ( Get::is_real_url( $object ) ) {
									Debug::log( sprintf( __( 'Scanning URL ID: %s', FIXALTTEXT_SLUG ), $object ) );
									//Debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' )
									$this->scan::status_check_update( $object, [], $this->cache_status_codes_option );
									Debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
								}
							} elseif ( 'menus' == $portion ) {
								$menu = Menu::get_by_id( (int) $object );
								if ( $menu ) {
									Debug::log( sprintf( __( 'Scanning menu ID: %d', FIXALTTEXT_SLUG ), $object ) );
									//Debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
									$this->scan::scan_menu( $menu, FIXALTTEXT_HELPERSLIBRARY_CURRENT_SITE_ID, $this->cache_status_codes_option );
									Debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
								}
							}
						}

						// Set the object
						if ( $sql ) {
							// Grab next row
							$object = $result->fetch_object();
						} else {
							// Grab next array entry
							$object = array_shift( $result );
						}

					}

					if ( $sql ) {
						// Clear mysqli memory
						$result->free_result();
					}

				}

			} else {
				Debug::log( 'Found flag: ' . $flag );
			}

			if ( $this->update_progress( $group, $portion ) ) {

				// Process done or cancelled
				Debug::log( __( 'Stopping process.', FIXALTTEXT_SLUG ) );

				break;
			}

			// Refresh the group
			$group = $this->get_group( $portion );

		}

		// Update the progress one last time
		$this->update_progress( $group, $portion );

		// Tell next process to continue
		return true;

	}

	/**
	 * Updates the progress for the batch data. Return true if process should stop.
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	private function update_progress( array $group, string $portion = '' ): bool {

		Debug::log( sprintf( __( 'Updating progress for %s...', FIXALTTEXT_SLUG ), $this->scan_type ) );

		// Should we stop the scan process?
		$stop = false;

		$this->scan = Scans::get_recent_scan();

		Debug::log( 'Updating Scan: ' . print_r( $this->scan, true ) );

		if ( $portion ) {
			$this->scan->set_currently( ucfirst( str_replace( [
				'-',
				'_',
			], ' ', $portion ) ) );
		}

		// Update the queue with the new numbers
		$this->update( $group );

		$queue_count = $this->get_queue_count();

		// Update stored scan progress
		$this->scan->update_progress( $queue_count );

		// Update or delete current batch.
		if ( $queue_count ) {

			Debug::log( __( 'We still have items in the queue.', FIXALTTEXT_SLUG ) );

			if( $this->scan->is_paused() ) {
				// The scan has been paused
				Debug::log( __( 'Scan paused...stopping process.', FIXALTTEXT_SLUG ) );

				// Clear Status Code Cache
				delete_option( $this->cache_status_codes_option );

				$stop = true;
			} else if ( $this->scan->get( 'cancelled' ) ) {
				// The scan has been cancelled
				Debug::log( __( 'Scan cancelled...stopping process.', FIXALTTEXT_SLUG ) );

				// Delete the queue
				$this->delete();

				// Clear Status Code Cache
				delete_option( $this->cache_status_codes_option );

				$stop = true;
			} elseif ( $this->time_exceeded() || $this->memory_exceeded() ) {
				// Batch limits reached.
				Debug::log( __( 'Resource limits reached...cool down process.', FIXALTTEXT_SLUG ) );
				$stop = true;
			}

		} else {

			Debug::log( __( 'No queue count.', FIXALTTEXT_SLUG ) );

		}

		return $stop;

	}

	/**
	 * Counts the number of lines in all the queue files
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	public function get_queue_count(): int {

		$count = 0;

		// Grab counts directly from the queue files

		$queue_file = $this->get_queue_file_path();

		if ( $queue_file && file_exists( $queue_file ) ) {
			// count lines of each file

			$stream = fopen( $queue_file, "r" );

			$line_count = 0;
			while ( ! feof( $stream ) ) {
				// Grab next line of file
				fgets( $stream );
				$line_count ++;
			}

			fclose( $stream );

			if ( $line_count ) {
				$count += $line_count;
			} else {
				// File is empty; remove it
				unlink( $queue_file );
				clearstatcache();
			}
		}

		Debug::log( sprintf( __( 'Queue count: %d', FIXALTTEXT_SLUG ), $count ) );

		return $count;
	}

	/**
	 * Complete
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	protected function complete(): void {

		// Unschedule the cron healthcheck.
		$this->clear_scheduled_event();

		Debug::log( __( 'Scan Complete', FIXALTTEXT_SLUG ) );

		// Mark the scan as complete
		$this->scan->set_currently(''); // Clear currently scanning status
		$this->scan->set_end_date(); // Add end date
		$this->scan->save();

		$cancelled = $this->scan->get( 'cancelled' );

		$scans = Scans::get_current();

		if ( 'full' === $this->scan_type ) {
			if ( $cancelled > 0 ) {
				$user = get_user_by( 'ID', $cancelled );

				Notification::add_notification( [
					'message' => sprintf( __( 'A full scan has been cancelled by %s. It is recommended to run a full scan uninterrupted.', FIXALTTEXT_SLUG ), $user->display_name ),
					'link_url' => FIXALTTEXT_ADMIN_URL . '#scan',
					'link_anchor_text' => __( 'View Previous Scan Details', FIXALTTEXT_SLUG ),
					'alert_level' => 'warning',
				] );

				$scans->set_needed( true, $this->scan_type, '', [] );
				$scans->save();
			} else {
				Notification::add_notification( [
					'message' => __( 'A full scan has completed.', FIXALTTEXT_SLUG ),
					'link_url' => FIXALTTEXT_ADMIN_URL . '#scan',
					'link_anchor_text' => __( 'View Scan Details', FIXALTTEXT_SLUG ),
					'alert_level' => 'success',
				] );

				// Scan not needed
				$scans->set_needed( false, '', '', [] );
				$scans->save();
			}
		} elseif ( 'portion' === $this->scan_type ) {
			// Status check scan

			if ( $cancelled > 0 ) {
				$user = get_user_by( 'ID', $cancelled );

				Notification::add_notification( [
					'message' => sprintf( __( 'The %s scan has been cancelled by %s.', FIXALTTEXT_SLUG ), 'statuses' === $this->scan_portion, $user->display_name ),
					'link_url' => FIXALTTEXT_ADMIN_URL . '#scan',
					'link_anchor_text' => __( 'View Previous Scan Details', FIXALTTEXT_SLUG ),
					'alert_level' => 'warning',
				] );

				// Scan needed since it was cancelled
				$scans->set_needed( true, $this->scan_type, $this->scan_portion, $this->scan_sub_portions );
				$scans->save();
			} else {

				$message = sprintf( __( 'The portion:%s scan has completed.', FIXALTTEXT_SLUG ), $this->scan_portion );

				if ( wp_doing_cron() ) {
					$message = 'WPCron: ' . $message;
				}

				Notification::add_notification( [
					'message' => $message,
					'link_url' => FIXALTTEXT_ADMIN_URL . '#scan',
					'link_anchor_text' => __( 'View Scan Details', FIXALTTEXT_SLUG ),
					'alert_level' => 'success',
				] );

				if ( $scans->get( 'type' ) == $this->scan_type && $scans->get( 'portion' ) == $this->scan_portion && $scans->get( 'sub_portions' ) == $this->scan_sub_portions ) {
					// The needed scan was performed and completed so mark no scan needed
					$scans->set_needed( false, '', '', [] );
					$scans->save();
				}
			}
		}

		// Clear Status Code Cache
		delete_option( $this->cache_status_codes_option );

		// Delete the queue file
		$this->delete();

	}

	/**
	 * Determine if we can actually start this scan IF process is not running and queue is empty
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	public function can_we_start(): bool {

		$return = true;

		if ( $this->is_process_running() ) {

			Debug::log( __( 'Cannot start a scan due to current running process. Try again later.', FIXALTTEXT_SLUG ) );
			$return = false;

		}

		return $return;
	}

	/**
	 * Tries to start a scan
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	public function try_to_start( string $scan_type = '', string $scan_portion = '', array $scan_sub_portions = [], int $scan_specific = 0, bool $force = false ): void {

		Debug::log( 'try_to_start' );

		$scan_type = $scan_type ?: REQUEST::key( 'scan_type', '', 'full' );
		$scan_portion = $scan_portion ?: REQUEST::key( 'scan_portion', '', 'all' );
		$scan_sub_portions = $scan_sub_portions ?: Scan::convert_sub_portions_to_array( REQUEST::text_field( 'scan_sub_portions' ) );
		$scan_specific = $scan_specific ?: REQUEST::int( 'scan_specific' );

		Debug::log( 'try_to_start: ' . $scan_type . ' ' . $scan_portion . ' ' . Scan::convert_sub_portions_to_string($scan_sub_portions) . ' ' . $scan_specific );

		$current_user_id = get_current_user_id();
		$settings = Settings::get_current_settings();

		if ( ! in_array( $scan_type, Scans::get_types() ) ) {

			$response = FIXALTTEXT_SLUG . ' - ' . sprintf( __( 'Invalid scan type: %s', FIXALTTEXT_SLUG ), $scan_type );

			Debug::log( $response, 'error' );
			error_log( $response );

			if ( $force ) {
				echo esc_html( $response );
			} else {
				wp_send_json( [ 'html' => $response ], 403 );
			}

		} elseif ( ! $force && ! wp_verify_nonce( REQUEST::text_field( 'nonce' ), FIXALTTEXT_SLUG . '-start-scan-' . $current_user_id ) ) {

			// Require nonce specific to this user

			$response = FIXALTTEXT_SLUG . ' - ' . __( 'Valid nonce required to start scan.', FIXALTTEXT_SLUG );

			Debug::log( $response, 'error' );
			error_log( $response );

			if ( $force ) {
				echo esc_html( $response );
			} else {
				wp_send_json( [ 'html' => $response ], 401 );
			}

		} elseif ( ! $force && ! $settings->can_user_access_settings() ) {

			// Only users who have access to settings can initiate a scan

			$response = FIXALTTEXT_SLUG . ' - ' . __( 'User must have ability to access settings to start a scan.', FIXALTTEXT_SLUG );

			Debug::log( $response, 'error' );
			error_log( $response );

			wp_send_json( [ 'html' => $response ], 401 );

		} elseif ( $this->can_we_start() ) {

			Debug::log( 'Starting scan: ' . $scan_type . '=>' . $scan_portion );

			// start scan
			$this->start( $scan_type, $scan_portion, $scan_sub_portions, $scan_specific );

		} else {

			// Already a scan running somewhere

			Debug::log( 'Already a scan running somewhere' );

			$sites = Get::sites();

			if ( is_multisite() ) {

				foreach ( $sites as $site ) {

					switch_to_blog( $site->blog_id );

					if ( is_plugin_active( FIXALTTEXT_PLUGIN ) ) {

						// Get scan settings
						$scan = Scans::get_active_scan(true);

						if ( ! is_null( $scan ) && $scan->is_running() ) {

							// We found the scan that is actively running
							break;
						}

					}

					restore_current_blog();
				}

			} else {

				// Single Site
				$site = $sites[0];

				// Get scan settings
				$scan = $this->scan;

			}

			if ( $scan->get( 'start_date' ) ) {
				// We have a scan running

				$started_by = $scan->get_started_by();

				$done = $scan->get( 'progress' );
				$total = $scan->get( 'progress_total' );
				$percent = ( $total ) ? round( ( $done / $total ) * 100, 1 ) : 0;

				ob_start();

				if ( is_multisite() ) {

					$message = sprintf( __( 'There is already a scan running on %s. Please wait until it is finished or cancel the scan.', FIXALTTEXT_SLUG ), $site->domain );

					echo '<p>' . esc_html( $message ) . '</p>';
				} else {

					$message = __( 'There is already a scan running. Please wait until it is finished or cancel the scan.', FIXALTTEXT_SLUG );

					echo '<p>' . esc_html( $message ) . '</p>';
				}

				Debug::log( $message, 'warning' );

				echo '<h3>' . esc_html__( 'Scan Details', FIXALTTEXT_SLUG ) . '</h3>';
				echo '<ul>';

				if ( is_multisite() ) {
					echo '<li><b>' . esc_html__( 'Site', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $site->domain ) . '</li>';
				}

				echo '<li><b>' . esc_html__( 'Type', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $scan->get( 'type' ) ) . '</li>';
				echo '<li><b>' . esc_html__( 'Start Date', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $scan->get( 'start_date' ) ) . '</li>';
				echo '<li><b>' . esc_html__( 'Started By', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $started_by ) . '</li>';
				echo '<li><b>' . esc_html__( 'Progress', FIXALTTEXT_SLUG ) . ':</b> ' . esc_html( $percent ) . '%</li>';
				echo '</ul>';

				if ( $force ) {
					echo ob_get_clean();
				} else {
					wp_send_json( [ 'html' => ob_get_clean() ] );
				}

			} else {
				// Attempt to start again

				// Delete all batches bc we know there isn't a scan actually running
				$this->delete_all_scan_batches();

				// Clear queue
				$this->delete();

				if ( $this->can_we_start() ) {

					// start scan
					$this->start( $scan_type, $scan_portion, $scan_sub_portions, $scan_specific );

				} else {

					// Unknown scenario

					$message = __( 'This should never happen. If you are seeing this error, please contact the developers so we can fix this.', FIXALTTEXT_SLUG );

					Debug::log( $message, 'error' );

					echo '<p>' . esc_html( $message ) . '</p>';

					if ( $force ) {
						echo ob_get_clean();
					} else {
						wp_send_json( [ 'html' => ob_get_clean() ] );
					}

				}
			}

		}

	}

	/**
	 * Memory exceeded
	 *
	 * Ensures the batch process never exceeds 90%
	 * of the maximum WordPress memory.
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	protected function memory_exceeded(): bool {

		if ( ! $this->memory_exceeded ) {
			$memory_limit = $this->get_memory_limit() * 0.8; // 80% of max memory
			$current_memory = memory_get_usage( true );

			if ( $current_memory >= $memory_limit ) {
				$this->memory_exceeded = true;

				$message = sprintf( __( 'The %s scan ran out of memory resources (%s of %s). We will attempt the scan again in 1 minute', FIXALTTEXT_SLUG ), $this->scan_type, $current_memory, $memory_limit );

				Debug::log( $message, 'warning' );

				Notification::add_notification( [
					'message' => $message,
					'alert_level' => 'warning',
				] );
			}
		}

		return $this->memory_exceeded;
	}

	/**
	 * Time exceeded.
	 *
	 * Ensures the batch never exceeds a sensible time limit.
	 * A timeout limit of 30s is common on shared hosting.
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	protected function time_exceeded(): bool {

		if ( ! $this->time_exceeded ) {
			$finish = $this->start_time + $this->get_time_limit();

			if ( time() >= $finish ) {
				Debug::log( __( 'Time limit exceeded...pausing process. Process will continue in a few minutes.', FIXALTTEXT_SLUG ) );
				$this->time_exceeded = true;
			}
		}

		return apply_filters( $this->identifier . '_time_exceeded', $this->time_exceeded );
	}

	/**
	 * The number of seconds that the scan can run before pausing.
	 *
	 * @note    Warning: The default value is 20 seconds to accommodate shared hosting. Any modification of this number can lead to higher CPU usage. Change at your own risk.
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 */
	public function get_time_limit(): int {

		return apply_filters( $this->identifier . '_default_time_limit', 20 );

	}

	/**
	 * Get memory limit
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.3.0
	 */
	protected function get_memory_limit(): int {

		if ( defined( 'WP_MAX_MEMORY_LIMIT' ) ) {

			$memory_limit = WP_MAX_MEMORY_LIMIT;
			Debug::log( 'Memory LIMIT WP_MAX_MEMORY_LIMIT - ' . $memory_limit );

		} elseif ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
			Debug::log( 'Memory LIMIT ini_get - ' . $memory_limit );
		} else {
			// Sensible default.
			$memory_limit = '64M';
			Debug::log( 'Memory LIMIT default - ' . $memory_limit );
		}

		if ( ! $memory_limit || - 1 === intval( $memory_limit ) ) {
			// Unlimited, set to 16GB.
			$memory_limit = '16000M';
			Debug::log( 'Memory LIMIT MAX - ' . $memory_limit );
		}

		return wp_convert_hr_to_bytes( $memory_limit );
	}

	/**
	 * Returns the identifier property
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.1.0
	 */
	public function get_identifier(): string {

		return $this->identifier;

	}

	/**
	 * Push to queue
	 *
	 * @package FixAltText\HelpersLibrary
	 * @since   1.2.0
	 */
	public function push_to_queue( string $sql, string $scan_portion = 'all' ): void {

		Debug::log( 'sql: ' . $sql, 'notice: ' . $scan_portion );

		$mysqli = Get::db_connection();
		$result = $mysqli->query( $sql );

		Debug::log( 'result: ' . print_r( $result, true ) );

		if ( $result ) {
			$file_path = $this->get_queue_file_path();

			if ( file_exists( $file_path ) ) {
				// Add to existing file
				$fh = fopen( $file_path, 'a' );
			} else {
				// Start new file
				$fh = fopen( $file_path, 'w' );
			}

			if ( $fh === false ) {
				Debug::log( 'Could not open file: ' . $file_path );
			} else {

				// Add prepend flag
				fwrite( $fh, $this->get_prepend_flag($scan_portion) . "\n" );

				while ( $row = $result->fetch_object() ) {


					$queue = [];

					if ( $row->queue ) {

						// An ID or URL
						$queue[] = $row->queue;

						$queue[] = $scan_portion;

						$description = $row->description ?? '';

						if ( 'users' != $scan_portion && $description ) {
							// Adds more context to what is getting scanned
							$queue[] = $description;
						}

						//Debug::log( 'adding to queue: ' . print_r( $queue, true ) );

						fwrite( $fh, implode( self::get_delimiter_marker(), $queue ) . self::get_end_marker() . "\n" );
					}
				}

				// Close the file handle; when you are done using
				if ( fclose( $fh ) === false ) {
					Debug::log( 'Could not close file: ' . $file_path );
				}
			}

			// Clear mysqli memory
			$result->free_result();

		} else {
			Debug::log( 'no results' );
		}

	}

	/**
	 * Get query args array
	 */
	protected function get_query_args() : array {
		if ( property_exists( $this, 'query_args' ) ) {
			return $this->query_args;
		}

		$args = [
			'action' => $this->identifier,
			'nonce' => wp_create_nonce( $this->identifier ),
		];

		/**
		 * Filters the post arguments during an async request.
		 *
		 * @param array $url
		 */
		return apply_filters( $this->identifier . '_query_args', $args );
	}

	/**
	 * Get post args array
	 */
	protected function get_post_args() : array {
		if ( property_exists( $this, 'post_args' ) ) {
			return $this->post_args;
		}

		$args = [
			'timeout' => 0.01,
			'blocking' => false,
			'body' => '',
			'cookies' => $_COOKIE,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		];

		/**
		 * Filters the post arguments during an async request.
		 *
		 * @param array $args
		 */
		return apply_filters( $this->identifier . '_post_args', $args );
	}

	/**
	 * Generate key
	 *
	 * Generates a unique key based on microtime. Queue items are
	 * given a unique key so that they can be merged upon save.
	 */
	protected function generate_key( int $length = 64 ): string {
		$unique = md5( microtime() . rand() );
		$prepend = $this->identifier . '_batch_';

		return substr( $prepend . $unique, 0, $length );
	}

	/**
	 * Maybe process queue
	 *
	 * Checks whether data exists within the queue and that
	 * the process is not already running.
	 */
	public function maybe_handle(): void {

		// Don't lock up other requests while processing
		session_write_close();

		Debug::log( 'Attempting to start background scan process.' );

		if ( $this->is_process_running() ) {
			// Background process already running.
			Debug::log( 'Process already running. Abort.' );
			wp_die();
		}

		Debug::log('This scan portion:'. print_r($this->scan, true));

		if ( $this->scan->is_paused() ) {
			// Scan is paused.
			Debug::log( 'Scan is paused. Abort.' );
			wp_die();
		}

		check_ajax_referer( $this->identifier, 'nonce' );

		Debug::log( 'Memory - (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );

		$this->handle();

		wp_die();
	}

	/**
	 * Is process running
	 *
	 * Check whether the current process is already running
	 * in a background process.
	 */
	protected function is_process_running(): bool {
		if ( get_site_transient( $this->identifier . '_process_lock' ) ) {
			// Process already running.
			return true;
		}

		return false;
	}

	/**
	 * Lock process
	 *
	 * Lock the process so that multiple instances can't run simultaneously.
	 * Override if applicable, but the duration should be greater than that
	 * defined in the time_exceeded() method.
	 */
	protected function lock_process() : void {
		$this->start_time = time(); // Set start time of current process.

		$lock_duration = ( property_exists( $this, 'queue_lock_time' ) ) ? $this->queue_lock_time : 60; // 1 minute
		$lock_duration = apply_filters( $this->identifier . '_queue_lock_time', $lock_duration );

		set_site_transient( $this->identifier . '_process_lock', microtime(), $lock_duration );
	}

	/**
	 * Unlock process
	 *
	 * Unlock the process so that other instances can spawn.
	 */
	protected function unlock_process(): void {
		delete_site_transient( $this->identifier . '_process_lock' );
	}

	/**
	 * Cancel Process
	 *
	 * Stop processing queue items, clear cronjob and delete batch.
	 *
	 */
	public function cancel_process(): void {

		$this->delete();

		wp_clear_scheduled_hook( $this->cron_hook_identifier );

	}

	/**
	 * Pause Process
	 *
	 * clear cronjob
	 *
	 */
	public function pause_process(): void {

		wp_clear_scheduled_hook( $this->cron_hook_identifier );

	}

	/**
	 * Cancels the current scan running in the background
	 */
	public function cancel(): void {

		// Require nonce specific to this user
		if ( ! wp_verify_nonce( REQUEST::text_field( 'nonce' ), FIXALTTEXT_SLUG . '-cancel-scan-' . get_current_user_id() ) ) {

			$response = __( 'Valid nonce required to cancel a scan.', FIXALTTEXT_SLUG );
			Debug::log( $response, 'error' );

			wp_send_json( [ 'html' => $response ], 401 );

		} else {

			$scan_type = REQUEST::text_field( 'scan_type' );
			$scan_portion = REQUEST::text_field( 'scan_portion' );
			$scan_sub_portions = Scan::convert_sub_portions_to_array( REQUEST::text_field( 'scan_sub_portions' ) );
			$scan_specific = REQUEST::int( 'scan_specific' );

			ob_start();

			$this->scan = Scans::get_recent_scan( $scan_type, $scan_portion, $scan_sub_portions, $scan_specific );

			$type = ucwords( str_replace( '-', ' ', $this->scan->get( 'type' ) ) );

			$notes = sprintf( __( '%s scan was manually cancelled.', FIXALTTEXT_SLUG ), $type );
			$this->scan->cancel( $notes );

			Debug::log( $notes );

			echo '<p>' . esc_html( sprintf( __( '%s scan was manually cancelled. This dashboard will refresh in 3 seconds.' ), $type ) ) . '</p>';
			echo '<script>setTimeout(function () { window.location.href = "' . esc_url( FIXALTTEXT_ADMIN_URL ) . '";}, 3000);</script>';

			wp_send_json( [ 'html' => ob_get_clean() ] );

		}

	}

	/**
	 * Cancels the current scan running in the background
	 */
	public function pause(): void {

		// Require nonce specific to this user
		if ( ! wp_verify_nonce( REQUEST::text_field( 'nonce' ), FIXALTTEXT_SLUG . '-pause-scan-' . get_current_user_id() ) ) {

			$response = __( 'Valid nonce required to pause a scan.', FIXALTTEXT_SLUG );
			Debug::log( $response, 'error' );

			wp_send_json( [ 'html' => $response ], 401 );

		} else {

			$scan_type = REQUEST::text_field( 'scan_type' );
			$scan_portion = REQUEST::text_field( 'scan_portion' );
			$scan_sub_portions = Scan::convert_sub_portions_to_array( REQUEST::text_field( 'scan_sub_portions' ) );
			$scan_specific = REQUEST::int( 'scan_specific' );

			ob_start();

			$this->scan = Scans::get_recent_scan( $scan_type, $scan_portion, $scan_sub_portions, $scan_specific );

			$type = ucwords( str_replace( '-', ' ', $this->scan->get( 'type' ) ) );

			$notes = sprintf( __( '%s scan was manually paused.', FIXALTTEXT_SLUG ), $type );
			$this->scan->pause( $notes );

			Debug::log( $notes );

			$progress = $this->scan->get( 'progress' );
			$total = $this->scan->get( 'progress_total' );

			$percent = ( $total ) ? round( ( $progress / $total ) * 100, 1 ) : 0;

			static::display_paused_progress_bar( $percent, $type );
			static::display_scan_controls( $this->scan );

			wp_send_json( [ 'html' => ob_get_clean() ] );

		}

	}

	/**
	 * Cancels the current scan running in the background
	 */
	public function resume(): void {

		// Require nonce specific to this user
		if ( ! wp_verify_nonce( REQUEST::text_field( 'nonce' ), FIXALTTEXT_SLUG . '-resume-scan-' . get_current_user_id() ) ) {

			$response = __( 'Valid nonce required to resume a scan.', FIXALTTEXT_SLUG );
			Debug::log( $response, 'error' );

			wp_send_json( [ 'html' => $response ], 401 );

		} else {

			$scan_type = REQUEST::text_field( 'scan_type' );
			$scan_portion = REQUEST::text_field( 'scan_portion' );
			$scan_sub_portions = Scan::convert_sub_portions_to_array( REQUEST::text_field( 'scan_sub_portions' ) );
			$scan_specific = REQUEST::int( 'scan_specific' );

			ob_start();

			$this->scan = Scans::get_recent_scan( $scan_type, $scan_portion, $scan_sub_portions, $scan_specific );

			$type = ucwords( str_replace( '-', ' ', $this->scan->get( 'type' ) ) );

			$notes = sprintf( __( '%s scan was manually resumed.', FIXALTTEXT_SLUG ), $type );
			Debug::log( $notes );

			$this->scan->resume( $notes );

			$progress = $this->scan->get( 'progress' );
			$total = $this->scan->get( 'progress_total' );

			$percent = ( $total ) ? round( ( $progress / $total ) * 100, 1 ) : 0;

			static::display_running_progress_bar( $percent, $type, $this->scan->get( 'currently' ) );
			static::display_scan_controls( $this->scan );

			wp_send_json( [ 'html' => ob_get_clean() ] );

		}

	}

	public static function display_paused_progress_bar( float $percent, string $type ) : void {

		echo '<p class="scan-message" style="text-align: center;">' . esc_html( sprintf( __( 'A %s scan is paused. Please resume the scan and let it complete to ensure the WhereUsed data is accurate.', FIXALTTEXT_SLUG ), $type ) ) . '</p>
			<div id="progress-bar">
                <div class="current-progress paused-progress" style="width:' . esc_attr( $percent ) . '%;"></div>
                <span class="dashicons dashicons-controls-pause"></span>
                <span class="text">' . __( 'Paused' ) . ' - <span class="percent">' . esc_html( $percent ) . '%</span></span>
            </div>';

	}

	public static function display_running_progress_bar( float $percent, string $type, string $currently = '' ) : void {

		if ( ! $currently ) {
			$currently = '...';
		}

		echo '<p class="scan-message" style="text-align: center;">' . esc_html( sprintf( __( 'A %s scan is running. This scan runs in the background, so you can go do other things.', FIXALTTEXT_SLUG ), $type ) ) . '</p>';

		echo '<div id="progress-bar">
                <div class="current-progress" style="width:' . esc_attr( $percent ) . '%;"></div>
                <span class="dashicons spin dashicons-update"></span>
                <span class="text">' . __( 'Scanning' ) . ' <span class="currently">' . esc_html( $currently ) . '</span> - <span class="percent">' . esc_html( $percent ) . '%</span></span>
            </div>';

	}

	public static function display_scan_controls( object $scan ) : void {

		echo '<p style="text-align: center;" class="scan-controls">
			<a href="#scan" class="dashicons dashicons-no" id="cancel-scan" 
			data-nonce="' . esc_attr( wp_create_nonce( FIXALTTEXT_SLUG . '-cancel-scan-' . get_current_user_id() ) ) . '" 
			data-scan-type="' . esc_attr( $scan->get( 'type' ) ) . '" 
			data-scan-portion="' . esc_attr( $scan->get( 'portion' ) ) . '" 
			data-scan-sub-portions="' . esc_attr( Scan::convert_sub_portions_to_string( $scan->get( 'sub_portions' ), '' ) ) . '" 
			data-scan-specific="' . esc_attr( $scan->get( 'specific' ) ) . '"
				>' . esc_html__( 'Cancel', FIXALTTEXT_SLUG ) . '</a>';

		if ( $scan->is_paused() ) {
			echo '<a href="#scan" class="dashicons dashicons-controls-play" id="resume-scan" 
			data-nonce="' . esc_attr( wp_create_nonce( FIXALTTEXT_SLUG . '-resume-scan-' . get_current_user_id() ) ) . '"
			data-scan-type="' . esc_attr( $scan->get( 'type' ) ) . '" 
			data-scan-portion="' . esc_attr( $scan->get( 'portion' ) ) . '" 
			data-scan-sub-portions="' . esc_attr( Scan::convert_sub_portions_to_string( $scan->get( 'sub_portions' ), '' ) ) . '" 
			data-scan-specific="' . esc_attr( $scan->get( 'specific' ) ) . '"
				>' . esc_html__( 'Resume', FIXALTTEXT_SLUG ) . '</a>';
		} else {
			echo '<a href="#scan" class="dashicons dashicons-controls-pause" id="pause-scan" 
			data-nonce="' . esc_attr( wp_create_nonce( FIXALTTEXT_SLUG . '-pause-scan-' . get_current_user_id() ) ) . '"
			data-scan-type="' . esc_attr( $scan->get( 'type' ) ) . '" 
			data-scan-portion="' . esc_attr( $scan->get( 'portion' ) ) . '" 
			data-scan-sub-portions="' . esc_attr( Scan::convert_sub_portions_to_string( $scan->get( 'sub_portions' ), '' ) ) . '" 
			data-scan-specific="' . esc_attr( $scan->get( 'specific' ) ) . '"
				>' . esc_html__( 'Pause', FIXALTTEXT_SLUG ) . '</a>';
		}

		echo '</p>';

	}

	/**
	 * Displays the progress bar for the current scan
	 */
	final public static function display_progress_bar( ?object $scan = null ): void {

		Debug::log( 'display_progress_bar' );

		if ( null === $scan ) {
			$progress_only = REQUEST::bool( 'progress_only' );
			$scan_type = REQUEST::text_field( 'scan_type' );
			$scan_portion = REQUEST::text_field( 'scan_portion' );
			$scan_sub_portions = Scan::convert_sub_portions_to_array( REQUEST::text_field( 'scan_sub_portions' ) );
			$scan_specific = REQUEST::int( 'scan_specific' );

			Debug::log( 'display_progress_bar: ' . $scan_type .' '. $scan_portion.' '. print_r($scan_sub_portions, true).' '. $scan_specific );

			$scan = Scans::get_recent_scan( $scan_type, $scan_portion, $scan_sub_portions, $scan_specific );
		} else {
			$progress_only = false;
		}

		Debug::log( 'display_progress_bar scan: ' . print_r( $scan, true ) );

		$type = str_replace( '-', ' ', $scan->get( 'type' ) );
		$start_date = $scan->get( 'start_date' );
		$end_date = $scan->get( 'end_date' );
		$done = $scan->get( 'progress' );
		$total = $scan->get( 'progress_total' );
		$remaining = $total - $done;
		$percent = ( $total ) ? round( ( $done / $total ) * 100, 1 ) : 0;
		$currently = $scan->get( 'currently' );

		if ( $progress_only ) {
			// We are going to respond with JSON

			wp_send_json( [
				'done' => (int) $done,
				'total' => (int) $total,
				'remaining' => (int) $remaining,
				'percent' => (float) $percent,
				'startDate' => esc_html( $start_date ),
				'endDate' => esc_html( $end_date ),
				'currently' => esc_html( $currently ),
				'scan_type' => esc_html( $scan->get( 'type' ) ),
				'scan_portion' => esc_html( $scan->get( 'portion' ) ),
				'scan_sub_portions' => array_map( 'esc_html', $scan->get( 'sub_portions' ) ),
				'scan_specific' => esc_html( $scan->get( 'specific' ) ),
			] );

		} else {

			if ( $scan->is_paused() ) {
				static::display_paused_progress_bar( $percent, $type );
			} else {
				static::display_running_progress_bar( $percent, $type, $currently );
			}

			static::display_scan_controls( $scan );

		}

	}

	/**
	 * Dispatch the async request
	 *
	 * @access public
	 * @return array|WP_Error
	 */
	public function dispatch() {
		// Schedule the cron healthcheck.
		$this->schedule_event();

		// Perform remote post.
		$url = add_query_arg( $this->get_query_args(), $this->get_query_url() );
		$args = $this->get_post_args();

		return wp_remote_post( esc_url_raw( $url ), $args );
	}

	/**
	 * Schedule event
	 */
	protected function schedule_event(): void {
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			wp_schedule_event( time(), $this->cron_interval_identifier, $this->cron_hook_identifier );
		}
	}

	/**
	 * Clear scheduled event
	 */
	protected function clear_scheduled_event(): void {
		$timestamp = wp_next_scheduled( $this->cron_hook_identifier );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $this->cron_hook_identifier );
		}
	}

	/**
	 * Schedule cron healthcheck
	 */
	public function schedule_cron_healthcheck( array $schedules ):array  {
		$interval = $this->get_cron_interval();

		if ( property_exists( $this, 'cron_interval' ) ) {
			$interval = apply_filters( $this->identifier . '_cron_interval', $this->cron_interval );
		}

		// Adds every X minutes to the existing schedules.
		$schedules[ $this->identifier . '_cron_interval' ] = [
			'interval' => MINUTE_IN_SECONDS * $interval,
			'display' => sprintf( __( 'Every %d Minutes' ), $interval ),
		];

		return $schedules;
	}

	/**
	 * Handle cron healthcheck
	 *
	 * Restart the background process if not already running
	 * and data exists in the queue.
	 */
	public function handle_cron_healthcheck(): void {

		Debug::log( 'handle_cron_healthcheck' );

		if ( $this->is_queue_empty() ) {
			// No data to process.
			Debug::log( 'No data to process. Stopping cron.' );
			$this->clear_scheduled_event();
			exit;
		}

		Debug::log( 'restarting background process' );

		$this->dispatch();

		exit;

	}

	/**
	 * Adds an entry at the beginning of the array to serve as a flag. This flag allows the progress bar to know quickly that the scan is working.
	 */
	protected function get_prepend_flag( string $portion = '' ): string {

		$flag = '_update_progress';

		if ( $portion ) {
			$flag .= self::get_delimiter_marker() . $portion;
		}

		return $flag . self::get_end_marker();

	}

	/**
	 * Starts the scan: queues it up and then starts the background process
	 */
	public function start( string $scan_type, string $scan_portion, array $scan_sub_portions = [], int $scan_specific = 0, bool $return = true ): void {

		$skip_notifications = false; // Set to true if you do not want the scan to add notifications into the Admin area

		$response = '';
		$record = true; // Whether to record the scan

		if ( ! in_array( $scan_type, Scans::get_types() ) ) {
			Debug::log( sprintf( __( 'Invalid Scan Type: ', FIXALTTEXT_SLUG ), $scan_type ) );
			die( 'Invalid Scan Type: ' . $scan_type );
		}

		$scans = Scans::get_current( true );

		$values = [];
		$values[] = Get::table_name();

		Debug::log( sprintf( __( 'Starting %s scan: %s', FIXALTTEXT_SLUG ), $scan_type, $scan_portion ) );

		$settings = Settings::get_current_settings( true );

		$this->scan = new Scan();
		$this->scan->set_type( $scan_type );
		$this->scan->set_portion( $scan_portion );
		$this->scan->set_sub_portions( $scan_sub_portions );
		$this->scan->set_specific( $scan_specific );
		$this->set_scan_details();

		if (
			( 'portion' === $scan_type && 'menus' === $scan_portion ) ||
			'full' === $scan_type
		) {

			if ( $settings->get( 'scan_menus' ) ) {
				// Yep, need to scan menus
				$this->queue( $scan_type, 'menus' );
			}

		}
		if (
			( 'portion' === $scan_type && 'users' === $scan_portion ) ||
			'full' === $scan_type
		) {

			if ( $settings->get( 'scan_users' ) ) {
				// Yep, we need to scan users
				$this->queue( $scan_type, 'users' );
			}

		}
		if (
			( 'portion' === $scan_type && 'post-types' === $scan_portion ) ||
			'full' === $scan_type
		) {

			if ( ! empty( $post_types = $settings->get( 'scan_post_types' ) ) ) {
				// Yep, we need to scan defined post types
				$this->queue( $scan_type, 'post-types', $post_types );
			}

		}
		if (
			( 'portion' === $scan_type && 'taxonomies' === $scan_portion ) ||
			'full' === $scan_type
		) {

			if ( ! empty( $taxonomies = $settings->get( 'scan_taxonomies' ) ) ) {
				// Yep, need to scan taxonomies
				$this->queue( $scan_type, 'taxonomies', $taxonomies );
			}

		}
		if (
			in_array( $scan_type, [ 'portion', 'specific' ] ) &&
			'statuses' === $scan_portion
		) {
			Debug::log('Queueing Statuses');

			// Queue URLs to have statuses updated
			$this->queue( $scan_type, 'statuses', $scan_sub_portions, $scan_specific );

			if ( 'specific' === $scan_type ) {
				// Do not record this scan or notify the user about it
				$record = false;
				$skip_notifications = true;
			}

		}

		$scan_sub_portions_string = Scan::convert_sub_portions_to_string($scan_sub_portions);

		$message_prefix = ( wp_doing_cron() ) ? 'WPCron: ' : '';

		$total_count = $this->get_queue_count();

		Debug::log('total count: ' . $total_count);

		if ( $total_count ) {

			if ( 'full' === $scan_type ) {
				// Clear out DB for full scan only
				Run::purge_table( Get::table() );
			}

			if ( ! $skip_notifications ) {

				$message = sprintf( __( 'A %s scan has started (%s->%s->%s->%d).', FIXALTTEXT_SLUG ), $scan_type, $scan_type,$scan_portion, $scan_sub_portions_string, $scan_specific );
				$message = $message_prefix . $message;

				Debug::log( $message );

				Notification::add_notification( [
					'message' => $message,
					'link_url' => FIXALTTEXT_ADMIN_URL . '#scan',
					'link_anchor_text' => __( 'View Scan Progress', FIXALTTEXT_SLUG ),
					'alert_level' => 'notice',
				] );

			}

			if ( $record ) {

				// Mark the scan as no longer needed
				$scans->clear();

				$user_id = ( wp_doing_cron() ) ? - 1 : get_current_user_id();

				$this->scan->set_started( $user_id );

				// Start date and time
				$this->scan->set_start_date();

				// Update the total to be scanned
				$this->scan->set_progress_total( $total_count );
				$this->scan->save();
			}

			// clear cache for status codes
			delete_option( $this->cache_status_codes_option );

			Debug::log( __( 'Dispatching background scan process', FIXALTTEXT_SLUG ) );

			// Run the background process
			$this->dispatch();

		} else {

			if ( ! $skip_notifications ) {

				$message = sprintf( __( 'A %s scan has failed (%s->%s->%s->%d).', FIXALTTEXT_SLUG ), $scan_type, $scan_type, $scan_portion, $scan_sub_portions_string, $scan_specific );
				$message = $message_prefix . $message;

				Debug::log( $message, 'error' );

				Notification::add_notification( [
					'message' => $message,
					'link_url' => FIXALTTEXT_SETTINGS_URL,
					'link_anchor_text' => __( 'Check Settings', FIXALTTEXT_SLUG ),
					'alert_level' => 'error',
				] );

			}

		}

		if ( ! wp_doing_cron() ) {
			Debug::log('Not doing cron.');

			if ( $return ) {
				// Return progress bar to the user

				Debug::log('Return progress bar to the user initially.');

				ob_start();
				static::display_progress_bar( $this->scan );
				$response = ob_get_clean();
			}

			// Default return a response
			wp_send_json( [ 'html' => $response ] );
		}

		// WP_Cron: Safety net to prevent anything else from loading
		exit;

	}

	/**
	 * Queues the specified type
	 */
	public function queue( string $scan_type, string $scan_portion, array $scan_sub_portions = [], $scan_specific = 0 ): void {

		global $wpdb;

		$values = [];
		$placeholders = [];
		$excluded = [];

		// Set the table based on scan portion
		if ( 'post-types' === $scan_portion ) {
			$values[] = $wpdb->prefix . 'posts';
			$excluded = Get::excluded_post_types();
		} elseif ( 'taxonomies' === $scan_portion ) {
			$values[] = $wpdb->prefix . 'term_taxonomy';
		} elseif ( 'users' === $scan_portion ) {
			$values[] = $wpdb->prefix . 'users';
		} elseif ( 'menus' === $scan_portion ) {
			$values[] = $wpdb->prefix . 'term_taxonomy';
		} elseif ( 'statuses' === $scan_portion ) {
			$values[] = Get::table_name();
		}

		// Build placeholders for sub portions
		if ( ! empty( $scan_sub_portions ) ) {
			// Add single quotes around values
			foreach ( $scan_sub_portions as $key => $value ) {
				if ( empty( $excluded ) || ! in_array( $value, $excluded ) ) {
					// not excluded
					$values[] = $value;
					$placeholders[] = '"%' . count( $values ) . '$s"'; // Example: "%1$s"
				}
			}
		}

		// Set the SQL query
		if ( 'post-types' === $scan_portion ) {
			$sql = $wpdb->prepare( 'SELECT `ID` as `queue`, `post_type` as `description` FROM `%1$s` WHERE `post_type` IN ( ' . implode( ',', $placeholders ) . ' )ORDER BY `description`, `queue` ASC;', $values );
		} elseif ( 'taxonomies' === $scan_portion ) {
			$sql = $wpdb->prepare( 'SELECT `term_id` as `queue`, `taxonomy` as `description` FROM `%1$s` WHERE `taxonomy` IN ( ' . implode( ',', $placeholders ) . ' ) ORDER BY `description`, `queue` ASC;', $values );
		} elseif ( 'users' === $scan_portion ) {
			$sql = $wpdb->prepare( 'SELECT `ID` as `queue`, `user_login` as `description` FROM `%1$s` ORDER BY `description`, `queue` ASC;', $values );
		} elseif ( 'menus' === $scan_portion ) {
			$sql = $wpdb->prepare( 'SELECT `term_id` as `queue`, `taxonomy` as `description` FROM `%1$s` WHERE `taxonomy` LIKE "nav_menu" ORDER BY `queue` ASC;', $values );
		} elseif ( 'statuses' === $scan_portion ) {
			if ( 'specific' === $scan_type ) {
				$sql = $wpdb->prepare( 'SELECT `to_url_full` as `queue` FROM `%1$s` WHERE `to_url_status_date` = "1970-01-01 00:00:00" GROUP BY `queue` ORDER BY `queue` ASC;', $values );
			} else {
				// Check Status of All Urls
				// @todo only check URLs that are older than 7 days
				$sql = $wpdb->prepare( 'SELECT `to_url_full` as `queue` FROM `%1$s` GROUP BY `queue` ORDER BY `queue` ASC;', $values );
			}
		}

		$this->push_to_queue( $sql, $scan_portion );

	}

	/**
	 * Get query URL
	 */
	protected function get_query_url(): string {
		if ( property_exists( $this, 'query_url' ) ) {
			return $this->query_url;
		}

		$url = admin_url( 'admin-ajax.php' );

		/**
		 * Filters the post arguments during an async request.
		 *
		 * @param string $url
		 */
		return apply_filters( $this->identifier . '_query_url', $url );
	}

	/**
	 * Deletes all scan batches on the current site
	 */
	public function delete_all_scan_batches(): void {

		global $wpdb;

		Debug::log( __( 'Deleting all scan batches.', FIXALTTEXT_SLUG ) );

		$table = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$values = [];
		$values[] = $table;
		$values[] = $column;
		$values[] = $this->get_identifier() . '_batch_%';

		// Delete all
		$wpdb->query( $wpdb->prepare( '
			DELETE
			FROM `%1$s`
			WHERE `%2$s` LIKE "%3$s"
		', $values ) );

		// Delete scan background process transient
		delete_site_transient( $this->get_identifier() . '_process_lock' );

	}

}