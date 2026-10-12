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

	const PAGE = 'wpotkaz';
	const CAP  = 'manage_woocommerce';
	const AGENCY_URL = 'https://projectyordanov.com/?utm_source=wpotkaz&utm_medium=plugin&utm_campaign=wp-admin';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_wpotkaz_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_wpotkaz_resend', array( __CLASS__, 'resend' ) );
		add_action( 'admin_post_wpotkaz_page', array( __CLASS__, 'resolve_page_conflict' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_credit' ), 20 );
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
		$title  = __( 'Withdrawals', 'wpotkaz' );
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
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wpotkaz' ) );
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
		<div class="wrap wpotkaz-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Withdrawals from contract', 'wpotkaz' ); ?></h1>
			<a href="<?php echo esc_url( Settings::url() ); ?>" class="page-title-action"><?php esc_html_e( 'Settings', 'wpotkaz' ); ?></a>
			<hr class="wp-header-end" />
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<?php
				$table->search_box( __( 'Search', 'wpotkaz' ), 'wpotkaz-search' );
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
			echo '<div class="wrap"><p>' . esc_html__( 'Withdrawal not found.', 'wpotkaz' ) . '</p></div>';
			return;
		}
		$order = Verifier::get_order( $s->order_id );
		$rows  = array(
			__( 'Statement number', 'wpotkaz' )   => esc_html( $s->number ),
			__( 'Submitted on', 'wpotkaz' )       => esc_html( Util::format_gmt( $s->created_gmt, 'd.m.Y H:i:s' ) . ' (' . wp_timezone_string() . ') / ' . $s->created_gmt . ' UTC' ),
			__( 'Order', 'wpotkaz' )              => $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $s->order_number ) . '</a>' : esc_html( '#' . $s->order_number ),
			__( 'Name', 'wpotkaz' )               => esc_html( $s->name ),
			__( 'Email', 'wpotkaz' )              => '<a href="mailto:' . esc_attr( $s->email ) . '">' . esc_html( $s->email ) . '</a>',
			__( 'Deadline', 'wpotkaz' )           => self::deadline_badge( $s ) . ( $s->deadline_gmt ? ' ' . esc_html( Util::format_gmt( $s->deadline_gmt, 'date' ) ) : '' ),
			__( 'Products', 'wpotkaz' )           => self::items_html( $s ),
			__( 'Text of the statement', 'wpotkaz' ) => '<blockquote>' . esc_html( $s->statement ) . '</blockquote>',
			__( 'Reason (optional)', 'wpotkaz' )  => '' !== $s->reason ? nl2br( esc_html( $s->reason ) ) : '—',
			__( 'Source', 'wpotkaz' )             => esc_html( self::source_label( $s->source ) ),
			__( 'Language', 'wpotkaz' )           => esc_html( $s->language ? $s->language : '—' ),
			__( 'IP address', 'wpotkaz' )         => esc_html( $s->ip_address ),
			__( 'User agent', 'wpotkaz' )         => '<code>' . esc_html( $s->user_agent ) . '</code>',
			__( 'Customer confirmation', 'wpotkaz' ) => self::email_badge( $s ) . ( $s->email_sent_gmt ? ' ' . esc_html( Util::format_gmt( $s->email_sent_gmt, 'datetime' ) ) : '' ) . ' ' . self::resend_button( $s ),
			__( 'Store notification', 'wpotkaz' ) => esc_html( self::status_label( $s->admin_email_status ) ),
		);
		?>
		<div class="wrap wpotkaz-admin">
			<h1><?php echo esc_html( sprintf( /* translators: %s: number */ __( 'Withdrawal %s', 'wpotkaz' ), $s->number ) ); ?></h1>
			<p><a href="<?php echo esc_url( self::url() ); ?>">&larr; <?php esc_html_e( 'All withdrawals', 'wpotkaz' ); ?></a></p>
			<table class="widefat striped" style="max-width:960px">
				<tbody>
					<?php foreach ( $rows as $label => $html ) : ?>
						<tr><th scope="row" style="width:220px"><?php echo esc_html( $label ); ?></th><td><?php echo wp_kses_post( $html ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description" style="max-width:960px"><?php esc_html_e( 'The plugin does not cancel the order, refund the payment or restock automatically – process the withdrawal manually in the order (refund within 14 days of receiving the statement).', 'wpotkaz' ); ?></p>
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
		$out = '<ul class="wpotkaz-admin-items">';
		foreach ( $s->items as $item ) {
			$out .= '<li>' . esc_html( $item['name'] ) . ' &times; ' . (int) $item['qty'];
			if ( ! empty( $item['ordered_qty'] ) ) {
				/* translators: %d: purchased quantity */
				$out .= ' <span class="description">(' . esc_html( sprintf( __( 'of %d', 'wpotkaz' ), (int) $item['ordered_qty'] ) ) . ')</span>';
			}
			if ( ! empty( $item['excluded'] ) ) {
				$out .= ' <mark class="wpotkaz-badge wpotkaz-badge--warn" title="' . esc_attr( $item['excluded_reason'] ) . '">' . esc_html__( 'Excluded product', 'wpotkaz' ) . '</mark>';
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
			return '<mark class="wpotkaz-badge wpotkaz-badge--ok">' . esc_html__( 'In time', 'wpotkaz' ) . '</mark>';
		}
		if ( false === $s->within_deadline ) {
			return '<mark class="wpotkaz-badge wpotkaz-badge--warn">' . esc_html__( 'After the deadline', 'wpotkaz' ) . '</mark>';
		}
		return '<mark class="wpotkaz-badge" title="' . esc_attr__( 'The order was not completed when the statement was submitted.', 'wpotkaz' ) . '">' . esc_html__( 'Not started', 'wpotkaz' ) . '</mark>';
	}

	/**
	 * Email status badge.
	 *
	 * @param Submission $s Submission.
	 * @return string
	 */
	public static function email_badge( $s ) {
		$class = 'sent' === $s->email_status ? 'ok' : ( 'failed' === $s->email_status ? 'error' : '' );
		$label = 'failed' === $s->email_status ? __( 'Email not sent', 'wpotkaz' ) : self::status_label( $s->email_status );
		return '<mark class="wpotkaz-badge' . ( $class ? ' wpotkaz-badge--' . $class : '' ) . '">' . esc_html( $label ) . '</mark>';
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'sent'     => __( 'Sent', 'wpotkaz' ),
			'failed'   => __( 'Failed', 'wpotkaz' ),
			'pending'  => __( 'Pending', 'wpotkaz' ),
			'disabled' => __( 'Disabled', 'wpotkaz' ),
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
			'lookup'  => __( 'Order number + email', 'wpotkaz' ),
			'link'    => __( 'Link from email', 'wpotkaz' ),
			'account' => __( 'My account', 'wpotkaz' ),
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
					'action' => 'wpotkaz_resend',
					'id'     => $s->id,
				),
				admin_url( 'admin-post.php' )
			),
			'py_otkaz_resend_' . $s->id
		);
		return '<a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html( 'sent' === $s->email_status ? __( 'Send again', 'wpotkaz' ) : __( 'Resend', 'wpotkaz' ) ) . '</a>';
	}

	/**
	 * Handle resend.
	 */
	public static function resend() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'py_otkaz_resend_' . $id );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wpotkaz' ) );
		}
		$s  = Submission::get( $id );
		$ok = $s ? Emails::send_customer( $s ) : false;
		if ( $s && $ok ) {
			$order = Verifier::get_order( $s->order_id );
			if ( $order ) {
				/* translators: 1: statement number, 2: email */
				$order->add_order_note( sprintf( __( 'Withdrawal confirmation %1$s resent to %2$s.', 'wpotkaz' ), $s->number, $s->email ) );
			}
		}
		$back = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'wpotkaz_msg', $ok ? 'resent' : 'resend_failed', $back ? $back : self::url() ) );
		exit;
	}

	/**
	 * CSV export of the current filters (all columns).
	 */
	public static function export_csv() {
		check_admin_referer( 'wpotkaz_export' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wpotkaz' ) );
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
			__( 'Statement number', 'wpotkaz' ),
			__( 'Submitted (local)', 'wpotkaz' ),
			__( 'Submitted (UTC)', 'wpotkaz' ),
			__( 'Order ID', 'wpotkaz' ),
			__( 'Order number', 'wpotkaz' ),
			__( 'Customer ID', 'wpotkaz' ),
			__( 'Name', 'wpotkaz' ),
			__( 'Email', 'wpotkaz' ),
			__( 'Products', 'wpotkaz' ),
			__( 'Total quantity', 'wpotkaz' ),
			__( 'Excluded products', 'wpotkaz' ),
			__( 'Deadline', 'wpotkaz' ),
			__( 'Indicative deadline', 'wpotkaz' ),
			__( 'Text of the statement', 'wpotkaz' ),
			__( 'Reason (optional)', 'wpotkaz' ),
			__( 'Source', 'wpotkaz' ),
			__( 'Language', 'wpotkaz' ),
			__( 'IP address', 'wpotkaz' ),
			__( 'User agent', 'wpotkaz' ),
			__( 'Customer confirmation', 'wpotkaz' ),
			__( 'Confirmation sent (UTC)', 'wpotkaz' ),
			__( 'Send attempts', 'wpotkaz' ),
			__( 'Store notification', 'wpotkaz' ),
		);
		fputcsv( $out, $header, ',', '"', '' );

		foreach ( $result['items'] as $s ) {
			$items = array();
			foreach ( $s->items as $item ) {
				$items[] = $item['name'] . ( ! empty( $item['sku'] ) ? ' [' . $item['sku'] . ']' : '' ) . ' x ' . (int) $item['qty'] . ( ! empty( $item['excluded'] ) ? ' (' . $item['excluded_reason'] . ')' : '' );
			}
			if ( true === $s->within_deadline ) {
				$deadline = __( 'In time', 'wpotkaz' );
			} elseif ( false === $s->within_deadline ) {
				$deadline = __( 'After the deadline', 'wpotkaz' );
			} else {
				$deadline = __( 'Not started', 'wpotkaz' );
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
				$s->has_excluded ? __( 'Yes', 'wpotkaz' ) : __( 'No', 'wpotkaz' ),
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
		if ( get_transient( 'py_otkaz_activated' ) && current_user_can( self::CAP ) && ! get_option( Install::CONFLICT_OPTION ) ) {
			delete_transient( 'py_otkaz_activated' );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'WPOtkaz is active. A “Withdrawal from contract” page was created – review the settings and add the link to your footer.', 'wpotkaz' ),
				esc_url( Settings::url() ),
				esc_html__( 'Open settings', 'wpotkaz' )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = isset( $_GET['wpotkaz_msg'] ) ? sanitize_key( wp_unslash( $_GET['wpotkaz_msg'] ) ) : '';
		if ( 'resent' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The confirmation was sent to the customer.', 'wpotkaz' ) . '</p></div>';
		} elseif ( 'cache_checked' === $msg ) {
			$level = Cache_Check::level( Cache_Check::result() );
			$class = 'good' === $level ? 'notice-success' : ( 'critical' === $level ? 'notice-error' : 'notice-warning' );
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . wp_kses_post( Cache_Check::describe( Cache_Check::result() ) ) . '</p></div>';
		} elseif ( 'page_ok' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The withdrawal form page is set up. Check it and add the link to your footer.', 'wpotkaz' ) . '</p></div>';
		} elseif ( 'page_failed' === $msg ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The page could not be updated. Add [wpotkaz] to the page manually and select it in the settings.', 'wpotkaz' ) . '</p></div>';
		} elseif ( 'resend_failed' === $msg ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The email could not be sent. Check your SMTP settings and WooCommerce → Status → Logs (source “wpotkaz”).', 'wpotkaz' ) . '</p></div>';
		}

		// Cache self-check: critical everywhere, "cached" only on WPOtkaz screens.
		$cache_level = Cache_Check::level( Cache_Check::result() );
		$cache_scr   = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$on_ours     = $cache_scr && ( 'woocommerce_page_' . self::PAGE === $cache_scr->id || 'woocommerce_page_wc-settings' === $cache_scr->id );
		if ( 'cache_checked' !== $msg && current_user_can( self::CAP ) && ( 'critical' === $cache_level || ( 'recommended' === $cache_level && $on_ours ) ) ) {
			printf(
				'<div class="notice %1$s"><p><strong>%2$s</strong> %3$s</p><p><a class="button" href="%4$s">%5$s</a></p></div>',
				'critical' === $cache_level ? 'notice-error' : 'notice-warning',
				esc_html__( 'WPOtkaz:', 'wpotkaz' ),
				wp_kses_post( Cache_Check::describe( Cache_Check::result() ) ),
				esc_url( Cache_Check::button_url() ),
				esc_html__( 'Check cache now', 'wpotkaz' )
			);
		}

		$conflict = (int) get_option( Install::CONFLICT_OPTION );
		if ( $conflict && current_user_can( self::CAP ) && current_user_can( 'edit_pages' ) ) {
			self::conflict_notice( $conflict );
			return;
		}

		$page_id = (int) Settings::get( 'page_id' );
		$screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, array( 'woocommerce_page_wc-settings', 'woocommerce_page_' . self::PAGE ), true ) ) {
			$post = $page_id ? get_post( $page_id ) : null;
			if ( ! $post || 'publish' !== $post->post_status ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'The withdrawal form page is missing or not published. Customers cannot exercise their right of withdrawal online.', 'wpotkaz' ) . '</p></div>';
			}
		}
	}

	/**
	 * "A page with this slug already exists" notice with two choices.
	 *
	 * @param int $page_id Existing page ID.
	 */
	private static function conflict_notice( $page_id ) {
		$post = get_post( $page_id );
		if ( ! $post ) {
			delete_option( Install::CONFLICT_OPTION );
			return;
		}
		$url = static function ( $choice ) use ( $page_id ) {
			return wp_nonce_url( add_query_arg( array( 'action' => 'wpotkaz_page', 'choice' => $choice, 'page_id' => $page_id ), admin_url( 'admin-post.php' ) ), 'wpotkaz_page' );
		};
		?>
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( 'WPOtkaz: a page “Withdrawal from contract” already exists', 'wpotkaz' ); ?></strong></p>
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: 1: page title with link, 2: page status */
						__( 'The page %1$s (%2$s) uses the address that WPOtkaz needs, so no new page was created and nothing was changed. Choose how to continue:', 'wpotkaz' ),
						'<a href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $post ) ) . '</a>',
						esc_html( get_post_status_object( $post->post_status ) ? get_post_status_object( $post->post_status )->label : $post->post_status )
					)
				);
				?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $url( 'use' ) ); ?>"><?php esc_html_e( 'Use this page – add the form at the end', 'wpotkaz' ); ?></a>
				<a class="button" href="<?php echo esc_url( $url( 'new' ) ); ?>"><?php esc_html_e( 'Create a separate page', 'wpotkaz' ); ?></a>
				<a class="button-link" style="margin-left:8px" href="<?php echo esc_url( get_edit_post_link( $post ) ); ?>"><?php esc_html_e( 'Edit the page manually', 'wpotkaz' ); ?></a>
			</p>
			<p class="description"><?php esc_html_e( 'The existing content is kept. If the page contains an old returns form (Contact Form 7, WPForms, Forminator), remove it afterwards so customers see only one form. A complaints form stays separate.', 'wpotkaz' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Handle the page conflict choice.
	 */
	public static function resolve_page_conflict() {
		check_admin_referer( 'wpotkaz_page' );
		if ( ! current_user_can( self::CAP ) || ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wpotkaz' ) );
		}
		$choice  = isset( $_GET['choice'] ) ? sanitize_key( wp_unslash( $_GET['choice'] ) ) : '';
		$page_id = isset( $_GET['page_id'] ) ? absint( $_GET['page_id'] ) : 0;
		$ok      = false;
		if ( 'use' === $choice ) {
			$ok = Install::use_existing_page( $page_id );
		} elseif ( 'new' === $choice ) {
			$ok = (bool) Install::create_page();
		}
		wp_safe_redirect( add_query_arg( 'wpotkaz_msg', $ok ? 'page_ok' : 'page_failed', Settings::url() ) );
		exit;
	}

	/**
	 * Discreet developer link on the Plugins screen.
	 *
	 * @param array  $meta Row meta.
	 * @param string $file Plugin file.
	 * @return array
	 */
	public static function row_meta( $meta, $file ) {
		if ( PY_OTKAZ_BASENAME === $file ) {
			$meta[] = sprintf(
				/* translators: %s: agency name with link */
				esc_html__( 'Developed by %s', 'wpotkaz' ),
				'<a href="' . esc_url( self::AGENCY_URL ) . '" target="_blank" rel="noopener">Project Yordanov</a>'
			);
		}
		return $meta;
	}

	/**
	 * Discreet credit in the admin footer, only on WPOtkaz screens.
	 *
	 * @param string $text Footer text.
	 * @return string
	 */
	public static function footer_credit( $text ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return $text;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$ours = 'woocommerce_page_' . self::PAGE === $screen->id || ( 'woocommerce_page_wc-settings' === $screen->id && Settings::TAB === $tab );
		if ( ! $ours ) {
			return $text;
		}
		return sprintf(
			/* translators: 1: plugin name, 2: version, 3: agency name with link */
			esc_html__( '%1$s %2$s · developed by %3$s', 'wpotkaz' ),
			'WPOtkaz',
			esc_html( PY_OTKAZ_VERSION ),
			'<a href="' . esc_url( self::AGENCY_URL ) . '" target="_blank" rel="noopener">Project Yordanov</a>'
		);
	}

	/**
	 * Small admin CSS for badges.
	 */
	public static function admin_css() {
		?>
		<style>
			.wpotkaz-badge{display:inline-block;padding:2px 8px;border-radius:10px;background:#f0f0f1;color:#50575e;font-size:12px;line-height:1.6;white-space:nowrap}
			.wpotkaz-badge--ok{background:#edfaef;color:#00691c}
			.wpotkaz-badge--warn{background:#fcf9e8;color:#8a4b00}
			.wpotkaz-badge--error{background:#fcf0f1;color:#a00}
			.wpotkaz-admin-items{margin:0}
			.wpotkaz-admin-items li{margin:0 0 2px}
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
				$new['py_otkaz'] = __( 'Withdrawal', 'wpotkaz' );
			}
		}
		if ( ! isset( $new['py_otkaz'] ) ) {
			$new['py_otkaz'] = __( 'Withdrawal', 'wpotkaz' );
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
			'<a href="%s" class="wpotkaz-badge wpotkaz-badge--warn" title="%s">%s</a>',
			esc_url( self::url( array( 's' => $order->get_order_number() ) ) ),
			esc_attr( implode( ', ', $numbers ) ),
			esc_html(
				count( $history ) > 1
					/* translators: %d: count */
					? sprintf( __( 'Withdrawal ×%d', 'wpotkaz' ), count( $history ) )
					: __( 'Withdrawal', 'wpotkaz' )
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
		add_meta_box( 'wpotkaz-order', __( 'Withdrawal from contract', 'wpotkaz' ), array( __CLASS__, 'meta_box_content' ), $screen_id, 'side', 'high' );
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
			echo '<p class="description">' . esc_html( sprintf( __( 'Indicative deadline: %s', 'wpotkaz' ), wp_date( get_option( 'date_format' ), $deadline->getTimestamp() ) ) ) . '</p>';
		}
		echo '<p class="description">' . esc_html__( 'Refund and restock manually; the plugin does not change payments or stock.', 'wpotkaz' ) . '</p>';
	}
}
