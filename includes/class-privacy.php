<?php
/**
 * GDPR: personal data exporter / eraser, privacy policy text, retention cleanup.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Privacy module.
 */
class Privacy {

	const PER_PAGE = 50;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy_content' ) );
		add_action( Install::CRON_HOOK, array( Submission::class, 'cleanup' ) );
	}

	/**
	 * Register exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['otkazpro'] = array(
			'exporter_friendly_name' => __( 'Withdrawals from contract', 'otkazpro' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['otkazpro'] = array(
			'eraser_friendly_name' => __( 'Withdrawals from contract', 'otkazpro' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export data for an email.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function export( $email, $page = 1 ) {
		$result = Submission::query(
			array(
				'email'    => strtolower( trim( $email ) ),
				'per_page' => self::PER_PAGE,
				'page'     => (int) $page,
			)
		);

		$data = array();
		foreach ( $result['items'] as $s ) {
			$data[] = array(
				'group_id'    => 'otkazpro',
				'group_label' => __( 'Withdrawals from contract', 'otkazpro' ),
				'item_id'     => 'py-otkaz-' . $s->id,
				'data'        => array(
					array(
						'name'  => __( 'Statement number', 'otkazpro' ),
						'value' => $s->number,
					),
					array(
						'name'  => __( 'Submitted on', 'otkazpro' ),
						'value' => Util::format_gmt( $s->created_gmt, 'datetime' ),
					),
					array(
						'name'  => __( 'Order', 'otkazpro' ),
						'value' => '#' . $s->order_number,
					),
					array(
						'name'  => __( 'Name', 'otkazpro' ),
						'value' => $s->name,
					),
					array(
						'name'  => __( 'Email', 'otkazpro' ),
						'value' => $s->email,
					),
					array(
						'name'  => __( 'Products', 'otkazpro' ),
						'value' => $s->items_summary(),
					),
					array(
						'name'  => __( 'Text of the statement', 'otkazpro' ),
						'value' => $s->statement,
					),
					array(
						'name'  => __( 'IP address', 'otkazpro' ),
						'value' => $s->ip_address,
					),
					array(
						'name'  => __( 'User agent', 'otkazpro' ),
						'value' => $s->user_agent,
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => ( (int) $page * self::PER_PAGE ) >= $result['total'],
		);
	}

	/**
	 * Erasure request: records are evidence of a legal act and are retained until the
	 * retention period ends (GDPR Art. 17(3)(b) and (e)). Filter to allow anonymisation.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function erase( $email, $page = 1 ) {
		global $wpdb;
		$email  = strtolower( trim( $email ) );
		$result = Submission::query(
			array(
				'email'    => $email,
				'per_page' => 0,
			)
		);
		$count = count( $result['items'] );

		/**
		 * Whether to anonymise withdrawal records on an erasure request (default: retain).
		 *
		 * @param bool   $anonymise Anonymise.
		 * @param string $email     Email.
		 */
		if ( $count && apply_filters( 'py_otkaz_privacy_erase', false, $email ) ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				Install::table(),
				array(
					'name'       => '[deleted]',
					'email'      => 'deleted-' . wp_generate_password( 8, false ) . '@invalid',
					'ip_address' => '',
					'user_agent' => '',
					'reason'     => '',
				),
				array( 'email' => $email )
			);
			return array(
				'items_removed'  => $count,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		return array(
			'items_removed'  => 0,
			'items_retained' => $count,
			'messages'       => $count ? array(
				sprintf(
					/* translators: %d: years */
					__( 'Withdrawal statements are retained as evidence of a legal act for the configured retention period (%d years) and then deleted automatically.', 'otkazpro' ),
					(int) Settings::get( 'retention_years' )
				),
			) : array(),
			'done'           => true,
		);
	}

	/**
	 * Suggested privacy policy text.
	 */
	public static function policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . __( 'When you withdraw from a contract through our online form, we process your name, email address, order number, the selected products, the date and time of submission, your IP address and browser user agent. We use this data to process your withdrawal, to send you a confirmation on a durable medium and as evidence of the submission (legal obligation and legitimate interest, Art. 6(1)(c) and (f) GDPR).', 'otkazpro' ) . '</p>';
		$content .= '<p>' . sprintf(
			/* translators: %d: years */
			__( 'Withdrawal records are kept for %d years and then deleted automatically.', 'otkazpro' ),
			(int) Settings::get( 'retention_years' )
		) . '</p>';
		wp_add_privacy_policy_content( 'OtkazPro', wp_kses_post( $content ) );
	}
}
