<?php

namespace FixAltText;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\HelpersLibrary\Get_Library as Get_Library;
use function Network_Media_Library\get_site_id;

/**
 * Class Get - Get the information you need
 */
class Get extends Get_Library {

	/**
	 * Abstract method to get the tables.
	 * NOTICE: Table name should NOT include site prefix.
	 */
	public static function tables(): array {

		return [
			'fixalttext_images' => [
				'name' => 'fixalttext_images',
				'columns' => [
					[
						'name' => 'id',
						'type' => 'bigint',
						'null' => false,
						'auto-increment' => true,
					],
					[
						'name' => 'from_post_id',
						'type' => 'bigint',
						'null' => false,
						'default' => '0',
					],
					[
						'name' => 'from_post_type',
						'type' => 'varchar(20)',
						'null' => false,
					],
					[
						'name' => 'from_where',
						'type' => 'varchar(20)',
						'null' => false,
					],
					[
						'name' => 'from_where_key',
						'type' => 'varchar(255)',
						'null' => false,
					],
					[
						'name' => 'image_index',
						'type' => 'smallint',
						'null' => true,
					],
					[
						'name' => 'image_url',
						'type' => 'text',
						'default' => '',
						'null' => true,
					],
					[
						'name' => 'image_alt_text',
						'type' => 'text',
						'null' => true,
					],
					[
						'name' => 'image_ext',
						'type' => 'varchar(20)',
						'null' => true,
					],
					[
						'name' => 'image_issues',
						'type' => 'varchar(100)',
						'null' => true,
					],
				],
				'index' => [
					'primary' => 'id',
					'key' => [
						'from_post_id',
						'from_post_type',
						'from_where',
						'image_ext',
						'image_issues',
					],
				],
			],
		];
	}

	/**
	 * Retrieves the core blocks that can need force alt text
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @return array
	 */
	public static function blocks(): array {

		return [
			'core/image',
			'core/media-text',
			'core/gallery',
		];

	}

	/**
	 * Retrieves the other areas where we can force alt text
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @return array
	 */
	public static function others(): array {

		return [
			'Media Library',
		];
	}

	/**
	 * Returns an array of image extensions supported by this plugin
	 *
	 * Note: Image extensions are set as the key so that we can check an image extension using isset() versus in_array()
	 *
	 * @return array
	 */
	static function allowed_extensions(): array {

		$extensions = [];

		$mime_types = self::allowed_mime_types();

		if ( ! empty( $mime_types ) ) {
			foreach ( $mime_types as $mime ) {
				foreach ( $mime as $ext ) {
					$extensions[ $ext ] = $mime;
				}
			}
		}

		return $extensions;
	}

	/**
	 * Returns an array of image mime types supported by this plugin
	 *
	 * Note: Mime types are set as the key so that we can check a mime type using isset() versus in_array()
	 * @link https://developer.mozilla.org/en-US/docs/Web/Media/Formats/Image_types
	 *
	 * @return array
	 */
	static function allowed_mime_types(): array {
		return [
			'image/apng' => [ 'apng' ],
			'image/avif' => [ 'avif' ],
			'image/gif' => [ 'gif' ],
			'image/jpe' => [ 'jpe' ],
			'image/jpeg' => [
				'jpg',
				'jpeg',
				'jfif',
				'pjpeg',
				'pjp',
			],
			'image/png' => [ 'png' ],
			'image/svg+xml' => [ 'svg' ],
			'image/webp' => [ 'webp' ],
		];
	}

	/**
	 * Check to see if given URL is valid
	 *
	 * @package FixAltText
	 * @since 1.2.0
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	public static function is_valid_image_url( string $url ): bool {

		$valid = true;

		if (
			strpos( $url, 'https://' ) === false && strpos( $url, 'http://' ) === false && strpos( $url, '//' ) === false && strpos( $url, '/' ) === false ) {
			// Make sure the URL starts with appropriate prefix
			$valid = false;
		} elseif ( strip_tags( $url ) != $url ) {
			// Make sure there is no code in there
			$valid = false;
		}

		return $valid;
	}

	/**
	 * Check to see if a provided extension is allowed
	 *
	 * @package FixAltText
	 * @since 1.2.0
	 *
	 * @param string $extension
	 *
	 * @return bool
	 */
	public static function is_valid_image_ext( string $extension ): bool {

		$valid = false;

		$extension = trim( strtolower( $extension ) );

		$allowed_extensions = Get::allowed_extensions();

		if ( array_key_exists( $extension, $allowed_extensions ) ) {
			$valid = true;
		}

		return $valid;
	}

	/**
	 * Retrieves a master list of issues
	 *
	 * @since 1.8.0
	 *
	 * @return array
	 */
	public static function issues( bool $keys_only = false ) : array {

		$issues = [
			'alt-text-missing' => [
				'label' => 'Missing',
				'description' => [ 'text' => 'Detects whether you have images that are missing alt text.' ],
				'color' => 'rgb( 255, 99, 132 )'
			],
			'alt-text-contains-file-name' => [
				'label' => 'Contains File Name',
				'description' => [ 'text' => 'Detects if the alt text contains the name of the image\'s filename.' ],
				'color' => 'rgb( 255, 205, 86 )'
			],
			'alt-text-contains-code' => [
				'label' => 'Contains Code',
				'description' => [ 'text' => 'Detects whether the alt text contains HTML code.' ],
				'color' => 'rgb( 54, 162, 235 )'
			],
			'alt-text-contains-backslashes' => [
				'label' => 'Contains Backslash Characters',
				'description' => [ 'text' => 'Detects whether the alt text contains backslash characters.' ],
				'color' => 'rgb( 54, 162, 100 )'
			],
			'alt-text-too-short' => [
				'label' => 'Too Short',
				'description' => [ 'text' => 'Alt text is too short to be useful.' ],
				'color' => 'rgb( 75, 192, 192 )'
			],
			'alt-text-too-long' => [
				'label' => 'Too Long',
				'description' => [ 'text' => 'Alt text is too long.' ],
				'color' => 'rgb(122, 32, 217)'
			],
			'image-url-not-valid' => [
				'label' => 'Image URL Not Valid',
				'description' => [ 'text' => 'The image URL does not resolve.' ],
				'color' => 'rgb( 235, 0, 0 )'
			],
			'image-type-not-valid' => [
				'label' => 'Image Type Not Valid',
				'description' => [ 'text' => 'The image extension is not allowed.' ],
				'color' => 'rgb( 243, 104, 0 )'
			],
		];

		if ( $keys_only ) {
			// We only need an array of the keys for values

			$old_issues = $issues;

			// Reset
			$issues = [];

			foreach ( $old_issues as $key => $issue ) {
				$issues[] = $key;
			}
		}

		/**
		 * Filter: fixalttext_get_issues - Used to add custom issues to get get issues method
		 * @note Also see filter fixalttext_set_issues
		 *
		 * @package FixAltText
		 * @since   1.8.0
		 *
		 * @param array $issues
		 *
		 * @return array
		 */
		return apply_filters( FIXALTTEXT_HOOK_PREFIX . 'get_issues', $issues );

	}

}
