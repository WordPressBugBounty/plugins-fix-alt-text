<?php
namespace FixAltText\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\Run;
use FixAltText\Settings;
use FixAltText\Scan;
use FixAltText\Scans;
use FixAltText\Admin;

/**
 * Class Admin - The base functionality of the admin
 */
abstract class Admin_Library {

	/**
	 * Set Hooks and display errors
	 */
	public static function init(): void {

		if ( wp_doing_ajax() ) {
			// Setup AJAX Requests

			/**
			 * Make JS AJAX compatible with network admin
			 *
			 * @internal In JS send AJAX variable networkAdmin with true value to force is_network_admin() as true in PHP
			 */
			if ( REQUEST::bool( 'networkAdmin' ) ) {
				if ( ! defined( 'WP_NETWORK_ADMIN' ) ) {
					define( 'WP_NETWORK_ADMIN', true );
				}
			}

			// Display Page **Must use static:: so that AJAX works properly
			add_action( 'wp_ajax_' . FIXALTTEXT_HOOK_PREFIX . 'page', [
				static::class,
				'display_page',
			] );

		} else {

			// No AJAX

			if ( FIXALTTEXT_SLUG === REQUEST::key( 'page' ) ) {

				if ( ! defined( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_HIDE_NOTICES' ) ) {
					// Hide all other plugin notices on this page
					define( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_HIDE_NOTICES', true );
				}

				if ( ! defined( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_BODY_CLASS' ) ) {
					// Force styling to plugin pages
					define( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_BODY_CLASS', true );
				}

			}

			add_action( 'network_admin_menu', [
				static::class,
				'admin_menu',
			], 9999 );

			add_action( 'admin_menu', [
				static::class,
				'admin_menu',
			], 9999 );

			add_action( 'admin_head', [
				self::class,
				'hide_all_admin_notices',
			], 999 );

			add_filter( 'admin_body_class', [
				self::class,
				'body_class',
			], 999 );

			// Scripts that load on a full page load
			add_action( 'admin_enqueue_scripts', [
				self::class,
				'scripts',
			], 999 );

		}

		add_action( 'after_plugin_row_' . FIXALTTEXT_PLUGIN, [
			static::class,
			'prevent_update',
		], 0, 2 );

		add_action( FIXALTTEXT_HOOK_PREFIX . 'admin_notices', [
			static::class,
			'display_notices',
		], 999, 0 );

		// Hook into Helper's Library get_current_tab()
		add_filter( FIXALTTEXT_HOOK_PREFIX . 'admin_current_tab', [
			static::class,
			'get_current_tab',
		], 10, 0 );

		add_action( FIXALTTEXT_HOOK_PREFIX . 'display_header', [
			static::class,
			'display_scan_needed_notice',
		], 10, 0 );

		// Hook into Helper's Library to display notices inside of display_page()
		add_action( FIXALTTEXT_HOOK_PREFIX . 'admin_notices', [
			static::class,
			'check_notices',
		], 0 );

		// Add links to plugins page
		add_filter( 'plugin_action_links', [
			self::class,
			'plugin_action_links',
		], 10, 2 );

		add_filter( 'network_admin_plugin_action_links', [
			self::class,
			'plugin_action_links',
		], 10, 2 );

	}

	/**
	 * Adds links to this plugin on the plugin's management page
	 *
	 * @param array  $links Array of links for the plugins, adapted when the current plugin is found.
	 * @param string $file  The filename for the current plugin, which the filter loops through.
	 *
	 * @return array
	 */
	public static function plugin_action_links( $links, $file ): array {

		// Show Settings Link
		if ( FIXALTTEXT_PLUGIN === $file ) {

			if ( is_network_admin() ) {
				$label = __( 'Network Settings', FIXALTTEXT_SLUG );
				$settings_url = FIXALTTEXT_SETTINGS_NETWORK_URL;
			} else {
				$label = __( 'Settings', FIXALTTEXT_SLUG );
				$settings_url = FIXALTTEXT_SETTINGS_URL;
			}
			// Settings Link
			$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html( $label ) . '</a>';
			array_unshift( $links, $settings_link );

			if ( ! is_network_admin() ) {
				// Dashboard Link

				$dashboard_link = '<a href="' . esc_url( FIXALTTEXT_ADMIN_URL . '&tab=dashboard' ) . '">' . esc_html__( 'Dashboard', FIXALTTEXT_SLUG ) . '</a>';
				array_unshift( $links, $dashboard_link );
			}

			$review_url = str_replace( 'https://wordpress.org/plugins/', 'https://wordpress.org/support/plugin/', FIXALTTEXT_WP_URL ) . 'reviews/#new-post';

			// Style Links
			$rate_link = '
<br /><a href="' . esc_attr( $review_url ) . '">' . esc_html__( 'Rate:', FIXALTTEXT_SLUG ) . ' <span class="rate-us" data-stars="5"><span class="dashicons dashicons-star-filled star-1" title="' . esc_html__( 'Poor', FIXALTTEXT_SLUG ) . '"></span><span class="dashicons dashicons-star-filled star-2" title="' . esc_html__( 'Works', FIXALTTEXT_SLUG ) . '"></span><span class="dashicons dashicons-star-filled star-3" title="' . esc_html__( 'Good', FIXALTTEXT_SLUG ) . '"></span><span class="dashicons dashicons-star-filled star-4" title="' . esc_html__( 'Great', FIXALTTEXT_SLUG ) . '"></span><span class="dashicons dashicons-star-filled star-5" title="' . esc_html__( 'Fantastic!', FIXALTTEXT_SLUG ) . '"></span></span></a>
<style>
	.plugins .plugin-title [class*=dashicons-star-]{
		float: none;
		width: auto;
		height: auto;
		padding: 0;
		background: none;
	}
	.plugins .plugin-title .rate-us [class*=dashicons-star-]:before {
        font-size: 20px;
        color: #ffb900;
        background: none;
        padding: 0;
        box-shadow: none;
	}
	.plugins .plugin-title .rate-us:hover span:before {
		content: "\f154";
	}
	
	.plugins .plugin-title .rate-us:hover .star-1:before,
	.plugins .plugin-title .rate-us[data-stars="2"]:hover span.star-2:before,
	.plugins .plugin-title .rate-us[data-stars="3"]:hover span.star-2:before,
	.plugins .plugin-title .rate-us[data-stars="3"]:hover span.star-3:before,
	.plugins .plugin-title .rate-us[data-stars="4"]:hover span.star-2:before,
	.plugins .plugin-title .rate-us[data-stars="4"]:hover span.star-3:before,
	.plugins .plugin-title .rate-us[data-stars="4"]:hover span.star-4:before,
	.plugins .plugin-title .rate-us[data-stars="5"]:hover span:before {
		content: "\f155";
	}
</style>
<script>
jQuery(".plugins .plugin-title .rate-us span").on("mouseover", function(){
    let stars = jQuery(this).index() + 1;
   jQuery(this).closest(".rate-us").attr("data-stars", stars);
});
</script>';
			$links[] = $rate_link;
		}

		return $links;

	}

	/**
	 * Displays the notice to tell user that a scan is needed
	 *
	 * @return void
	 */
	public static function display_scan_needed_notice(): void {
		if ( ! is_network_admin() && ! Scans::has_full_scan_ran() && ! Scans::is_full_scan_running() ) {
			// Scan is needed or the last scan is not finished
			Scan::display_results_not_available( true );
		}
	}

	/**
	 * Prevents the user from updating the plugin if the plugin directory is on GIT versioning
	 *
	 * @param string $file        Plugin basename.
	 * @param array  $plugin_data Plugin information.
	 *
	 * @return void
	 */
	public static function prevent_update( string $file, array $plugin_data ): void {

		$current = get_site_transient( 'update_plugins' );
		if ( ! isset( $current->response[ $file ] ) || ! file_exists(FIXALTTEXT_DIR . '/.git') ) {
			return;
		}

		// Remove the core update notice
		remove_action( "after_plugin_row_{$file}", 'wp_plugin_update_row', 10, 2 );

		$response = $current->response[ $file ];
		$plugin_slug = isset( $response->slug ) ? $response->slug : $response->id;

		$plugins_allowedtags = [
			'a' => [
				'href' => [],
				'title' => [],
			],
			'abbr' => [ 'title' => [] ],
			'acronym' => [ 'title' => [] ],
			'code' => [],
			'em' => [],
			'strong' => [],
		];

		/** @var WP_Plugins_List_Table $wp_list_table */
		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table', [
			'screen' => get_current_screen(),
		] );

		// Determine if this plugin is actually active in this scope
		if ( is_network_admin() ) {
			$active_class = is_plugin_active_for_network( $file ) ? ' active' : '';
		} else {
			$active_class = is_plugin_active( $file ) ? ' active' : '';
		}

		printf( '<tr class="plugin-update-tr%s" id="%s" data-slug="%s" data-plugin="%s">' . '<td colspan="%s" class="plugin-update colspanchange">' . '<div class="update-message notice inline notice-warning notice-alt"><p>', $active_class, esc_attr( $plugin_slug . '-update' ), esc_attr( $plugin_slug ), esc_attr( $file ), esc_attr( $wp_list_table->get_column_count() ), );

		// Display a notice telling the user to PULL from git repo
		echo 'Version ' . esc_html( $response->new_version ) . ' of ' . wp_kses( $plugin_data['Name'], $plugins_allowedtags ) . ' is available. Git Repo: Please PULL from the MAIN branch.';

	}

	/**
	 * Adds class to the admin body so that we can force styling it appropriately
	 *
	 * @param string $classes
	 *
	 * @return string
	 */
	public static function body_class( string $classes ): string {

		if ( defined( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_BODY_CLASS' ) ) {
			$classes .= ' tools-page-custom';
		}

		return $classes;

	}

	/**
	 * Hides all admin notices from other plugins and core
	 */
	public final static function hide_all_admin_notices(): void {

		if ( defined( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_HIDE_NOTICES' ) && ! defined( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_HIDE_NOTICES_CLEARED' ) ) {

			define( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_HIDE_NOTICES_CLEARED', true );

			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
			remove_all_actions( 'user_admin_notices' );
			remove_all_actions( 'network_admin_notices' );
		}

	}

	/**
	 * Load Scripts
	 */
	public static function scripts(): void {

		if ( defined( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_SCRIPTS' ) ) {
			wp_enqueue_script( FIXALTTEXT_HELPERSLIBRARY_SLUG . '-plugin', FIXALTTEXT_HELPERSLIBRARY_ASSETS_URL . '/js/plugin.js', [], filemtime( FIXALTTEXT_HELPERSLIBRARY_ASSETS_DIR . '/js/plugin.js' ), 'all' );
			wp_localize_script( FIXALTTEXT_HELPERSLIBRARY_SLUG . '-plugin', 'FixAltTextHelpersLibraryAjax', [ 'ajaxURL' => admin_url( 'admin-ajax.php' ) ] );

			wp_enqueue_script( FIXALTTEXT_HELPERSLIBRARY_SLUG . '-notifications', FIXALTTEXT_HELPERSLIBRARY_ASSETS_URL . '/js/notifications.js', [], filemtime( FIXALTTEXT_HELPERSLIBRARY_ASSETS_DIR . '/js/notifications.js' ), 'all' );
			wp_localize_script( FIXALTTEXT_HELPERSLIBRARY_SLUG . '-notifications', 'FixAltTextHelpersLibraryAjax', [ 'ajaxURL' => admin_url( 'admin-ajax.php' ) ] );
		}

		if ( defined( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_DASHBOARD_SCRIPTS' ) ) {
			wp_enqueue_script( 'dashboard' );
			wp_admin_css( 'dashboard' );
			add_thickbox();
		}

		if ( defined( 'FIXALTTEXT_HELPERSLIBRARY_ADMIN_STYLING' ) ) {
			wp_enqueue_style( FIXALTTEXT_HELPERSLIBRARY_SLUG . '-styles', FIXALTTEXT_HELPERSLIBRARY_ASSETS_URL . '/styles.css', [], filemtime( FIXALTTEXT_HELPERSLIBRARY_ASSETS_DIR . '/styles.css' ) );
		}

	}

	/**
	 * Tells you which tab you are on adn sets the default tab
	 *
	 * @return string
	 */
	public static function get_current_tab(): string {

		$tab = REQUEST::key( 'tab' );

		// Compensate for missing dashboard in network
		if ( is_network_admin() ) {
			if ( '' === $tab ) {
				$tab = 'settings';
			}
		} else {

			// Default to dashboard
			if ( '' === $tab ) {
				$tab = 'dashboard';
			}
		}

		return $tab;
	}

	/**
	 * Displays the page
	 */
	public static function display_page(): void {

		Run::prevent_caching();

		$is_network_admin = is_network_admin();

		$page = REQUEST::key( 'page' );
		$prefix = $is_network_admin ? 'network-' : '';
		$tab = Admin::get_current_tab();

		// Template We are loading
		$template = FIXALTTEXT_TEMPLATES_DIR . '/' . $prefix . $tab . '.php';

		if ( file_exists( $template ) ) {

			// Load Template
			include_once( $template );

		} else {

			static::display_header();

			echo '<p>' . __( 'Error: This page does not exist: ', FIXALTTEXT_SLUG ) . esc_html( $template ) . '</p>';

			static::display_footer();
		}

	}

	/**
	 * Displays the page header
	 *
	 * @return void
	 */
	public static function display_header( string $h1 = '' ): void {

		if ( wp_doing_ajax() ) {
			// AJAX: Send status header before outputting html
			header( "HTTP/1.1 200 Ajax Response" );

			echo '<div class="wrap">';
		} else {

			include( FIXALTTEXT_TEMPLATES_DIR . '/header.php' );

			echo '<div class="content-body">';
			echo '<div class="wrap">';
		}

		// Hook to trigger any actions before the header displays anything within the content area
		do_action( FIXALTTEXT_HOOK_PREFIX . 'display_header' );

		if ( $h1 ) {
			echo '<h1>' . esc_html( $h1 ) . '</h1>';
		}

		do_action( FIXALTTEXT_HOOK_PREFIX . 'admin_notices' );

	}

	/**
	 * Displays the page footer
	 *
	 * @return void
	 */
	public static function display_footer(): void {

		echo '</div><!-- .wrap -->';

		if ( wp_doing_ajax() ) {
			// Stop PHP for AJAX
			exit;
		} else {

			echo '</div><!-- .content-body -->';
		}
	}

	/**
	 * Adds a notice to be displayed
	 *
	 * @param array $notice
	 */
	public final static function add_notice( array $notice ): void {

		// Grab plugin's global
		global $fixalttext;

		$notices = $fixalttext['admin-notices'] ?? [];
		$notices[] = $notice;

		$fixalttext['admin-notices'] = $notices;

	}

	/**
	 * Displays all admin notices
	 */
	public final static function display_notices(): void {

		// Grab plugin's global
		global $fixalttext;

		if ( ! empty( $fixalttext['admin-notices'] ) ) {

			foreach ( $fixalttext['admin-notices'] as $notice ) {

				// Display Single Notice
				static::display_notice( $notice );

			}

			// Reset Messages
			$fixalttext['admin-notices'] = [];

		}

	}

	/**
	 * Displays given message in the admin.
	 *
	 * @param array $notice
	 *
	 * @return void
	 */
	protected final static function display_notice( array $notice ): void {

		$message = $notice['message'] ?? '';

		if ( $message ) {

			$plugin = $notice['plugin'] ?? '';
			$link_url = $notice['link_url'] ?? '';
			$link_anchor_text = $notice['link_anchor_text'] ?? '';
			$alert_level = $notice['alert_level'] ?? '';
			$dismiss = $notice['dismiss'] ?? false;

			$classes = [];

			// Set Classes
			$classes[] = 'notice-success';
			if ( $alert_level == 'info' ) {
				$classes[] = 'notice-info';
			} elseif ( $alert_level == 'warning' ) {
				$classes[] = 'notice-warning';
			} elseif ( $alert_level == 'error' ) {
				$classes[] = 'notice-error';
			}

			$classes[] = 'active';
			$classes[] = 'notice';

			if ( $dismiss ) {
				$classes[] = ' is-dismissible';
			}

			if ( $plugin ) {
				$classes[] = 'plugin-' . $plugin;
			}

			// Each message must be sanitized when set due to variability of message types
			echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"><p>' . esc_html( $message );
			if ( $link_url && $link_anchor_text ) {
				echo ' <a href="' . esc_url( $link_url ) . '">' . esc_html( $link_anchor_text ) . '</a>';
			}
			echo '</p>';

			if ( $dismiss ) {
				echo '<button type="button" class="notice-dismiss" onclick="(function(el){el.closest(\'.notice\').remove();})(this)"><span class="screen-reader-text">' . esc_html__( 'Dismiss this notice', FIXALTTEXT_SLUG ) . '.</span></button>';
			}

			echo '</div>';

		}

	}

	/**
	 * Displays notices if needed
	 *
	 * @return void
	 */
	public static function check_notices(): void {

		$scans = Scans::get_current( true );

		if ( $scans->get( 'needed' ) ) {
			// Scan is needed

			$settings = Settings::get_current_settings();

			if ( $settings->can_user_access_settings() ) {
				// User can run a scan themselves
				if ( ! empty( $scans->get( 'history' ) ) ) {
					// Not our first rodeo
					$message = __( 'Settings have changed.', FIXALTTEXT_SLUG );
					$link_url = FIXALTTEXT_ADMIN_URL . '#scan';
					$link_anchor_text = __( 'Please run a new scan', FIXALTTEXT_SLUG );
				} else {
					$message = __( 'First, review settings and then, run an initial scan.', FIXALTTEXT_SLUG );
				}

			} else {
				// User doesn't have access to run a scan

				if ( ! empty( $scans->get( 'history' ) ) ) {
					// Not our first rodeo
					$message = __( 'Settings have changed. Please request the administrator to run a new scan.', FIXALTTEXT_SLUG );
				} else {
					// Needs an initial scan
					$message = __( 'Please request the administrator to run an initial full scan.', FIXALTTEXT_SLUG );
				}
			}

			if ( $message ) {
				// Set defaults
				$link_url = $link_url ?? '';
				$link_anchor_text = $link_anchor_text ?? '';

				self::add_notice( [
					'message' => $message,
					'link_url' => $link_url,
					'link_anchor_text' => $link_anchor_text,
					'alert_level' => 'warning',
				] );
			}

		}
	}

	/**
	 * Checks to see if the user has the permissions to view this resource and blocks them if they do not.
	 *
	 * @param bool   $network_only
	 * @param string $error
	 * @param bool   $return
	 * @param bool   $can_access_tool
	 *
	 * @return string
	 */
	public static function check_permissions( bool $network_only = false, string $error = '', bool $return = false ): string {

		$can_access_tool = Settings::get_current_settings()->can_user_access_tool();

		if ( $network_only ) {

			if ( ! current_user_can( 'manage_network' ) ) {
				$error = __( 'Network - You do not have sufficient permissions to access this page.', FIXALTTEXT_SLUG );
			}

		} else {
			if ( ! $can_access_tool ) {
				if ( wp_doing_ajax() ) {
					$error = __( 'You do not have proper access to do this AJAX request.', FIXALTTEXT_SLUG );
				} elseif ( is_admin() ) {
					$error = __( 'You do not have sufficient permissions to access this page.', FIXALTTEXT_SLUG );
				} else {
					// Catch All
					$error = __( 'You do not have sufficient permissions.', FIXALTTEXT_SLUG );
				}
			}
		}


		if ( ! $return && $error ) {
			die( $error );
		}

		return $error;

	}

	/**
	 * Add A Setting Page: Admin > Settings > SARE Backups
	 *
	 * @return void
	 */
	public static function admin_menu(): void {

		if ( is_multisite() ) {

			if ( is_network_admin() ) {
				// Super Admins Only
				add_submenu_page( 'settings.php', FIXALTTEXT_NAME, FIXALTTEXT_NAME, 'manage_network', FIXALTTEXT_SLUG, [
					self::class,
					'display_page',
				] );
			}

		}

		if ( is_admin() ) {

			$settings = Settings::get_current_settings();

			if ( $settings->can_user_access_tool() ) {
				// User is allowed access
				add_submenu_page( 'tools.php', FIXALTTEXT_NAME, FIXALTTEXT_NAME, Settings::get_user_access_capability(), FIXALTTEXT_SLUG, [
					self::class,
					'display_page',
				] );
			}

		}

	}

}
