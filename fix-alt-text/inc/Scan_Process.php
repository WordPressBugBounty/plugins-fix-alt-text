<?php

namespace FixAltText;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\HelpersLibrary\Scan_Process_Library as Scan_Process_Library;

// Include dependency
require_once( FIXALTTEXT_HELPERSLIBRARY_INC_DIR . '/Scan_Process_Library.php' );

/**
 * Class Background
 *
 * @package FixAltText
 * @since   1.0.0
 */
class Scan_Process extends Scan_Process_Library {

	/**
	 * Set the hooks
	 *
	 * @return void
	 */
	protected function set_hooks(): void {

		parent::set_hooks();

		// Modify default group sizes
		add_filter( $this->identifier . '_group_size', [
			$this,
			'modify_group_size',
		], 10, 2 );

	}

	/**
	 * Modify group sizes so we can scan faster since we are not checking URL statuses
	 * WARNING: This could potentially cause higher server load. Modify with your own risk.
	 *
	 * @package FixAltText
	 * @since   1.2.0
	 *
	 * @param int $group_size
	 * @param string $type
	 *
	 * @return int
	 */
	public function modify_group_size( int $group_size, string $type ): int {
		return 25;
	}

}
