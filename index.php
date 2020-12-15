<?php
/**
 * GitHub のユーザー名とリポジトリ名をもとに GitHub Releases から zip ファイルをダウンロードする。
 * DIR にある場合はそれを、ない場合は GitHub を参照する。
 *
 * example: https://snow-monkey.2inc.org/download-api/?user=inc2734&repository=snow-monkey
 */

require_once( './config.php' );
require_once( './lib.php' );

$user       = filter_input( INPUT_GET, 'user' );
$repository = filter_input( INPUT_GET, 'repository' );

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