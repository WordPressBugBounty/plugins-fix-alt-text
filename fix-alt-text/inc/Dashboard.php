<?php

namespace FixAltText;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WP_Roles;
use FixAltText\HelpersLibrary\Settings_Display_Library;

/**
 * Dashboard class
 *
 * @package FixAltText
 * @since   1.0.0
 */
final class Dashboard {

	/**
	 * Sets all the draggable dashboard widgets before we display the dashboard
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public static function set_widgets(): void {

		$scan_settings = Scans::get_current();

		if ( $scan_settings->get( 'needed' ) ) {
			if ( ! Scans::get_recent_scan()->get( 'start_date' ) ) {
				// Initial Scan Needed
				$show_dashboard = false;
			} else {
				// Scan needed but we have data
				$show_dashboard = true;
			}
		} else {
			$show_dashboard = true;
		}

		if ( $show_dashboard ) {

			wp_add_dashboard_widget( 'detected_issues', __( 'Detected Alt Text Related Issues', FIXALTTEXT_SLUG ), [
				self::class,
				'metabox_issues',
			], null, null, 'normal' );

		}

        wp_add_dashboard_widget( 'scan', __( 'Image Alt Text Scan', FIXALTTEXT_SLUG ), [
            self::class,
            'metabox_scan',
        ], null, null, 'normal' );

		wp_add_dashboard_widget( 'settings', __( 'Current Settings', FIXALTTEXT_SLUG ), [
			self::class,
			'metabox_settings',
		], null, null, 'side' );

	}

	/**
	 * Displays the current settings for this site
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public static function metabox_settings(): void {

		$settings = Settings::get_current_settings();

		if ( $settings->can_user_access_settings() ) {
			echo '<p>' . esc_html__( 'These setting affect the manual and automatic scans. If you adjust the settings, be sure to run a fresh scan.', FIXALTTEXT_SLUG ) . '</p>
			<p><a href="' . esc_url( FIXALTTEXT_SETTINGS_URL ) . '">' . esc_html__( 'Change Settings', FIXALTTEXT_SLUG ) . ' &raquo;</a></p>';
		} else {
			echo '<p>' . esc_html__( 'These setting affect the manual and automatic scans. If they are incorrect, please contact the site administrator.', FIXALTTEXT_SLUG ) . '</p>';
		}

		?>
        <hr/>

        <h2><span class="dashicons dashicons-images-alt"></span> <?php
			esc_html_e( 'Force Alt Text Options', FIXALTTEXT_SLUG ); ?></h2>
        <div class="padded-left">
			<?php
			Settings_Display_Library::list( __( 'Blocks Forcing Alt Text' ), $settings->get( 'blocks' ), 'dashicons-block-default' );
			Settings_Display_Library::list( __( 'Other Areas Forcing Alt Text' ), $settings->get( 'others' ), 'dashicons-images-alt' );
			?>
        </div>

        <h2><span class="dashicons dashicons-hourglass"></span> <?php
			esc_html_e( 'Scan Options', FIXALTTEXT_SLUG ); ?></h2>
        <div class="padded-left">
            <h3><span class="dashicons dashicons-admin-users"></span> <?php
				esc_html_e( 'Users', FIXALTTEXT_SLUG ); ?></h3>
			<?php
			$scan_users = $settings->get( 'scan_users' );
			echo '<ul><li>';
			echo ( $scan_users ) ? 'Yes' : 'No';
			echo '</li></ul>';
			?>
	        <?php
	        $post_types = $settings->get( 'scan_post_types', 'array' );
	        $all_post_types = [];

	        if ( ! empty( $post_types ) ) {
		        foreach ( $post_types as $slug ) {
			        if ( ! in_array( $slug, Get::excluded_post_types() ) ) {
				        $post_type = get_post_type_object( $slug );
				        $all_post_types[ $post_type->name ] = $post_type->label;
			        }
		        }
	        }

			Settings_Display_Library::list( __( 'Post Types' ), $all_post_types, 'dashicons-admin-post' );

			$taxonomies = $settings->get( 'scan_taxonomies', 'array' );
			$all_taxonomies = [];

			if ( ! empty( $taxonomies ) ) {
				foreach ( $taxonomies as $slug ) {
					$taxonomy = get_taxonomy( $slug );
					$all_taxonomies[ $taxonomy->name ] = $taxonomy->label . ' (' . $taxonomy->name . ') ';
				}
			}

			Settings_Display_Library::list( __( 'Taxonomies' ), $all_taxonomies, 'dashicons-tag' );
			?>
        </div>

		<?php

		// Grab the roles
		$roles_obj = new WP_Roles();
		$roles_names = $roles_obj->get_names();

		// Add the administrator to the list of role that can access
		$access_tool_roles = array_merge( [ 'administrator' ], $settings->get( 'access_tool_roles', 'array' ) );
		if ( ! empty( $access_tool_roles ) ) {
			foreach ( $access_tool_roles as $key => $role_slug ) {
				// Replace role slug with role name
				if ( isset( $roles_names[ $role_slug ] ) ) {
					$access_tool_roles[ $key ] = $roles_names[ $role_slug ];
				}
			}
		}

		$access_settings_roles = array_merge( [ 'administrator' ], $settings->get( 'access_settings_roles', 'array' ) );

		if ( ! empty( $access_settings_roles ) ) {
			foreach ( $access_settings_roles as $key => $role_slug ) {
				// Replace role slug with role name
				$access_settings_roles[ $key ] = $roles_names[ $role_slug ];
			}
		}
		?>

        <h2><span class="dashicons dashicons-admin-users"></span> <?php
			esc_html_e( 'User Access Options', FIXALTTEXT_SLUG ); ?></h2>
        <div class="padded-left">
			<?php
			Settings_Display_Library::list( __( 'Roles Can Access This Plugin' ), $access_tool_roles, 'dashicons-admin-plugins' );
			Settings_Display_Library::list( __( "Roles Can Modify This Plugin's Settings" ), $access_settings_roles, 'dashicons-admin-settings' );
			?>
        </div>
		<?php
	}

	/**
	 * Displays the scan button and progress bar
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public static function metabox_scan(): void {

		$scan_settings = Scans::get_current();
		$scan = Scans::get_recent_scan();

		$scan_start_date = $scan->get( 'start_date' );
		$scan_needed = $scan_settings->get( 'needed' );

		$settings = Settings::get_current_settings();

		if ( $settings->can_user_access_settings() ) {

            if ( $scan->is_running() ){

	            // Scan in progress
	            Scan_Process::display_progress_bar();

            }elseif ( $scan_needed || ! $scan_start_date ) {

	            if ( $scan->is_paused() ) {

		            Scan::display_scan_stats();

	            } elseif ( $scan_start_date ) {
		            // New Scan is needed
		            Scan::display_start_scan_button( 'new' );

		            Scan::display_scan_stats();
	            } else {
		            // Initial Scan is needed
		            Scan::display_start_scan_button();
	            }
			} else {
				// New Scan is needed
				Scan::display_start_scan_button( 'new' );

				Scan::display_scan_stats();
			}

		} else {

			// User doesn't have access to run a scan

			Scan::display_scan_stats();

			?><p><span class="notice-text"><?php
				_e( 'Notice:', FIXALTTEXT_SLUG ); ?></span> <?php
			_e( 'Only users with access to FixAltText settings can start full scans. Please contact your website administrator for assistance.', FIXALTTEXT_SLUG ); ?></p><?php
		}

	}

	/**
	 * Media Library Images Without Default Alt Text
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public static function metabox_issues(): void {

		$settings = Settings::get_current_settings();
		$scan_settings = Scans::get_current();
		$scan = Scans::get_recent_scan();

		if ( $scan_settings->get( 'needed' ) && ! $scan->get( 'start_date' ) ) {
			// Initial Scan Needs to be run
			echo '<p>' . esc_html__( 'An initial full scan needs to be ran before results are available.', FIXALTTEXT_SLUG ) . '</p>';

			if ( ! $settings->can_user_access_settings() ) {
				echo '<p>' . esc_html__( 'Contact your site administrator to perform a full scan.', FIXALTTEXT_SLUG ) . '</p>';
			}
		} else {
			if ( $scan_settings->get( 'needed' ) ){

				// Initial Scan Needs to be run
				echo '<p>' . esc_html__( 'A full scan needs to be ran before results are available.', FIXALTTEXT_SLUG ) . ' <a href="#scan" class="dashboard-start-scan">'.esc_html__('Start Scan', FIXALTTEXT_SLUG).'</a></p>';

			} else {
				$issues = Filters::get_issues( true );

				if ( empty( $issues ) ) {
					echo '<p>' . esc_html__( 'Congratulations! We did not detect any issues with your alt text for your images.', FIXALTTEXT_SLUG ) . '</p>';
				} else {

					// Calculate issues
					$total_issues = 0;
					foreach ( $issues as $issue_count ) {
						$total_issues += $issue_count;
					}

					$all_issues = Get::issues();
					$colors_key = [];

					// Build color key
					foreach ( $all_issues as $issue_key => $issue_details ) {
						$colors_key[ $issue_key ] = $issue_details['color'];
					}

					/**
					 * Filter: fixalttext_issues_color_key - Associate a specific color for custom issues in the pie chart on the dashboard.
					 *
					 * @package FixAltText
					 * @since 1.2.0
					 *
					 * @param array $colors_key
					 *
					 * @return array
					 */
					$colors_key = apply_filters_deprecated( FIXALTTEXT_HOOK_PREFIX . 'issues_color_key', [ $colors_key ], '1.8.0', FIXALTTEXT_HOOK_PREFIX . 'get_issues' );

					$labels = [];
					$data = [];
					$colors = [];

					foreach ( $issues as $issue => $count ) {
						$label = ucwords( str_replace( '-', ' ', $issue ) );
						$labels[] = $label;
						$data[] = $count;
						$colors[] = $colors_key[ $issue ];
					}

					$labels = implode( '|', $labels );
					$data = implode( '|', $data );
					$colors = implode( '|', $colors );

					echo '<div class="doughnut-chart"><canvas id="issues-chart" data-labels="' . esc_attr( $labels ) . '" data-datasets-label="' . esc_attr__( 'Detected Alt Text Issues', FIXALTTEXT_SLUG ) . '" data-data="' . esc_attr( $data ) . '" data-backgroundColor="' . esc_attr( $colors ) . '"></canvas><div class="center-label">' . wp_kses( sprintf( __( 'Found <span>%d</span> Issues', FIXALTTEXT_SLUG ), $total_issues ), [ 'span' => [] ] ) . '</div></div>
				<br /><p style="font-weight: bold; text-align: center">' . esc_html__( 'Click on the legend below to view results.', FIXALTTEXT_SLUG ) . '</p>';

					// Display ordered list of issues with links
					echo '<ul class="chart-legend">';
					foreach ( $issues as $issue => $count ) {
						$label = ucwords( str_replace( '-', ' ', $issue ) );
						echo '<li><a href="' . esc_url( FIXALTTEXT_ADMIN_URL ) . '&tab=references&issue=' . esc_attr( $issue ) . '"><span class="color-box" style="background: ' . esc_attr( $colors_key[ $issue ] ) . '">' . esc_html( $count ) . '</span>' . esc_html( $label ) . '</a></li>';
					}
					echo '</ul>';

				}
			}

		}

	}

}
