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
 * Link previews. Open Graph and Twitter card tags make a link shared in a text
 * message or on Facebook show a title, a short description and a photograph.
 * A page's own excerpt is used as its description when it has one.
 */
add_action(
	'wp_head',
	function () {
		$title       = wp_get_document_title();
		$description = 'Commissioner, District 6, on the Memphis-Shelby County Schools Board of Education. The Standard Starts Now.';
		$url         = home_url( '/' );

		if ( is_singular() ) {
			$url = get_permalink();
			if ( has_excerpt() ) {
				$excerpt = trim( wp_strip_all_tags( get_the_excerpt() ) );
				if ( '' !== $excerpt ) {
					$description = $excerpt;
				}
			}
		}

		$image = get_theme_file_uri( 'assets/images/share.jpg' );
		$alt   = 'T. L. Harris taking the oath of office as District 6 commissioner.';

		echo "\n";
		printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
		printf( '<meta property="og:type" content="website" />' . "\n" );
		printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );
		printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) );
		printf( '<meta property="og:image:width" content="1200" />' . "\n" );
		printf( '<meta property="og:image:height" content="630" />' . "\n" );
		printf( '<meta property="og:image:alt" content="%s" />' . "\n", esc_attr( $alt ) );
		printf( '<meta name="twitter:card" content="summary_large_image" />' . "\n" );
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $description ) );
		printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $image ) );
	},
	5
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
