<?php

namespace FixAltText;

// Prevent Direct Access
use DOMDocument;

( defined( 'ABSPATH' ) ) || die;

use FixAltText\HelpersLibrary\REQUEST;

/**
 * Class Table_AJAX
 *
 * @package FixAltText
 * @since   1.0.0
 */
final class Table_AJAX {

	/**
	 * Load Hooks
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 */
	public static function init(): void {

		add_action( 'wp_ajax_' . FIXALTTEXT_HOOK_PREFIX . 'edit_inline_alt_text', [
			self::class,
			'edit_inline_alt_text',
		] );

		add_action( 'wp_ajax_' . FIXALTTEXT_HOOK_PREFIX . 'update_inline_alt_text', [
			self::class,
			'update_inline_alt_text',
		] );

		add_action( 'wp_ajax_' . FIXALTTEXT_HOOK_PREFIX . 'cancel_inline_alt_text', [
			self::class,
			'cancel_inline_alt_text',
		] );

	}

	/**
	 * Edits the Alt Text inline in the table
	 *
	 * @return void
	 */
	public static function edit_inline_alt_text(): void {

		if ( ! class_exists( 'References_Table' ) ) {
			require_once( FIXALTTEXT_TABLES_DIR . '/References_Table.php' );
		}

		ob_start();

		$row = self::get_inline_alt_text_row();

		echo '<ul><li><form><textarea name="replace" rows="5" style="max-width: 100%;">' . esc_textarea( $row->get( 'image_alt_text' ) ) . '</textarea><input type="submit" class="pri-button" value="' . esc_attr__( 'Save', FIXALTTEXT_SLUG ) . '" ></form></li>';

		// Create Row Actions
		$row_actions_args['cancel'] = '<a href="#cancel" aria-label="' . esc_attr__( 'Cancel', FIXALTTEXT_SLUG ) . '">' . esc_html__( 'Cancel', FIXALTTEXT_SLUG ) . '</a>';

		// Create temp table to get access to row actions method
		include_once( FIXALTTEXT_TABLES_DIR . '/Table.php' );
		echo '<li>';

		References_Table::display_row_actions( $row_actions_args );

		echo '</li></ul>';

		wp_send_json( [ 'html' => ob_get_clean() ] );

	}

	/**
	 * Get the row of the table for inline edit
	 *
	 * @return object
	 */
	public static function get_inline_alt_text_row(): object {

		global $wpdb;

		// Make sure they have the right access
		if ( $error = Admin::check_permissions(false, '', true) ) {
			wp_send_json( [ 'html' => $error ], 401 );
		}

		$data = REQUEST::array('data');

		if ( empty( $data ) ) {
			$message = __( 'Error: No data provided for the ajax request.', FIXALTTEXT_SLUG );
			wp_send_json( [ 'html' => $message ], 401 );
		}

		if ( ! wp_verify_nonce( $data['nonce'], FIXALTTEXT_SLUG . '-inline-edit-' . get_current_user_id() ) ) {
			$message = __( 'Error: session expired. Refresh this page and try again.' );
			wp_send_json( [ 'html' => $message ], 401 );
		}

		$from_post_id = $data['from_post_id'] ?? 0;
		$from_post_id = (int) $from_post_id;

		if ( ! $from_post_id ) {
			$message = __( 'Error: From Post ID Missing', FIXALTTEXT_SLUG ) . ': ' . $from_post_id;
			wp_send_json( [ 'html' => $message ], 400 );
		}

		$from_post_type = $data['from_post_type'] ?? '';

		if ( ! $from_post_type ) {
			$message = __( 'Error: From Post Type Missing', FIXALTTEXT_SLUG ) . ': ' . $from_post_type;
			wp_send_json( [ 'html' => $message ], 400 );
		}

		$id = $data['id'] ?? 0;
		$id = (int) $id;

		if ( ! $id ) {
			$message = __( 'Error: ID Missing', FIXALTTEXT_SLUG );
			wp_send_json( [ 'html' => $message ], 400 );
		}

		$from_where = $data['from_where'] ?? '';
		$from_where_key = $data['from_where_key'] ?? '';

		if ( ! $from_where ) {
			$message = __( 'Error: from_where Missing', FIXALTTEXT_SLUG );
			wp_send_json( [ 'html' => $message ], 400 );
		}

		$image_index = $data['image_index'] ?? '';
		$image_index = (int) $image_index;

		$image_url = $data['image_url'] ?? '';

		$table = Get::table_name();

		$query = $wpdb->prepare( 'SELECT * FROM `%1$s` WHERE `from_post_id` = %2$d AND `from_where` = "%3$s" AND `from_where_key` = "%4$s" AND `image_index` = %5$d AND `image_url` = "%6$s" LIMIT 1;', $table, $from_post_id, $from_where, $from_where_key, $image_index, $image_url );

		$rows = Reference::get_results( $wpdb->get_results( $query ) );

		if ( empty( $rows ) ) {
			$message = __( 'Error: Entry not found. If this keeps happening try running a new full scan from the dashboard.' . $query, FIXALTTEXT_SLUG );
			wp_send_json( [ 'html' => $message ], 400 );
		}

		// Set the $row variable so we can return the first result
		foreach ( $rows as $row ) {
			break;
		}

		return $row;

	}

	/**
	 * Edits the Alt Text inline in the table
	 *
	 * @return void
	 */
	public static function update_inline_alt_text(): void {

		global $wpdb;

		$new_alt_text = REQUEST::text_field('data','replace' );
		$new_alt_text = stripslashes( $new_alt_text );

		if ( '!!ERROR!!' === $new_alt_text ) {
			$message = __( 'Error: Replacement alt text not provided.', FIXALTTEXT_SLUG );
			wp_send_json( [ 'html' => $message ], 400 );
		}

		// DB Row to update
		$row = self::get_inline_alt_text_row();

		// Check to make sure the new alt text is not the same as the old
		if ( $new_alt_text != $row->get( 'image_alt_text' ) ) {

			$post_id = $row->get( 'from_post_id' );
			$from_where = $row->get( 'from_where' );
			$from_where_key = $row->get( 'from_where_key' );
			$image_index = $row->get( 'image_index' );
			$image_url = $row->get( 'image_url' );

			if ( 'media library' === $from_where ) {

				// Media Library Attachment
				update_post_meta( $post_id, '_wp_attachment_image_alt', $new_alt_text );

				// rescan post to update db index table
				Scan::scan_post( get_post( $post_id ) );

			} else {

				// Dealing with post meta or content

				$html = '';

				// Get html content from source
				if ( 'term meta' === $from_where || 'term description' === $from_where ) {
					// Taxonomy Term
					$this_term = get_term( $post_id );

					if ( 'term meta' === $from_where ) {
						// get current term meta
						$html = get_term_meta( $post_id, $from_where_key, true );
					} else {
						// get current term description
						$html = $this_term->description;
					}
				} elseif ( 'user meta' === $from_where ) {
					// Users
					$this_user = get_user_by( 'id', $post_id );

					// get current term meta
					$html = get_user_meta( $post_id, $from_where_key, true );
				} elseif ( 'content' === $from_where || 'post meta' === $from_where ) {
					// Posts
					$this_post = get_post( $post_id );

					if ( 'post meta' === $from_where ) {
						// get current post meta
						$html = get_post_meta( $post_id, $from_where_key, true );
					} else {
						// get current content
						$html = $this_post->post_content;
					}
				} else {
					// Default error
					$message = __( 'Error: invalid from_where.', FIXALTTEXT_SLUG );
					wp_send_json( [ 'html' => $message ], 400 );
				}

				$html_original = $html;

				$html_type = 'string';

				if ( is_array( $html ) ) {
					$html_type = 'array';
				} else if ( is_object( $html ) ) {
					$html_type = 'object';
				}

				if ( 'string' !== $html_type ) {
				$html = json_encode( $html, JSON_UNESCAPED_SLASHES );

					if ( ! $html ) {
						Debug::log( 'Warning - (' . __LINE__ . '): The inline edit has failed silently.' );
						$html = '';
					}
				}

				// Grab all the <img> from the html
				preg_match_all( '/<img[^>]+>/i', $html, $images );

				// Set the images to use the full matches
				$images = $images[0] ?? [];

				// Grab the specific image we are dealing with as a string of html
				$old_image = $images[ $image_index ] ?? '';
				$old_image_orig = $old_image;

				if ( 'string' !== $html_type ) {
					// Additional escaping was added due to json_encoding
					$old_image = stripslashes( $old_image );
				}

				$dom = new DOMDocument();

				// Silence warnings
				libxml_use_internal_errors( true );

				// Ensure we are using UTF-8 Encoding
				if ( function_exists( 'mb_convert_encoding' ) ) {
					// mbstring non-default extension installed and enabled
					$old_image = mb_convert_encoding( $old_image, 'HTML-ENTITIES', 'UTF-8' );
				} else {
					// fallback
					$old_image = htmlspecialchars_decode( utf8_decode( htmlentities( $old_image, ENT_COMPAT, 'utf-8', false ) ) );
				}

				// Load the URL's content into the DOM
				$dom->loadHTML( $old_image, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED );

				$dom_images = $dom->getElementsByTagName( 'img' );

				// Loop through and only process the first entry
				foreach ( $dom_images as $dom_img ) {
					$dom_img_src = $dom_img->getAttribute( 'src' );

					// verify image index has the same src url
					if ( $dom_img_src != $image_url ) {
						// Default error
						$message = __( 'Error: image_url mismatch.', FIXALTTEXT_SLUG );
						$message .= ' - ' . $dom_img_src . ' != ' . $image_url . ' ALT: - ' . $dom_img->getAttribute( 'alt' );
						wp_send_json( [ 'html' => $message ], 400 );
					}

					// Ensure the new alt text doesn't have garbage in it
					$new_alt_text = strip_tags( htmlspecialchars( $new_alt_text, ENT_QUOTES ) );
					$new_alt_text = stripslashes($new_alt_text);

					// Set the id
					$dom_img->setAttribute( 'alt', $new_alt_text );

					// Get the html of the image
					$new_image = $dom->saveHTML();

					if ( 'string' !== $html_type ) {
						// Add back the escaping caused by JSON encoding
						$new_image = addslashes($new_image);
					}

					// @todo Known Bug: If two <img> tags are identical in the DOM, then both will have their alt text updated t the same time.
					// replace old image with new image
					$html = str_replace( $old_image_orig, trim($new_image), $html );

					if ( 'array' === $html_type ) {
						// Convert back into an array
						$html = json_decode( $html, true );
					} else if ( 'object' === $html_type ) {
						// Convert back into an object
						$html = json_decode( $html );
					}

					// Update html
					if ( $html_original != $html ){
						if ( 'post meta' === $from_where ) {
							if ( update_post_meta( $post_id, $from_where_key, $html ) ){
								// Posts

								$this_post = get_post( $post_id );

								// Clear wp object cache
								clean_post_cache( $this_post );

								// rescan post to update db index table
								Scan::scan_post( get_post( $post_id ) );
							} else {
								$message = __( 'Error: Post meta alt text not updated.', FIXALTTEXT_SLUG );
								wp_send_json( [ 'html' => $message ], 400 );
							}
						} elseif ( 'term meta' === $from_where ) {
							if ( update_term_meta( $post_id, $from_where_key, $html ) ){
								// Clear wp object cache (require ID as int)
								clean_term_cache( $post_id );

								// rescan term to update db index table
								Scan::scan_term( get_term( $post_id ) );
							} else {
								$message = __( 'Error: Term meta alt text not updated.', FIXALTTEXT_SLUG );
								wp_send_json( [ 'html' => $message ], 400 );
							}
						} elseif ( 'user meta' === $from_where ) {
							if ( update_user_meta( $post_id, $from_where_key, $html ) ) {
								// Users

								// Clear wp object cache
								clean_user_cache( $this_user );

								// rescan user to update db index table
								Scan::scan_user( get_user_by( 'id', $post_id ) );
							} else {
								$message = __( 'Error: User meta alt text not updated.', FIXALTTEXT_SLUG );
								wp_send_json( [ 'html' => $message ], 400 );
							}
						} elseif ( 'term description' === $from_where ) {
							// Update the db directly using custom query for content
							$sql = $wpdb->prepare( 'UPDATE `' . $wpdb->prefix . 'term_taxonomy` SET `description` = %s WHERE `term_id` = %d;', $html, $post_id );
							$wpdb->query( $sql );

							// Clear wp object cache (require ID as int)
							clean_term_cache( $post_id );

							// rescan term to update db index table
							Scan::scan_term( get_term( $post_id ) );
						} else {
							// Update the db directly using custom query for content
							$sql = 'UPDATE `' . $wpdb->prefix . 'posts` SET `post_content` = %s WHERE `ID` = %d;';


							$wpdb->query( $wpdb->prepare( $sql, $html, $post_id ) );

							// CPTs

							$this_post = get_post( $post_id );

							// Clear wp object cache
							clean_post_cache( $this_post );

							// rescan post to update db index table
							Scan::scan_post( get_post( $post_id ) );
						}
					}

					// Highlander: there can only be one image
					break;

				}

			}

			// Grab updated version of the row
			$row = self::get_inline_alt_text_row();

		}

		ob_start();

		// display new values
		self::display_inline_alt_text_column( $row, true );

		wp_send_json( [ 'html' => ob_get_clean() ] );

	}

	/**
	 * Displays the content of the alt text column
	 *
	 * @param      $row
	 * @param bool $ajax
	 *
	 */
	public static function display_inline_alt_text_column( $row, $ajax = false ): void {

		if ( ! class_exists( 'References_Table' ) ) {
			require_once( FIXALTTEXT_TABLES_DIR . '/References_Table.php' );
		}

		echo ( $ajax ) ? '' : '<div class="td-content">';

		$alt_text = $row->get( 'image_alt_text' );
		$alt_text_length = mb_strlen( $alt_text );
		if ( $alt_text_length >= 255 ) {
			$alt_text = trim( substr( $alt_text, 0, 255 ) ) . '...';
		}

		echo '<ul><li>';
		References_Table::highlight_search( 'image_alt_text', $alt_text, true );
		echo '</li>';

		// Create Row Actions
		$row_actions_args['edit-inline'] = '<a href="#edit" aria-label="' . esc_attr__( 'Edit Alt Text Inline', FIXALTTEXT_SLUG ) . '">' . esc_html__( 'Edit Inline', FIXALTTEXT_SLUG ) . '</a>';

		echo '<li>';
		References_Table::display_row_actions( $row_actions_args );
		echo '</li>';

		if ( $ajax ) {
			echo '<li class="hidden"><div class="update-id-column">';
			self::display_id_column( $row );
			echo '</div></li>';
		}

		echo '</ul>';
		echo ( $ajax ) ? '' : '</div>';

	}

	/**
	 * Get the content of the ID column
	 *
	 * @param $row
	 *
	 * @return string
	 */
	public static function display_id_column( $row ): void {

		$id = $row->get( 'id' );

		// Image Issues
		$image_issues = implode( ', ', $row->get( 'image_issues' ) );
		$image_issues = ucwords( str_replace( '-', ' ', $image_issues ) );
		$image_issues = str_replace( 'Html', 'HTML', $image_issues );

		echo '<div class="id-content"><div class="ajax-data" data-nonce-inline-edit="' . esc_attr( wp_create_nonce( FIXALTTEXT_SLUG . '-inline-edit-' . get_current_user_id() ) ) . '" data-id="' . esc_attr( $id ) . '" data-from-post-id="' . esc_attr( $row->get( 'from_post_id' ) ) . '" data-from-post-type="' . esc_attr( $row->get( 'from_post_type' ) ) . '" data-from-where="' . esc_attr( $row->get( 'from_where' ) ) . '" data-from-where-key="' . esc_attr( $row->get( 'from_where_key' ) ) . '" data-image-index="' . esc_attr( $row->get( 'image_index' ) ) . '" data-image-url="' . esc_attr( $row->get( 'image_url' ) ) . '" data-image-issue="' . esc_attr( $image_issues ) . '"></div>' . esc_html( $id ) . '</div>';

	}

	/**
	 * Cancels the users request to edit and restores the original inline text into the table row
	 *
	 * @return void
	 */
	public static function cancel_inline_alt_text(): void {

		ob_start();

		$row = self::get_inline_alt_text_row();

		// get original values
		self::display_inline_alt_text_column( $row, true );

		wp_send_json( [ 'html' => ob_get_clean() ] );

	}

}
