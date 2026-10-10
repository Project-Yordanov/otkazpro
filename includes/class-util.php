<?php
/**
 * Small helpers.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Utility functions.
 */
class Util {

	/**
	 * Client IP (filterable for proxies / Cloudflare setups).
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip = class_exists( '\WC_Geolocation' ) ? \WC_Geolocation::get_ip_address() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		$ip = (string) apply_filters( 'py_otkaz_client_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Client user agent.
	 *
	 * @return string
	 */
	public static function user_agent() {
		$ua = function_exists( 'wc_get_user_agent' ) ? wc_get_user_agent() : '';
		return substr( sanitize_text_field( (string) $ua ), 0, 500 );
	}

	/**
	 * Format a GMT MySQL datetime in the site timezone.
	 *
	 * @param string $gmt    GMT datetime (Y-m-d H:i:s).
	 * @param string $format 'date', 'time', 'datetime' or a PHP date format.
	 * @return string
	 */
	public static function format_gmt( $gmt, $format = 'datetime' ) {
		if ( ! $gmt ) {
			return '';
		}
		$ts = strtotime( $gmt . ' UTC' );
		if ( ! $ts ) {
			return '';
		}
		switch ( $format ) {
			case 'date':
				$format = get_option( 'date_format' );
				break;
			case 'time':
				$format = get_option( 'time_format' );
				break;
			case 'datetime':
				$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
				break;
		}
		return wp_date( $format, $ts );
	}

	/**
	 * Relative luminance (WCAG 2.x).
	 *
	 * @param string $hex Hex colour.
	 * @return float
	 */
	public static function luminance( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return 0.0;
		}
		$rgb = array();
		foreach ( array( 0, 2, 4 ) as $i ) {
			$c     = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$rgb[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
	}

	/**
	 * Contrast ratio between two colours.
	 *
	 * @param string $a Hex.
	 * @param string $b Hex.
	 * @return float
	 */
	public static function contrast( $a, $b ) {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * Black or white – whichever reads better on the given background.
	 *
	 * @param string $bg Background hex.
	 * @return string
	 */
	public static function text_on( $bg ) {
		return self::contrast( $bg, '#ffffff' ) >= self::contrast( $bg, '#000000' ) ? '#ffffff' : '#000000';
	}

	/**
	 * Accent variant usable as text/focus colour on white (≥ 4.5:1), darkening if needed.
	 *
	 * @param string $hex Accent.
	 * @return string
	 */
	public static function ink_on_white( $hex ) {
		$hex = sanitize_hex_color( $hex );
		if ( ! $hex ) {
			return '#111111';
		}
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		for ( $i = 0; $i < 20; $i++ ) {
			$color = sprintf( '#%02x%02x%02x', $r, $g, $b );
			if ( self::contrast( $color, '#ffffff' ) >= 4.5 ) {
				return $color;
			}
			$r = (int) floor( $r * 0.85 );
			$g = (int) floor( $g * 0.85 );
			$b = (int) floor( $b * 0.85 );
		}
		return '#111111';
	}

	/**
	 * Sanitise a CSV cell against spreadsheet formula injection.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}
		return $value;
	}
}
