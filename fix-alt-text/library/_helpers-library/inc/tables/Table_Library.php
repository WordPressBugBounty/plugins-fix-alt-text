<?php
namespace FixAltText\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WP_List_Table;
use wpdb;
use FixAltText\Get;
use FixAltText\Run;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( FIXALTTEXT_HELPERSLIBRARY_ADMIN_DIR . '/includes/class-wp-list-table.php' );
}

/**
 * Class Table
 */
abstract class Table_Library extends WP_List_Table {

	/**
	 * @var string The table where the data exists
	 */
	protected string $table = '';

	/**
	 * @var array The additional filters displayed above the table. Defined by the child class.
	 */
	protected array $filters = [];

	/**
	 * @var array The column filters displayed in the column heading. Defined by the child class.
	 */
	protected array $column_filters = [];

	/**
	 * @var array The errors encountered during the retrieval of the data.
	 */
	protected array $errors = [];

	/**
	 * Table constructor. Sets the filters and column headers by default and runs the parent __construct()
	 */
	function __construct() {

		parent::__construct();

		Run::clean_request_uri();

		if ( wp_doing_ajax() ) {
			// We have to remove the AJAX portion of the URL so that the sorting links in the table work properly
			$_SERVER['REQUEST_URI'] = '/' . FIXALTTEXT_HELPERSLIBRARY_ADMIN_FOLDER . FIXALTTEXT_HELPERSLIBRARY_ADMIN_URI_CURRENT;
		}

		// Set filters
		$this->set_filters();

		add_filter( 'default_hidden_columns', [
			$this,
			'default_hidden_columns',
		] );

		// Set the columns
		$this->_column_headers = $this->get_column_info();

	}

	/**
	 * Set the 'id' column as hidden by default.
	 *
	 * @param array<string> $hidden List of hidden columns.
	 *
	 * @return array<string> Modified list of hidden columns.
	 */
	abstract public function default_hidden_columns( array $hidden ): array;

	/**
	 * Sets the filters for this table
	 *
	 * @param array $override
	 *
	 * @return void
	 */
	public abstract function set_filters( array $override = [] ): void;

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

		// Must override with child class
		return [];

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

		// Must override with child class
		return [];

	}

	/**
	 * Get the array of searchable columns in the database
	 *
	 * @param bool $stripped Removes table specific designation and table ticks
	 *
	 * @return  array An unassociated array.
	 */
	abstract protected static function get_searchable_columns( bool $stripped = true): array;

	/**
	 * Generates the table navigation above or below the table
	 *
	 * @since 3.1.0
	 *
	 * @param string $which
	 */
	protected function display_tablenav( $which ) {

		// Added custom form and fields
		if ( 'top' === $which ) {

			echo '<form method="get" action="' . esc_attr( FIXALTTEXT_HELPERSLIBRARY_ADMIN_URL_CURRENT ) . '">
			<input type="hidden" name="page" value="' . esc_attr( REQUEST::key( 'page' ) ) . '" />
			<input type="hidden" name="tab" value="' . esc_attr( REQUEST::key( 'tab' ) ) . '" />
	<input type="hidden" name="table" value="' . esc_attr( REQUEST::key( 'table' ) ) . '" />';

			wp_nonce_field( 'bulk-' . $this->_args['plural'], '_wpnonce', true );

			$this->search_box( __( 'Search', FIXALTTEXT_SLUG ), 's' );

		}

		echo '<div class="tablenav ' . esc_attr( $which ) . '"><div class="alignleft actions bulkactions">' . $this->bulk_actions( $which ) . '</div>';

		$this->extra_tablenav( $which );
		$this->pagination( $which );

		echo '<br class="clear" /></div>';

	}

	/**
	 * Prints column headers, accounting for hidden and sortable columns.
	 *
	 * @since 3.1.0
	 *
	 * @param bool $with_id Whether to set the ID attribute or not
	 */
	public function print_column_headers( $with_id = true ) {
		[
			$columns,
			$hidden,
			$sortable,
			$primary
		] = $this->get_column_info();

		$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$current_url = remove_query_arg( 'paged', $current_url );

		// When users click on a column header to sort by other columns.
		if ( isset( $_GET['orderby'] ) ) {
			$current_orderby = $_GET['orderby'];
			// In the initial view there's no orderby parameter.
		} else {
			$current_orderby = '';
		}

		// Not in the initial view and descending order.
		if ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) {
			$current_order = 'desc';
		} else {
			// The initial view is not always 'asc', we'll take care of this below.
			$current_order = 'asc';
		}

		if ( ! empty( $columns['cb'] ) ) {
			static $cb_counter = 1;
			$columns['cb'] = '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />
			<label for="cb-select-all-' . $cb_counter . '">' . '<span class="screen-reader-text">' . /* translators: Hidden accessibility text. */
			                 __( 'Select All' ) . '</span>' . '</label>';
			++ $cb_counter;
		}

		foreach ( $columns as $column_key => $column_display_name ) {
			$class = [
				'manage-column',
				"column-$column_key"
			];
			$aria_sort_attr = '';
			$abbr_attr = '';
			$order_text = '';

			if ( in_array( $column_key, $hidden, true ) ) {
				$class[] = 'hidden';
			}

			if ( 'cb' === $column_key ) {
				$class[] = 'check-column';
			} elseif ( in_array( $column_key, [
				'posts',
				'comments',
				'links'
			], true ) ) {
				$class[] = 'num';
			}

			if ( $column_key === $primary ) {
				$class[] = 'column-primary';
			}

			if ( isset( $sortable[ $column_key ] ) ) {
				$orderby = isset( $sortable[ $column_key ][0] ) ? $sortable[ $column_key ][0] : '';
				$desc_first = isset( $sortable[ $column_key ][1] ) ? $sortable[ $column_key ][1] : false;
				$abbr = isset( $sortable[ $column_key ][2] ) ? $sortable[ $column_key ][2] : '';
				$orderby_text = isset( $sortable[ $column_key ][3] ) ? $sortable[ $column_key ][3] : '';
				$initial_order = isset( $sortable[ $column_key ][4] ) ? $sortable[ $column_key ][4] : '';

				/*
				 * We're in the initial view and there's no $_GET['orderby'] then check if the
				 * initial sorting information is set in the sortable columns and use that.
				 */
				if ( '' === $current_orderby && $initial_order ) {
					// Use the initially sorted column $orderby as current orderby.
					$current_orderby = $orderby;
					// Use the initially sorted column asc/desc order as initial order.
					$current_order = $initial_order;
				}

				/*
				 * True in the initial view when an initial orderby is set via get_sortable_columns()
				 * and true in the sorted views when the actual $_GET['orderby'] is equal to $orderby.
				 */
				if ( $current_orderby === $orderby ) {
					// The sorted column. The `aria-sort` attribute must be set only on the sorted column.
					if ( 'asc' === $current_order ) {
						$order = 'desc';
						$aria_sort_attr = ' aria-sort="ascending"';
					} else {
						$order = 'asc';
						$aria_sort_attr = ' aria-sort="descending"';
					}

					$class[] = 'sorted';
					$class[] = $current_order;
				} else {
					// The other sortable columns.
					$order = strtolower( $desc_first );

					if ( ! in_array( $order, [
						'desc',
						'asc'
					], true ) ) {
						$order = $desc_first ? 'desc' : 'asc';
					}

					$class[] = 'sortable';
					$class[] = 'desc' === $order ? 'asc' : 'desc';

					/* translators: Hidden accessibility text. */
					$asc_text = __( 'Sort ascending.' );
					/* translators: Hidden accessibility text. */
					$desc_text = __( 'Sort descending.' );
					$order_text = 'asc' === $order ? $asc_text : $desc_text;
				}

				if ( '' !== $order_text ) {
					$order_text = ' <span class="screen-reader-text">' . $order_text . '</span>';
				}

				// Print an 'abbr' attribute if a value is provided via get_sortable_columns().
				$abbr_attr = $abbr ? ' abbr="' . esc_attr( $abbr ) . '"' : '';

				$column_display_name = sprintf( '<a href="%1$s">' . '<span>%2$s</span>' . '<span class="sorting-indicators">' . '<span class="sorting-indicator asc" aria-hidden="true"></span>' . '<span class="sorting-indicator desc" aria-hidden="true"></span>' . '</span>' . '%3$s' . '</a>', esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ), $column_display_name, $order_text );
			}

			$tag = ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) {
				$class = "class='" . implode( ' ', $class ) . "'";
			}

			// ---------------- START CUSTOM ------------------------

			$icons = $this::get_icons();

			// Added visual icon to column
			$icon = isset( $icons[ $column_key ] ) ? '<span class="dashicons dashicons-' . esc_attr( $icons[ $column_key ] ) . '"></span>' : '';

			$form = '';
			$column_filters = '';
			if ( ! defined( 'FIXALTTEXT_HELPERSLIBRARY_TABLE_HEADER' ) ) {

				$column_filters = static::get_column_filters( $column_key );

				$columns = $this->get_columns();
				$last_column_display_name = end( $columns );
				if ( $last_column_display_name == $column_display_name ) {
					// Display filters only on the top header of the table
					define( 'FIXALTTEXT_HELPERSLIBRARY_TABLE_HEADER', true );

					// Close the filters form so that submitting the form works properly
					$form = '</form>';
				}
			}

			// Override default columns
			$column_display_name = $icon . $column_display_name . $column_filters . $form;

			// ---------------- END CUSTOM ------------------------

			echo "<$tag $scope $id $class $aria_sort_attr $abbr_attr>$column_display_name</$tag>";

		}
	}

	/**
	 * Displays the search box (static so we can access outside of the object
	 */
	public static function display_search_box( $text, $input_id ): void {

		$orderby = Get::orderby();
		$order = Get::order();
		$post_mime_type = REQUEST::text_field( 'post_mime_type' );
		$detached = REQUEST::text_field( 'detached' );
		$exact_search = REQUEST::bool( 'exact-search' );

		$input_id = $input_id . '-search-input';

		if ( ! empty( $orderby ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $orderby ) . '" />';
		}
		if ( ! empty( $order ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( $order ) . '" />';
		}
		if ( ! empty( $post_mime_type ) ) {
			echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( $post_mime_type ) . '" />';
		}
		if ( ! empty( $detached ) ) {
			echo '<input type="hidden" name="detached" value="' . esc_attr( $detached ) . '" />';
		}
		?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php
			echo esc_attr( $input_id ); ?>"><?php
				echo esc_html( $text ); ?>:</label>
            <input type="search" id="<?php
			echo esc_attr( $input_id ); ?>" name="s" value="<?php
			_admin_search_query(); ?>"/>
			<?php
			submit_button( $text, '', '', false, [ 'id' => 'search-submit' ] ); ?><br/>

            <input type="checkbox" name="exact-search" <?php
			if ( $exact_search ) {
				echo 'CHECKED';
			} ?>/>
            <label for="exact-search">
				<?php
				esc_html_e( 'Exact Search', FIXALTTEXT_SLUG ); ?>
            </label>
        </p>
		<?php

	}

	/**
	 * Wrapper for displaying the search box.
	 *
	 * @since 3.1.0
	 *
	 * @param string $text     The 'submit' button label.
	 * @param string $input_id ID attribute value for the search input field.
	 */
	public function search_box( $text, $input_id ) {

		static::display_search_box( $text, $input_id );

	}

	/**
	 * Gets the number of items to display on a single page.
	 *
	 * @since    3.1.0
	 * @internal This overwrites the core version
	 *
	 * @param string $option
	 * @param int    $default
	 *
	 * @return int
	 */
	protected function get_items_per_page( $option = '', $default = 25 ): int {

		return REQUEST::int( 'per_page', '', $default );

	}

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @package WordPress
	 * @since   3.1.0
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

			$where_query = $this->get_where_query( $values );

			$total_items = $wpdb->get_col( $wpdb->prepare( 'SELECT count(`id`) FROM `%1$s` ' . $where_query, $values ) );
			$total_items = $total_items[0] ?? 0;

			$values_count = count( $values );
			$page = $this->get_pagenum();
			$per_page = $this->get_items_per_page();
			$values[] = $this->get_orderby();
			$values[] = $this->get_order();
			$values[] = ( $page - 1 ) * $per_page; // Limit Start
			$values[] = $per_page;

			$sql = $wpdb->prepare( 'SELECT * FROM `%1$s` ' . $where_query . ' ORDER BY `%' . ++ $values_count . '$s` %' . ++ $values_count . '$s LIMIT %' . ++ $values_count . '$d, %' . ++ $values_count . '$d', $values );

			$this->items = static::get_results( $wpdb->get_results( $sql ) );

			$this->set_pagination_args( [
				'total_items' => $total_items,
				'per_page' => $per_page,
			] );

		}

	}

	/**
	 * Builds the where query
	 *
	 * @param $values
	 *
	 * @return string
	 */
	protected function get_where_query( array &$values, array $where_queries = [] ): string {

		$exact_search = REQUEST::bool( 'exact-search' );
		if ( $search_query = $this->get_search_query( $values, $exact_search ) ) {
			$where_queries[] = $search_query;
		}

		if ( $filters_query = $this->get_additional_filters_query( $values ) ) {
			$where_queries[] = $filters_query;
		}

		return ! empty( $where_queries ) ? ' WHERE ' . implode( ' AND ', $where_queries ) : '';

	}

	/**
	 * Converts the results into the proper format for the plugin.
	 * Must be overwritten so that we can allow the plugin to have it's own format for each row (Row class)
	 */
	protected abstract static function get_results( array $results ): array;

	/**
	 * Gets the orderby
	 *
	 * @return string
	 */
	function get_orderby(): string {

		return Get::orderby( static::get_sortable_columns(), 'date' );

	}

	/**
	 * Gets the order ASC / DESC
	 *
	 * @return string
	 */
	function get_order(): string {

		return Get::order();

	}

	/**
	 * Gets the search query
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param bool  $exact
	 *
	 * @return array
	 */
	protected function get_search_query( &$values, $exact = false ): string {

		$query = '';

		$search = REQUEST::text_field( 's' );
		$search = $search ?? '';

		// Strip extra spaces
        $search = trim( $search ?? '' );

		$searchable_columns = $this::get_searchable_columns(false);

		// Add and Sanitize Search Query
		if ( $search !== '' && isset( $searchable_columns[0] ) ) {

			$query = ' ( ';

			$search_query = [];
			$num = count( $values );

			foreach ( $searchable_columns as $column ) {
				$search_query[] = '(%' . ++ $num . '$s LIKE \'%' . ++ $num . '$s\')';

				$values[] = $column;

				$values[] = ( $exact ) ? $search : '%' . $search . '%';
			}

			$query .= implode( ' OR ', $search_query );
			$query .= ' ) ';

		}

		return $query;

	}

	/**
	 * Set the plugin's icons
	 */
	protected abstract static function get_icons( string $type = 'column' ): array;

	private function get_column_filters( string $column_name ): string {

		$html = '';

		if ( ! empty( $this->column_filters[ $column_name ] ) ) {
			$html = '<span class="column-filters">';
			foreach ( $this->column_filters[ $column_name ] as $name => $values ) {
				$html .= static::display_filter( $name, $values, $column_name, true );
			}
			$html .= '</span>';
		}

		return $html;
	}

	public function display_filter( string $name, array $values, string $exclude_column_name = '', bool $return = false ): string {

		$icons = static::get_icons( 'filter' );

		ob_start();

		$icon = isset( $icons[ $name ] ) ? 'dashicons dashicons-' . $icons[ $name ] : '';

		$selected_value = REQUEST::text_field( $name );

		// remove column name prefix if provided
		$default_label = $exclude_column_name ? str_replace( [
			$exclude_column_name . '-',
			$exclude_column_name . '_',
		], '', $name ) : $name;

		$default_label = ucwords( str_replace( [
			'-',
			'_',
		], ' ', $default_label ) );

		echo '<span class="select-wrapper"><span class="' . esc_attr( $icon ) . '"></span><select name="' . esc_attr( $name ) . '" aria-label="' . esc_attr__( 'Select', FIXALTTEXT_SLUG ) . ' ' . esc_html( ucwords( str_replace( [
				'-',
				'_',
			], ' ', $name ) ) ) . '">
                <option value="">' . ' - - ' . esc_html( $default_label ) . ' - -</option>';

		foreach ( $values as $value => $label ) {
			$selected = selected( $value, $selected_value, false ) ? ' SELECTED' : '';

			echo '<option value="' . esc_attr( $value ) . '"' . esc_attr( $selected ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select></span>';

		if ( $return ) {
			return ob_get_clean();
		} else {
			echo ob_get_clean();

			return '';
		}

	}

	/**
	 * Adds additional dropdown filters to the bulk actions
	 *
	 * @return string
	 */
	public function display_additional_filters(): void {

		if ( ! empty( $this->filters ) ) {

			foreach ( $this->filters as $name => $values ) {

				static::display_filter( $name, $values );

			}

		}

	}

	/**
	 * Gets the query for the additional filters
	 *
	 * @param array $values
	 *
	 * @return string
	 */
	public function get_additional_filters_query( array &$values ): string {

		// Override with a child class
		return '';

	}

	/**
	 * Display the filters and per_page. This should be wrapped by the bulk_actions() method in a child
	 * class.
	 *
	 * @param string $which The location of the bulk actions: 'top' or 'bottom'.
	 *                      This is designated as optional for backward compatibility.
	 *
	 * @return void
	 */
	public function bulk_actions_load( string $which = '' ): void {

		if ( $which != 'top' ) {
			return;
		}

		echo '<span class="additional-filters">';

		$allowed_per_page = [
			'25',
			'50',
			'100',
		];

		$per_page = REQUEST::key( 'per_page' );

		$this->display_additional_filters();

		echo '<span class="select-wrapper"><span class="dashicons dashicons-editor-ol"></span><select name="per_page" aria-label="' . esc_attr__( 'Results Per Page', FIXALTTEXT_SLUG ) . '">';

		foreach ( $allowed_per_page as $value ) {
			$selected = selected( $value, $per_page, false ) ? ' SELECTED' : '';

			echo '<option value="' . esc_attr( $value ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $value ) . ' ' . esc_html__( 'Per Page', FIXALTTEXT_SLUG ) . '</option>';
		}

		echo '</select></span>';

		$this->display_reset_filters_link();

		echo '</span>';

	}

	/**
	 * Displays the reset filters link
	 *
	 * @return string
	 */
	public function display_reset_filters_link(): void {

		if ( static::is_active_filters() ) {
			$reset_url = FIXALTTEXT_HELPERSLIBRARY_ADMIN_URL_CURRENT;
			$reset_url .= ( $table = REQUEST::text_field( 'table' ) ) ? '&table=' . $table : '';
			echo ' <a href="' . esc_url( $reset_url ) . '" class="reset-filters" style="padding:5px 13px;display:inline-block;">' . esc_html__( 'reset filters', FIXALTTEXT_SLUG ) . '</a>';
		}

	}

	/**
	 * Tells you whether filters are active or not for the current table results
	 *
	 * @return bool
	 */
	public static function is_active_filters(): bool {

		parse_str( parse_url( REQUEST::SERVER_text_field( 'REQUEST_URI' ), PHP_URL_QUERY ), $get_vars );

		unset( $get_vars['page'], $get_vars['tab'] );

		return ! empty( $get_vars );
	}

	/**
	 * Highlights words that are in the search
	 *
	 * @param string $column_name
	 * @param        $value
	 * @param bool   $return
	 */
	public static function highlight_search( string $column_name, $value, bool $echo = false ): string {

		$s = REQUEST::text_field( 's' );

		if ( empty( $s ) ) {
			$s = REQUEST::text_field( 'data', 's' );
		}

		// Prevent % sign in value from causing false positive escaping (fatal error)
		$value = str_replace( '%', '&#37;', esc_html( $value ) );

		if ( $s ) {

			$searchable = static::get_searchable_columns();

			if ( in_array( $column_name, $searchable ) ) {
				$pattern = str_replace( '/', '\/', addslashes( $s ) );
				$value = preg_replace( '/(' . $pattern . ')/i', '<span class="highlight">%1$s</span>', $value );
			}

		}

		if ( $echo ) {
			echo sprintf( $value, esc_html( $s ) );

			return '';
		} else {
			return sprintf( $value, esc_html( $s ) );
		}
	}

	/**
	 * Message to be displayed when there are no items
	 *
	 * @package WordPress
	 * @since   3.1.0
	 *
	 * @return void
	 */
	public function no_items(): void {

		ob_start();
		// Run this but do not display so that we can detect filters
		$this->bulk_actions_load( 'top' );
		ob_clean();

		_e( 'No results found.', FIXALTTEXT_SLUG );

		// Display reset link if needed
		$this->display_reset_filters_link();

	}

	/**
	 * Allow access to protected method row_actions()
	 *
	 * @param $args
	 *
	 * @return string
	 */
	public static function display_row_actions( $args ) {

		$table = new static( false );

		// core method
		echo $table->row_actions( $args );

	}

	/**
	 * Displays the default column
	 *
	 * @package WordPress
	 *
	 * @param        $row
	 * @param string $column_name
	 *
	 * @return string
	 *
	 * @note    Add type hinting to these params throws a WARNING
	 */
	protected function column_default( $row, $column_name ) {

		return '<div class="inner">' . $this->highlight_search( $column_name, $row->get( $column_name ) ) . '</div>';

	}

}
