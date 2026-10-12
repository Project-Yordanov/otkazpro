<?php
/**
 * Self-test: is the withdrawal form page served from a cache?
 *
 * Every render of the form carries a unique marker. The check loads the page twice
 * as an anonymous visitor; the same marker twice means the HTML came from a cache.
 * The same is done for a URL with a query string (a cache that stores those could
 * show one customer's order page to another visitor).
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Cache self-check, admin notice and Site Health test.
 */
class Cache_Check {

	const OPTION     = 'py_otkaz_cache_check';
	const ONCE_HOOK  = 'py_otkaz_cache_check_once';
	const MARKER_KEY = 'wpotkaz-render:';

	/**
	 * Marker of the current request (one per page view).
	 *
	 * @var string
	 */
	private static $marker = '';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( Install::CRON_HOOK, array( __CLASS__, 'run' ), 20 );
		add_action( self::ONCE_HOOK, array( __CLASS__, 'run' ) );
		add_action( 'admin_post_py_otkaz_cache_check', array( __CLASS__, 'handle_button' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'register_site_health' ) );
	}

	/**
	 * HTML comment with a unique marker (added to every form render).
	 *
	 * @return string
	 */
	public static function marker() {
		if ( '' === self::$marker ) {
			self::$marker = wp_generate_password( 20, false );
		}
		// Comment + attribute: HTML minifiers may strip comments but keep attributes.
		return '<!-- ' . self::MARKER_KEY . self::$marker . ' --><span hidden data-py-render="' . esc_attr( self::$marker ) . '"></span>';
	}

	/**
	 * Schedule a check in about a minute (after activation / update / settings save).
	 */
	public static function schedule_soon() {
		if ( ! wp_next_scheduled( self::ONCE_HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::ONCE_HOOK );
		}
	}

	/**
	 * Last stored result.
	 *
	 * @return array|null
	 */
	public static function result() {
		$r = get_option( self::OPTION );
		return is_array( $r ) ? $r : null;
	}

	/**
	 * Run the check and store the result.
	 *
	 * @return array
	 */
	public static function run() {
		$page_id = (int) Settings::get( 'page_id' );
		$url     = $page_id ? get_permalink( $page_id ) : '';

		if ( ! $url || 'publish' !== get_post_status( $page_id ) ) {
			$result = array(
				'time'     => time(),
				'url'      => '',
				'bare'     => 'error',
				'query'    => 'error',
				'detected' => array(),
				'error'    => __( 'The form page is missing or not published.', 'wpotkaz' ),
			);
			update_option( self::OPTION, $result, false );
			return $result;
		}

		$bare  = self::probe( $url );
		$query = self::probe( add_query_arg( 'py_done', 'cachecheck', $url ) );

		$detected = array_merge( $bare['detected'], $query['detected'] );
		if ( 'cached' === $bare['status'] || 'cached' === $query['status'] ) {
			$detected = array_merge( $detected, self::active_cache_plugins() );
		}

		$result = array(
			'time'     => time(),
			'url'      => $url,
			'bare'     => $bare['status'],
			'query'    => $query['status'],
			'detected' => array_values( array_unique( $detected ) ),
			'error'    => $bare['error'] ? $bare['error'] : $query['error'],
		);
		update_option( self::OPTION, $result, false );

		/**
		 * Fires after the cache self-check.
		 *
		 * @param array $result Result.
		 */
		do_action( 'py_otkaz_cache_checked', $result );
		return $result;
	}

	/**
	 * Load a URL twice as an anonymous visitor and compare the render markers.
	 *
	 * @param string $url URL.
	 * @return array status ok|cached|error, detected[], error.
	 */
	private static function probe( $url ) {
		$markers  = array();
		$detected = array();
		for ( $i = 0; $i < 2; $i++ ) {
			$response = self::fetch( $url );
			if ( is_wp_error( $response ) ) {
				return array(
					'status'   => 'error',
					'detected' => $detected,
					'error'    => $response->get_error_message(),
				);
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = (string) wp_remote_retrieve_body( $response );
			$detected = array_merge( $detected, self::detect_from_response( $response, $body ) );
			if ( 200 !== $code ) {
				return array(
					'status'   => 'error',
					/* translators: %d: HTTP status code */
					'error'    => sprintf( __( 'The form page returned HTTP %d to the check (firewall or bot protection?).', 'wpotkaz' ), $code ),
					'detected' => $detected,
				);
			}
			if ( preg_match( '/data-py-render="([A-Za-z0-9]+)"/', $body, $m ) || preg_match( '/' . preg_quote( self::MARKER_KEY, '/' ) . '([A-Za-z0-9]+)/', $body, $m ) ) {
				$markers[] = $m[1];
				continue;
			}
			if ( false !== strpos( $body, 'data-py-otkaz' ) ) {
				// The form is there but without a render marker: an old cached copy
				// (made before the marker existed) – i.e. the page is served from cache.
				return array(
					'status'   => 'cached',
					'detected' => array_values( array_unique( $detected ) ),
					'error'    => '',
				);
			}
			return array(
				'status'   => 'error',
				'error'    => __( 'The form was not found on the page (is the shortcode on the selected page?).', 'wpotkaz' ),
				'detected' => $detected,
			);
		}
		return array(
			'status'   => $markers[0] === $markers[1] ? 'cached' : 'ok',
			'detected' => $markers[0] === $markers[1] ? array_values( array_unique( $detected ) ) : array(),
			'error'    => '',
		);
	}

	/**
	 * GET as an anonymous visitor, one retry on transport errors.
	 *
	 * @param string $url URL.
	 * @return array|\WP_Error
	 */
	private static function fetch( $url ) {
		$args = array(
			'timeout'     => 20,
			'redirection' => 3,
			'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			'headers'     => array(
				// A regular visitor – some caches skip bots.
				'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
				'Accept'          => 'text/html,application/xhtml+xml',
				'Accept-Language' => 'bg-BG,bg;q=0.9,en;q=0.8',
			),
		);
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			sleep( 1 );
			$response = wp_remote_get( $url, $args );
		}
		return $response;
	}

	/**
	 * Cache layer names recognisable from response headers / HTML.
	 *
	 * @param array|\WP_HTTP_Response $response Response.
	 * @param string                  $body     Body.
	 * @return string[]
	 */
	private static function detect_from_response( $response, $body ) {
		$found = array();
		$h     = static function ( $name ) use ( $response ) {
			return strtolower( (string) wp_remote_retrieve_header( $response, $name ) );
		};
		if ( '' !== $h( 'x-flying-press-cache' ) || false !== stripos( $body, 'Powered by FlyingPress' ) ) {
			$found[] = 'FlyingPress';
		}
		if ( '' !== $h( 'x-litespeed-cache' ) ) {
			$found[] = 'LiteSpeed Cache';
		}
		if ( false !== stripos( $body, 'This website is like a Rocket' ) ) {
			$found[] = 'WP Rocket';
		}
		if ( false !== stripos( $body, 'Performance optimized by W3 Total Cache' ) && false === stripos( $body, 'DONOTCACHEPAGE' ) ) {
			$found[] = 'W3 Total Cache';
		}
		if ( false !== stripos( $body, 'WP Fastest Cache' ) ) {
			$found[] = 'WP Fastest Cache';
		}
		if ( in_array( $h( 'cf-cache-status' ), array( 'hit', 'stale', 'revalidated', 'updating' ), true ) ) {
			$found[] = 'Cloudflare';
		}
		if ( '' !== $h( 'x-kinsta-cache' ) ) {
			$found[] = 'Kinsta (server cache)';
		}
		if ( '' !== $h( 'x-sg-cache' ) || ( '' !== $h( 'x-proxy-cache' ) && false !== strpos( $h( 'x-proxy-cache' ), 'hit' ) ) ) {
			$found[] = 'Hosting proxy cache (SiteGround / Nginx)';
		}
		if ( '' !== $h( 'x-varnish' ) ) {
			$found[] = 'Varnish';
		}
		if ( '' !== $h( 'x-sucuri-cache' ) ) {
			$found[] = 'Sucuri';
		}
		return $found;
	}

	/**
	 * Active page-cache plugins (to name them in the instructions).
	 *
	 * @return string[]
	 */
	private static function active_cache_plugins() {
		$map = array(
			'WP Rocket'                  => defined( 'WP_ROCKET_VERSION' ),
			'LiteSpeed Cache'            => defined( 'LSCWP_V' ),
			'W3 Total Cache'             => defined( 'W3TC' ),
			'WP Super Cache'             => defined( 'WPCACHEHOME' ),
			'WP Fastest Cache'           => class_exists( 'WpFastestCache' ),
			'FlyingPress'                => defined( 'FLYING_PRESS_VERSION' ) || class_exists( 'FlyingPress\\Caching' ),
			'SiteGround Speed Optimizer' => class_exists( 'SiteGround_Optimizer\\Loader\\Loader' ),
			'Breeze'                     => defined( 'BREEZE_VERSION' ),
			'NitroPack'                  => defined( 'NITROPACK_VERSION' ),
			'Hummingbird'                => defined( 'WPHB_VERSION' ),
			'Cache Enabler'              => class_exists( 'Cache_Enabler' ),
		);
		return array_keys( array_filter( $map ) );
	}

	/**
	 * Overall status: good | recommended | critical | unknown.
	 *
	 * @param array|null $r Result.
	 * @return string
	 */
	public static function level( $r ) {
		if ( ! $r ) {
			return 'unknown';
		}
		if ( 'cached' === $r['query'] ) {
			return 'critical';
		}
		if ( 'cached' === $r['bare'] ) {
			return 'recommended';
		}
		if ( 'error' === $r['bare'] || 'error' === $r['query'] ) {
			return 'unknown';
		}
		return 'good';
	}

	/**
	 * Human-readable summary + instructions (HTML).
	 *
	 * @param array|null $r Result.
	 * @return string
	 */
	public static function describe( $r ) {
		$level = self::level( $r );
		if ( ! $r ) {
			return esc_html__( 'The cache check has not run yet.', 'wpotkaz' );
		}
		$names = $r['detected'] ? implode( ', ', $r['detected'] ) : __( 'a page cache (plugin, hosting or CDN)', 'wpotkaz' );
		$path  = $r['url'] ? (string) wp_parse_url( $r['url'], PHP_URL_PATH ) : '/otkaz-ot-dogovor/';
		if ( $r['url'] && ( '/' === $path || '' === $path ) ) {
			$path = $r['url']; // Plain permalinks (?page_id=…): show the full address.
		}
		$fix   = sprintf(
			/* translators: 1: cache names, 2: page path */
			__( 'Exclude %2$s and everything after it from caching in %1$s (for Cloudflare: a Cache Rule “Bypass cache” for this path), purge the cache and run the check again.', 'wpotkaz' ),
			$names,
			$path
		);
		switch ( $level ) {
			case 'critical':
				return '<strong>' . esc_html__( 'Pages with a query string are served from cache: a customer’s order page could be shown to other visitors.', 'wpotkaz' ) . '</strong> ' . esc_html( $fix );
			case 'recommended':
				return esc_html__( 'The form page is served from cache. The form keeps working, but excluding it is recommended.', 'wpotkaz' ) . ' ' . esc_html( $fix );
			case 'good':
				return esc_html__( 'The form page is not cached.', 'wpotkaz' );
			default:
				return esc_html__( 'The check could not be completed:', 'wpotkaz' ) . ' ' . esc_html( $r['error'] );
		}
	}

	/**
	 * "Check cache" button.
	 */
	public static function handle_button() {
		check_admin_referer( 'py_otkaz_cache_check' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wpotkaz' ) );
		}
		self::run();
		wp_safe_redirect( add_query_arg( 'py_otkaz_msg', 'cache_checked', Settings::url() ) );
		exit;
	}

	/**
	 * Button URL.
	 *
	 * @return string
	 */
	public static function button_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=py_otkaz_cache_check' ), 'py_otkaz_cache_check' );
	}

	/**
	 * Register the Site Health test.
	 *
	 * @param array $tests Tests.
	 * @return array
	 */
	public static function register_site_health( $tests ) {
		$tests['direct']['wpotkaz_cache'] = array(
			'label' => __( 'WPOtkaz: withdrawal form is not cached', 'wpotkaz' ),
			'test'  => array( __CLASS__, 'site_health_result' ),
		);
		return $tests;
	}

	/**
	 * Site Health result (from the stored check – no live requests here).
	 *
	 * @return array
	 */
	public static function site_health_result() {
		$r      = self::result();
		$level  = self::level( $r );
		$status = in_array( $level, array( 'good', 'recommended', 'critical' ), true ) ? $level : 'recommended';
		$labels = array(
			'good'        => __( 'The withdrawal form page is not cached', 'wpotkaz' ),
			'recommended' => __( 'The withdrawal form page is cached or could not be checked', 'wpotkaz' ),
			'critical'    => __( 'Customer order pages of the withdrawal form are cached', 'wpotkaz' ),
		);
		return array(
			'label'       => $labels[ $status ],
			'status'      => $status,
			'badge'       => array(
				'label' => 'WPOtkaz',
				'color' => 'critical' === $status ? 'red' : ( 'good' === $status ? 'blue' : 'orange' ),
			),
			'description' => '<p>' . self::describe( $r ) . '</p>',
			'actions'     => sprintf( '<p><a href="%s">%s</a></p>', esc_url( Settings::url() ), esc_html__( 'Open WPOtkaz settings', 'wpotkaz' ) ),
			'test'        => 'wpotkaz_cache',
		);
	}
}
