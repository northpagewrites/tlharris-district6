<?php
/**
 * T. L. Harris Public Service theme bootstrap.
 *
 * Presentation only. Content types, identity settings and anything that must
 * survive a theme change live in the tlharris-core plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
		add_editor_style( 'style.css' );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'tlharris-public',
			get_stylesheet_uri(),
			array(),
			wp_get_theme()->get( 'Version' )
		);
	}
);

add_filter(
	'body_class',
	function ( $classes ) {
		$classes[] = 'tlharris-public-site';
		return $classes;
	}
);

/**
 * Skip link. Every template's <main> carries id="main-content" through the
 * group block's anchor support.
 */
add_action(
	'wp_body_open',
	function () {
		echo '<a class="tlharris-skip" href="#main-content">Skip to content</a>';
	}
);

/**
 * Register the pattern category used by this theme's page-content patterns.
 */
add_action(
	'init',
	function () {
		if ( function_exists( 'register_block_pattern_category' ) ) {
			register_block_pattern_category(
				'tlharris',
				array( 'label' => 'T. L. Harris' )
			);
		}
	}
);
