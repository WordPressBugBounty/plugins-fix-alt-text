<?php

namespace FixAltText;

/**
 * Network Settings Page
 *
 * This page manages settings across all sites for a multisite setups
 */

// Prevent Direct Access (require main file to be loaded)
( defined( 'ABSPATH' ) ) || die;

Admin::check_permissions( true );

// Check to ensure only multisite can see
( is_multisite() ) || die( 'Not Multisite' );

use FixAltText\HelpersLibrary\REQUEST;
use FixAltText\HelpersLibrary\Settings_Display_Library;

$sites = Get::sites();

$settings = Network_Settings::get_current_settings();

$current_user_id = get_current_user_id();
$disabled = false;

$scan_running = [];
$scan_paused = [];

// check all the sites to see if any scans are running
foreach ( $sites as $site ) {
	switch_to_blog( $site->blog_id );

	if ( is_plugin_active( FIXALTTEXT_PLUGIN ) ) {

		if ( ! is_null( $scan = Scans::get_active_scan() ) ) {
			$scan_running[ $site->blog_id ] = $scan;
		} else if ( ! is_null( $scan = Scans::get_paused_scan() ) ) {
			$scan_paused[ $site->blog_id ] = $scan;
		}
	}

	restore_current_blog();
}

if ( ! empty( $scan_running ) ) {
	Admin::add_notice( [
		'message' => __( 'Warning: You cannot change network settings while a scan is running on one of the sites. Please wait for the scan to finish or cancel the scan.', FIXALTTEXT_SLUG ),
		'alert_level' => 'warning',
	] );

    $disabled = true;
} elseif ( ! empty( $scan_paused ) ) {
	Admin::add_notice( [
		'message' => __( 'Warning: You cannot change network settings while a scan is paused on one of the sites that are using network settings. Please resume the scan and let it finish or cancel the scan.', FIXALTTEXT_SLUG ),
		'alert_level' => 'warning',
	] );

	$disabled = true;
} elseif ( ! wp_doing_ajax() && 'POST' === REQUEST::SERVER_text_field('REQUEST_METHOD') ) {
	// Handle Saving Settings

	if ( wp_verify_nonce( REQUEST::text_field( 'nonce' ), FIXALTTEXT_SLUG . '-save-network-settings-' . $current_user_id ) ) {

		$var_types = [
			'sites' => 'array',
			'blocks' => 'array',
			'others' => 'array',
			'scan_post_types' => 'array',
			'scan_taxonomies' => 'array',
			'scan_users' => 'array',
			'access_tool_roles' => 'array',
			'access_settings_roles' => 'array',
		];

		// This makes sure that empty arrays exists for these keys if they do not exist
		$default_values = [
			'sites' => [],
			'blocks' => [],
			'others' => [],
			'scan_post_types' => [],
			'scan_taxonomies' => [],
			'scan_users' => [],
			'access_tool_roles' => [],
			'access_settings_roles' => [],
		];

		$settings->overwrite( REQUEST::POST( $var_types, $default_values ) );

		// Use saved settings
		$settings = $settings->save();

	} else {
		Admin::add_notice( [
			'message' => __( 'Error: Expired session. Please try again.', FIXALTTEXT_SLUG ),
			'alert_level' => 'error',
		] );
	}
}

Admin::display_header();

?>
    <form method="post" action="<?php
	echo esc_url( FIXALTTEXT_SETTINGS_NETWORK_URL ); ?>">
        <h2 style="text-align:center"><span class="dashicons dashicons-admin-multisite"></span> <?php
			esc_html_e( 'Network Settings', FIXALTTEXT_SLUG ); ?></h2>
        <p style="text-align:center"><?php
			esc_html_e( 'These settings are global and affect all sites selected below:', FIXALTTEXT_SLUG ); ?></p>
        <table class="settings-network">
            <tr class="sites-row">

                <td class="label">
                    <label for="sites[]"><?php
						esc_html_e( 'Sites Using Network Settings', FIXALTTEXT_SLUG ); ?></label>
                    <p class="info"><?php
						esc_html_e( "Choose which sites that you would like to use the network settings.", FIXALTTEXT_SLUG ); ?></p>
                </td>
                <td>
					<?php

					$options = [];
					foreach ( $sites as $site ) {

						if ( is_multisite() ) {
							switch_to_blog( $site->blog_id );
						}

						if ( is_plugin_active( FIXALTTEXT_PLUGIN ) ) {
							$scan_settings = Scans::get_current( true );

							// Links beside checkboxes
							$append = [];
							$append[] = [
								'text' => __( 'settings', FIXALTTEXT_SLUG ),
								'link' => get_admin_url( $site->blog_id, FIXALTTEXT_SETTINGS_URI ),
								'style' => '',
							];

							if ( isset( $scan_running[ $site->blog_id ] )){
								// This site's scan is running

								$append[] = [
									'text' => __( 'scan running', FIXALTTEXT_SLUG ),
									'link' => get_admin_url( $site->blog_id, FIXALTTEXT_ADMIN_URI . '#scan' ),
									'link-icon' => 'spin dashicons-update',
									'style' => 'color:#128000; font-weight: bold;',
									'before' => ' - ',
								];
							} else if ( isset( $scan_paused[ $site->blog_id ] )){
								// This site's scan is running

								$append[] = [
									'text' => __( 'scan paused', FIXALTTEXT_SLUG ),
									'link' => get_admin_url( $site->blog_id, FIXALTTEXT_ADMIN_URI . '#scan' ),
									'link-icon' => 'dashicons-controls-pause',
									'style' => 'color:#2271b1; font-weight: bold;',
									'before' => ' - ',
								];
							} else {
								// Site is not currently running a scan

								// Add link if a scan is needed
								if ( $scan_settings->get( 'needed' ) ) {

									$type_of_scan = $scan_settings->get( 'type' ) . ' scan';
									if( 'portion' === $scan_settings->get( 'type' ) ) {
										$type_of_scan .= ' (' . $scan_settings->get( 'portion' ) . ')';
									}

									$append[] = [
										'text' => sprintf( __( '%s needed', FIXALTTEXT_SLUG ), $type_of_scan ),
										'link' => get_admin_url( $site->blog_id, FIXALTTEXT_ADMIN_URI . '#scan' ),
										'style' => 'color:#ba4200;',
										'before' => ' - ',
									];
								}
							}

							$options[] = [
								'value' => $site->blog_id,
								'label' => $site->domain,
								'description' => '',
								'append' => $append,
							];
						}

						if ( is_multisite() ) {
							restore_current_blog();
						}
					}

					$args = [
						'property' => 'sites',
						'settings' => $settings,
						'options' => $options,
						'disabled' => $disabled,
					];

					Settings_Display_Library::checkboxes( $args );

					?>
                </td>
            </tr>
            <tr>
                <td colspan="2">

					<?php
					include( 'shared-settings.php' );
					?>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <p class="submit" style="text-align: center;">
				        <?php
				        if ( $disabled ) {
					        if ( ! empty( $scan_running ) ) {
						        echo esc_html__( 'Editing disabled while a scan is running.', FIXALTTEXT_SLUG );
					        } elseif ( ! empty( $scan_paused ) ) {
						        echo esc_html__( 'Editing disabled while a scan is paused.', FIXALTTEXT_SLUG );
					        } else {
                                echo esc_html__('Disabled for unknown reasons. Contact support.', FIXALTTEXT_SLUG );
                            }
				        } else { ?>
                            <input type="hidden" name="nonce" value="<?php
					        echo esc_attr( wp_create_nonce( FIXALTTEXT_SLUG . '-save-network-settings-' . $current_user_id ) ); ?>"/>

					        <?php
					        submit_button( __( 'Save Network Settings', FIXALTTEXT_SLUG ) );
                            ?>
				        <?php } ?>
                    </p>
                </td>
            </tr>
        </table>
    </form>
	<?php

Admin::display_footer();