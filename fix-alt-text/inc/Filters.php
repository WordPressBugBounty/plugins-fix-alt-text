<?php

namespace FixAltText;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Class Filters
 *
 * @package FixAltText
 * @since   1.2.0
 */
final class Filters {

	/**
	 * Grabs all the from_where values from the database
	 *
	 * @package FixAltText
	 * @since   1.2.0
	 *
	 * @return array
	 */
	public static function get_from_where(): array {

		global $wpdb;

		$where = $wpdb->get_col( $wpdb->prepare( 'SELECT `from_where` FROM `%1$s` GROUP BY `from_where` ORDER BY `from_where` ASC;', Get::table_name() ) );

		$all = [];
		foreach ( $where as $w ) {

			if ( $w = trim( $w ) ) {
				$all[ $w ] = ucwords( str_replace( [
					'-',
					'_',
				], ' ', $w ) );
			}

		}

		return $all;

	}

	/**
	 * Grabs all the image extensions for images found
	 *
	 * @package FixAltText
	 * @since   1.2.0
	 *
	 * @return array
	 */
	public static function get_image_exts(): array {

		global $wpdb;

		$exts = $wpdb->get_col( $wpdb->prepare( 'SELECT `image_ext` FROM `%1$s` GROUP BY `image_ext` ORDER BY `image_ext` ASC;', Get::table_name() ) );

		$all = [];
		foreach ( $exts as $ext ) {

			if ( $ext = trim( $ext ) ) {
				$all[ $ext ] = $ext;
			}

		}

		return $all;

	}

	/**
	 * Get all possible issues
	 *
	 * @package FixAltText
	 * @since   1.2.0
	 *
	 * @param bool $counts
	 *
	 * @return array
	 */
	public static function get_issues( bool $counts = false ): array {

		global $wpdb;

		if ( $counts ) {
			$json_issues = $wpdb->get_col( $wpdb->prepare('SELECT `image_issues` FROM `%1$s`;', Get::table_name() ) );
		} else {
			$json_issues = $wpdb->get_col( $wpdb->prepare('SELECT `image_issues` FROM `%1$s` GROUP BY `image_issues` ORDER BY `image_issues` ASC;', Get::table_name() ) );
		}

		$all = [];

		if ( ! empty($json_issues) ){
			foreach ( $json_issues as $json ) {

				$issues = json_decode( $json );

				if ( ! empty( $issues ) ) {
					foreach ( $issues as $issue ) {

						if ( $counts ) {
							if ( isset( $all[ $issue ] ) ) {
								++ $all[ $issue ];
							} else {
								$all[ $issue ] = 1;
							}
						} else {
							if ( ! isset( $all[ $issue ] ) ) {
								$all[ $issue ] = ucwords( str_replace( [
									'-',
									'_',
								], ' ', $issue ) );
							}
						}

					}
				}
			}

			// Sort all issues alphabetically
			ksort($all);
		}

		return $all;

	}

	/**
	 * The icons for filters
	 *
	 * @return array
	 */
	public static function get_icons(): array {

		return [
			'where' => 'location',
			'file-type' => 'format-image',
			'issue' => 'warning',
		];

	}

}