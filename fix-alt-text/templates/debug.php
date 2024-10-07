<?php

namespace FixAltText;

/**
 * Debug Page
 *
 * Provides tools to debug issues
 *
 * @package FixAltText
 * @since   1.2.0
 */

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

if ( is_network_admin() ) {
	// Load Network Debug Page Instead
	include( FIXALTTEXT_TEMPLATES_DIR . 'network-debug.php' );

	return;
}

Admin::check_permissions();

// Grab settings
$settings = Settings::get_current_settings( true );

if ( ! $settings->can_user_access_settings() ) {
	error_log( $error = __( 'You do not have permission to access settings.', FIXALTTEXT_SLUG ) );
	die( $error );
}

Admin::display_header();

echo Get::subheader();
?>

    <nav class="nav-tab-wrapper">
        <a href="#settings" class="nav-tab nav-tab-active nav-tab-settings"><?php
			_e( 'Settings', FIXALTTEXT_SLUG ); ?></a> <a href="#scans" class="nav-tab nav-tab-scans"><?php
			_e( 'Scans', FIXALTTEXT_SLUG ); ?></a> <a href="#constants" class="nav-tab nav-tab-constants"><?php
			_e( 'Constants', FIXALTTEXT_SLUG ); ?></a> <a href="#logs" class="nav-tab nav-tab-logs"><?php
			_e( 'Logs', FIXALTTEXT_SLUG ); ?></a>
    </nav>
    <div class="all-tab-content">
        <section id="settings" class="tab-content active">
            <h2><?php
				_e( 'Settings', FIXALTTEXT_SLUG ); ?></h2>

			<?php
			Debug::display_table( $settings, 'settings' ); ?>
        </section>
        <section id="scans" class="tab-content">
			<?php
			$scan_settings = Scans::get_current( true );
			?>
            <h2><?php
				_e( 'Type of Scan Needed', FIXALTTEXT_SLUG ); ?></h2>
			<?php
			Debug::display_table( $scan_settings, 'scan', [ 'history' ] );
			?>

            <br><br>
            <h2><?php
				_e( 'Scan History Details', FIXALTTEXT_SLUG ); ?></h2>

			<?php
			$history = $scan_settings->get( 'history' );

			if ( empty( $history ) ) {
				echo '<p>' . __( 'No scans found.', FIXALTTEXT_SLUG ) . '</p>';
			} else {

				?>

                <div style="width:100%; height: 500px; overflow: auto;">
					<?php

					$exclude = [
						'needed',
						'history',
						'started',
					];

					foreach ( $history as $h ) {

						$history_scan = new Scan( $h );
						Debug::display_table( $history_scan, 'scan', $exclude );
					}
					?>
                </div><p><a href="<?php
					echo esc_url( FIXALTTEXT_ADMIN_URL . '&tab=debug&reset-scans=1' ); ?>" class="button-link button-link-delete"><?php
						_e( 'Clear
                    Scan History', FIXALTTEXT_SLUG ) ?></a></p>

				<?php
			} ?>
        </section>
        <section id="constants" class="tab-content">
            <h2><?php
				_e( 'Constant Variables', FIXALTTEXT_SLUG ); ?></h2>

			<?php
			Debug::display_constants_table();
			?>
        </section>
        <section id="logs" class="tab-content">
            <h2><?php
				_e( 'Debug Log', FIXALTTEXT_SLUG ); ?></h2>

            <textarea id="debug-log" style="width:100%; height:500px; background: black; color: white;">
                <?php
                // Grab existing content
                $data = ( file_exists( Debug::get_debug_log_file() ) ) ? file_get_contents( Debug::get_debug_log_file() ) : 'Debug log not found';

                echo $data;
                ?>
            </textarea>

            <p><a href="<?php
				echo esc_url( FIXALTTEXT_ADMIN_URL . '&tab=debug&reset=1' ); ?>" class="button-link button-link-delete"><?php
					_e( 'Clear Log', FIXALTTEXT_SLUG ) ?></a></p>

        </section>
    </div>

	<?php
Admin::display_footer();
