<?php

namespace FixAltText;

use WP_Roles;
use FixAltText\HelpersLibrary\Settings_Display_Library as Settings_Display;

/**
 * Shared Settings Section
 *
 * This section of content is on both the network and the site settings pages
 */

// Prevent Direct Access (require main file to be loaded)
( defined( 'ABSPATH' ) ) || die;

$disabled_by_network = ! is_network_admin() && isset( $using_network_settings ) && $using_network_settings;
$disabled = $disabled_by_network || ! empty( $scan_running ) || ! empty( $scan_paused );

Admin::check_permissions();

// $settings should be set in the parent template file that includes this file (network.php or settings.php)
$settings = $settings ?? [];

// Create args for displaying checkboxes
$blocks = Get::blocks();
$options = [];
foreach ( $blocks as $label ) {
	$options[] = [
		'value' => $label,
		'label' => $label,
		'description' => '',
		'append' => [],
	];
}

$args = [
	'property' => 'blocks',
	'settings' => $settings,
	'options' => $options,
	'disabled' => $disabled,
];

?>
    <h2 style="text-align: center"><span class="dashicons dashicons-images-alt"></span> <?php
		esc_html_e( 'Force Alt Text Options', FIXALTTEXT_SLUG ); ?></h2>
    <table id="scan-options">

        <tr class="blocks-row">
            <td class="label"><label for="blocks"><?php
					esc_html_e( 'Blocks ', FIXALTTEXT_SLUG ); ?></label>
                <p class="info"><?php
					esc_html_e( "Choose which blocks should be forced to use alt text.", FIXALTTEXT_SLUG ); ?></p>
            </td>
            <td>
				<?php
				Settings_Display::checkboxes( $args );

				/**
				 * @todo this require some additional testing and the implementation of hooks and documentation so the users know how to use it
				 *
				 * if ( ! $disabled_by_network ) {
				 * $num = count( $blocks ) + 1;
				 *
				 * ?>
				 *
				 * <div class="block-row custom-block"><input name="blocks[]" value="" type="checkbox" CHECKED/> <label
				 * for="block-<?php
				 * echo esc_attr( $num ); ?>"><input type="text" value=""/>
				 * <button class="button remove"><span><?php
				 * esc_html_e( 'remove', FIXALTTEXT_SLUG ); ?></span></button>
				 * </label></div>
				 * <button id="add-custom-block" class="sec-button button"><?php
				 * esc_html_e( 'Add Custom Block', FIXALTTEXT_SLUG ); ?></button>
				 * <br/><small><i><?php
				 * esc_html_e( 'NOTICE: If you add a custom block, it must have the ability to input alt text, otherwise this setting will prevent you from saving the post.', FIXALTTEXT_SLUG ); ?></i></small>
				 *
				 * <?php
				 *
				 * }*/
				?>
            </td>
        </tr>
		<?php
		// Create args for displaying checkboxes
		$others = Get::others();
		$options = [];
		foreach ( $others as $label ) {

			$description = [];

			if ( 'Media Library' === $label ) {
				$description[] = [
					'text' => __( 'Disabling the media library setting will not force the user to input alt text while they are in the media library popup modal for a block. Affects the Gallery block, and others.', FIXALTTEXT_SLUG ),
				];
			}

			$options[] = [
				'value' => $label,
				'label' => $label,
				'description' => $description,
			];
		}

		$args = [
			'property' => 'others',
			'settings' => $settings,
			'options' => $options,
			'disabled' => $disabled,
		];
		?>

        <tr class="others-row">
            <td class="label"><label for="others"><?php
					esc_html_e( 'Other Areas ', FIXALTTEXT_SLUG ); ?></label>
                <p class="info"><?php
					esc_html_e( "Choose other areas where you want to force alt text.", FIXALTTEXT_SLUG ); ?></p>
            </td>
            <td>
				<?php
				Settings_Display::checkboxes( $args ); ?>
            </td>
        </tr>
    </table>

    <h2 style="text-align: center"><span class="dashicons dashicons-hourglass"></span> <?php
		esc_html_e( 'Scan Options', FIXALTTEXT_SLUG ); ?></h2>

    <p style="text-align: center"><?php
		esc_html_e( 'Choose which areas of the site to scan.', FIXALTTEXT_SLUG ); ?>
    </p>

    <table id="scan-options">
        <tr class="users-row">

            <td class="label"><label for="scan_users"><?php
					esc_html_e( 'Users', FIXALTTEXT_SLUG ); ?></label>
                <p class="info"><?php
					esc_html_e( 'Do you want to scan user meta?', FIXALTTEXT_SLUG ); ?></p>
            </td>
            <td>
				<?php

				$options = [];

				$options[] = [
					'value' => 1,
					'label' => 'Yes',
				];

				$options[] = [
					'value' => 0,
					'label' => 'No',
				];

				$args = [
					'property' => 'scan_users',
					'settings' => $settings,
					'options' => $options,
					'disabled' => $disabled,
				];

				Settings_Display::select( $args );

				?>
            </td>
        </tr>
        <tr class="post-types-row">

            <td class="label"><label for="scan_post_types"><?php
					esc_html_e( 'Post Types', FIXALTTEXT_SLUG ); ?></label>
                <p class="info"><?php
					esc_html_e( 'Choose the post types to scan. Scan includes content and post meta.', FIXALTTEXT_SLUG ); ?></p>
            </td>
            <td>
	            <?php
	            $all_post_types = [];

	            $excluded_post_types = Get::excluded_post_types();

	            if ( is_network_admin() ) {

		            $sites = get_sites();

		            if ( ! empty( $sites ) ) {
			            foreach ( $sites as $site ) {
				            switch_to_blog( $site->blog_id );
				            $post_types = get_post_types();

				            foreach ( $post_types as $slug ) {

					            if ( in_array( $slug, $excluded_post_types ) ) {
						            // Post type excluded
						            continue;
					            }

					            $post_type = get_post_type_object( $slug );
					            $all_post_types[ $post_type->name ] = $post_type->label . ' (' . $post_type->name . ') ';

				            }
				            restore_current_blog();
			            }
		            }

	            } else {
		            $post_types = get_post_types();

		            foreach ( $post_types as $slug ) {
			            if ( in_array( $slug, $excluded_post_types ) ) {
				            // Post type excluded
				            continue;
			            }

			            $post_type = get_post_type_object( $slug );
			            $all_post_types[ $post_type->name ] = $post_type->label . ' (' . $post_type->name . ') ';

		            }
	            }

	            asort( $all_post_types );

	            $options = [];

	            $recommended = Get::recommended_post_types();
	            if ( ! empty( $all_post_types ) ) {
		            foreach ( $all_post_types as $slug => $label ) {

			            if ( in_array( $slug, $excluded_post_types ) ) {
				            // Post type excluded
				            continue;
			            }

			            $append = [];

			            if ( in_array( $slug, $recommended ) ) {
				            $append[] = [
					            'text' => __( 'recommended', FIXALTTEXT_SLUG ),
					            'link' => '',
					            'style' => 'font-weight:bold; font-style:italic',
				            ];

				            // Add a dash between recommended and the label
				            $label .= ' - ';
			            }

			            $options[] = [
				            'value' => $slug,
				            'label' => $label,
				            'description' => '',
				            'append' => $append,
			            ];
		            }

		            $args = [
			            'property' => 'scan_post_types',
			            'settings' => $settings,
			            'options' => $options,
			            'disabled' => $disabled,
		            ];

		            Settings_Display::checkboxes( $args );
	            }

	            ?>

            </td>
        </tr>
        <tr class="taxonomies-row">

            <td class="label"><label for="scan_taxonomies"><?php
					esc_html_e( 'Taxonomies', FIXALTTEXT_SLUG ); ?></label>
                <p class="info"><?php
					esc_html_e( 'Choose taxonomies to scan. Scan includes term description and term meta.', FIXALTTEXT_SLUG ); ?></p>
            </td>
            <td>
				<?php
				$excluded_taxonomies = Get::excluded_taxonomies();

				if ( is_network_admin() ) {

					$all_taxonomies = [];
					$sites = get_sites();

					if ( ! empty( $sites ) ) {
						foreach ( $sites as $site ) {
							switch_to_blog( $site->blog_id );
							$taxonomies = get_taxonomies();

							foreach ( $taxonomies as $slug ) {
								if ( in_array( $slug, $excluded_taxonomies ) ) {
									continue;
								}
								$taxonomy = get_taxonomy( $slug );
								$all_taxonomies[ $taxonomy->name ] = $taxonomy->label . ' (' . $taxonomy->name . ') ';
							}
							restore_current_blog();
						}
					}

				} else {
					$all_taxonomies = [];
					$taxonomies = get_taxonomies();

					foreach ( $taxonomies as $slug ) {
						if ( in_array( $slug, $excluded_taxonomies ) ) {
							continue;
						}
						$taxonomy = get_taxonomy( $slug );
						$all_taxonomies[ $taxonomy->name ] = $taxonomy->label . ' (' . $taxonomy->name . ') ';
					}
				}

				asort( $all_taxonomies );

				$options = [];

				$recommended = Get::recommended_taxonomies();
				if ( ! empty( $all_taxonomies ) ) {
					foreach ( $all_taxonomies as $slug => $label ) {

						$append = [];
						if ( in_array( $slug, $recommended ) ) {
							$append[] = [
								'text' => __( 'recommended', FIXALTTEXT_SLUG ),
								'link' => '',
								'style' => 'font-weight:bold; font-style:italic',
							];
							// Add a dash between recommended and the label
							$label .= ' - ';
						}

						$options[] = [
							'value' => $slug,
							'label' => $label,
							'description' => '',
							'append' => $append,
						];
					}

					$args = [
						'property' => 'scan_taxonomies',
						'settings' => $settings,
						'options' => $options,
						'disabled' => $disabled,
					];

					Settings_Display::checkboxes( $args );
				}

				?>
            </td>
        </tr>
        <tr class="issues-row">

            <td class="label"><label for="scan_issues"><?php
				    esc_html_e( 'Alt Text Issues', FIXALTTEXT_SLUG ); ?></label>
                <p class="info"><?php
				    esc_html_e( 'These issue will be detected when a scan is performed.', FIXALTTEXT_SLUG ); ?></p>
            </td>
            <td>
			    <?php

			    $issues = Get::issues();
			    $options = [];

			    if ( ! empty( $issues ) ) {
				    foreach ( $issues as $slug => $values ) {

					    $specific_disabled = false;
                        $append = [];

                        if( 'alt-text-too-short' == $slug ) {
                            ob_start();

	                        Settings_Display::input( [
		                        'type' => 'number',
		                        'property' => 'scan_issues_min_words',
		                        'settings' => $settings,
		                        'disabled' => $disabled,
                                'style' => 'width: 62px;',
                                'default_value' => 3,
		                        'attributes' => ['min' => 3, 'max' => 10000],
	                        ] );

                            $append[] = [ 'html' => ' - <label for="scan_issues_min_words">Minimum Words:</label> ' . ob_get_clean() . ' (default: 3)' ];
                        } else if( 'alt-text-too-long' == $slug ) {
	                        ob_start();

	                        Settings_Display::input( [
		                        'type' => 'number',
		                        'property' => 'scan_issues_max_characters',
		                        'settings' => $settings,
		                        'disabled' => $disabled,
		                        'style' => 'width: 62px;',
                                'default_value' => 150,
                                'attributes' => ['min' => 150, 'max' => 10000],
	                        ] );

	                        $append[] = [ 'html' => ' - <label for="scan_issues_max_characters">Maximum Characters:</label> ' . ob_get_clean() . ' (default: 150)' ];

                        } else if( 'image-url-not-valid' == $slug || 'image-type-not-valid' == $slug ) {
						    $append[] = [
							    'text' => ' - ' . __( 'required', FIXALTTEXT_SLUG ),
							    'link' => '',
							    'style' => 'font-weight:bold; font-style:italic',
						    ];

	                        $specific_disabled = true;
					    }

                        $specific_disabled = $disabled ?: $specific_disabled;

					    $options[] = [
						    'value' => $slug,
						    'label' => $values['label'],
						    'description' => $values['description'],
						    'append' => $append,
                            'disabled' => $specific_disabled
					    ];
				    }

				    $args = [
					    'property' => 'scan_issues',
					    'settings' => $settings,
					    'options' => $options,
					    'disabled' => $disabled,
				    ];

				    Settings_Display::checkboxes( $args );
			    }

			    ?>
            </td>
        </tr>
    </table>

	<?php
// Grab the roles
$roles_obj = new WP_Roles();
$roles = $roles_obj->get_names();

$rows_value = [];
foreach ( $roles as $role_slug => $role_name ) {

	$row_value = [
		'label' => $role_name,
		'value' => $role_slug,
		'excluded' => [],
		// Displays an X
		'disabled' => [],
		// Show checkbox, but disabled
		'checked' => [],
		// Fields to automatically check
	];

	if ( 'administrator' == $role_slug ) {
		// Of course admin has access
		$row_value['disabled'] = [
			'access_tool_roles',
			'access_settings_roles',
		];
		$row_value['checked'] = [
			'access_tool_roles',
			'access_settings_roles',
		];
	} else {
		$role = get_role( $role_slug );

		if ( ! $role->has_cap( Settings::get_user_access_capability() ) ) {
			$row_value['excluded'] = [
				'access_tool_roles',
				'access_settings_roles',
			];
		}
	}

	$rows_value[] = $row_value;
}

$args = [
	'rows' => [
		'label' => __( 'User Role', FIXALTTEXT_SLUG ),
		'values' => $rows_value,
	],
	'options' => [
		0 => [
			'label' => __( 'Tool Access', FIXALTTEXT_SLUG ),
			'property' => 'access_tool_roles',
			'value' => $settings->get( 'access_tool_roles' ),
		],
		1 => [
			'label' => __( 'Settings Access', FIXALTTEXT_SLUG ),
			'property' => 'access_settings_roles',
			'value' => $settings->get( 'access_settings_roles' ),
		],
	],
	'disabled' => $disabled,
];

?>
    <h2 style="text-align: center;"><span class="dashicons dashicons-admin-users"></span> <?php
		esc_html_e( 'Access Options', FIXALTTEXT_SLUG ); ?></h2>

    <table id="access-options">
        <tr class="access-tool-roles-row">
            <td class="label"><label for="scan_post_types"><?php
					esc_html_e( 'User Tool Access', FIXALTTEXT_SLUG ); ?></label>
                <p class="info"><?php
					echo esc_html( sprintf( __( 'Modify this setting to control which WordPress users have access this tool. Only users with the capability "%s", can be selected.', FIXALTTEXT_SLUG ), Settings::get_user_access_capability() ) );
					?>
                </p>
            </td>
            <td>
				<?php Settings_Display::checkbox_table( $args ); ?>
            </td>
        </tr>
    </table>
    <table id="debug-options">
        <tr class="debug-row">
            <td class="label"><label for="debug"><?php
					esc_html_e( 'Debug Mode', FIXALTTEXT_SLUG ); ?></label>
                <p class="info"><?php echo esc_html(__('Enable to display "Debug" page in the main menu.', FIXALTTEXT_SLUG)); ?></p>
            </td>
            <td>
				<?php

				$options = [
					'' => __( 'Disabled', FIXALTTEXT_SLUG ),
					'1' => __( 'Enabled', FIXALTTEXT_SLUG ),
				];

				$args = [
					'property' => 'debug',
					'settings' => $settings,
					'options' => $options,
					'disabled' => $disabled,
				];

				Settings_Display::select( $args );
				?>
            </td>
        </tr>
    </table>

<?php
