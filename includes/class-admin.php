<?php
/**
 * Admin: WooCommerce → Withdrawals, CSV export, resend, order list badge, order meta box.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Admin module.
 */
class Admin {

	const PAGE = 'otkazpro';
	const CAP  = 'manage_woocommerce';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_py_otkaz_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_py_otkaz_resend', array( __CLASS__, 'resend' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_head', array( __CLASS__, 'admin_css' ) );

		// Orders list column – HPOS and legacy.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'orders_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'orders_column_content' ), 20, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'orders_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'orders_column_content' ), 20, 2 );

		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ), 30, 2 );
	}

	/**
	 * Admin page URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Submenu under WooCommerce.
	 */
	public static function menu() {
		$failed = Submission::count_failed();
		$title  = __( 'Withdrawals', 'otkazpro' );
		$menu   = $title;
		if ( $failed ) {
			$menu .= ' <span class="awaiting-mod count-' . (int) $failed . '"><span class="pending-count">' . (int) $failed . '</span></span>';
		}
		add_submenu_page( 'woocommerce', $title, $menu, self::CAP, self::PAGE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Page router.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'otkazpro' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;
		if ( $view ) {
			self::render_detail( $view );
			return;
		}

		$table = new Admin_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap py-otkaz-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Withdrawals from contract', 'otkazpro' ); ?></h1>
			<a href="<?php echo esc_url( Settings::url() ); ?>" class="page-title-action"><?php esc_html_e( 'Settings', 'otkazpro' ); ?></a>
			<hr class="wp-header-end" />
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<?php
				$table->search_box( __( 'Search', 'otkazpro' ), 'py-otkaz-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Detail view of one statement.
	 *
	 * @param int $id ID.
	 */
	private static function render_detail( $id ) {
		$s = Submission::get( $id );
		if ( ! $s ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Withdrawal not found.', 'otkazpro' ) . '</p></div>';
			return;
		}
		$order = Verifier::get_order( $s->order_id );
		$rows  = array(
			__( 'Statement number', 'otkazpro' )   => esc_html( $s->number ),
			__( 'Submitted on', 'otkazpro' )       => esc_html( Util::format_gmt( $s->created_gmt, 'd.m.Y H:i:s' ) . ' (' . wp_timezone_string() . ') / ' . $s->created_gmt . ' UTC' ),
			__( 'Order', 'otkazpro' )              => $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $s->order_number ) . '</a>' : esc_html( '#' . $s->order_number ),
			__( 'Name', 'otkazpro' )               => esc_html( $s->name ),
			__( 'Email', 'otkazpro' )              => '<a href="mailto:' . esc_attr( $s->email ) . '">' . esc_html( $s->email ) . '</a>',
			__( 'Deadline', 'otkazpro' )           => self::deadline_badge( $s ) . ( $s->deadline_gmt ? ' ' . esc_html( Util::format_gmt( $s->deadline_gmt, 'date' ) ) : '' ),
			__( 'Products', 'otkazpro' )           => self::items_html( $s ),
			__( 'Text of the statement', 'otkazpro' ) => '<blockquote>' . esc_html( $s->statement ) . '</blockquote>',
			__( 'Reason (optional)', 'otkazpro' )  => '' !== $s->reason ? nl2br( esc_html( $s->reason ) ) : '—',
			__( 'Source', 'otkazpro' )             => esc_html( self::source_label( $s->source ) ),
			__( 'Language', 'otkazpro' )           => esc_html( $s->language ? $s->language : '—' ),
			__( 'IP address', 'otkazpro' )         => esc_html( $s->ip_address ),
			__( 'User agent', 'otkazpro' )         => '<code>' . esc_html( $s->user_agent ) . '</code>',
			__( 'Customer confirmation', 'otkazpro' ) => self::email_badge( $s ) . ( $s->email_sent_gmt ? ' ' . esc_html( Util::format_gmt( $s->email_sent_gmt, 'datetime' ) ) : '' ) . ' ' . self::resend_button( $s ),
			__( 'Store notification', 'otkazpro' ) => esc_html( self::status_label( $s->admin_email_status ) ),
		);
		?>
		<div class="wrap py-otkaz-admin">
			<h1><?php echo esc_html( sprintf( /* translators: %s: number */ __( 'Withdrawal %s', 'otkazpro' ), $s->number ) ); ?></h1>
			<p><a href="<?php echo esc_url( self::url() ); ?>">&larr; <?php esc_html_e( 'All withdrawals', 'otkazpro' ); ?></a></p>
			<table class="widefat striped" style="max-width:960px">
				<tbody>
					<?php foreach ( $rows as $label => $html ) : ?>
						<tr><th scope="row" style="width:220px"><?php echo esc_html( $label ); ?></th><td><?php echo wp_kses_post( $html ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description" style="max-width:960px"><?php esc_html_e( 'The plugin does not cancel the order, refund the payment or restock automatically – process the withdrawal manually in the order (refund within 14 days of receiving the statement).', 'otkazpro' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Items list markup.
	 *
	 * @param Submission $s Submission.
	 * @return string
	 */
	public static function items_html( $s ) {
		$out = '<ul class="py-otkaz-admin-items">';
		foreach ( $s->items as $item ) {
			$out .= '<li>' . esc_html( $item['name'] ) . ' &times; ' . (int) $item['qty'];
			if ( ! empty( $item['ordered_qty'] ) ) {
				/* translators: %d: purchased quantity */
				$out .= ' <span class="description">(' . esc_html( sprintf( __( 'of %d', 'otkazpro' ), (int) $item['ordered_qty'] ) ) . ')</span>';
			}
			if ( ! empty( $item['excluded'] ) ) {
				$out .= ' <mark class="py-otkaz-badge py-otkaz-badge--warn" title="' . esc_attr( $item['excluded_reason'] ) . '">' . esc_html__( 'Excluded product', 'otkazpro' ) . '</mark>';
			}
			$out .= '</li>';
		}
		return $out . '</ul>';
	}

	/**
	 * Deadline badge.
	 *
	 * @param Submission $s Submission.
	 * @return string
	 */
	public static function deadline_badge( $s ) {
		if ( true === $s->within_deadline ) {
			return '<mark class="py-otkaz-badge py-otkaz-badge--ok">' . esc_html__( 'In time', 'otkazpro' ) . '</mark>';
		}
		if ( false === $s->within_deadline ) {
			return '<mark class="py-otkaz-badge py-otkaz-badge--warn">' . esc_html__( 'After the deadline', 'otkazpro' ) . '</mark>';
		}
		return '<mark class="py-otkaz-badge" title="' . esc_attr__( 'The order was not completed when the statement was submitted.', 'otkazpro' ) . '">' . esc_html__( 'Not started', 'otkazpro' ) . '</mark>';
	}

	/**
	 * Email status badge.
	 *
	 * @param Submission $s Submission.
	 * @return string
	 */
	public static function email_badge( $s ) {
		$class = 'sent' === $s->email_status ? 'ok' : ( 'failed' === $s->email_status ? 'error' : '' );
		$label = 'failed' === $s->email_status ? __( 'Email not sent', 'otkazpro' ) : self::status_label( $s->email_status );
		return '<mark class="py-otkaz-badge' . ( $class ? ' py-otkaz-badge--' . $class : '' ) . '">' . esc_html( $label ) . '</mark>';
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'sent'     => __( 'Sent', 'otkazpro' ),
			'failed'   => __( 'Failed', 'otkazpro' ),
			'pending'  => __( 'Pending', 'otkazpro' ),
			'disabled' => __( 'Disabled', 'otkazpro' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Source label.
	 *
	 * @param string $source Source.
	 * @return string
	 */
	public static function source_label( $source ) {
		$labels = array(
			'lookup'  => __( 'Order number + email', 'otkazpro' ),
			'link'    => __( 'Link from email', 'otkazpro' ),
			'account' => __( 'My account', 'otkazpro' ),
		);
		return isset( $labels[ $source ] ) ? $labels[ $source ] : (string) $source;
	}

	/**
	 * Resend form button.
	 *
	 * @param Submission $s Submission.
	 * @return string
	 */
	public static function resend_button( $s ) {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'py_otkaz_resend',
					'id'     => $s->id,
				),
				admin_url( 'admin-post.php' )
			),
			'py_otkaz_resend_' . $s->id
		);
		return '<a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html( 'sent' === $s->email_status ? __( 'Send again', 'otkazpro' ) : __( 'Resend', 'otkazpro' ) ) . '</a>';
	}

	/**
	 * Handle resend.
	 */
	public static function resend() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'py_otkaz_resend_' . $id );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'otkazpro' ) );
		}
		$s  = Submission::get( $id );
		$ok = $s ? Emails::send_customer( $s ) : false;
		if ( $s && $ok ) {
			$order = Verifier::get_order( $s->order_id );
			if ( $order ) {
				/* translators: 1: statement number, 2: email */
				$order->add_order_note( sprintf( __( 'Withdrawal confirmation %1$s resent to %2$s.', 'otkazpro' ), $s->number, $s->email ) );
			}
		}
		$back = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'py_otkaz_msg', $ok ? 'resent' : 'resend_failed', $back ? $back : self::url() ) );
		exit;
	}

	/**
	 * CSV export of the current filters (all columns).
	 */
	public static function export_csv() {
		check_admin_referer( 'py_otkaz_export' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'otkazpro' ) );
		}

		$args             = Admin_List_Table::filters_from_request();
		$args['per_page'] = 0;
		$result           = Submission::query( $args );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=otkazi-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM for Excel.

		$header = array(
			__( 'Statement number', 'otkazpro' ),
			__( 'Submitted (local)', 'otkazpro' ),
			__( 'Submitted (UTC)', 'otkazpro' ),
			__( 'Order ID', 'otkazpro' ),
			__( 'Order number', 'otkazpro' ),
			__( 'Customer ID', 'otkazpro' ),
			__( 'Name', 'otkazpro' ),
			__( 'Email', 'otkazpro' ),
			__( 'Products', 'otkazpro' ),
			__( 'Total quantity', 'otkazpro' ),
			__( 'Excluded products', 'otkazpro' ),
			__( 'Deadline', 'otkazpro' ),
			__( 'Indicative deadline', 'otkazpro' ),
			__( 'Text of the statement', 'otkazpro' ),
			__( 'Reason (optional)', 'otkazpro' ),
			__( 'Source', 'otkazpro' ),
			__( 'Language', 'otkazpro' ),
			__( 'IP address', 'otkazpro' ),
			__( 'User agent', 'otkazpro' ),
			__( 'Customer confirmation', 'otkazpro' ),
			__( 'Confirmation sent (UTC)', 'otkazpro' ),
			__( 'Send attempts', 'otkazpro' ),
			__( 'Store notification', 'otkazpro' ),
		);
		fputcsv( $out, $header, ',', '"', '' );

		foreach ( $result['items'] as $s ) {
			$items = array();
			foreach ( $s->items as $item ) {
				$items[] = $item['name'] . ( ! empty( $item['sku'] ) ? ' [' . $item['sku'] . ']' : '' ) . ' x ' . (int) $item['qty'] . ( ! empty( $item['excluded'] ) ? ' (' . $item['excluded_reason'] . ')' : '' );
			}
			if ( true === $s->within_deadline ) {
				$deadline = __( 'In time', 'otkazpro' );
			} elseif ( false === $s->within_deadline ) {
				$deadline = __( 'After the deadline', 'otkazpro' );
			} else {
				$deadline = __( 'Not started', 'otkazpro' );
			}
			$row = array(
				$s->number,
				$s->created_local,
				$s->created_gmt,
				$s->order_id,
				$s->order_number,
				$s->customer_id,
				$s->name,
				$s->email,
				implode( '; ', $items ),
				$s->total_qty(),
				$s->has_excluded ? __( 'Yes', 'otkazpro' ) : __( 'No', 'otkazpro' ),
				$deadline,
				$s->deadline_gmt ? Util::format_gmt( $s->deadline_gmt, 'Y-m-d' ) : '',
				$s->statement,
				$s->reason,
				self::source_label( $s->source ),
				$s->language,
				$s->ip_address,
				$s->user_agent,
				self::status_label( $s->email_status ),
				$s->email_sent_gmt,
				$s->email_attempts,
				self::status_label( $s->admin_email_status ),
			);
			fputcsv( $out, array_map( array( Util::class, 'csv_cell' ), $row ), ',', '"', '' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Admin notices.
	 */
	public static function notices() {
		if ( get_transient( 'py_otkaz_activated' ) && current_user_can( self::CAP ) ) {
			delete_transient( 'py_otkaz_activated' );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'OtkazPro is active. A “Withdrawal from contract” page was created – review the settings and add the link to your footer.', 'otkazpro' ),
				esc_url( Settings::url() ),
				esc_html__( 'Open settings', 'otkazpro' )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = isset( $_GET['py_otkaz_msg'] ) ? sanitize_key( wp_unslash( $_GET['py_otkaz_msg'] ) ) : '';
		if ( 'resent' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The confirmation was sent to the customer.', 'otkazpro' ) . '</p></div>';
		} elseif ( 'resend_failed' === $msg ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The email could not be sent. Check your SMTP settings and WooCommerce → Status → Logs (source “otkazpro”).', 'otkazpro' ) . '</p></div>';
		}

		$page_id = (int) Settings::get( 'page_id' );
		$screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, array( 'woocommerce_page_wc-settings', 'woocommerce_page_' . self::PAGE ), true ) ) {
			$post = $page_id ? get_post( $page_id ) : null;
			if ( ! $post || 'publish' !== $post->post_status ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'The withdrawal form page is missing or not published. Customers cannot exercise their right of withdrawal online.', 'otkazpro' ) . '</p></div>';
			}
		}
	}

	/**
	 * Small admin CSS for badges.
	 */
	public static function admin_css() {
		?>
		<style>
			.py-otkaz-badge{display:inline-block;padding:2px 8px;border-radius:10px;background:#f0f0f1;color:#50575e;font-size:12px;line-height:1.6;white-space:nowrap}
			.py-otkaz-badge--ok{background:#edfaef;color:#00691c}
			.py-otkaz-badge--warn{background:#fcf9e8;color:#8a4b00}
			.py-otkaz-badge--error{background:#fcf0f1;color:#a00}
			.py-otkaz-admin-items{margin:0}
			.py-otkaz-admin-items li{margin:0 0 2px}
			.column-py_otkaz{width:90px}
		</style>
		<?php
	}

	/**
	 * Orders list column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function orders_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$new['py_otkaz'] = __( 'Withdrawal', 'otkazpro' );
			}
		}
		if ( ! isset( $new['py_otkaz'] ) ) {
			$new['py_otkaz'] = __( 'Withdrawal', 'otkazpro' );
		}
		return $new;
	}

	/**
	 * Orders list column content.
	 *
	 * @param string        $column Column.
	 * @param int|\WC_Order $order  Order or post ID.
	 */
	public static function orders_column_content( $column, $order ) {
		if ( 'py_otkaz' !== $column ) {
			return;
		}
		$order = $order instanceof \WC_Order ? $order : Verifier::get_order( (int) $order );
		if ( ! $order ) {
			return;
		}
		$history = Order_Data::history( $order );
		if ( ! $history ) {
			return;
		}
		$numbers = wp_list_pluck( $history, 'number' );
		printf(
			'<a href="%s" class="py-otkaz-badge py-otkaz-badge--warn" title="%s">%s</a>',
			esc_url( self::url( array( 's' => $order->get_order_number() ) ) ),
			esc_attr( implode( ', ', $numbers ) ),
			esc_html(
				count( $history ) > 1
					/* translators: %d: count */
					? sprintf( __( 'Withdrawal ×%d', 'otkazpro' ), count( $history ) )
					: __( 'Withdrawal', 'otkazpro' )
			)
		);
	}

	/**
	 * Meta box on the order edit screen.
	 *
	 * @param string          $screen_id Screen ID.
	 * @param \WP_Post|object $object    Post or order.
	 */
	public static function meta_box( $screen_id, $object = null ) {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		if ( ! in_array( $screen_id, $screens, true ) ) {
			return;
		}
		$order = $object instanceof \WP_Post ? Verifier::get_order( $object->ID ) : ( $object instanceof \WC_Order ? $object : null );
		if ( ! $order || ! Order_Data::history( $order ) ) {
			return;
		}
		add_meta_box( 'py-otkaz-order', __( 'Withdrawal from contract', 'otkazpro' ), array( __CLASS__, 'meta_box_content' ), $screen_id, 'side', 'high' );
	}

	/**
	 * Meta box content.
	 *
	 * @param \WP_Post|\WC_Order $object Post or order.
	 */
	public static function meta_box_content( $object ) {
		$order = $object instanceof \WP_Post ? Verifier::get_order( $object->ID ) : $object;
		if ( ! $order ) {
			return;
		}
		$deadline = Order_Data::deadline( $order );
		foreach ( Submission::for_order( $order->get_id() ) as $s ) {
			echo '<div style="padding:8px 0;border-bottom:1px solid #f0f0f1">';
			echo '<p style="margin:0 0 4px"><a href="' . esc_url( self::url( array( 'view' => $s->id ) ) ) . '"><strong>' . esc_html( $s->number ) . '</strong></a> · ' . esc_html( Util::format_gmt( $s->created_gmt, 'datetime' ) ) . '</p>';
			echo wp_kses_post( self::items_html( $s ) );
			echo '<p style="margin:4px 0 0">' . wp_kses_post( self::deadline_badge( $s ) . ' ' . self::email_badge( $s ) ) . '</p>';
			echo '</div>';
		}
		if ( $deadline ) {
			/* translators: %s: date */
			echo '<p class="description">' . esc_html( sprintf( __( 'Indicative deadline: %s', 'otkazpro' ), wp_date( get_option( 'date_format' ), $deadline->getTimestamp() ) ) ) . '</p>';
		}
		echo '<p class="description">' . esc_html__( 'Refund and restock manually; the plugin does not change payments or stock.', 'otkazpro' ) . '</p>';
	}
}
