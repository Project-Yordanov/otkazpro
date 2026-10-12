<?php
/**
 * Front-end withdrawal form ([wpotkaz]).
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode, request handling (POST → redirect → GET) and rendering.
 */
class Form {

	const SHORTCODE = 'wpotkaz';

	/**
	 * Earlier shortcode names, kept as aliases.
	 */
	const LEGACY_SHORTCODES = array( 'otkazpro', 'py_otkaz_form' );
	const HONEYPOT  = 'py_website';

	/**
	 * Request state shared between template_redirect and shortcode render.
	 *
	 * @var array
	 */
	private static $state = array(
		'handled'    => false,
		'view'       => '',
		'errors'     => array(),
		'fields'     => array(),
		'values'     => array(),
		'submission' => null,
		'notice'     => '',
	);

	/**
	 * Hooks.
	 */
	public static function init() {
		foreach ( self::shortcodes() as $tag ) {
			add_shortcode( $tag, array( __CLASS__, 'shortcode' ) );
		}
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
	}

	/**
	 * Current + legacy shortcode tags.
	 *
	 * @return string[]
	 */
	public static function shortcodes() {
		return array_merge( array( self::SHORTCODE ), self::LEGACY_SHORTCODES );
	}

	/**
	 * Does the content contain the form (any shortcode name)?
	 *
	 * @param string $content Content.
	 * @return bool
	 */
	public static function has_form_shortcode( $content ) {
		foreach ( self::shortcodes() as $tag ) {
			if ( has_shortcode( $content, $tag ) ) {
				return true;
			}
		}
		return false;
	}

	/* ---------------------------------------------------------------------
	 * URLs
	 * ------------------------------------------------------------------ */

	/**
	 * Form page URL (translated page when WPML/Polylang is active).
	 *
	 * @param string $lang Language code.
	 * @return string
	 */
	public static function page_url( $lang = '' ) {
		$page_id = (int) Settings::get( 'page_id' );
		if ( $page_id && $lang ) {
			$translated = apply_filters( 'wpml_object_id', $page_id, 'page', true, $lang );
			if ( $translated ) {
				$page_id = (int) $translated;
			} elseif ( function_exists( 'pll_get_post' ) ) {
				$pll     = pll_get_post( $page_id, $lang );
				$page_id = $pll ? (int) $pll : $page_id;
			}
		}
		$url = $page_id ? get_permalink( $page_id ) : '';
		return $url ? $url : home_url( '/' );
	}

	/**
	 * Direct (token) URL to the form for an order – works without login.
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $lang  Language.
	 * @return string
	 */
	public static function order_url( $order, $lang = '' ) {
		return add_query_arg(
			array(
				'py_order' => $order->get_id(),
				'py_key'   => $order->get_order_key(),
			),
			self::page_url( $lang )
		);
	}

	/**
	 * URL of the current page (form action).
	 *
	 * @return string
	 */
	private static function current_page_url() {
		$id = get_queried_object_id();
		return $id ? get_permalink( $id ) : self::page_url();
	}

	/* ---------------------------------------------------------------------
	 * Request lifecycle
	 * ------------------------------------------------------------------ */

	/**
	 * Is the current request the form page?
	 *
	 * @return bool
	 */
	public static function is_form_page() {
		if ( ! is_singular() ) {
			return false;
		}
		$page_id = (int) Settings::get( 'page_id' );
		if ( $page_id && is_page( $page_id ) ) {
			return true;
		}
		$translated = $page_id ? apply_filters( 'wpml_object_id', $page_id, 'page', false ) : 0;
		if ( $translated && is_page( (int) $translated ) ) {
			return true;
		}
		$post = get_post();
		return $post && self::has_form_shortcode( (string) $post->post_content );
	}

	/**
	 * Has the request any order / result parameters?
	 *
	 * @return bool
	 */
	private static function has_private_params() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['py_order'] ) || isset( $_GET['py_key'] ) || isset( $_GET['py_done'] );
	}

	/**
	 * No caching / no indexing / no referrer on the form page; handle POST before output.
	 */
	public static function template_redirect() {
		if ( ! self::is_form_page() ) {
			return;
		}
		self::no_cache();
		self::maybe_handle_post( true );
	}

	/**
	 * Prevent page caches from storing personal data or stale nonces.
	 */
	private static function no_cache() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Referrer-Policy: no-referrer' );
			if ( self::has_private_params() ) {
				header( 'X-Robots-Tag: noindex, nofollow' );
			}
		}
		// Cache plugins that do not honour DONOTCACHEPAGE / no-store.
		do_action( 'litespeed_control_set_nocache', 'wpotkaz form' );
		add_filter( 'flying_press_is_cacheable', '__return_false', 999 );
		add_filter( 'rocket_override_donotcachepage', '__return_false', 999 );
		/**
		 * Fires when the form page is excluded from caching (hook up other cache plugins here).
		 */
		do_action( 'py_otkaz_no_cache' );
	}

	/**
	 * Robots meta for parameterised URLs.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( self::has_private_params() && self::is_form_page() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['index'], $robots['follow'] );
		}
		return $robots;
	}

	/**
	 * Process a POST once (from template_redirect or, as fallback, from the shortcode).
	 *
	 * @param bool $can_redirect Whether headers can still be sent.
	 */
	private static function maybe_handle_post( $can_redirect ) {
		if ( self::$state['handled'] ) {
			return;
		}
		self::$state['handled'] = true;

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in the handlers.
		if ( 'POST' !== $method || empty( $_POST['py_otkaz_action'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = sanitize_key( wp_unslash( $_POST['py_otkaz_action'] ) );

		if ( 'lookup' === $action ) {
			self::handle_lookup( $can_redirect );
		} elseif ( 'submit' === $action ) {
			self::handle_submit( $can_redirect );
		}
	}

	/**
	 * Read a POST string.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function post( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) : '';
	}

	/**
	 * Honeypot filled?
	 *
	 * @return bool
	 */
	private static function is_bot() {
		return '' !== self::post( self::HONEYPOT );
	}

	/**
	 * Add a form-level error.
	 *
	 * @param string $message Message.
	 */
	private static function error( $message ) {
		self::$state['errors'][] = $message;
	}

	/**
	 * Generic "not found" message (does not reveal whether the order exists).
	 *
	 * @return string
	 */
	private static function generic_not_found() {
		return __( 'We could not find an order with these details. Check the order number and use the email address the order was placed with.', 'wpotkaz' );
	}

	/**
	 * Step 1: order number + email.
	 *
	 * @param bool $can_redirect Can redirect.
	 */
	private static function handle_lookup( $can_redirect ) {
		$number = self::post( 'py_order_number' );
		$email  = sanitize_email( self::post( 'py_email' ) );

		self::$state['view']   = 'lookup';
		self::$state['values'] = array(
			'order_number' => $number,
			'email'        => self::post( 'py_email' ),
		);

		if ( Verifier::is_rate_limited() ) {
			self::error( __( 'Too many unsuccessful attempts. Please try again in 15 minutes or contact us.', 'wpotkaz' ) );
			return;
		}
		if ( self::is_bot() ) {
			Verifier::register_failure();
			self::error( self::generic_not_found() );
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST['_py_otkaz_nonce'] ) ? sanitize_key( wp_unslash( $_POST['_py_otkaz_nonce'] ) ) : '';
		// Guests: no nonce. Page caches that ignore DONOTCACHEPAGE would otherwise serve a
		// stale nonce and block every lookup (= obstructing the withdrawal). A cross-site
		// lookup changes nothing; rate limiting + honeypot protect against enumeration.
		if ( is_user_logged_in() && ! wp_verify_nonce( $nonce, 'py_otkaz_lookup' ) ) {
			self::error( __( 'The page was open for too long. Please submit the form again.', 'wpotkaz' ) );
			return;
		}

		if ( '' === $number ) {
			self::$state['fields']['py_order_number'] = __( 'Enter the order number.', 'wpotkaz' );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			self::$state['fields']['py_email'] = __( 'Enter a valid email address, e.g. name@example.com.', 'wpotkaz' );
		}

		/**
		 * Extra validation for the lookup step (e.g. a CAPTCHA). Return a WP_Error to block.
		 *
		 * @param null|\WP_Error $result Result.
		 * @param array          $values Submitted values.
		 */
		$extra = apply_filters( 'py_otkaz_validate_lookup', null, self::$state['values'] );
		if ( is_wp_error( $extra ) ) {
			self::error( $extra->get_error_message() );
			return;
		}
		if ( self::$state['fields'] ) {
			return;
		}

		$order = Verifier::lookup( $number, $email );
		if ( ! $order ) {
			Verifier::register_failure();
			self::error( self::generic_not_found() );
			return;
		}

		$url = add_query_arg( 'py_src', 'lookup', self::order_url( $order, I18n::current_language() ) );
		self::redirect( $url, $can_redirect );
	}

	/**
	 * Step 2: confirm withdrawal.
	 *
	 * @param bool $can_redirect Can redirect.
	 */
	private static function handle_submit( $can_redirect ) {
		$order_id = absint( self::post( 'py_order' ) );
		$key      = self::post( 'py_key' );
		$order    = Verifier::get_order( $order_id );

		self::$state['view'] = 'order';

		if ( ! Verifier::can_access( $order, $key ) ) {
			Verifier::register_failure();
			self::$state['view'] = 'lookup';
			self::error( __( 'This link is invalid or has expired. Please find your order using its number and email.', 'wpotkaz' ) );
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$raw_items = isset( $_POST['py_items'] ) && is_array( $_POST['py_items'] ) ? wp_unslash( $_POST['py_items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised below.
		$items     = array();
		$posted    = array();
		foreach ( $raw_items as $item_id => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$item_id            = absint( $item_id );
			$qty                = isset( $item['qty'] ) ? (int) $item['qty'] : 1;
			$on                 = ! empty( $item['selected'] );
			$posted[ $item_id ] = array(
				'selected' => $on,
				'qty'      => $qty,
			);
			if ( $item_id && $on ) {
				$items[ $item_id ] = $qty;
			}
		}
		$reason = Settings::enabled( 'reason_field' ) && isset( $_POST['py_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['py_reason'] ) ) : '';
		$token  = self::post( 'py_token' );
		$nonce  = isset( $_POST['_py_otkaz_nonce'] ) ? sanitize_key( wp_unslash( $_POST['_py_otkaz_nonce'] ) ) : '';
		// phpcs:enable

		$name  = self::post( 'py_name' );
		$email = strtolower( sanitize_email( self::post( 'py_email' ) ) );

		self::$state['values'] = array(
			'name'   => $name,
			'email'  => self::post( 'py_email' ),
			'items'  => $posted,
			'reason' => $reason,
		);

		// Double submit (refresh / double click) → show the existing result.
		if ( $token && preg_match( '/^[a-zA-Z0-9]{16,64}$/', $token ) ) {
			$done = get_transient( 'py_otkaz_idem_' . $token );
			if ( $done ) {
				self::redirect( add_query_arg( 'py_done', $done, self::current_page_url() ), $can_redirect, (string) $done );
				return;
			}
		}

		if ( self::is_bot() ) {
			self::error( __( 'Your withdrawal could not be submitted. Please try again.', 'wpotkaz' ) );
			return;
		}
		// The secret order key already proves the request comes from the order email / page,
		// so it doubles as CSRF protection; the nonce is required only for owner access.
		$has_key = '' !== $key && hash_equals( (string) $order->get_order_key(), $key );
		if ( ! $has_key && ! wp_verify_nonce( $nonce, 'py_otkaz_submit_' . $order->get_id() ) ) {
			self::error( __( 'The page was open for too long. Please review the details and confirm again.', 'wpotkaz' ) );
			return;
		}

		if ( '' === $name ) {
			self::$state['fields']['py_name'] = __( 'Enter your name.', 'wpotkaz' );
		} elseif ( mb_strlen( $name ) > 200 ) {
			self::$state['fields']['py_name'] = __( 'The name is too long.', 'wpotkaz' );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			self::$state['fields']['py_email'] = __( 'Enter a valid email address, e.g. name@example.com.', 'wpotkaz' );
		}
		if ( mb_strlen( $reason ) > 2000 ) {
			$reason = mb_substr( $reason, 0, 2000 );
		}
		if ( self::$state['fields'] ) {
			return;
		}

		$source = Verifier::is_owner( $order ) ? 'account' : 'link';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['py_src'] ) && 'lookup' === $_POST['py_src'] ) {
			$source = 'lookup';
		}

		$result = Submission::create(
			$order,
			array(
				'name'   => $name,
				'email'  => $email,
				'items'  => $items,
				'reason' => $reason,
				'source' => $source,
			)
		);

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && ! empty( $data['fields'] ) ) {
				self::$state['fields'] = array_merge( self::$state['fields'], $data['fields'] );
			} else {
				self::error( $result->get_error_message() );
			}
			return;
		}

		$done = wp_generate_password( 32, false );
		set_transient( 'py_otkaz_done_' . $done, $result->id, DAY_IN_SECONDS );
		if ( $token && preg_match( '/^[a-zA-Z0-9]{16,64}$/', $token ) ) {
			set_transient( 'py_otkaz_idem_' . $token, $done, DAY_IN_SECONDS );
		}
		self::$state['submission'] = $result;
		self::redirect( add_query_arg( 'py_done', $done, self::current_page_url() ), $can_redirect, $done );
	}

	/**
	 * PRG redirect, or render the success view in place when headers are gone.
	 *
	 * @param string $url          URL.
	 * @param bool   $can_redirect Can redirect.
	 * @param string $done         Success token (fallback render).
	 */
	private static function redirect( $url, $can_redirect, $done = '' ) {
		if ( $can_redirect && ! headers_sent() ) {
			wp_safe_redirect( $url, 303 );
			exit;
		}
		if ( $done ) {
			self::$state['view']       = 'success';
			self::$state['submission'] = self::submission_from_token( $done );
		} else {
			// Fallback: render the target in place.
			$query = wp_parse_url( $url, PHP_URL_QUERY );
			parse_str( (string) $query, $args );
			$_GET = array_merge( $_GET, $args ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::$state['view'] = '';
		}
	}

	/**
	 * Load the submission behind a success token.
	 *
	 * @param string $token Token.
	 * @return Submission|null
	 */
	private static function submission_from_token( $token ) {
		if ( ! preg_match( '/^[a-zA-Z0-9]{16,64}$/', (string) $token ) ) {
			return null;
		}
		$id = (int) get_transient( 'py_otkaz_done_' . $token );
		return $id ? Submission::get( $id ) : null;
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	/**
	 * Shortcode callback.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts = array() ) {
		if ( is_admin() || wp_is_json_request() ) {
			return '';
		}
		self::enqueue_assets();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		self::maybe_handle_post( false );

		$view = self::$state['view'];
		$args = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! $view && isset( $_GET['py_done'] ) ) {
			$submission = self::submission_from_token( sanitize_text_field( wp_unslash( $_GET['py_done'] ) ) );
			if ( $submission ) {
				$view                      = 'success';
				self::$state['submission'] = $submission;
			} else {
				self::$state['notice'] = __( 'Your withdrawal has already been submitted. The confirmation was sent to your email.', 'wpotkaz' );
				$view                  = 'lookup';
			}
		}

		if ( 'success' === $view && ! self::$state['submission'] ) {
			$view = 'lookup';
		}

		$order = null;
		if ( ! $view || 'order' === $view ) {
			$order_id = isset( $_POST['py_order'] ) ? absint( $_POST['py_order'] ) : ( isset( $_GET['py_order'] ) ? absint( $_GET['py_order'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$key      = isset( $_POST['py_key'] ) ? sanitize_text_field( wp_unslash( $_POST['py_key'] ) ) : ( isset( $_GET['py_key'] ) ? sanitize_text_field( wp_unslash( $_GET['py_key'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $order_id ) {
				$order = Verifier::get_order( $order_id );
				if ( Verifier::can_access( $order, $key ) ) {
					$view = 'order';
				} else {
					$order = null;
					$view  = 'lookup';
					if ( ! self::$state['errors'] ) {
						self::error( __( 'This link is invalid or has expired. Please find your order using its number and email.', 'wpotkaz' ) );
					}
				}
			} else {
				$view = 'lookup';
			}
		}
		// phpcs:enable

		$common = array(
			'form_action' => self::current_page_url(),
			'errors'      => self::$state['errors'],
			'fields'      => self::$state['fields'],
			'values'      => self::$state['values'],
			'notice'      => self::$state['notice'],
			'honeypot'    => self::HONEYPOT,
		);

		switch ( $view ) {
			case 'success':
				$args = self::success_args( self::$state['submission'] );
				break;
			case 'order':
				$args = self::order_args( $order );
				break;
			default:
				$view = 'lookup';
				$args = self::lookup_args();
		}

		$args = array_merge( $common, $args, array( 'view' => $view ) );

		/**
		 * Filter template args for the form views.
		 *
		 * @param array  $args Args.
		 * @param string $view lookup|order|success.
		 */
		$args = apply_filters( 'py_otkaz_form_args', $args, $view );

		return self::template( 'form-' . $view . '.php', $args );
	}

	/**
	 * Args for the lookup view.
	 *
	 * @return array
	 */
	private static function lookup_args() {
		$orders = array();
		if ( is_user_logged_in() ) {
			$candidates = wc_get_orders(
				array(
					'customer_id' => get_current_user_id(),
					'status'      => array_map(
						static function ( $s ) {
							return 'wc-' . $s;
						},
						Order_Data::eligible_statuses()
					),
					'limit'       => 10,
					'orderby'     => 'date',
					'order'       => 'DESC',
				)
			);
			foreach ( $candidates as $candidate ) {
				if ( $candidate instanceof \WC_Order && Order_Data::has_selectable_items( $candidate ) ) {
					$orders[] = $candidate;
				}
			}
		}

		return array(
			'intro'          => Settings::text( 'text_intro' ),
			'start_label'    => Settings::text( 'text_start_button' ),
			'account_orders' => $orders,
			'rate_limited'   => Verifier::is_rate_limited(),
		);
	}

	/**
	 * Args for the order view.
	 *
	 * @param \WC_Order $order Order.
	 * @return array
	 */
	private static function order_args( $order ) {
		$items      = Order_Data::get_items( $order );
		$eligible   = Order_Data::is_eligible_status( $order );
		$values     = self::$state['values'];
		$has_posted = isset( $values['items'] );

		$groups = array(
			'selectable' => array(),
			'excluded'   => array(),
			'done'       => array(),
		);
		foreach ( $items as $id => $item ) {
			if ( $has_posted ) {
				$item['checked'] = ! empty( $values['items'][ $id ]['selected'] );
				$item['qty']     = isset( $values['items'][ $id ]['qty'] ) ? max( 1, min( $item['remaining'], (int) $values['items'][ $id ]['qty'] ) ) : $item['remaining'];
			} else {
				$item['checked'] = $item['preselect'];
				$item['qty']     = $item['remaining'];
			}
			if ( $item['remaining'] < 1 ) {
				$groups['done'][ $id ] = $item;
			} elseif ( $item['excluded'] ) {
				$groups['excluded'][ $id ] = $item;
			} else {
				$groups['selectable'][ $id ] = $item;
			}
		}

		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

		// phpcs:ignore WordPress.Security.NonceVerification
		$src = isset( $_REQUEST['py_src'] ) && 'lookup' === $_REQUEST['py_src'] ? 'lookup' : '';

		return array(
			'order'          => $order,
			'order_key'      => $order->get_order_key(),
			'eligible'       => $eligible,
			'groups'         => $groups,
			'any_selectable' => (bool) array_filter( wp_list_pluck( $items, 'selectable' ) ),
			'strict'         => 'strict' === Settings::get( 'exclusion_mode' ),
			'reason_enabled' => Settings::enabled( 'reason_field' ),
			'confirm_label'  => Settings::text( 'text_confirm_button' ),
			'name'           => isset( $values['name'] ) ? $values['name'] : $name,
			'email'          => isset( $values['email'] ) ? $values['email'] : $order->get_billing_email(),
			'reason'         => isset( $values['reason'] ) ? $values['reason'] : '',
			'token'          => wp_generate_password( 32, false ),
			'src'            => $src,
			'store_email'    => get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ),
		);
	}

	/**
	 * Args for the success view.
	 *
	 * @param Submission $submission Submission.
	 * @return array
	 */
	private static function success_args( $submission ) {
		return array(
			'submission'          => $submission,
			'order'               => Verifier::get_order( $submission->order_id ),
			'return_instructions' => Settings::text( 'text_return_instructions' ),
		);
	}

	/**
	 * Render a template (overridable in theme/woocommerce/wpotkaz/).
	 *
	 * @param string $name Template file.
	 * @param array  $args Args.
	 * @return string
	 */
	public static function template( $name, $args ) {
		return wc_get_template_html( $name, $args, 'woocommerce/wpotkaz/', PY_OTKAZ_DIR . 'templates/' );
	}

	/* ---------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * Enqueue on the form page and in My Account.
	 */
	public static function enqueue() {
		if ( self::is_form_page() || ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
			self::enqueue_assets();
		}
	}

	/**
	 * Register + enqueue CSS/JS with the accent variables.
	 */
	public static function enqueue_assets() {
		if ( wp_style_is( 'wpotkaz', 'enqueued' ) ) {
			return;
		}
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$css    = file_exists( PY_OTKAZ_DIR . 'assets/css/wpotkaz' . $suffix . '.css' ) ? $suffix : '';
		$js     = file_exists( PY_OTKAZ_DIR . 'assets/js/wpotkaz' . $suffix . '.js' ) ? $suffix : '';

		wp_enqueue_style( 'wpotkaz', PY_OTKAZ_URL . 'assets/css/wpotkaz' . $css . '.css', array(), PY_OTKAZ_VERSION );
		wp_add_inline_style( 'wpotkaz', self::inline_css() );

		wp_enqueue_script(
			'wpotkaz',
			PY_OTKAZ_URL . 'assets/js/wpotkaz' . $js . '.js',
			array(),
			PY_OTKAZ_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'wpotkaz',
			'pyOtkaz',
			array(
				'sending'   => __( 'Sending…', 'wpotkaz' ),
				'selectOne' => __( 'Select at least one product to continue.', 'wpotkaz' ),
				/* translators: 1: number of products, 2: number of pieces */
				'selected'  => __( 'Selected: %1$s products, %2$s pcs.', 'wpotkaz' ),
			)
		);
	}

	/**
	 * CSS custom properties from the settings.
	 *
	 * @return string
	 */
	private static function inline_css() {
		$accent = sanitize_hex_color( (string) Settings::get( 'accent_color' ) );
		$accent = $accent ? $accent : '#111111';
		$radius = (int) Settings::get( 'radius' );
		$radius = in_array( $radius, array( 0, 4, 8, 999 ), true ) ? $radius : 8;

		return sprintf(
			'.py-otkaz,.py-otkaz-account{--py-otkaz-accent:%1$s;--py-otkaz-accent-contrast:%2$s;--py-otkaz-ink:%3$s;--py-otkaz-radius:%4$dpx;--py-otkaz-btn-radius:%5$dpx}',
			$accent,
			Util::text_on( $accent ),
			Util::ink_on_white( $accent ),
			min( 8, $radius ),
			$radius
		);
	}
}
