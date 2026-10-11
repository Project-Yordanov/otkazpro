<?php
/**
 * Settings tab: WooCommerce → Settings → Withdrawal.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Settings registry + WooCommerce settings tab.
 */
class Settings {

	const TAB    = 'wpotkaz';
	const PREFIX = 'py_otkaz_';

	/**
	 * Text settings that must never be empty (fallback to default).
	 */
	const REQUIRED_TEXTS = array( 'text_start_button', 'text_confirm_button', 'text_statement' );

	/**
	 * Cached defaults (built lazily, after translations are loaded).
	 *
	 * @var array|null
	 */
	private static $defaults = null;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_tab' ), 50 );
		add_action( 'woocommerce_settings_' . self::TAB, array( __CLASS__, 'output' ) );
		add_action( 'woocommerce_update_options_' . self::TAB, array( __CLASS__, 'save' ) );
		add_action( 'woocommerce_admin_field_py_otkaz_info', array( __CLASS__, 'field_info' ) );
		add_action( 'switch_locale', array( __CLASS__, 'reset_defaults' ) );
		add_action( 'restore_previous_locale', array( __CLASS__, 'reset_defaults' ) );
	}

	/**
	 * Non-translatable defaults (safe to read before `init`).
	 *
	 * @return array
	 */
	public static function base_defaults() {
		return array(
			'page_id'                  => 0,
			'statuses'                 => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
			'deadline_days'            => 14,
			'custom_status'            => 'no',
			'text_return_instructions' => '',
			'reason_field'             => 'no',
			'notify_emails'            => get_option( 'admin_email' ),
			'email_link_emails'        => array( 'customer_processing_order', 'customer_completed_order' ),
			'exclusion_mode'           => 'soft',
			'exclusion_meta_keys'      => '',
			'exclusion_label'          => 'no',
			'accent_color'             => '#111111',
			'radius'                   => '8',
			'retention_years'          => 5,
			'delete_data'              => 'no',
		);
	}

	/**
	 * All defaults incl. translated texts (call after `init`).
	 *
	 * @return array
	 */
	public static function defaults() {
		if ( null === self::$defaults ) {
			self::$defaults = array_merge(
				self::base_defaults(),
				array(
					'text_intro'          => __( 'You can withdraw from a distance contract within 14 days without giving any reason. Find your order, choose the products and confirm – you will receive a confirmation by email straight away.', 'wpotkaz' ),
					'text_start_button'   => __( 'Withdraw from the contract here', 'wpotkaz' ),
					'text_confirm_button' => __( 'Confirm withdrawal', 'wpotkaz' ),
					'text_statement'      => __( 'I hereby give notice that I withdraw from the contract for the following goods: {items}, order No. {order_number}, submitted on {date} at {time}.', 'wpotkaz' ),
				)
			);
		}
		return self::$defaults;
	}

	/**
	 * Drop cached (translated) defaults when the locale changes.
	 */
	public static function reset_defaults() {
		self::$defaults = null;
	}

	/**
	 * Default for one key; translated texts are built only when needed.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	private static function default_for( $key ) {
		$base = self::base_defaults();
		if ( array_key_exists( $key, $base ) ) {
			return $base[ $key ];
		}
		$all = self::defaults();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Get a raw setting value (with default).
	 *
	 * @param string $key Setting key without prefix.
	 * @return mixed
	 */
	public static function get( $key ) {
		$value = get_option( self::PREFIX . $key, null );

		if ( null === $value ) {
			$value = self::default_for( $key );
		} elseif ( in_array( $key, self::REQUIRED_TEXTS, true ) && '' === trim( (string) $value ) ) {
			$value = self::default_for( $key );
		}

		return apply_filters( 'py_otkaz_setting_' . $key, $value );
	}

	/**
	 * Whether a stored option exists (i.e. merchant saved it).
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public static function is_stored( $key ) {
		return null !== get_option( self::PREFIX . $key, null );
	}

	/**
	 * Get a yes/no setting as bool.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public static function enabled( $key ) {
		return 'yes' === self::get( $key );
	}

	/**
	 * Get a translatable text setting, translated via WPML/Polylang when stored.
	 *
	 * @param string      $key  Key.
	 * @param string|null $lang Language code or null for current.
	 * @return string
	 */
	public static function text( $key, $lang = null ) {
		$value = (string) self::get( $key );
		if ( self::is_stored( $key ) && '' !== $value ) {
			$value = I18n::translate( $key, $value, $lang );
		}
		return $value;
	}

	/**
	 * Settings tab URL.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=wc-settings&tab=' . self::TAB );
	}

	/**
	 * Register the tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function add_tab( $tabs ) {
		$tabs[ self::TAB ] = __( 'Withdrawal', 'wpotkaz' );
		return $tabs;
	}

	/**
	 * Render the tab.
	 */
	public static function output() {
		// Defaults must be in the site language, not the admin user's profile language.
		$switched = switch_to_locale( get_locale() );
		woocommerce_admin_fields( self::fields() );
		if ( $switched ) {
			restore_previous_locale();
		}
	}

	/**
	 * Save the tab.
	 */
	public static function save() {
		$switched = switch_to_locale( get_locale() );
		woocommerce_update_options( self::fields() );
		if ( $switched ) {
			restore_previous_locale();
		}

		$color = sanitize_hex_color( (string) get_option( self::PREFIX . 'accent_color' ) );
		update_option( self::PREFIX . 'accent_color', $color ? $color : '#111111' );

		$days = absint( get_option( self::PREFIX . 'deadline_days' ) );
		update_option( self::PREFIX . 'deadline_days', $days > 0 ? $days : 14 );

		$emails = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) get_option( self::PREFIX . 'notify_emails' ) ) ) ), 'is_email' );
		update_option( self::PREFIX . 'notify_emails', implode( ', ', $emails ) );

		self::$defaults = null;
		I18n::register_strings();

		if ( class_exists( Order_Status::class ) ) {
			// Custom status visibility may have changed.
			wp_cache_delete( 'wc_order_statuses', 'woocommerce' );
		}
	}

	/**
	 * Read-only info row with the form page URL.
	 *
	 * @param array $value Field definition.
	 */
	public static function field_info( $value ) {
		$page_id = (int) self::get( 'page_id' );
		$url     = $page_id ? get_permalink( $page_id ) : '';
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html( $value['title'] ); ?></th>
			<td class="forminp">
				<?php if ( $url ) : ?>
					<input type="text" readonly class="regular-input" style="width:100%;max-width:520px" value="<?php echo esc_attr( $url ); ?>" onfocus="this.select()" />
					<p class="description"><?php esc_html_e( 'Add this link to the footer / menu with the text “Withdraw from the contract here”. Customers can use it without logging in.', 'wpotkaz' ); ?></p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Choose the page that contains the [wpotkaz] shortcode.', 'wpotkaz' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Field definitions.
	 *
	 * @return array
	 */
	public static function fields() {
		$d        = self::defaults();
		$statuses = wc_get_order_statuses();
		unset( $statuses[ 'wc-' . Order_Status::STATUS ] );

		$order_emails = array(
			'customer_processing_order' => __( 'Processing order', 'wpotkaz' ),
			'customer_completed_order'  => __( 'Completed order', 'wpotkaz' ),
			'customer_on_hold_order'    => __( 'Order on-hold', 'wpotkaz' ),
			'customer_invoice'          => __( 'Order details / invoice', 'wpotkaz' ),
		);

		$fields = array(
			// General.
			array(
				'title' => __( 'General', 'wpotkaz' ),
				'type'  => 'title',
				'desc'  => __( 'Online withdrawal function under Art. 52a of the Consumer Protection Act. The function stays available for eligible orders at all times – the 14-day deadline is shown only to you in the admin.', 'wpotkaz' ),
				'id'    => 'py_otkaz_general',
			),
			array(
				'title'    => __( 'Form page', 'wpotkaz' ),
				'id'       => self::PREFIX . 'page_id',
				'type'     => 'single_select_page',
				'default'  => '',
				'class'    => 'wc-enhanced-select-nostd',
				'css'      => 'min-width:300px;',
				'desc_tip' => __( 'Page containing the [wpotkaz] shortcode. Created automatically on activation.', 'wpotkaz' ),
				'autoload' => true,
			),
			array(
				'title'     => __( 'Form URL', 'wpotkaz' ),
				'type'      => 'py_otkaz_info',
				'id'        => 'py_otkaz_info_url',
				'is_option' => false,
			),
			array(
				'title'    => __( 'Eligible order statuses', 'wpotkaz' ),
				'id'       => self::PREFIX . 'statuses',
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width:300px;',
				'options'  => $statuses,
				'default'  => $d['statuses'],
				'desc_tip' => __( 'Orders in these statuses can be withdrawn from. Do not add cancelled, refunded, failed or pending payment.', 'wpotkaz' ),
			),
			array(
				'title'             => __( 'Withdrawal period (days)', 'wpotkaz' ),
				'id'                => self::PREFIX . 'deadline_days',
				'type'              => 'number',
				'default'           => $d['deadline_days'],
				'css'               => 'width:80px;',
				'custom_attributes' => array(
					'min'  => 1,
					'step' => 1,
				),
				'desc'              => __( 'Used only for the admin “in time / late” indicator (counted from the completed date). The form never closes automatically.', 'wpotkaz' ),
			),
			array(
				'title'   => __( '“Withdrawal requested” status', 'wpotkaz' ),
				'id'      => self::PREFIX . 'custom_status',
				'type'    => 'checkbox',
				'default' => $d['custom_status'],
				'desc'    => __( 'Move the order to a custom “Withdrawal requested” status after each submission.', 'wpotkaz' ),
				'desc_tip' => __( 'Off by default: changing the status may interfere with invoicing, ERP or courier integrations that depend on order status.', 'wpotkaz' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'py_otkaz_general',
			),

			// Texts.
			array(
				'title' => __( 'Texts', 'wpotkaz' ),
				'type'  => 'title',
				'desc'  => __( 'With WPML / Polylang these texts can be translated in String Translation (domain “wpotkaz”). Keep the legally required wording of the two buttons.', 'wpotkaz' ),
				'id'    => 'py_otkaz_texts',
			),
			array(
				'title'   => __( 'Intro text', 'wpotkaz' ),
				'id'      => self::PREFIX . 'text_intro',
				'type'    => 'textarea',
				'css'     => 'min-width:420px;height:70px;',
				'default' => $d['text_intro'],
			),
			array(
				'title'   => __( 'Start button', 'wpotkaz' ),
				'id'      => self::PREFIX . 'text_start_button',
				'type'    => 'text',
				'css'     => 'min-width:420px;',
				'default' => $d['text_start_button'],
			),
			array(
				'title'   => __( 'Confirmation button', 'wpotkaz' ),
				'id'      => self::PREFIX . 'text_confirm_button',
				'type'    => 'text',
				'css'     => 'min-width:420px;',
				'default' => $d['text_confirm_button'],
			),
			array(
				'title'   => __( 'Statement template', 'wpotkaz' ),
				'id'      => self::PREFIX . 'text_statement',
				'type'    => 'textarea',
				'css'     => 'min-width:420px;height:80px;',
				'default' => $d['text_statement'],
				'desc'    => __( 'Placeholders: {items}, {order_number}, {order_date}, {date}, {time}, {name}, {email}, {site_title}.', 'wpotkaz' ),
			),
			array(
				'title'    => __( 'Return instructions', 'wpotkaz' ),
				'id'       => self::PREFIX . 'text_return_instructions',
				'type'     => 'textarea',
				'css'      => 'min-width:420px;height:100px;',
				'default'  => '',
				'desc'     => __( 'Where and how to return the goods. Shown on the success screen and in the confirmation email. Basic HTML allowed.', 'wpotkaz' ),
			),
			array(
				'title'    => __( 'Optional “Reason” field', 'wpotkaz' ),
				'id'       => self::PREFIX . 'reason_field',
				'type'     => 'checkbox',
				'default'  => $d['reason_field'],
				'desc'     => __( 'Show an optional free-text reason field (never required).', 'wpotkaz' ),
				'desc_tip' => __( 'Off by default: the law does not provide for asking a reason. Enable only after legal review.', 'wpotkaz' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'py_otkaz_texts',
			),

			// Emails.
			array(
				'title' => __( 'Emails', 'wpotkaz' ),
				'type'  => 'title',
				'desc'  => sprintf(
					/* translators: %s: link to WooCommerce emails settings */
					__( 'Subjects, headings and design of the two emails are edited in %s.', 'wpotkaz' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) ) . '">' . esc_html__( 'WooCommerce → Settings → Emails', 'wpotkaz' ) . '</a>'
				),
				'id'    => 'py_otkaz_emails',
			),
			array(
				'title'    => __( 'Store notification email(s)', 'wpotkaz' ),
				'id'       => self::PREFIX . 'notify_emails',
				'type'     => 'text',
				'css'      => 'min-width:420px;',
				'default'  => $d['notify_emails'],
				'desc_tip' => __( 'Comma-separated. Receives the “New withdrawal” notification.', 'wpotkaz' ),
			),
			array(
				'title'   => __( 'Link in order emails', 'wpotkaz' ),
				'id'      => self::PREFIX . 'email_link_emails',
				'type'    => 'multiselect',
				'class'   => 'wc-enhanced-select',
				'css'     => 'min-width:300px;',
				'options' => $order_emails,
				'default' => $d['email_link_emails'],
				'desc'    => __( 'Adds a “Withdraw from the contract here” link (works without login) to these customer emails.', 'wpotkaz' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'py_otkaz_emails',
			),

			// Exclusions.
			array(
				'title' => __( 'Products without right of withdrawal', 'wpotkaz' ),
				'type'  => 'title',
				'desc'  => __( 'Mark products in the product / variation / category edit screens. Do not mark goods that lose the right only once unsealed (hygiene, cosmetics, sealed media).', 'wpotkaz' ),
				'id'    => 'py_otkaz_exclusions',
			),
			array(
				'title'   => __( 'Mode', 'wpotkaz' ),
				'id'      => self::PREFIX . 'exclusion_mode',
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'options' => array(
					'soft'   => __( 'Soft – selectable with a warning, flagged for you', 'wpotkaz' ),
					'strict' => __( 'Strict – cannot be selected', 'wpotkaz' ),
				),
				'default' => $d['exclusion_mode'],
			),
			array(
				'title'    => __( 'Personalisation meta keys', 'wpotkaz' ),
				'id'       => self::PREFIX . 'exclusion_meta_keys',
				'type'     => 'textarea',
				'css'      => 'min-width:420px;height:70px;',
				'default'  => '',
				'desc'     => __( 'One per line. An order item with a non-empty value for any of these meta keys (e.g. engraving text from an add-ons plugin) is treated as personalised.', 'wpotkaz' ),
			),
			array(
				'title'   => __( 'Storefront label', 'wpotkaz' ),
				'id'      => self::PREFIX . 'exclusion_label',
				'type'    => 'checkbox',
				'default' => $d['exclusion_label'],
				'desc'    => __( 'Show “No right of withdrawal” on the product page and next to the item in cart / checkout.', 'wpotkaz' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'py_otkaz_exclusions',
			),

			// Appearance.
			array(
				'title' => __( 'Appearance', 'wpotkaz' ),
				'type'  => 'title',
				'desc'  => __( 'The form inherits the theme font. The accent colour is used only for primary buttons, focus rings, checkboxes, links and the active step; button text colour is chosen automatically for contrast.', 'wpotkaz' ),
				'id'    => 'py_otkaz_look',
			),
			array(
				'title'   => __( 'Accent colour', 'wpotkaz' ),
				'id'      => self::PREFIX . 'accent_color',
				'type'    => 'color',
				'css'     => 'width:6em;',
				'default' => $d['accent_color'],
			),
			array(
				'title'   => __( 'Corner radius', 'wpotkaz' ),
				'id'      => self::PREFIX . 'radius',
				'type'    => 'select',
				'options' => array(
					'0'   => __( 'Sharp (0)', 'wpotkaz' ),
					'4'   => __( 'Slight (4px)', 'wpotkaz' ),
					'8'   => __( 'Rounded (8px)', 'wpotkaz' ),
					'999' => __( 'Pill buttons', 'wpotkaz' ),
				),
				'default' => $d['radius'],
			),
			array(
				'type' => 'sectionend',
				'id'   => 'py_otkaz_look',
			),

			// Privacy.
			array(
				'title' => __( 'Data retention', 'wpotkaz' ),
				'type'  => 'title',
				'id'    => 'py_otkaz_privacy',
			),
			array(
				'title'             => __( 'Keep records (years)', 'wpotkaz' ),
				'id'                => self::PREFIX . 'retention_years',
				'type'              => 'number',
				'css'               => 'width:80px;',
				'default'           => $d['retention_years'],
				'custom_attributes' => array(
					'min'  => 0,
					'step' => 1,
				),
				'desc'              => __( 'Withdrawal records (incl. IP and user agent as evidence) older than this are deleted automatically. 0 = keep forever.', 'wpotkaz' ),
			),
			array(
				'title'   => __( 'Delete data on uninstall', 'wpotkaz' ),
				'id'      => self::PREFIX . 'delete_data',
				'type'    => 'checkbox',
				'default' => $d['delete_data'],
				'desc'    => __( 'Also delete all withdrawal records when the plugin is deleted. Off = records are kept as evidence.', 'wpotkaz' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'py_otkaz_privacy',
			),
		);

		return apply_filters( 'py_otkaz_settings_fields', $fields );
	}
}
