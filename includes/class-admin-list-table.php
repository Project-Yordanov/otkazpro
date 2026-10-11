<?php
/**
 * Withdrawals list table.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for WooCommerce → Withdrawals.
 */
class Admin_List_Table extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'py_otkaz',
				'plural'   => 'py_otkazi',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Filters from the request (shared with CSV export).
	 *
	 * @return array
	 */
	public static function filters_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$get = static function ( $key ) {
			return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		};
		$args = array(
			'search'       => $get( 's' ),
			'deadline'     => $get( 'deadline' ),
			'email_status' => $get( 'email_status' ),
			'date_from'    => $get( 'date_from' ),
			'date_to'      => $get( 'date_to' ),
			'orderby'      => $get( 'orderby' ) ? $get( 'orderby' ) : 'created_gmt',
			'order'        => $get( 'order' ) ? $get( 'order' ) : 'DESC',
		);
		// phpcs:enable
		return $args;
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'number'   => __( 'Number', 'wpotkaz' ),
			'date'     => __( 'Date', 'wpotkaz' ),
			'order'    => __( 'Order', 'wpotkaz' ),
			'customer' => __( 'Customer', 'wpotkaz' ),
			'items'    => __( 'Products', 'wpotkaz' ),
			'deadline' => __( 'Deadline', 'wpotkaz' ),
			'email'    => __( 'Confirmation', 'wpotkaz' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'number'   => array( 'number', false ),
			'date'     => array( 'created_gmt', true ),
			'order'    => array( 'order_id', false ),
			'customer' => array( 'name', false ),
		);
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'py_otkaz_per_page', 20 );
		$args     = self::filters_from_request();

		$args['per_page'] = $per_page;
		$args['page']     = $this->get_pagenum();

		$result      = Submission::query( $args );
		$this->items = $result['items'];

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'number' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / max( 1, $per_page ) ),
			)
		);
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		esc_html_e( 'No withdrawals yet.', 'wpotkaz' );
	}

	/**
	 * Filters + export.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$f = self::filters_from_request();
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="py-otkaz-deadline"><?php esc_html_e( 'Deadline', 'wpotkaz' ); ?></label>
			<select name="deadline" id="py-otkaz-deadline">
				<option value=""><?php esc_html_e( 'Any deadline', 'wpotkaz' ); ?></option>
				<option value="in" <?php selected( $f['deadline'], 'in' ); ?>><?php esc_html_e( 'In time', 'wpotkaz' ); ?></option>
				<option value="late" <?php selected( $f['deadline'], 'late' ); ?>><?php esc_html_e( 'After the deadline', 'wpotkaz' ); ?></option>
				<option value="open" <?php selected( $f['deadline'], 'open' ); ?>><?php esc_html_e( 'Not started', 'wpotkaz' ); ?></option>
			</select>
			<label class="screen-reader-text" for="py-otkaz-email-status"><?php esc_html_e( 'Confirmation', 'wpotkaz' ); ?></label>
			<select name="email_status" id="py-otkaz-email-status">
				<option value=""><?php esc_html_e( 'Any email status', 'wpotkaz' ); ?></option>
				<option value="sent" <?php selected( $f['email_status'], 'sent' ); ?>><?php esc_html_e( 'Sent', 'wpotkaz' ); ?></option>
				<option value="failed" <?php selected( $f['email_status'], 'failed' ); ?>><?php esc_html_e( 'Email not sent', 'wpotkaz' ); ?></option>
			</select>
			<label for="py-otkaz-from"><?php esc_html_e( 'From', 'wpotkaz' ); ?></label>
			<input type="date" id="py-otkaz-from" name="date_from" value="<?php echo esc_attr( $f['date_from'] ); ?>" />
			<label for="py-otkaz-to"><?php esc_html_e( 'To', 'wpotkaz' ); ?></label>
			<input type="date" id="py-otkaz-to" name="date_to" value="<?php echo esc_attr( $f['date_to'] ); ?>" />
			<?php submit_button( __( 'Filter', 'wpotkaz' ), '', 'filter_action', false ); ?>
			<?php
			$export = wp_nonce_url(
				add_query_arg(
					array_merge(
						array( 'action' => 'py_otkaz_export' ),
						array_filter(
							array(
								's'            => $f['search'],
								'deadline'     => $f['deadline'],
								'email_status' => $f['email_status'],
								'date_from'    => $f['date_from'],
								'date_to'      => $f['date_to'],
							)
						)
					),
					admin_url( 'admin-post.php' )
				),
				'py_otkaz_export'
			);
			?>
			<a class="button" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Export CSV', 'wpotkaz' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Number column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_number( $s ) {
		$out = '<strong><a href="' . esc_url( Admin::url( array( 'view' => $s->id ) ) ) . '">' . esc_html( $s->number ) . '</a></strong>';
		if ( $s->has_excluded ) {
			$out .= '<br /><mark class="py-otkaz-badge py-otkaz-badge--warn">' . esc_html__( 'Excluded product', 'wpotkaz' ) . '</mark>';
		}
		return $out;
	}

	/**
	 * Date column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_date( $s ) {
		return esc_html( Util::format_gmt( $s->created_gmt, 'datetime' ) );
	}

	/**
	 * Order column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_order( $s ) {
		$order = Verifier::get_order( $s->order_id );
		if ( ! $order ) {
			return esc_html( '#' . $s->order_number );
		}
		return '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $s->order_number ) . '</a><br /><span class="description">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span>';
	}

	/**
	 * Customer column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_customer( $s ) {
		return esc_html( $s->name ) . '<br /><a href="mailto:' . esc_attr( $s->email ) . '">' . esc_html( $s->email ) . '</a>';
	}

	/**
	 * Items column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_items( $s ) {
		return Admin::items_html( $s );
	}

	/**
	 * Deadline column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_deadline( $s ) {
		return Admin::deadline_badge( $s );
	}

	/**
	 * Email column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_email( $s ) {
		$out = Admin::email_badge( $s );
		if ( 'sent' !== $s->email_status ) {
			$out .= '<br />' . Admin::resend_button( $s );
		}
		return $out;
	}
}
