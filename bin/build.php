<?php
/**
 * Dev build: minify CSS and regenerate translations.
 *
 * Usage (from the plugin root):  php bin/build.php
 * Translations source: bin/i18n/bg_BG.php (msgid => msgstr). Add new strings there after
 * changing texts in the code – the build fails on missing translations or placeholder mismatches.
 *
 * @package PY\Otkaz
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$root = dirname( __DIR__ );

// 1. CSS → .min.css.
$css = file_get_contents( $root . '/assets/css/otkazpro.css' );
$css = preg_replace( '#/\*.*?\*/#s', '', $css );
$css = preg_replace( '/\s+/', ' ', $css );
$css = preg_replace( '/\s*([{}:;,>])\s*/', '$1', $css );
$css = str_replace( ';}', '}', trim( $css ) );
// Restore spaces required inside media queries ("and (").
$css = str_replace( 'and(', 'and (', $css );
file_put_contents( $root . '/assets/css/otkazpro.min.css', $css );
printf( "CSS: %d bytes (gzip %d)\n", strlen( $css ), strlen( gzencode( $css, 9 ) ) );

// 2. Translations.
$version = '';
if ( preg_match( '/^\s*\*\s*Version:\s*(\S+)/mi', file_get_contents( $root . '/otkazpro.php' ), $m ) ) {
	$version = $m[1];
}
$tmp = sys_get_temp_dir() . '/otkazpro-strings.json';
passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/i18n/extract.php' ) . ' ' . escapeshellarg( $root ) . ' ' . escapeshellarg( $tmp ), $code );
if ( $code ) {
	exit( $code );
}
passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/i18n/build-l10n.php' ) . ' ' . escapeshellarg( $tmp ) . ' ' . escapeshellarg( __DIR__ . '/i18n/bg_BG.php' ) . ' ' . escapeshellarg( $root . '/languages' ) . ' ' . escapeshellarg( $version ), $code );
exit( $code );
