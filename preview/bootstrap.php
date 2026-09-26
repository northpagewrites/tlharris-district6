<?php
/**
 * WordPress Playground preview bootstrap. PREVIEW ONLY.
 *
 * Runs once, from blueprint.json, when Playground builds a preview. It exists so
 * that someone doing visual QA sees a populated site rather than an empty one.
 * Nothing in wp-content/ depends on it, and it refuses to run anywhere else.
 *
 * What it does, in order:
 *   1. Fingerprints what was checked out and records it in a stamp file, so the
 *      build can be verified against a Git commit (docs/preview-workflow.md).
 *   2. Removes WordPress's own sample post and page.
 *   3. Creates each page from the theme's page pattern and sets the front page.
 *   4. Runs the plugin's ordinary importers. They create every record as a DRAFT.
 *   5. PREVIEW ONLY: publishes the drafts those importers just created, so the
 *      pages have something to show. The importers never publish. On a real
 *      site, imported records stay drafts until the office has reviewed them.
 */

if ( ! defined( 'TLHARRIS_PLAYGROUND_PREVIEW' ) || 'PHP.wasm' !== ( $_SERVER['SERVER_SOFTWARE'] ?? '' ) ) {
	die( "tlharris preview bootstrap: refusing to run outside a WordPress Playground preview.\n" );
}

$tlharris_root = dirname( __DIR__ );
require_once $tlharris_root . '/wp-load.php';
require_once __DIR__ . '/fingerprint.php';

$tlharris_stamp_file = $tlharris_root . '/tlharris-preview-stamp.json';
$tlharris_stamp      = array_merge(
	array( 'status' => 'running' ),
	tlharris_preview_fingerprint( $tlharris_root ),
	array(
		'wp'     => $GLOBALS['wp_version'],
		'php'    => PHP_VERSION,
		'server' => $_SERVER['SERVER_SOFTWARE'],
		'errors' => array(),
	)
);
// Written now as well as at the end, so a bootstrap that dies half way is
// visible as "running" and cannot be mistaken for a finished build.
file_put_contents( $tlharris_stamp_file, wp_json_encode( $tlharris_stamp ) );

if ( 'tlharris-public' !== get_stylesheet() ) {
	$tlharris_stamp['errors'][] = 'The tlharris-public theme is not active.';
}

// 2. WordPress's own sample content would otherwise appear in the news list.
foreach ( array( 'post' => 'hello-world', 'page' => 'sample-page' ) as $tlharris_type => $tlharris_slug ) {
	$tlharris_sample = get_page_by_path( $tlharris_slug, OBJECT, $tlharris_type );
	if ( $tlharris_sample ) {
		wp_delete_post( $tlharris_sample->ID, true );
	}
}

// 3. Pages, from the theme's own patterns. slug => array( title, pattern file ).
$tlharris_patterns = get_stylesheet_directory() . '/patterns/';
$tlharris_pages    = array(
	'home'                 => array( 'T. L. Harris', 'page-home' ),
	'about'                => array( 'About T. L. Harris', 'page-about' ),
	'district-6'           => array( 'District 6', 'page-district-6' ),
	'board-work'           => array( 'The Board', 'page-board-work' ),
	'priorities-progress'  => array( 'Priorities & Progress', 'page-priorities-progress' ),
	'news'                 => array( 'News & Updates', 'page-news' ),
	'events'               => array( 'Events', 'page-events' ),
	'press'                => array( 'Press & Media', 'page-press' ),
	'contact'              => array( 'Contact', 'page-contact' ),
	'get-involved'         => array( 'Get Involved', 'page-get-involved' ),
	'support'              => array( 'Support', 'page-support' ),
	'privacy'              => array( 'Privacy', 'page-privacy' ),
	'terms'                => array( 'Terms', 'page-terms' ),
	'accessibility'        => array( 'Accessibility', 'page-accessibility' ),
);

foreach ( $tlharris_pages as $tlharris_slug => $tlharris_page ) {
	list( $tlharris_title, $tlharris_pattern ) = $tlharris_page;

	$tlharris_content = '';
	if ( $tlharris_pattern && is_readable( $tlharris_patterns . $tlharris_pattern . '.php' ) ) {
		// Drop the pattern file's PHP header comment; keep the block markup.
		$tlharris_content = preg_replace( '/^<\?php[\s\S]*?\?>\s*/', '', file_get_contents( $tlharris_patterns . $tlharris_pattern . '.php' ) );
	}

	$tlharris_data     = array(
		'post_type'    => 'page',
		'post_title'   => $tlharris_title,
		'post_name'    => $tlharris_slug,
		'post_status'  => 'publish',
		'post_content' => $tlharris_content,
	);
	$tlharris_existing = get_page_by_path( $tlharris_slug );

	if ( $tlharris_existing ) {
		$tlharris_data['ID'] = $tlharris_existing->ID;
		wp_update_post( $tlharris_data );
	} else {
		wp_insert_post( $tlharris_data );
	}
}

$tlharris_home = get_page_by_path( 'home' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $tlharris_home ? $tlharris_home->ID : 0 );

// 4. The plugin's real importers. Every record they create is a draft.
$tlharris_imported = array();
foreach ( array( 'schools', 'meetings', 'priorities' ) as $tlharris_what ) {
	$tlharris_function = 'tlharris_import_' . $tlharris_what;

	if ( ! function_exists( $tlharris_function ) ) {
		$tlharris_stamp['errors'][] = $tlharris_function . '() does not exist. Is tlharris-core active?';
		continue;
	}

	$tlharris_result = $tlharris_function();
	foreach ( (array) ( $tlharris_result['errors'] ?? array() ) as $tlharris_error ) {
		$tlharris_stamp['errors'][] = $tlharris_what . ': ' . $tlharris_error;
	}
	unset( $tlharris_result['errors'] );
	$tlharris_imported[ $tlharris_what ] = $tlharris_result;
}

// 5. PREVIEW ONLY. Publish the drafts the importers created, and only those.
$tlharris_published = array();
foreach ( array( 'district_school', 'event', 'priority' ) as $tlharris_type ) {
	$tlharris_ids = get_posts(
		array(
			'post_type'      => $tlharris_type,
			'post_status'    => 'draft',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => 'tlharris_import_key',
			'meta_compare'   => 'EXISTS',
		)
	);

	foreach ( $tlharris_ids as $tlharris_id ) {
		wp_update_post(
			array(
				'ID'          => $tlharris_id,
				'post_status' => 'publish',
			)
		);
	}
	$tlharris_published[ $tlharris_type ] = count( $tlharris_ids );
}

flush_rewrite_rules( true );

$tlharris_stamp['imported']  = $tlharris_imported;
$tlharris_stamp['published'] = $tlharris_published;
$tlharris_stamp['status']    = $tlharris_stamp['errors'] ? 'error' : 'ok';
file_put_contents( $tlharris_stamp_file, wp_json_encode( $tlharris_stamp ) );
