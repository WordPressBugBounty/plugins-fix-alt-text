<?php

namespace FixAltText;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\HelpersLibrary\Run_Library as Run_Library;

/**
 * Class Run - Run maintenance tasks and other misc tasks
 */
class Run extends Run_Library {

	/**
	 * Deletes all the options related to the plugin
	 *
	 * @return array
	 */
	public static function delete_options(): array {

		global $fixalttext;

		$responses = [];

		$responses[] = delete_option( FIXALTTEXT_OPTION );
		wp_cache_delete( FIXALTTEXT_OPTION, 'options' );

		if ( is_multisite() ){
			$responses[] = delete_site_option( FIXALTTEXT_NETWORK_OPTION );
			wp_cache_delete( '1:notoptions', 'site-options' );
		}

		$responses[] = delete_option( FIXALTTEXT_SCAN_OPTION );
		wp_cache_delete( FIXALTTEXT_SCAN_OPTION, 'options' );

		$responses[] = delete_option( FIXALTTEXT_NOTIFICATIONS_OPTION );
		wp_cache_delete( FIXALTTEXT_NOTIFICATIONS_OPTION, 'options' );

		if ( ! isset( $fixalttext['scan-process'] ) ) {
			Scan_Process::init();
		}
		$fixalttext['scan-process']->delete_all_scan_batches();

		return $responses;
	}

}
