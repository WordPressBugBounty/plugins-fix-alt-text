<?php
namespace FixAltText;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\HelpersLibrary\Scans_Library;

/**
 * Class Scans
 * @since   1.6.0
 */
class Scans extends Scans_Library {

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
		];

	}

}