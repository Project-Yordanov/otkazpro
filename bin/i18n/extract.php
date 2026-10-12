<?php
// Usage: php bin/i18n/extract.php <plugin_dir> <out.json> (called by bin/build.php).
// Extract gettext calls for text domain otkazpro. Usage: php extract.php <plugin_dir> <out.json>
$dir = rtrim( str_replace( chr(92), '/', $argv[1] ), '/' );
$fns = array( '__' => array( 's' ), '_e' => array( 's' ), 'esc_html__' => array( 's' ), 'esc_html_e' => array( 's' ), 'esc_attr__' => array( 's' ), 'esc_attr_e' => array( 's' ), '_x' => array( 's', 'c' ), 'esc_html_x' => array( 's', 'c' ), 'esc_attr_x' => array( 's', 'c' ), '_n' => array( 's', 'p', 'n' ), '_n_noop' => array( 's', 'p' ) );
$out = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	if ( 'php' !== $file->getExtension() ) continue;
	$rel = str_replace( chr(92), "/", substr( $file->getPathname(), strlen( $dir ) + 1 ) );
	$src = file_get_contents( $file->getPathname() );
	$toks = token_get_all( $src );
	$comment = '';
	for ( $i = 0, $n = count( $toks ); $i < $n; $i++ ) {
		$t = $toks[ $i ];
		if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) && false !== stripos( $t[1], 'translators:' ) ) { $comment = trim( preg_replace( '#^/\*+|\*+/$|^//#', '', trim( $t[1] ) ) ); continue; }
		if ( ! is_array( $t ) || T_STRING !== $t[0] || ! isset( $fns[ $t[1] ] ) ) continue;
		$line = $t[2];
		$j = $i + 1; while ( isset( $toks[ $j ] ) && is_array( $toks[ $j ] ) && T_WHITESPACE === $toks[ $j ][0] ) $j++;
		if ( '(' !== $toks[ $j ] ) continue;
		// collect args (literal strings only, top level)
		$args = array(); $depth = 0; $cur = null; $j++;
		for ( ; $j < $n; $j++ ) {
			$tk = $toks[ $j ];
			if ( '(' === $tk || '[' === $tk ) { $depth++; $cur = false; continue; }
			if ( ')' === $tk || ']' === $tk ) { if ( 0 === $depth ) { $args[] = $cur; break; } $depth--; continue; }
			if ( ',' === $tk && 0 === $depth ) { $args[] = $cur; $cur = null; continue; }
			if ( 0 === $depth && is_array( $tk ) && T_CONSTANT_ENCAPSED_STRING === $tk[0] && null === $cur ) { $cur = eval( 'return ' . $tk[1] . ';' ); continue; }
			if ( is_array( $tk ) && in_array( $tk[0], array( T_WHITESPACE, T_COMMENT ), true ) ) continue;
			if ( 0 === $depth ) $cur = false;
		}
		$spec = $fns[ $t[1] ];
		$domain = $args[ count( $spec ) ] ?? null;
		if ( 'otkazpro' !== $domain ) { $comment = ''; continue; }
		$e = array();
		foreach ( $spec as $k => $role ) { $e[ $role ] = $args[ $k ]; }
		if ( ! is_string( $e['s'] ) ) { fwrite( STDERR, "non-literal at $rel:$line\n" ); continue; }
		$key = ( isset( $e['c'] ) ? $e['c'] . "\4" : '' ) . $e['s'];
		if ( ! isset( $out[ $key ] ) ) $out[ $key ] = array( 's' => $e['s'], 'p' => isset( $e['p'] ) && is_string( $e['p'] ) ? $e['p'] : null, 'c' => $e['c'] ?? null, 'refs' => array(), 'comment' => '' );
		$out[ $key ]['refs'][] = "$rel:$line";
		if ( $comment ) $out[ $key ]['comment'] = $comment;
		$comment = '';
	}
}
// Plugin header strings (translated by WordPress on the Plugins screen).
foreach ( glob( $dir . '/*.php' ) as $main ) {
	$head = file_get_contents( $main, false, null, 0, 8192 );
	if ( ! preg_match( '/^\s*\*\s*Plugin Name:\s*(.+)$/mi', $head, $nm ) ) {
		continue;
	}
	$hdr = array( 'Plugin Name of the plugin' => trim( $nm[1] ) );
	if ( preg_match( '/^\s*\*\s*Description:\s*(.+)$/mi', $head, $dm ) ) {
		$hdr['Description of the plugin'] = trim( $dm[1] );
	}
	foreach ( $hdr as $comment => $text ) {
		$out[ $text ] = array( 's' => $text, 'p' => null, 'c' => null, 'refs' => array( basename( $main ) ), 'comment' => $comment );
	}
}
ksort( $out );
file_put_contents( $argv[2], json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
echo count( $out ) . " strings\n";
