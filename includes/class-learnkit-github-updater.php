<?php
/**
 * GitHub Updater for LearnKit.
 *
 * Hooks into WordPress's plugin update system and checks the GitHub Releases
 * API for a newer version. When one is found, WordPress will show the standard
 * "Update available" notice on the Plugins page and allow one-click updates.
 *
 * @package LearnKit
 * @since   0.7.2
 */

namespace LearnKit\GitHubUpdater;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LEARNKIT_GITHUB_OWNER', 'welbinator' );
define( 'LEARNKIT_GITHUB_REPO', 'learnkit' );

/**
 * Check GitHub for a newer release and populate the WP update transient.
 *
 * @param  object $transient The `update_plugins` site transient.
 * @return object
 */
function check_for_update( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$plugin_basename = LEARNKIT_PLUGIN_BASENAME;
	$current_version = $transient->checked[ $plugin_basename ] ?? null;

	if ( ! $current_version ) {
		return $transient;
	}

	$release = get_latest_release();
	if ( ! $release ) {
		return $transient;
	}

	$latest_version = $release['version'];
	$download_url   = $release['download_url'];

	if ( version_compare( $latest_version, $current_version, '<=' ) ) {
		return $transient;
	}

	$transient->response[ $plugin_basename ] = (object) array(
		'id'          => 'github.com/' . LEARNKIT_GITHUB_OWNER . '/' . LEARNKIT_GITHUB_REPO,
		'slug'        => dirname( $plugin_basename ),
		'plugin'      => $plugin_basename,
		'new_version' => $latest_version,
		'url'         => 'https://github.com/' . LEARNKIT_GITHUB_OWNER . '/' . LEARNKIT_GITHUB_REPO,
		'package'     => $download_url,
		'icons'       => array(),
		'banners'     => array(),
		'tested'      => '',
		'requires'    => '',
		'requires_php' => '',
	);

	return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', __NAMESPACE__ . '\\check_for_update' );

/**
 * Populate the plugin info popup (the "View version X.X.X details" link).
 *
 * @param  false|object|array $result The result object/array. False if not set.
 * @param  string             $action The API action (e.g. 'plugin_information').
 * @param  object             $args   Request arguments.
 * @return false|object
 */
function plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action ) {
		return $result;
	}

	if ( ! isset( $args->slug ) || $args->slug !== dirname( LEARNKIT_PLUGIN_BASENAME ) ) {
		return $result;
	}

	$release = get_latest_release();
	if ( ! $release ) {
		return $result;
	}

	return (object) array(
		'name'          => 'LearnKit',
		'slug'          => dirname( LEARNKIT_PLUGIN_BASENAME ),
		'version'       => $release['version'],
		'author'        => '<a href="https://github.com/' . LEARNKIT_GITHUB_OWNER . '">' . esc_html( LEARNKIT_GITHUB_OWNER ) . '</a>',
		'homepage'      => 'https://github.com/' . LEARNKIT_GITHUB_OWNER . '/' . LEARNKIT_GITHUB_REPO,
		'download_link' => $release['download_url'],
		'sections'      => array(
			'description' => 'A lightweight LMS plugin for WordPress.',
			'changelog'   => nl2br( esc_html( $release['body'] ) ),
		),
		'last_updated'  => $release['published_at'],
		'requires'      => '6.2',
		'tested'        => get_bloginfo( 'version' ),
		'requires_php'  => '7.4',
	);
}
add_filter( 'plugins_api', __NAMESPACE__ . '\\plugin_info', 20, 3 );

/**
 * Fetch the latest release from the GitHub API with caching.
 *
 * Results are cached in a transient for 12 hours to avoid hammering the API.
 *
 * @return array|false Associative array with keys: version, download_url, body, published_at.
 *                     Returns false on failure.
 */
function get_latest_release() {
	$cache_key = 'learnkit_github_latest_release';
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$api_url  = 'https://api.github.com/repos/' . LEARNKIT_GITHUB_OWNER . '/' . LEARNKIT_GITHUB_REPO . '/releases/latest';
	$response = wp_remote_get(
		$api_url,
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				'X-GitHub-Api-Version' => '2022-11-28',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== (int) $code ) {
		return false;
	}

	$release = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
		return false;
	}

	// Find the zip asset. Fall back to the auto-generated source zip if no explicit asset.
	$download_url = '';
	if ( ! empty( $release['assets'] ) ) {
		foreach ( $release['assets'] as $asset ) {
			if ( isset( $asset['content_type'] ) && 'application/zip' === $asset['content_type'] ) {
				$download_url = $asset['browser_download_url'];
				break;
			}
		}
	}

	// GitHub's auto-generated source zip if no explicit asset found.
	if ( empty( $download_url ) ) {
		$tag          = rawurlencode( $release['tag_name'] );
		$download_url = 'https://github.com/' . LEARNKIT_GITHUB_OWNER . '/' . LEARNKIT_GITHUB_REPO . '/archive/refs/tags/' . $tag . '.zip';
	}

	$data = array(
		'version'      => ltrim( $release['tag_name'], 'v' ),
		'download_url' => esc_url_raw( $download_url ),
		'body'         => wp_strip_all_tags( $release['body'] ?? '' ),
		'published_at' => $release['published_at'] ?? '',
	);

	// Cache for 12 hours.
	set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );

	return $data;
}

/**
 * Bust the release cache after a successful update so the next check is fresh.
 *
 * @param  \WP_Upgrader $upgrader Upgrader instance.
 * @param  array        $options  Update options.
 */
function bust_cache_after_update( $upgrader, $options ) {
	if (
		'update' === $options['action'] &&
		'plugin' === $options['type'] &&
		! empty( $options['plugins'] ) &&
		in_array( LEARNKIT_PLUGIN_BASENAME, (array) $options['plugins'], true )
	) {
		delete_transient( 'learnkit_github_latest_release' );
	}
}
add_action( 'upgrader_process_complete', __NAMESPACE__ . '\\bust_cache_after_update', 10, 2 );
