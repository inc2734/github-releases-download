<?php
/**
 * GitHub のユーザー名とリポジトリ名をもとに GitHub Releases から zip ファイルをダウンロードする。
 * DIR にある場合はそれを、ない場合は GitHub を参照する。
 *
 * @example: https://snow-monkey.2inc.org/download-api/?user=inc2734&repository=snow-monkey-diet
 * @example: https://snow-monkey.2inc.org/download-api/inc2734/snow-monkey-diet/
 */

require_once( './config.php' );
require_once( './lib.php' );

$path_info = isset( $_SERVER['PATH_INFO'] ) ? trim( $_SERVER['PATH_INFO'], '/' ) : false;
if ( $path_info ) {
	$path_info = explode( '/', $path_info );
	if ( 2 !== count( $path_info ) ) {
		error( 'Invalid requesst.' );
		exit;
	}

	$user       = $path_info[0];
	$repository = $path_info[1];
} else {
	$user       = filter_input( INPUT_GET, 'user' );
	$repository = filter_input( INPUT_GET, 'repository' );
}

if ( ! $user || ! $repository ) {
	error( 'Invalid requesst.' );
	exit;
}

$dist    = $user . '/' . $repository;
$release = get_release_data( $user, $repository );
if ( ! $release ) {
	error( 'Failed get release data.' );
	exit;
}

$zip = get_zip( $release, $user, $repository );
if ( ! $zip ) {
	error( 'Failed get zip data.' );
	exit;
}

download( $zip );