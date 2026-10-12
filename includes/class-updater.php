<?php
/**
 * Self-hosted updates from GitHub Releases (public or private repo, no external library).
 *
 * Configure in wp-config.php:
 *   define( 'PY_OTKAZ_UPDATE_REPO', 'owner/wpotkaz' );
 *   define( 'PY_OTKAZ_GITHUB_TOKEN', 'github_pat_…' ); // only for private repos (read-only "Contents" permission).
 * or set Updater::DEFAULT_REPO below once the repository exists.
 *
 * Release convention: tag "v1.2.3" (or "1.2.3") with an attached asset "wpotkaz.zip"
 * whose root folder is "wpotkaz/". Without the asset the source zipball is used.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * GitHub release updater (uses the "Update URI" header → update_plugins_github.com).
 */
class Updater {

	const DEFAULT_REPO = 'Project-Yordanov/wpotkaz';
	const SLUG         = 'wpotkaz';
	const CACHE_KEY    = 'py_otkaz_release';

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( ! self::repo() ) {
			return;
		}
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'download' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush' ), 10, 0 );
		add_action( 'load-update-core.php', array( __CLASS__, 'maybe_flush_on_force_check' ) );
	}

	/**
	 * owner/repo.
	 *
	 * @return string
	 */
	public static function repo() {
		$repo = defined( 'PY_OTKAZ_UPDATE_REPO' ) ? (string) PY_OTKAZ_UPDATE_REPO : self::DEFAULT_REPO;
		$repo = (string) apply_filters( 'py_otkaz_update_repo', $repo );
		return preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo ) ? $repo : '';
	}

	/**
	 * Token for private repos.
	 *
	 * @return string
	 */
	private static function token() {
		$token = defined( 'PY_OTKAZ_GITHUB_TOKEN' ) ? (string) PY_OTKAZ_GITHUB_TOKEN : '';
		return (string) apply_filters( 'py_otkaz_github_token', $token );
	}

	/**
	 * Request headers.
	 *
	 * @param string $accept Accept header.
	 * @return array
	 */
	private static function headers( $accept = 'application/vnd.github+json' ) {
		$headers = array(
			'Accept'               => $accept,
			'User-Agent'           => 'wpotkaz/' . PY_OTKAZ_VERSION . '; ' . home_url(),
			'X-GitHub-Api-Version' => '2022-11-28',
		);
		if ( self::token() ) {
			$headers['Authorization'] = 'Bearer ' . self::token();
		}
		return $headers;
	}

	/**
	 * Latest release (cached 3h; failures cached 30 min).
	 *
	 * Public repos: read the version from the github.com "releases/latest" redirect – this is
	 * not the GitHub API, so the 60 requests/hour per IP limit (shared hosting!) does not apply.
	 * The API is used only for private repos (token) or as a fallback.
	 *
	 * @param bool $force Skip cache.
	 * @return array|null version, package, url, body, published, requires, requires_php, tested.
	 */
	public static function release( $force = false ) {
		$cached = $force ? false : get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return empty( $cached['version'] ) ? null : $cached;
		}

		$data = self::token() ? null : self::release_from_redirect();
		if ( ! $data ) {
			$data = self::release_from_api();
		}

		set_site_transient( self::CACHE_KEY, $data ? $data : array( 'error' => true ), $data ? 3 * HOUR_IN_SECONDS : 30 * MINUTE_IN_SECONDS );
		return $data ? $data : null;
	}

	/**
	 * Version from https://github.com/{repo}/releases/latest → 302 …/releases/tag/vX.Y.Z.
	 *
	 * @return array|null
	 */
	private static function release_from_redirect() {
		$response = wp_remote_head(
			'https://github.com/' . self::repo() . '/releases/latest',
			array(
				'redirection' => 0,
				'timeout'     => 10,
				'headers'     => array( 'User-Agent' => 'wpotkaz/' . PY_OTKAZ_VERSION . '; ' . home_url() ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$location = (string) wp_remote_retrieve_header( $response, 'location' );
		if ( ! preg_match( '#/releases/tag/(v?([0-9]+(?:\.[0-9]+){1,3}))/?$#', $location, $m ) ) {
			return null;
		}
		return array(
			'version'      => $m[2],
			'package'      => 'https://github.com/' . self::repo() . '/releases/download/' . rawurlencode( $m[1] ) . '/' . self::SLUG . '.zip',
			'url'          => 'https://github.com/' . self::repo() . '/releases/tag/' . rawurlencode( $m[1] ),
			'body'         => '',
			'published'    => '',
			'requires'     => '6.4',
			'requires_php' => '7.4',
			'tested'       => '',
		);
	}

	/**
	 * Latest release via the GitHub API (private repos / fallback).
	 *
	 * @return array|null
	 */
	private static function release_from_api() {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::repo() . '/releases/latest',
			array(
				'headers' => self::headers(),
				'timeout' => 10,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $json ) || empty( $json['tag_name'] ) ) {
			return null;
		}
		$package = isset( $json['zipball_url'] ) ? $json['zipball_url'] : '';
		foreach ( isset( $json['assets'] ) ? (array) $json['assets'] : array() as $asset ) {
			if ( isset( $asset['name'] ) && self::SLUG . '.zip' === $asset['name'] ) {
				// Private repos must use the API asset URL with auth; public can use the browser URL.
				$package = self::token() ? $asset['url'] : $asset['browser_download_url'];
				break;
			}
		}
		$body = isset( $json['body'] ) ? (string) $json['body'] : '';
		return array(
			'version'      => ltrim( (string) $json['tag_name'], 'vV' ),
			'package'      => $package,
			'url'          => isset( $json['html_url'] ) ? $json['html_url'] : 'https://github.com/' . self::repo(),
			'body'         => $body,
			'published'    => isset( $json['published_at'] ) ? $json['published_at'] : '',
			'requires'     => self::meta_from_body( $body, 'Requires at least', '6.4' ),
			'requires_php' => self::meta_from_body( $body, 'Requires PHP', '7.4' ),
			'tested'       => self::meta_from_body( $body, 'Tested up to', '' ),
		);
	}

	/**
	 * "Check again" on Dashboard → Updates also refreshes our release cache.
	 */
	public static function maybe_flush_on_force_check() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) {
			self::flush();
		}
	}

	/**
	 * Read "Key: value" lines from the release notes.
	 *
	 * @param string $body    Body.
	 * @param string $key     Key.
	 * @param string $default Default.
	 * @return string
	 */
	private static function meta_from_body( $body, $key, $default ) {
		if ( preg_match( '/^\s*' . preg_quote( $key, '/' ) . '\s*:\s*([0-9.]+)/mi', $body, $m ) ) {
			return $m[1];
		}
		return $default;
	}

	/**
	 * update_plugins_github.com – WordPress compares versions itself.
	 *
	 * @param array|false $update      Update.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin file.
	 * @param string[]    $locales     Locales.
	 * @return array|false
	 */
	public static function check( $update, $plugin_data, $plugin_file, $locales ) {
		if ( PY_OTKAZ_BASENAME !== $plugin_file ) {
			return $update;
		}
		$release = self::release();
		if ( ! $release || ! $release['package'] ) {
			return $update;
		}
		return array(
			'slug'         => self::SLUG,
			'plugin'       => PY_OTKAZ_BASENAME,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires'     => $release['requires'],
			'requires_php' => $release['requires_php'],
			'tested'       => $release['tested'],
		);
	}

	/**
	 * "View details" modal.
	 *
	 * @param false|object|array $result Result.
	 * @param string             $action Action.
	 * @param object             $args   Args.
	 * @return false|object|array
	 */
	public static function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}
		$release = self::release();
		if ( ! $release ) {
			return $result;
		}
		return (object) array(
			'name'          => 'WPOtkaz – Бутон за отказ от договор',
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => 'Project Yordanov',
			'homepage'      => $release['url'],
			'requires'      => $release['requires'],
			'requires_php'  => $release['requires_php'],
			'tested'        => $release['tested'],
			'last_updated'  => $release['published'],
			'download_link' => $release['package'],
			'sections'      => array(
				'changelog' => '' !== $release['body'] ? wpautop( esc_html( $release['body'] ) ) : '<p><a href="' . esc_url( $release['url'] ) . '" target="_blank" rel="noopener">GitHub release ' . esc_html( $release['version'] ) . '</a></p>',
			),
		);
	}

	/**
	 * Download GitHub API URLs ourselves: auth on api.github.com, no auth on the redirect target.
	 *
	 * @param bool|\WP_Error|string $reply      Reply.
	 * @param string                $package    Package URL.
	 * @param \WP_Upgrader          $upgrader   Upgrader.
	 * @param array                 $hook_extra Extra.
	 * @return bool|\WP_Error|string
	 */
	public static function download( $reply, $package, $upgrader = null, $hook_extra = array() ) {
		if ( false !== $reply || 0 !== stripos( (string) $package, 'https://api.github.com/repos/' . self::repo() . '/' ) ) {
			return $reply;
		}

		$response = wp_remote_get(
			$package,
			array(
				'headers'     => self::headers( 'application/octet-stream' ),
				'redirection' => 0,
				'timeout'     => 30,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( ! $location ) {
				return new \WP_Error( 'py_otkaz_update', 'GitHub redirect without location.' );
			}
			return download_url( $location, 300 );
		}

		if ( 200 === $code ) {
			$tmp = wp_tempnam( self::SLUG . '.zip' );
			if ( ! $tmp || false === file_put_contents( $tmp, wp_remote_retrieve_body( $response ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				return new \WP_Error( 'py_otkaz_update', 'Could not write the update package.' );
			}
			return $tmp;
		}

		return new \WP_Error( 'py_otkaz_update', sprintf( 'GitHub download failed (HTTP %d). Check PY_OTKAZ_GITHUB_TOKEN.', $code ) );
	}

	/**
	 * Install the update into the folder the plugin already lives in (e.g. "otkazpro" or
	 * "otkazpro-main" when installed from a GitHub source zip), so it stays active.
	 *
	 * @param string       $source        Extracted source.
	 * @param string       $remote_source Remote source dir.
	 * @param \WP_Upgrader $upgrader      Upgrader.
	 * @param array        $hook_extra    Extra.
	 * @return string|\WP_Error
	 */
	public static function fix_folder( $source, $remote_source, $upgrader = null, $hook_extra = array() ) {
		global $wp_filesystem;
		if ( empty( $hook_extra['plugin'] ) || PY_OTKAZ_BASENAME !== $hook_extra['plugin'] ) {
			return $source;
		}
		$folder = dirname( PY_OTKAZ_BASENAME );
		$wanted = trailingslashit( $remote_source ) . ( '.' !== $folder && '' !== $folder ? $folder : self::SLUG ) . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $wanted ) ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( $source, $wanted, true ) ) {
			return $wanted;
		}
		return new \WP_Error( 'py_otkaz_update', 'Could not rename the update folder.' );
	}

	/**
	 * Clear cache after updates.
	 */
	public static function flush() {
		delete_site_transient( self::CACHE_KEY );
	}
}
