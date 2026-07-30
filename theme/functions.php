<?php
/**
 * The-abyss theme bootstrap.
 *
 * @package The_abyss
 */
defined( 'ABSPATH' ) || exit;

define( 'THE_ABYSS_VERSION', wp_get_theme()->get( 'Version' ) );
define( 'THE_ABYSS_DIR', get_template_directory() );
define( 'THE_ABYSS_URI', get_template_directory_uri() );

/**
 * Theme supports, nav menus, image sizes, translations.
 */
function the_abyss_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array( 'height' => 48, 'flex-width' => true ) );

	// theme.json carries the palette, type scale, and spacing. Editor styles are
	// loaded so the editor renders against the same tokens as the front end,
	// because a theme with an authoring surface has two surfaces to get right.
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/main.css' );

	add_image_size( 'the-abyss-card', 640, 420, true );
	add_image_size( 'the-abyss-hero', 1920, 1080, true );

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'the-abyss' ),
			'footer'  => __( 'Footer Menu', 'the-abyss' ),
		)
	);

	load_theme_textdomain( 'the-abyss', THE_ABYSS_DIR . '/languages' );
}
add_action( 'after_setup_theme', 'the_abyss_setup' );

/**
 * Enqueue front-end assets.
 *
 * fonts.css is a dependency of main.css rather than a separate concern, so the
 * @font-face rules are always parsed before anything references the family.
 */
function the_abyss_assets() {
	wp_enqueue_style(
		'the-abyss-fonts',
		THE_ABYSS_URI . '/assets/css/fonts.css',
		array(),
		THE_ABYSS_VERSION
	);

	wp_enqueue_style(
		'the-abyss-main',
		THE_ABYSS_URI . '/assets/css/main.css',
		array( 'the-abyss-fonts' ),
		THE_ABYSS_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'the_abyss_assets' );

/**
 * Force rel="sponsored nofollow" on outbound links in post content.
 *
 * PLAN.md requires this be structural rather than a rule authors remember. A
 * documented convention that depends on tagging every affiliate link by hand
 * will eventually be missed, so it is applied at render time instead.
 *
 * Internal links and anchors are left alone. Existing rel values are preserved
 * and merged rather than overwritten, so a hand-set rel is never silently
 * discarded.
 *
 * @param string $content Post content.
 * @return string
 */
function the_abyss_sponsor_outbound_links( $content ) {
	if ( empty( $content ) || false === strpos( $content, '<a ' ) ) {
		return $content;
	}

	$home = wp_parse_url( home_url(), PHP_URL_HOST );

	return preg_replace_callback(
		'/<a\s([^>]+)>/i',
		static function ( $matches ) use ( $home ) {
			$attrs = $matches[1];

			if ( ! preg_match( '/href=(["\'])(.*?)\1/i', $attrs, $href ) ) {
				return $matches[0];
			}

			$host = wp_parse_url( $href[2], PHP_URL_HOST );

			// No host means a relative link or a fragment. Same host means
			// internal. Neither is an affiliate link.
			if ( empty( $host ) || $host === $home ) {
				return $matches[0];
			}

			$rel = array( 'sponsored', 'nofollow' );

			if ( preg_match( '/rel=(["\'])(.*?)\1/i', $attrs, $existing ) ) {
				$rel   = array_unique( array_merge( preg_split( '/\s+/', trim( $existing[2] ) ), $rel ) );
				$attrs = str_replace( $existing[0], 'rel="' . esc_attr( implode( ' ', array_filter( $rel ) ) ) . '"', $attrs );
			} else {
				$attrs .= ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"';
			}

			return '<a ' . $attrs . '>';
		},
		$content
	);
}
add_filter( 'the_content', 'the_abyss_sponsor_outbound_links', 20 );
