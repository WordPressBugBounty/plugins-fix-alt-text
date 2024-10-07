<?php

namespace FixAltText;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\HelpersLibrary\Table_Library as Table_Library;

// Extend the Helper's Library Table Class
if ( ! class_exists( 'Table_Library' ) ) {
	require_once( FIXALTTEXT_HELPERSLIBRARY_TABLES_DIR . '/Table_Library.php' );
}

/**
 * Class Table - Extends the Helper's Library Table Class
 * Notice: Must set the get_icons()
 * Notice: Must create the get_results()
 * Notice: This is where you add all the custom column methods
 */
abstract class Table extends Table_Library {

	/**
	 * The icons for the table columns
	 */
	protected static function get_icons( string $type = 'column' ): array {

		$icons = [
			'image_preview' => 'visibility',
			'image_url' => 'admin-links',
			'image_alt_text' => 'format-quote',
		];

		if ( 'filter' == $type ) {
			// Get filter icons instead
			$icons = Filters::get_icons();
		}

		return $icons;

	}

	/**
	 * Converts the results into the proper format for the plugin.
	 * Must be overwritten so that we can allow the plugin to have it's own format for each row (Row class)
	 */
	protected static function get_results( array $results ): array {

		return Reference::get_results( $results );

	}

	/**
	 * Displays the default column
	 *
	 * @package WordPress
	 *
	 * @param \FixAltText\Row $row
	 *
	 * @return string
	 *
	 * @note    Add type hinting to these params throws a WARNING
	 */
	protected function column_id( $row ) {

		ob_start();

		Table_AJAX::display_id_column( $row );

		return ob_get_clean();

	}

	/**
	 * @param $row
	 *
	 * @return string
	 */
	protected function column_reference( $row ) {

		$from_post_id = $row->get( 'from_post_id' );
		$post_type = $row->get( 'from_post_type' );
		$from_where = $row->get( 'from_where' );
		$from_where_key = $row->get( 'from_where_key' );
		$from_where .= ( $from_where_key ) ? ': ' . $from_where_key : '';

		$view_link = '';

		if ( 'taxonomy term' === $post_type ) {
			$term = get_term( $from_post_id );
			$title = trim( $term->name );
			$edit_link = get_edit_term_link( $term );
		} elseif ( 'user' === $post_type ) {
			$user = get_user_by( 'id', $from_post_id );
			$title = trim( $user->display_name );
			$edit_link = get_edit_user_link( $from_post_id );
		} else {
			$title = trim( get_the_title( $from_post_id ) );
			$edit_link = get_edit_post_link( $from_post_id );
			$view_link = get_permalink( $from_post_id );
		}

		ob_start();

		echo '<div class="td-content"><ul>';

		if ( $title ) {
			echo '<li><a href="' . esc_attr( $edit_link ) . '">' . static::highlight_search( 'post_title', $title ) . '</a></li>';
		} else {
			$title = __( 'No Title', FIXALTTEXT_SLUG );
			echo '<li>' . esc_html( $title ) . '</li>';
		}

		echo '<li><span class="dashicons dashicons-admin-post"></span> ' . esc_html( $post_type ) . '</li>';
		echo '<li><span class="dashicons dashicons-location"></span> ' . esc_html( $from_where ) . '</li>';

		// Create Row Actions
		$row_actions_args['edit'] = '<a href="' . esc_url( $edit_link ) . '" aria-label="Edit ' . esc_attr( $title ) . '">' . esc_html__( 'Edit', FIXALTTEXT_SLUG ) . '</a>';
		if ( $view_link && 'attachment' != $post_type ) {
			$row_actions_args['view'] = '<a href="' . esc_url( $view_link ) . '" aria-label="View ' . esc_attr( $title ) . '">' . esc_html__( 'View', FIXALTTEXT_SLUG ) . '</a>';
		}
		echo '<li>';

		// Core method
		echo $this->row_actions( $row_actions_args );

		echo '</li></ul></div>';

		return ob_get_clean();
	}

	/**
	 * @param $row
	 *
	 * @return string
	 */
	protected function column_image_alt_text( $row ) {

		ob_start();

		Table_AJAX::display_inline_alt_text_column( $row );

		return ob_get_clean();

	}

	/**
	 * @param $row
	 *
	 * @return string
	 */
	protected function column_image_issues( $row ) {
		$value = implode( ', ', $row->get( 'image_issues' ) );
		$value = ucwords( str_replace( '-', ' ', $value ) );

		return '<div class="issue-content">' . esc_html( $value ) . '</div>';
	}

	/**
	 * @param $row
	 *
	 * @return string|void
	 */
	protected function column_image_preview( $row ) {

		// Full size image URL being referenced
		$image_src_full = $row->get( 'image_url' );

		if ( $image_src_full ) {
			$post_type = $row->get( 'from_post_type' );

			if ( 'attachment' === $post_type ) {
				// Use the thumbnail size to display preview
				$image_src = wp_get_attachment_thumb_url( $row->get( 'from_post_id' ) );
			} else {
				$image_src = $image_src_full;
			}

			if ( Get::is_valid_image_url($image_src) ){
				$content = '<div class="image-preview-thumbnail-wrap"><span class="dashicons dashicons-hidden"></span><img class="image-preview-thumbnail" src="' . esc_attr( $image_src ) . '" alt="' . esc_attr__( 'Image Preview', FIXALTTEXT_SLUG ) . '" style="max-width:100px; max-height: 75px;" data-large-src="' . esc_attr( $image_src_full ) . '" /></div>';
			} else {
				$content = esc_html( __( 'Invalid image URL. Try using a full URL.', FIXALTTEXT_SLUG ) );
			}

		} else {
			$content = esc_html__( 'No Preview', FIXALTTEXT_SLUG );
		}

		return $content;
	}

	/**
	 * Set the 'id' column as hidden by default.
	 *
	 * @param array<string> $hidden List of hidden columns.
	 *
	 * @return array<string> Modified list of hidden columns.
	 */
	public function default_hidden_columns( array $hidden ): array {

		return $hidden;

	}

}
