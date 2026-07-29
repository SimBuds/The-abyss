<?php
/**
 * FutureBuild theme bootstrap.
 *
 * @package FutureBuild
 */
defined( 'ABSPATH' ) || exit;

define( 'FB_VERSION', wp_get_theme()->get( 'Version' ) );
define( 'FB_DIR', get_template_directory() );
define( 'FB_URI', get_template_directory_uri() );

/**
 * Theme supports, nav menus, image sizes, translations.
 */
function fb_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array( 'height' => 48, 'flex-width' => true ) );

	add_image_size( 'fb-card', 640, 420, true );
	add_image_size( 'fb-hero', 1920, 1080, true );

	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'futurebuild' ),
		'footer'  => __( 'Footer Menu', 'futurebuild' ),
	) );

	load_theme_textdomain( 'futurebuild', FB_DIR . '/languages' );
}
add_action( 'after_setup_theme', 'fb_setup' );

/**
 * Enqueue front-end assets.
 */
function fb_assets() {
	wp_enqueue_style( 'fb-fonts', FB_URI . '/assets/css/fonts.css', array(), FB_VERSION );
	wp_enqueue_style( 'fb-main', FB_URI . '/assets/css/main.css', array( 'fb-fonts' ), FB_VERSION );
}

add_action( 'wp_enqueue_scripts', 'fb_assets' );

require_once FB_DIR . '/inc/cpt.php';