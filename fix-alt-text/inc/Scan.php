<?php

namespace FixAltText;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use DOMDocument;
use WP_Post;
use FixAltText\HelpersLibrary\Scan_Library as Scan_Library;

/**
 * Class Scan
 *
 * @package FixAltText
 * @since   1.0.0
 *
 * @todo    make the attachment auto scan when uploaded (this might be done. Need to verify. 07/11/2023 -SA )
 * @todo    add a warning that the image needs alt text on the bulk upload screen when a web image is uploaded (add inline)
 */
final class Scan extends Scan_Library {

	/**
	 * Registers all hooks
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public static function init(): void {

		//------------- POSTS ----------------------------------------------//

		// Save Post
		add_action( 'save_post', [
			self::class,
			'save_post_scan',
		], 999, 1 );

		// Trash Post
		add_action( 'wp_trash_post', [
			self::class,
			'delete_post_entries',
		], 999, 1 );

		// Delete Post
		add_action( 'deleted_post', [
			self::class,
			'deleted_post',
		], 999, 2 );

		//------------- ATTACHMENTS ----------------------------------------------//

		// Save Attachment
		add_action( 'attachment_updated', [
			self::class,
			'save_post_scan',
		], 999, 1 );

		// New Attachment
		add_action( 'add_attachment', [
			self::class,
			'save_post_scan',
		], 999, 1 );

		// Ensure that we are getting the correct attachment URL when using Network Media Library
		add_filter( 'wp_get_original_image_url', [
			self::class,
			'wp_get_original_image_url',
		], 999, 1 );

		// Can't trash an attachment.
		// Delete attachment is the same as delete post ("deleted_post" hook)

		//------------- TERMS ----------------------------------------------//

		// Term is edited or added
		add_action( 'saved_term', [
			self::class,
			'save_term_scan',
		], 999, 3 );

		// Term is deleted
		add_action( 'delete_term', [
			self::class,
			'delete_term_scan',
		], 999, 3 );

		//------------- USERS ----------------------------------------------//

		// @todo profile_update scan
		// @todo deleted_user scan

	}


	/**
	 * A taxonomy term has been updated
	 *
	 * @link https://developer.wordpress.org/reference/hooks/saved_term/
	 *
	 * @param int    $term_id
	 * @param int    $taxonomy_id
	 * @param string $taxonomy_slug
	 *
	 * @return void
	 */
	public static function save_term_scan( int $term_id, int $taxonomy_id, string $taxonomy_slug) : void {

		Run::prevent_caching(true);

		$settings = Settings::get_current_settings();

		if ( ! in_array( $taxonomy_slug, $settings->get( 'scan_taxonomies' ) ) ) {
			// Post type is not set to be scanned
			return;
		}

		// We should scan this taxonomy term
		self::scan_term( get_term($term_id) );

	}

	/**
	 * A taxonomy term has been deleted. Remove entries in the database.
	 *
	 * @link https://developer.wordpress.org/reference/hooks/delete_term/
	 *
	 * @param int    $term_id
	 * @param int    $taxonomy_id
	 * @param string $taxonomy_slug
	 *
	 * @return void
	 */
	public static function delete_term_scan( int $term_id, int $taxonomy_id, string $taxonomy_slug ) : void {

		Run::prevent_caching(true);

		$settings = Settings::get_current_settings();

		if ( ! in_array( $taxonomy_slug, $settings->get( 'scan_taxonomies' ) ) ) {
			// Post type is not set to be scanned
			return;
		}

		// Remove entries in the database
		self::delete_post_entries( $term_id, 'taxonomy term');

	}

	/**
	 * Filters the URL to the original attachment image. Ensure that we are getting the correct attachment URL when using Network Media Library
	 *
	 * @package FixAltText
	 * @since 1.5.0
	 *
	 * @param string $original_image_url URL to original image.
	 *
	 * @return string
	 */
	public static function wp_get_original_image_url( string $original_image_url ): string {

		if ( $original_image_url && Get::using_network_media_library() ) {

			$url_parsed = parse_url( $original_image_url );
			$url_host = $url_parsed['host'] ?? '';
			$pri_blog_url_parsed = parse_url( get_site_url( get_main_site_id() ) );
			$pri_blog_url_host = $pri_blog_url_parsed['host'] ?? '';

			if ( $url_host != $pri_blog_url_host ) {
				// Convert the URL to use primary blog domain
				$original_image_url = str_replace( $url_host, $pri_blog_url_host, $original_image_url );
			}

		}

		return $original_image_url;
	}

	/**
	 * Scans the post after the post is saved
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @param int      $post_id
	 *
	 * @return void
	 */
	public static function save_post_scan( int $post_id ): void {

		Run::prevent_caching(true);

		$this_post = get_post( $post_id );

		if ( ! isset( $this_post->post_type ) ) {
			// Post does not exist
			return;
		} elseif ( in_array( $this_post->post_type, Get::excluded_post_types() ) ) {
			// Post type is excluded from getting scanned
			return;
		} elseif ( in_array( $this_post->post_status, [ 'trash' ] ) ) {
			// These post statuses are not scanned
			return;
		}

		$settings = Settings::get_current_settings();

		if ( ! in_array( $this_post->post_type, $settings->get( 'scan_post_types' ) ) ) {
			// Post type is not set to be scanned
			return;
		}

		// Scan this post
		self::scan_post( $this_post );

	}

	/**
	 * Scans an individual post to find all references and insert into the database
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @param object $this_post
	 * @param int    $from_site_id
	 *
	 * @return void
	 */
	public static function scan_post( object $this_post ): void {

		// Delete all current entries in database for this post
		Reference::delete_old_entries( $this_post->ID, $this_post->post_type );

		// Array of all Reference objects created from the page
		$all_references = [];

		if ( 'attachment' === $this_post->post_type ) {

			$allowed_extensions = Get::allowed_mime_types();

			if ( isset( $allowed_extensions[ $this_post->post_mime_type ] ) ) {
				// Scan attachment in the Media Library

				$image_url = wp_get_original_image_url( $this_post->ID );
				$alt_text = get_post_meta( $this_post->ID, '_wp_attachment_image_alt', true );

				$data = [
					'from_post_id' => $this_post->ID,
					'from_post_type' => $this_post->post_type,
					'from_where' => 'media library',
					'from_where_key' => '',
					'image_index' => 0,
					'image_url' => $image_url,
					'image_alt_text' => $alt_text,
				];

				$all_references[] = new Reference( $data );
			}

		} else {

			if ( $this_post->post_content ) {

				// Grab all images from content

				$args = [
					'from_post_id' => $this_post->ID,
					'from_post_type' => $this_post->post_type,
					'from_where' => 'content',
				];
				$all_references = self::get_from_html( $this_post->post_content, $args );
			}

			if ( $this_post->post_excerpt ) {

				// Grab all images from excerpt

				$args = [
					'from_post_id' => $this_post->ID,
					'from_post_type' => $this_post->post_type,
					'from_where' => 'excerpt',
				];

				$all_references = self::get_from_html( $this_post->post_excerpt, $args );
			}

			// Scan post meta to get all references
			$all_references = array_merge( $all_references, self::scan_meta( $this_post->ID, $this_post->post_type, 'post meta' ) );
		}

		// Adds the references to the database
		Reference::add_entries( $all_references );

	}

	/**
	 * Scans an individual term to find all references and insert into the database
	 *
	 * @package FixAltText
	 * @since   1.1.0
	 *
	 * @param object $from
	 *
	 * @return void
	 */
	public static function scan_term( object $from ): void {

		// Delete all current entries in database for this post
		Reference::delete_old_entries( $from->term_id, 'taxonomy term' );

		// Array of all Reference objects created from the page
		$all_references = [];

		// Check term description
		$args = [
			'from_post_id' => $from->term_id,
			'from_post_type' => 'taxonomy term',
			'from_where' => 'term description',
		];

		$all_references = array_merge( $all_references, self::get_from_html( $from->description, $args ) );

		// Scan term meta to get all references
		$all_references = array_merge( $all_references, self::scan_meta( $from->term_id, 'taxonomy term', 'term meta' ) );

		// Adds the references to the database
		Reference::add_entries( $all_references );

	}

	/**
	 * Scans an individual user to find all references and insert into the database
	 *
	 * @package FixAltText
	 * @since   1.1.0
	 *
	 * @param object $from
	 *
	 * @return void
	 */
	public static function scan_user( object $from ): void {

		global $wpdb;

		Debug::log('Scanning user id: ' . $from->ID);

		Debug::log( 'Memory - (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );

		// Delete all current entries in database for this post
		Reference::delete_old_entries( $from->ID, 'user' );

		Debug::log( 'Memory - (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );

		// Scan user meta to get all references
		$all_references = self::scan_meta( $from->ID, 'user', 'user meta' );

		Debug::log( 'Memory - (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );

		// Clear Caches
		//wp_cache_delete( $from->ID, 'user_meta' );
		//clean_user_cache( $from->ID );
		//$wpdb->flush();

		Debug::log( 'Memory - (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );

		// Adds the references to the database
		Reference::add_entries( $all_references );

		Debug::log( 'Memory - (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );

		Debug::log( 'Added References: ' . print_r( $all_references, true ));

	}

	private static function scan_meta( int $from_id, string $from_post_type, string $from_where ): array {

		// Array of all Reference objects created from the page
		$all_references = [];

		Debug::log( 'Memory - (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );

		if ( 'post' === $from_post_type || 'page' === $from_post_type ) {
			// Blog Post / Page
			$meta = get_post_meta( $from_id );
		} elseif ( 'taxonomy term' === $from_post_type ) {
			// Taxonomy
			$meta = get_term_meta( $from_id );
		} elseif ( 'user' === $from_post_type ) {
			// User
			$meta = get_user_meta( $from_id );
		} else {
			// Custom post type
			$meta = get_post_meta( $from_id );
		}

		Debug::log( 'Memory - (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );

		if ( ! empty( $meta ) ) {

			foreach ( $meta as $key => $value ) {

				$value = $value[0] ?? '';

				$args = [
					'from_post_id' => $from_id,
					'from_post_type' => $from_post_type,
					'from_where' => $from_where,
					'from_where_key' => $key,
				];

				Debug::log( 'Memory - (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );

				$refs = self::get_from_html( $value, $args );

				Debug::log( 'Memory - (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );

				if ( ! empty( $refs ) ) {
					$all_references = array_merge( $all_references, $refs );
				}

				// Cleanup Memory
				unset( $meta[ $key ] );
			}
		}

		return $all_references;

	}

	/**
	 * Builds the references structure and then adds them to the database
	 *
	 * @package FixAltText
	 * @since   1.1.0
	 *
	 * @param array $all_references
	 *
	 * @return void
	 */
	private static function add_references( array $all_references ): void {

		// Insert in this local table
		Reference::add_entries( $all_references );

	}

	/**
	 * Post has been deleted. Now we need to cleanup the references table
	 *
	 * @package FixAltText
	 * @since   1.4.3
	 *
	 * @param int      $post_id
	 * @param \WP_Post $this_post
	 *
	 * @return void
	 */
	public static function deleted_post( int $post_id, WP_Post $this_post ) : void {

		self::delete_post_entries( $post_id, $this_post->post_type );

	}

	/**
	 * Deletes all post entries in the custom table when a post is deleted
	 *
	 * @package FixAltText
	 * @since   1.1.0
	 *
	 * @param int    $post_id
	 * @param string $post_type
	 *
	 * @return void
	 */
	public static function delete_post_entries( int $post_id, string $post_type = '' ): void {

		if ( $post_id ) {

			// Ensure we have a post type defined
			if ( empty( $post_type ) ) {
				$this_post = get_post( $post_id );
				$post_type = $this_post->post_type ?? '';
			}

			if ( $post_type ) {
				Reference::delete_old_entries( $post_id, $post_type );
			}

		}

	}

	/**
	 * Grabs images from the DOM content
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @param string $html
	 * @param array  $args
	 *
	 * @return array
	 */
	public static function get_from_html( string $html, array $args = [] ): array {

		// Must have something to scan
		if ( empty( $args ) ) {
			return [];
		}

		// Require HTML
		if ( empty( $html ) ) {
			return [];
		}

		// Empty array to hold all links to return
		$all_references = [];

		// Create DOM structure so we can reliably grab all img tags
		$dom = new DOMDocument();

		// Silence warnings
		libxml_use_internal_errors( true );

		// Ensure we are using UTF-8 Encoding
		$html = htmlspecialchars_decode( htmlentities( $html, ENT_COMPAT, 'utf-8', false ) );

		// Load the URL's content into the DOM
		$dom->loadHTML( $html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED );

		$images = $dom->getElementsByTagName( 'img' );
		$image_index = 0;

		// Ensure we have default values
		$args['from_post_id'] = $args['from_post_id'] ?? 0;
		$args['from_post_type'] = $args['from_post_type'] ?? '';
		$args['from_where'] = $args['from_where'] ?? '';
		$args['from_where_key'] = $args['from_where_key'] ?? '';

		// Loop through each <img> tag in the dom and add it to the link array
		foreach ( $images as $img ) {

			// Inherit base details from args
			$ref = $args;

			// Fixing legacy code from breaking Gutenburg Editor
			$img->removeAttribute( 'id' );

			$ref['image_index'] = $image_index;
			$ref['image_url'] = $img->getAttribute( 'src' );
			$ref['image_alt_text'] = $img->getAttribute( 'alt' );

			$all_references[] = new Reference( $ref );

			++ $image_index;

		}

		// Done silencing errors
		libxml_clear_errors();

		//Return the links
		return $all_references;
	}

}