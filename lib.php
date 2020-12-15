<?php
require_once( './config.php' );

/**
 * Return Release data.
 *
 * @param string $user       GitHub user name.
 * @param string $repository GitHub repository name.
 * @return object|false
 */
function get_release_data( $user, $repository ) {
	$dist = _create_dir( $user . '/' . $repository );
	if ( ! $dist ) {
		return false;
	}

	$json_file = $dist . '/release.json';
	if ( file_exists( $json_file ) ) {
		$saved_data = json_decode( file_get_contents( $json_file ) );

		if ( 60 * 10 >= time() - filemtime( $json_file ) ) {
			return $saved_data;
		}

		$release = _request_release_data( $user, $repository );
		if (
			strtotime( $saved_data->published_at ) === strtotime( $release->published_at )
			&& $saved_data->tag_name === $release->tag_name
		) {
			return $saved_data;
		}

		$unlink = unlink( $json_file );
		if ( ! $unlink ) {
			error( 'Failed unlink json file: ' . $json_file );
			return false;
		}

		$byte = file_put_contents( $json_file, json_encode( $release ), LOCK_EX );
		if ( ! $byte ) {
			error( 'Failed write json file: ' . $json_file );
			return false;
		}

		return $release;
	}

	$release = _request_release_data( $user, $repository );
	if ( ! $release ) {
		return false;
	}

	$byte = file_put_contents( $json_file, json_encode( $release ), LOCK_EX );
	if ( ! $byte ) {
		error( 'Failed write json file: ' . $json_file );
		return false;
	}

	return $release;
}

/**
 * Return zip data.
 *
 * @param object $release    GitHub release api response object.
 * @param string $user       GitHub user name.
 * @param string $repository GitHub repository name.
 * @return string|false
 */
function get_zip( $release, $user, $repository ) {
	$tag_name = $release->tag_name;
	$dist     = _create_dir( $user . '/' . $repository . '/' . $tag_name );
	if ( ! $dist ) {
		return false;
	}

	$filename = $repository . '.zip';
	$filepath = $dist . '/' . $filename;
	if ( file_exists( $filepath ) ) {
		return $filepath;
	}

	if ( ! empty( $release->assets ) && is_array( $release->assets ) ) {
		if ( ! empty( $release->assets[0] ) && is_object( $release->assets[0] ) ) {
			if ( ! empty( $release->assets[0]->browser_download_url ) ) {
				$byte = _save_zip( $release->assets[0]->browser_download_url, $filepath );
				return false !== $byte ? $filepath : false;
			}
		}
	}

	return false;
}

function download( $zip_path ) {
	header( 'Content-Type: application/octet-stream' );
	header( 'Content-Length: ' . filesize( $zip_path ) );
	header( 'Content-Disposition: attachment; filename="' . basename( $zip_path ) . '"' );
	header( 'X-Download-Options: noopen' ); // For IE
	header( 'Connection: close' );
	while ( ob_get_level()) {
		ob_end_clean();
	}

	readfile( $zip_path );
	exit;
}

function error( $message ) {
	error_log(
		date( '[Y-m-d H:i:s] ' ) . $message . "\n",
		3,
		__DIR__ . '/error_log'
	);
}

/**
 * Create directory. If directory exist, No create.
 *
 * @param string $dist The dist directory path.
 * @return string|false
 */
function _create_dir( $dist_slug ) {
	$dist_slug     = trim( $dist_slug, '/' );
	$dist_arr = explode( '/', $dist_slug );

	$dir = DIR;
	foreach ( $dist_arr as $_dir ) {
		$dir = $dir . '/' . $_dir;
		if ( ! file_exists( $dir ) ) {
			$mkdir = mkdir( $dir );
			if ( false === $mkdir ) {
				error( 'Failed create directory: ' . $dir );
				return false;
			}
		}
	}

	if ( ! file_exists( $dir ) ) {
		error( 'Failed create directory: ' . $dir );
		return false;
	}

	return $dir;
}

/**
 * Download and save zip data.
 *
 * @param string $src  The zip url.
 * @param string $dist The dist file path.
 * @return string|false
 */
function _save_zip( $src, $dist ) {
	if ( file_exists( $dist ) ) {
		$unlink = unlink( $dist );
		if ( false === $unlink ) {
			error( 'Failed unlink old zip file: ' . $dist );
			return false;
		}
	}

	$fp = fopen( $src, 'r' );
	if ( false === $fp ) {
		error( 'Failed src fopen(): ' . $src );
		return false;
	}

	$fpw = fopen( $dist, 'w' );
	if ( false === $fpw ) {
		error( 'Failed dist fopen(): ' . $dist );
		return false;
	}

	$size = 0;
	while ( ! feof( $fp ) ) {
		$buffer = fread( $fp, 4096 );
		if ( false === $buffer ) {
			$size = false;
			error( 'Failed fread(): ' . $dist );
			break;
		}

		$_size = fwrite( $fpw, $buffer );
		if ( false === $_size ) {
			$size = false;
			error( 'Failed fwrite(): ' . $dist );
			break;
		}

		$size += $_size;
	}

	fclose( $fp );
	fclose( $fpw );
	return $size;
}

/**
 * Request GitHub release API.
 *
 * @param string $user       GitHub user name.
 * @param string $repository GitHub repository name.
 * @return object|false
 */
function _request_release_data( $user, $repository ) {
	$CUSTOM_RELEASE_API = CUSTOM_RELEASE_API;
	$slug               = $user . '/' . $repository;
	if ( ! empty( $CUSTOM_RELEASE_API[ $slug ] ) ) {
		$api = $CUSTOM_RELEASE_API[ $slug ];
	} else {
		$api = 'https://api.github.com/repos/' . $slug . '/releases/latest';
	}

	$context = stream_context_create(
		[
			'http' => [
				'method' => 'GET',
				'header' => [
					'User-Agent: inc2734/download-api',
					'Authorization: token ' . ACCESS_TOKEN,
					'Content-type: application/json; charset=UTF-8',
				],
			],
		]
	);

	$json = file_get_contents( $api, false, $context );
	if ( false === $json ) {
		return false;
	}

	return json_decode( $json );
}
