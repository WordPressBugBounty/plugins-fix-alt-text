<?php

namespace FixAltText;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\HelpersLibrary\Base_Library;

/**
 * Class Row - Class matches the structure of the database table
 *
 * @package FixAltText
 * @since   1.0.0
 */
abstract class Row extends Base_Library {

	// Universal
	protected int $id = 0; // unique id in the database

	// From Source
	protected int $from_post_id = 0; // The post id, term id, or user id referencing the URL
	protected string $from_post_type = ''; // The post type of the from, or "taxonomy", "user"
	protected string $from_where = ''; // content, post meta, term meta, user meta, media library
	protected string $from_where_key = ''; // the post meta key, term meta key, user meta key, or other specific reference related to from_where

	// To Destination (image)
	protected string $image_index = ''; // The ID of the image in the dom. Used as a reference for inline editing
	protected string $image_url = ''; // The image URL referenced
	protected string $image_alt_text = ''; // The ALT Text used
	protected string $image_ext = ''; // ext of file
	protected array $image_issues = []; // Detected issues

	/**
	 * Constructor for Row class: starts setting all properties
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @param object | array $data
	 */
	function __construct( $data = [] ) {

		parent::__construct( $data );

		$this->set_ext();
		$this->set_issues();
	}

	/**
	 * Set the image's extension
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public function set_ext(): void {

		if ( $this->image_url ) {
			// Separate the parts of the URL
			$url_parts = parse_url( $this->image_url );

			// Grab only path so we remove the query
			$url_path = $url_parts['path'] ?? '';

			// Force extension to be lowercase
			$this->image_ext = trim( strtolower( pathinfo( $url_path, PATHINFO_EXTENSION ) ) );
		}
	}

	/**
	 * Detects and sets the issue related to the alt text
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @return void
	 */
	private function set_issues(): void {

		if ( ! $this->get( 'image_issues' ) ) {
			// No issue set yet: let's test

			// Grab settings
			$settings = Settings::get_current_settings();
			$settings_issues = $settings->get('scan_issues', 'array');

			$image_alt_text = trim( $this->get( 'image_alt_text' ) );
			$issues = [];

			if ( in_array( 'alt-text-missing', $settings_issues ) && '' === $image_alt_text ) {

				$issues[] = 'alt-text-missing';

			} else {

				if ( in_array( 'alt-text-contains-file-name', $settings_issues ) ) {
					// Setting is on
					if( strpos( $image_alt_text, '.' . $this->get( 'image_ext' ) ) !== false ){
						$issues[] = 'alt-text-contains-file-name';
					}
				}

				if ( in_array( 'alt-text-contains-code', $settings_issues ) ) {
					// Setting is on
					if( $image_alt_text !== strip_tags( html_entity_decode( $image_alt_text, ENT_NOQUOTES ) ) ){
						$issues[] = 'alt-text-contains-code';
					}
				}

				if ( in_array( 'alt-text-contains-backslashes', $settings_issues ) ) {
					// Setting is on
					if ( $image_alt_text !== stripslashes( $image_alt_text ) ) {
						$issues[] = 'alt-text-contains-backslashes';
					}
				}

				if ( in_array( 'alt-text-too-short', $settings_issues ) ) {
					// Setting is on

					$words = explode( ' ', $image_alt_text );
					if( count( $words ) < $settings->get( 'scan_issues_min_words' ) ){
						$issues[] = 'alt-text-too-short';
					}

				}

				if ( in_array( 'alt-text-too-long', $settings_issues ) ) {
					// Setting is on
					if( mb_strlen( $image_alt_text ) > $settings->get( 'scan_issues_max_characters' ) ){
						$issues[] = 'alt-text-too-long';
					}
				}

			}

			if ( ! Get::is_valid_image_url( $this->get( 'image_url' ) ) ) {
				if ( $this->get( 'from_where' ) != 'media library' ) {
					$issues[] = 'image-url-not-valid';
				}
			} else {
				if ( ! Get::is_valid_image_ext( $this->get( 'image_ext' ) ) ) {
					// Check to see if the image extension is valid
					$issues[] = 'image-type-not-valid';
				}
			}

			/**
			 * Filter: fixalttext_set_issues - Used to detect custom issues
			 * @note Also see filter fixalttext_get_issues
			 *
			 * @package FixAltText
			 * @since   1.2.0
			 *
			 * @param array $issues
			 *
			 * @return array
			 */
			$issues = apply_filters( FIXALTTEXT_HOOK_PREFIX . 'set_issues', $issues );

			$this->set( 'image_issues', $issues );
		}
	}

	/**
	 * Converts the raw DB query into Row objects
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @param array $results
	 * @param bool  $load_only
	 *
	 * @return array
	 */
	public static function get_results( array $results, bool $load_only = true ): array {

		$rows = [];

		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				$result->load_only = $load_only;
				$rows[] = new static( $result );
			}
		}

		return $rows;

	}

	/**
	 * Inserts multiple entries at the same time
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @param array $rows
	 *
	 * @return bool
	 */
	public static function add_entries( array $rows ): bool {

		global $wpdb;

		$results = false;

		if ( ! empty( $rows ) ) {

			$columns_set = false;
			$exclude_properties = [
				'id' => true,
				'cache' => true,
			];

			// Insert the data into the database
			$column_name_placeholders = []; // Holds all the placeholders for the column names
			$value_placeholders = []; // Will hold all the sanitization data types
			$values = [];
			$values[] = Get::table_name();

			// Get Row properties
			$properties = (new class extends Row{})->get_properties();

			// Array of traces to insert
			foreach ( $rows as $row ) {

				if ( false === $columns_set ) {
					// Add in the column placeholders and values

					$columns_set = true; // set flag so we only do this once

					foreach ( $properties as $property_name => $default_value ) {
						if ( ! isset( $exclude_properties[ $property_name ] ) ) {
							// Build the columns names placeholders
							$values[] = $property_name;
							$column_name_placeholders[] = '`%' . count( $values ) . '$s`';
						}
					}
				}

				$column_value_placeholders = [];

				foreach ( $properties as $property_name => $not_used ) {
					// Add in each rows' placeholders and values

					if ( isset( $properties[ $property_name ] ) && ! isset( $exclude_properties[ $property_name ] ) ) {
						// Property exists and is not excluded, let's add this data

						$value = $row->get( $property_name );

						if ( is_array( $value ) ) {
							// We are dealing with an array: let's convert it to a JSON string
							$values[] = json_encode( $value );
							$column_value_placeholders[] = '"%' . count( $values ) . '$s"';
						} else {
							$values[] = $value;
							$column_value_placeholders[] = is_int( $value ) ? '%' . count( $values ) . '$d' : '"%' . count( $values ) . '$s"';
						}
					}
				}

				if ( ! empty( $column_value_placeholders ) ) {
					$value_placeholders[] = "(" . implode( ',', $column_value_placeholders ) . ")";
				}

			}

			$results = $wpdb->query( $wpdb->prepare( 'INSERT INTO `%1$s` (' . implode( ',', $column_name_placeholders ) . ') ' . 'VALUES ' . implode( ', ', $value_placeholders ), $values ) );


			if ( ! $results ) {

				// Table might not exist, let's try again

				// Create Table
				Run::create_tables( Get::tables() );

				// Try query again
				$results = $wpdb->query( $wpdb->prepare( 'INSERT INTO `%1$s` (' . implode( ',', $column_name_placeholders ) . ') ' . 'VALUES ' . implode( ', ', $value_placeholders ), $values ) );

			}

		}

		return (bool) $results;

	}

	/**
	 * Deletes all old entries for the provided from_id
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @param int $from_post_id The specific post ID
	 * @param string $from_post_type The specific post type we want to delete
	 *
	 * @return void
	 */
	public static function delete_old_entries( int $from_post_id, string $from_post_type ): void {

		global $wpdb;

		$values = [];
		$values[] = Get::table_name();
		$values[] = $from_post_id;
		$values[] = $from_post_type;

		$sql = $wpdb->prepare( 'DELETE FROM `%1$s` WHERE `from_post_id` = %2$d AND `from_post_type` = "%3$s";', $values );
		$wpdb->query( $sql );

	}

	/**
	 * Returns properties as array
	 *
	 * @package FixAltText
	 * @since 1.2.0
	 *
	 * @return array
	 */
	public function get_properties(): array {

		$array = [];

		foreach ( $this as $property => $value ) {
			$array[ $property ] = true;
		}

		return $array;

	}

}
