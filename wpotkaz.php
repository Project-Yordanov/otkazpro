<?php
/**
 * Plugin Name:       WPOtkaz – Бутон за отказ от договор
 * Plugin URI:        https://github.com/Project-Yordanov/wpotkaz
 * Description:       Онлайн функция за отказ от договор (чл. 52а ЗЗП / Директива (ЕС) 2023/2673) за WooCommerce: бутон „Откажете се от договора тук“, двустъпкова форма, частичен отказ, потвърждение на траен носител и регистър на изявленията.
 * Version:           1.2.3
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Project Yordanov
 * Author URI:        https://projectyordanov.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpotkaz
 * Domain Path:       /languages
 * Update URI:        https://github.com/Project-Yordanov/wpotkaz
 * WC requires at least: 8.0
 * WC tested up to:   11.2
 *
 * @package PY\Otkaz
 */

defined( 'ABSPATH' ) || exit;

define( 'PY_OTKAZ_VERSION', '1.2.3' );
define( 'PY_OTKAZ_FILE', __FILE__ );
define( 'PY_OTKAZ_DIR', plugin_dir_path( __FILE__ ) );
define( 'PY_OTKAZ_URL', plugin_dir_url( __FILE__ ) );
define( 'PY_OTKAZ_BASENAME', plugin_basename( __FILE__ ) );
define( 'PY_OTKAZ_MIN_WC', '8.0' );

/**
 * PSR-4-like autoloader: PY\Otkaz\Foo_Bar => includes/class-foo-bar.php,
 * PY\Otkaz\Emails\Foo => includes/emails/class-foo.php.
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'PY\\Otkaz\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$parts = explode( '\\', substr( $class, strlen( $prefix ) ) );
		$name  = array_pop( $parts );
		$dir   = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
		$file  = PY_OTKAZ_DIR . 'includes/' . $dir . 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

// HPOS + Cart/Checkout blocks compatibility.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

// Private plugin (not on wp.org) – translations ship in /languages.
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'wpotkaz', false, dirname( PY_OTKAZ_BASENAME ) . '/languages' );
	},
	0
);

register_activation_hook( __FILE__, array( 'PY\\Otkaz\\Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PY\\Otkaz\\Install', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, PY_OTKAZ_MIN_WC, '<' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html(
							sprintf(
								/* translators: %s: minimum WooCommerce version */
								__( 'WPOtkaz requires WooCommerce %s or newer to be installed and active.', 'wpotkaz' ),
								PY_OTKAZ_MIN_WC
							)
						)
					);
				}
			);
			return;
		}
		\PY\Otkaz\Plugin::instance()->init();
	}
);
