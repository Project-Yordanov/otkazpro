<?php
/**
 * Activation, database schema and upgrades.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class Install {

	const DB_VERSION        = '1.0.0';
	const DB_VERSION_OPTION = 'py_otkaz_db_version';
	const CRON_HOOK         = 'py_otkaz_daily_cleanup';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ), 20 );
	}

	/**
	 * Activation hook.
	 */
	public static function activate() {
		// Translations are needed for the default page title.
		load_plugin_textdomain( 'otkazpro', false, dirname( PY_OTKAZ_BASENAME ) . '/languages' );

		self::create_tables();
		self::maybe_create_page();
		self::ensure_cron();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		set_transient( 'py_otkaz_activated', 1, MINUTE_IN_SECONDS * 5 );
	}

	/**
	 * Deactivation hook.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Run schema upgrades after plugin updates (activation hook does not run on update).
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::create_tables();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		}
	}

	/**
	 * Make sure the retention cleanup is scheduled.
	 */
	public static function ensure_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Submissions table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'py_otkaz_submissions';
	}

	/**
	 * Create / update the submissions table.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  number varchar(64) NOT NULL,
  order_id bigint(20) unsigned NOT NULL,
  order_number varchar(64) NOT NULL DEFAULT '',
  customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
  name varchar(255) NOT NULL DEFAULT '',
  email varchar(200) NOT NULL DEFAULT '',
  items longtext NOT NULL,
  statement longtext NOT NULL,
  reason text NULL,
  has_excluded tinyint(1) NOT NULL DEFAULT 0,
  within_deadline tinyint(1) NULL DEFAULT NULL,
  deadline_gmt datetime NULL DEFAULT NULL,
  source varchar(20) NOT NULL DEFAULT '',
  language varchar(20) NOT NULL DEFAULT '',
  ip_address varchar(100) NOT NULL DEFAULT '',
  user_agent varchar(500) NOT NULL DEFAULT '',
  email_status varchar(20) NOT NULL DEFAULT 'pending',
  email_sent_gmt datetime NULL DEFAULT NULL,
  email_attempts smallint(5) unsigned NOT NULL DEFAULT 0,
  admin_email_status varchar(20) NOT NULL DEFAULT 'pending',
  created_local datetime NOT NULL,
  created_gmt datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY number (number),
  KEY order_id (order_id),
  KEY email (email(100)),
  KEY created_gmt (created_gmt),
  KEY email_status (email_status)
) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Create the "Withdrawal from contract" page with the shortcode on first activation.
	 */
	public static function maybe_create_page() {
		$page_id = (int) get_option( Settings::PREFIX . 'page_id' );
		if ( $page_id && 'trash' !== get_post_status( $page_id ) && get_post( $page_id ) ) {
			return;
		}

		// Reuse an existing page (e.g. after uninstall + reinstall) instead of creating a duplicate.
		$existing = self::find_existing_page();
		if ( $existing ) {
			update_option( Settings::PREFIX . 'page_id', $existing );
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => __( 'Withdrawal from contract', 'otkazpro' ),
				'post_name'      => 'otkaz-ot-dogovor',
				'post_content'   => '<!-- wp:shortcode -->[' . Form::SHORTCODE . ']<!-- /wp:shortcode -->',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( Settings::PREFIX . 'page_id', (int) $page_id );
		}
	}

	/**
	 * The oldest non-trashed page that already contains the form shortcode (new or legacy).
	 *
	 * @return int Page ID or 0.
	 */
	private static function find_existing_page() {
		$found = array();
		foreach ( array( Form::SHORTCODE, Form::LEGACY_SHORTCODE ) as $shortcode ) {
			$ids = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
					's'              => '[' . $shortcode . ']',
					'fields'         => 'ids',
					'posts_per_page' => 5,
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);
			foreach ( $ids as $id ) {
				if ( has_shortcode( (string) get_post_field( 'post_content', $id ), $shortcode ) ) {
					$found[] = (int) $id;
				}
			}
		}
		return $found ? min( $found ) : 0;
	}
}
