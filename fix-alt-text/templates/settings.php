<?php

namespace FixAltText;

/**
 * Site Settings Page
 *
 * This page manages settings for a specific site
 *
 */

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

Admin::check_permissions();

use FixAltText\HelpersLibrary\REQUEST;

// Grab settings
$settings = Settings::get_current_settings( true );

if ( ! $settings->can_user_access_settings() ) {
	error_log( $error = __( 'You do not have permission to access settings.', FIXALTTEXT_SLUG ) );
	die( $error );
}

$current_user_id = get_current_user_id();

$using_network_settings = $settings->using_network_settings();
$settings_network = [];

$disabled = false;

$scan_running = [];
$scan_paused = [];

if ( ! $using_network_settings && ! is_null( $scan = Scans::get_active_scan() ) ) {
	// A Scan is currently running

	$scan_running[ get_current_blog_id() ] = $scan;
	$disabled = true;

	Admin::add_notice( [
		'message' => __( 'Warning: You cannot changes setting while a scan is running. Please cancel the scan on the dashboard or wait for it to finish.', FIXALTTEXT_SLUG ),
		'alert_level' => 'warning',
	] );

} elseif ( ! $using_network_settings && ! is_null( $scan = Scans::get_paused_scan() ) ) {

	// A Scan is currently paused

	$scan_paused[ get_current_blog_id() ] = $scan;
	$disabled = true;

	Admin::add_notice( [
		'message' => __( 'Warning: You cannot changes setting while a scan is paused. Please cancel the scan on the dashboard or resume the scan and let it finish.', FIXALTTEXT_SLUG ),
		'alert_level' => 'warning',
	] );

} else if ( ! wp_doing_ajax() && ! $using_network_settings && 'POST' === REQUEST::SERVER_text_field('REQUEST_METHOD') ) {

	// Handle Saving Settings
	if ( wp_verify_nonce( REQUEST::text_field( 'nonce' ), FIXALTTEXT_SLUG . '-save-settings-' . $current_user_id ) ) {

		$var_types = [
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
			'blocks' => [],
			'others' => [],
			'scan_post_types' => [],
			'scan_taxonomies' => [],
			'scan_users' => [],
			'access_tool_roles' => [],
			'access_settings_roles' => [],
		];

		$settings->overwrite( REQUEST::POST($var_types, $default_values) );
		$settings->save();

	} else {
		Admin::add_notice( [
			'message' => __( 'Error: Expired session. Please try again.', FIXALTTEXT_SLUG ),
			'alert_level' => 'error',
		] );

	}
}

Admin::display_header();

echo Get::subheader();

?>
    <form method="post" action="<?php
	echo esc_url( FIXALTTEXT_SETTINGS_URL ); ?>">
        <table class="settings-site">
            <tr>
                <td>
					<?php

					if ( $using_network_settings ) {

						echo '<div class="notice-success notice-info active notice">';

						// Notify the user that we are using network settings
						if ( current_user_can( 'manage_network' ) ) {
							echo '<p>' . wp_kses( sprintf( __( 'This site is using the <a href="%s">network settings</a>.', FIXALTTEXT_SLUG ), FIXALTTEXT_SETTINGS_NETWORK_URL ), [ 'a' => [ 'href' => [] ] ] ) . '</p>';
						} else {
							echo '<p>' . esc_html__( 'Settings are maintained at the network level. Contact your site administrator.', FIXALTTEXT_SLUG ) . '</p>';
						}

                        echo '</div>';
					}

					// Display Shared Settings
					include( 'shared-settings.php' );

					?>
                </td>
            </tr>
            <tr>
                <td>
                    <p class="submit" style="text-align: center;">
			<?php
			if ( $using_network_settings ) {
                        // Notify the user that we are using network settings
                        if ( current_user_can( 'manage_network' ) ) {
                            echo wp_kses( sprintf( __( 'This site is using the <a href="%s">network settings</a>.', FIXALTTEXT_SLUG ), FIXALTTEXT_SETTINGS_NETWORK_URL ), [ 'a' => [ 'href' => [] ] ] );
                        } else {
                            echo esc_html__( 'Settings are maintained at the network level. Contact your site administrator.', FIXALTTEXT_SLUG );
                        }
			} elseif ( ! empty( $scan_running ) || ! empty( $scan_paused ) ) {
				if ( ! empty( $scan_running ) ) {
					echo esc_html__( 'Editing disabled while a scan is running.', FIXALTTEXT_SLUG );
				} elseif ( ! empty( $scan_paused ) ) {
					echo esc_html__( 'Editing disabled while a scan is paused.', FIXALTTEXT_SLUG );
				}
            } else { ?>
                    <input type="hidden" name="nonce" value="<?php
                    echo esc_attr( wp_create_nonce( FIXALTTEXT_SLUG . '-save-settings-' . $current_user_id ) ) ?>"/>
                    <?php
                    submit_button( __( 'Save Settings', FIXALTTEXT_SLUG ) );
                    ?>
			<?php } ?>
                    </p>
                </td>
            </tr>

        </table>
    </form>
	<?php

Admin::display_footer();