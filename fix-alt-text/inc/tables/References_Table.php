<?php

namespace FixAltText;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use FixAltText\HelpersLibrary\REQUEST;

if ( ! class_exists( 'Table' ) ) {
	require_once( FIXALTTEXT_TABLES_DIR . '/Table.php' );
}

/**
 * Class References_Table
 *
 * @package FixAltText
 * @since   1.0.0
 */
final class References_Table extends Table {

	/**
	 * Unused_Attachments constructor. Automatically prepares and displays table
	 *
	 * @param bool $display
	 */
	public function __construct( bool $display = true ) {

		// Set the DB table (must be run first)
		$this->table = Get::table_name();

		if ( $display ) {

			parent::__construct();

			$this->prepare_items();
		}

	}

	/**
	 * Get a list of columns. The format is:
	 * 'internal-name' => 'Title'
	 *
	 * @package WordPress
	 * @since   3.1.0
	 * @abstract
	 *
	 * @return array
	 */
	function get_columns(): array {

		$columns = [];
		$columns['id'] = __( 'ID', FIXALTTEXT_SLUG );
		$columns['reference'] = __( 'From', FIXALTTEXT_SLUG );
		$columns['image_ext'] = __( 'Type', FIXALTTEXT_SLUG );
		$columns['image_preview'] = __( 'Preview', FIXALTTEXT_SLUG );
		$columns['image_url'] = __( 'URL', FIXALTTEXT_SLUG );
		$columns['image_alt_text'] = __( 'Alt Text', FIXALTTEXT_SLUG );
		$columns['image_issues'] = __( 'Issues', FIXALTTEXT_SLUG );

		return $columns;
	}

	/**
	 * Get a list of sortable columns. The format is:
	 * 'internal-name' => 'orderby'
	 * or
	 * 'internal-name' => array( 'orderby', true )
	 *
	 * The second format will make the initial sorting order be descending
	 *
	 * @package WordPress
	 * @since   3.1.0
	 *
	 * @return array
	 */
	function get_sortable_columns(): array {

		return [];

	}

	/**
	 * Get the array of searchable columns in the database
	 *
	 * @package FixAltText
	 * @since   1.0.0
	 *
	 * @param bool $stripped Removes table specific designation and table ticks
	 *
	 * @return  array An unassociated array.
	 */
	public static function get_searchable_columns(bool $stripped = true): array {

		$searchable = [
			't1.`image_url`',
			't1.`image_alt_text`',
			't2.`post_title`',
		];

		if ( $stripped ) {
			foreach ( $searchable as $index => $column ) {
				if ( strpos( $column, '.' ) !== false ) {
					// Remove specific table designation
					$column = substr( $column, strpos( $column, "." ) + 1 );
				}
				$searchable[ $index ] = str_replace( '`', '', $column );
			}
		}

		return $searchable;
	}

	/**
	 * Sets the filters for this table
	 *
	 * @param array $override
	 *
	 * @return void
	 */
	public function set_filters( array $override = [] ): void {

		if ( ! empty( $override ) ) {
			// Override filters
			$this->filters = $override;

			return;
		}

		// Set filters

		$this->column_filters['reference']['where'] = Filters::get_from_where();
		$this->column_filters['image_ext']['file-type'] = Filters::get_image_exts();
		$this->column_filters['image_issues']['issue'] = Filters::get_issues();

	}

	/**
	 * Gets the query for the additional filters
	 *
	 * @param array $values
	 *
	 * @return string
	 */
	public function get_additional_filters_query( array &$values ): string {

		$filters = [];

		$to_type = REQUEST::text_field('file-type' );

		if ( $to_type ) {
			// Add filter if it exists
			$values[] = $to_type;
			$filters[] = "t1.image_ext = '%" . count( $values ) . "\$s'";
		}

		// from_where Filter
		$where = Filters::get_from_where();
		$from_where = REQUEST::text_field( 'where' );
		$from_where = ( isset( $where[ $from_where ] ) ) ? $where[ $from_where ] : '';

		if ( $from_where ) {
			// Add filter if it exists
			$values[] = $from_where;
			$filters[] = "t1.from_where = '%" . count( $values ) . "\$s'";
		}

		$issue = REQUEST::text_field('issue');

		if ( $issue ) {
			// Add filter if it exists
			$values[] = $issue;
			$filters[] = "t1.image_issues LIKE '%%%" . count( $values ) . "\$s%%'";
		}

		return ( empty( $filters ) ) ? '' : implode( ' AND ', $filters );

	}

	/**
	 * Add filters and per_page
	 *
	 * @param string $which
	 *
	 * @return void
	 */
	public function bulk_actions( $which = '' ): void {

		$this->bulk_actions_load( $which );

	}

	function get_orderby(): string {

		$sortable_columns = $this->get_sortable_columns();

		$orderby = REQUEST::text_field('orderby');

		return ( $orderby && isset( $sortable_columns[ $orderby ] ) ) ? $sortable_columns[ $orderby ][0] : 'id';

	}

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @package  WordPress
	 * @since    3.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	function prepare_items(): void {

		global $wpdb;

		if ( $this->table ) {

			// Process Bulk Deletes
			if ( 'delete' === $this->current_action() ) {
				$this->bulk_delete();
			}

			// Array of all the values to be sanitized
			$values = [];
			$values[] = $this->table;
			$values[] = $wpdb->prefix . 'posts';

			$where_query = $this->get_where_query( $values );

			$total_items = $wpdb->get_col( $wpdb->prepare( 'SELECT COUNT(t1.`id`) FROM `%1$s` as t1 LEFT JOIN `%2$s` as t2 on t2.`ID`=t1.`from_post_id` ' . $where_query, $values ) );
			$total_items = $total_items[0] ?? 0;

			$values_count = count( $values );
			$page = $this->get_pagenum();
			$per_page = $this->get_items_per_page();
			$values[] = $this->get_orderby();
			$values[] = $this->get_order();
			$values[] = ( $page - 1 ) * $per_page; // Limit Start
			$values[] = $per_page;

			$sql = $wpdb->prepare( 'SELECT t1.`id`, t1.`from_post_id`, t2.`post_title` as from_post_title, t1.`from_post_type`, t1.`from_where`, t1.`from_where_key`, t1.`image_index`, t1.`image_url`, t1.`image_alt_text`, t1.`image_ext`, t1.`image_issues` FROM `%1$s` as t1 LEFT JOIN `%2$s` as t2 on t2.`ID`=t1.`from_post_id` ' . $where_query . ' ORDER BY `%' . ++ $values_count . '$s` %' . ++ $values_count . '$s LIMIT %' . ++ $values_count . '$d, %' . ++ $values_count . '$d', $values );

			$this->items = static::get_results( $wpdb->get_results( $sql ) );

			$this->set_pagination_args( [
				'total_items' => $total_items,
				'per_page' => $per_page,
			] );

		}

	}

}