<?php
/**
 * Abyss theme functions.
 *
 * @package Abyss
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ABYSS_VERSION', '1.0.0' );

/*
 * Compliance, schema, and the disclosure that sits above the article. Carried
 * over from the previous theme on 2026-08-01 and kept separable rather than
 * merged in, because it is the part a future theme change must not drop.
 */
require_once get_template_directory() . '/inc/compliance.php';

/* -------------------------------------------------------------------------
 * Setup
 * ---------------------------------------------------------------------- */

function abyss_setup() {
	load_theme_textdomain( 'abyss', get_template_directory() . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor.css' );
	add_theme_support( 'custom-logo', array(
		'height'      => 68,
		'width'       => 296,
		'flex-height' => true,
		'flex-width'  => true,
	) );

	add_image_size( 'abyss-lead', 1280, 720, true );
	add_image_size( 'abyss-card', 720, 480, true );
	add_image_size( 'abyss-pick', 640, 480, true );

	register_nav_menus( array(
		'primary'      => __( 'Primary (header)', 'abyss' ),
		'footer-one'   => __( 'Footer column 1', 'abyss' ),
		'footer-two'   => __( 'Footer column 2', 'abyss' ),
		'footer-three' => __( 'Footer column 3', 'abyss' ),
		'legal'        => __( 'Legal (footer bottom)', 'abyss' ),
	) );
}
add_action( 'after_setup_theme', 'abyss_setup' );

function abyss_content_width() {
	$GLOBALS['content_width'] = 704;
}
add_action( 'after_setup_theme', 'abyss_content_width', 0 );

/* -------------------------------------------------------------------------
 * Assets
 * ---------------------------------------------------------------------- */

function abyss_assets() {
	/*
	 * Self-hosted since 2026-08-01. See assets/css/fonts.css for why the Google
	 * Fonts CDN was removed; the short version is that PLAN.md requires
	 * self-hosted fonts, and the CDN sees every visitor's IP address.
	 *
	 * Loaded as a dependency of the main stylesheet rather than alongside it, so
	 * the @font-face rules are always parsed before anything references the
	 * family.
	 */
	wp_enqueue_style(
		'abyss-fonts',
		get_template_directory_uri() . '/assets/css/fonts.css',
		array(),
		abyss_asset_version( 'assets/css/fonts.css' )
	);

	wp_enqueue_style( 'abyss', get_stylesheet_uri(), array( 'abyss-fonts' ), abyss_asset_version( 'style.css' ) );
	wp_add_inline_style( 'abyss', abyss_palette_css() );

	wp_enqueue_script(
		'abyss',
		get_template_directory_uri() . '/assets/js/theme.js',
		array(),
		abyss_asset_version( 'assets/js/theme.js' ),
		true
	);

	/*
	 * Threaded replies need this core script or the Reply link is inert: it
	 * jumps to the form's anchor instead of moving the form under the comment
	 * being replied to, and the reply is filed as a new top-level comment.
	 * Discussion settings own whether threading is on, so the condition reads
	 * that rather than assuming.
	 */
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'abyss_assets' );

/**
 * Cache-busting version for a theme asset, from its modification time.
 *
 * ABYSS_VERSION only changes at a release, so during a build every edit to
 * style.css shipped under the same version and browsers were entitled to serve
 * the cached copy. That turns "the fix did not work" and "you did not see the
 * fix" into the same symptom, which cost real debugging time on the previous
 * theme.
 *
 * In production this settles to one stable value per deploy, which is the
 * correct caching behaviour there too, so it is not a development-only hack.
 *
 * @param string $rel Path relative to the theme root, no leading slash.
 * @return string
 */
function abyss_asset_version( $rel ) {
	$path = get_template_directory() . '/' . $rel;

	return file_exists( $path ) ? (string) filemtime( $path ) : ABYSS_VERSION;
}

function abyss_editor_palette_css() {
	wp_add_inline_style( 'wp-block-library', abyss_palette_css() );
}
add_action( 'admin_enqueue_scripts', 'abyss_editor_palette_css' );

/* -------------------------------------------------------------------------
 * Palettes
 * ---------------------------------------------------------------------- */

function abyss_palettes() {
	/*
	 * One palette. Normalised 2026-08-01: the theme shipped with four
	 * selectable schemes, and the other three were removed rather than left
	 * unused. A palette picker means every contrast measurement is only valid
	 * for whichever scheme happens to be active, so "the site is accessible"
	 * stops being a property of the theme and becomes a property of a setting.
	 *
	 * Measured against WCAG on 2026-08-01, all against --color-bg #151a2a:
	 *   text          15.31:1     accent text        9.44:1
	 *   neutral-800   11.01:1     on-accent on fill  9.35:1
	 *   neutral-700    6.79:1     negative           5.98:1
	 * All clear 4.5:1 for body text under 1.4.3.
	 *
	 * --color-neutral-400 was #48526e, which measured 2.23:1 against the
	 * background. It is the border on .btn--secondary and on form inputs, so it
	 * is a UI component boundary and 1.4.11 requires 3:1. Raised to #67739a,
	 * which measures 3.71:1 on the background, 3.33:1 on card, and 3.00:1 on
	 * surface — inputs appear on all three.
	 *
	 * --color-divider stays at 1.49:1 deliberately. It draws decorative rules
	 * between sections and identifies no control, which is the exemption in
	 * 1.4.11. Do not "fix" it to match neutral-400; raising it would make every
	 * section rule shout.
	 */
	return array(
		'navy-amber' => array(
			'label' => __( 'Deep navy + amber', 'abyss' ),
			'dark'  => true,
			'vars'  => array(
				'--color-bg' => '#151a2a', '--color-card' => '#1d2338', '--color-surface' => '#232b44',
				'--color-text' => '#eef1f8', '--color-divider' => '#2f3853',
				'--color-neutral-400' => '#67739a', '--color-neutral-700' => '#98a2bd', '--color-neutral-800' => '#c7cee2',
				'--color-accent' => '#f2b53c', '--color-accent-100' => '#2a2718', '--color-accent-200' => '#3a3320',
				'--color-accent-300' => '#5d4f28', '--color-accent-600' => '#f7c765', '--color-accent-700' => '#fad78f',
				'--color-negative' => '#f2705e', '--on-accent' => '#241a04', '--on-accent-soft' => 'rgba(36,26,4,.75)',
			),
		),
	);
}

function abyss_active_palette() {
	$palettes = abyss_palettes();

	// Single palette since 2026-08-01. reset() rather than a hardcoded key so
	// this keeps working if the palette is ever renamed.
	return reset( $palettes );
}

function abyss_palette_css() {
	$palette = abyss_active_palette();
	$out     = ':root{';

	foreach ( $palette['vars'] as $name => $value ) {
		$out .= $name . ':' . $value . ';';
	}

	$out .= '}';

	return $out;
}

function abyss_is_dark() {
	$palette = abyss_active_palette();

	return ! empty( $palette['dark'] );
}

/* -------------------------------------------------------------------------
 * Custom post types: affiliate offers and product picks
 * ---------------------------------------------------------------------- */

function abyss_post_types() {
	register_post_type( 'abyss_offer', array(
		'labels' => array(
			'name'               => __( 'Rates &amp; offers', 'abyss' ),
			'singular_name'      => __( 'Offer', 'abyss' ),
			'add_new_item'       => __( 'Add offer', 'abyss' ),
			'edit_item'          => __( 'Edit offer', 'abyss' ),
			'menu_name'          => __( 'Rates &amp; offers', 'abyss' ),
		),
		'public'        => false,
		'show_ui'       => true,
		'show_in_menu'  => true,
		'show_in_rest'  => true,
		'menu_icon'     => 'dashicons-chart-line',
		'menu_position' => 21,
		'supports'      => array( 'title', 'page-attributes' ),
	) );

	register_post_type( 'abyss_pick', array(
		'labels' => array(
			'name'          => __( 'Product picks', 'abyss' ),
			'singular_name' => __( 'Pick', 'abyss' ),
			'add_new_item'  => __( 'Add pick', 'abyss' ),
			'edit_item'     => __( 'Edit pick', 'abyss' ),
			'menu_name'     => __( 'Product picks', 'abyss' ),
		),
		'public'        => false,
		'show_ui'       => true,
		'show_in_menu'  => true,
		'show_in_rest'  => true,
		'menu_icon'     => 'dashicons-awards',
		'menu_position' => 22,
		'supports'      => array( 'title', 'thumbnail', 'excerpt', 'page-attributes' ),
	) );
}
add_action( 'init', 'abyss_post_types' );

function abyss_offer_fields() {
	return array(
		/*
		 * Added 2026-08-01. One offer list previously fed both the homepage rate
		 * snapshot and the comparison table, and the table sorts by rate
		 * descending. A 6.48% mortgage therefore outranked every savings account
		 * in a table headed "Best high-yield savings accounts", and sorted to the
		 * top of "Today's best rates", where a high rate is the opposite of good
		 * news. The two surfaces want different things from the same data, so the
		 * offer has to say what kind of product it is.
		 */
		'kind'     => array(
			'label'   => __( 'Product type', 'abyss' ),
			'type'    => 'select',
			'choices' => array(
				'savings'  => __( 'Savings account', 'abyss' ),
				'cd'       => __( 'Certificate of deposit', 'abyss' ),
				'mortgage' => __( 'Mortgage', 'abyss' ),
				'card'     => __( 'Credit card', 'abyss' ),
				'other'    => __( 'Other', 'abyss' ),
			),
		),
		'apy'      => array( 'label' => __( 'Headline rate (number, e.g. 4.35)', 'abyss' ), 'type' => 'text' ),
		'unit'     => array( 'label' => __( 'Rate unit (e.g. % APY)', 'abyss' ), 'type' => 'text' ),
		'minimum'  => array( 'label' => __( 'Minimum deposit (number, 0 for none)', 'abyss' ), 'type' => 'text' ),
		'fee'      => array( 'label' => __( 'Monthly fee (number, 0 for none)', 'abyss' ), 'type' => 'text' ),
		'note'     => array( 'label' => __( 'Worth knowing', 'abyss' ), 'type' => 'textarea' ),
		'url'      => array( 'label' => __( 'Affiliate URL', 'abyss' ), 'type' => 'url' ),
		'cta'      => array( 'label' => __( 'Button label (default: Open account)', 'abyss' ), 'type' => 'text' ),
		'snapshot' => array( 'label' => __( 'Show in homepage rate snapshot', 'abyss' ), 'type' => 'checkbox' ),
		'snapkey'  => array( 'label' => __( 'Snapshot label (e.g. Savings)', 'abyss' ), 'type' => 'text' ),
	);
}

function abyss_pick_fields() {
	return array(
		'kicker'   => array( 'label' => __( 'Kicker (e.g. Best coding assistant)', 'abyss' ), 'type' => 'text' ),
		'price'    => array( 'label' => __( 'Price (e.g. $249 or $20/mo)', 'abyss' ), 'type' => 'text' ),
		'merchant' => array( 'label' => __( 'Merchant (e.g. Amazon)', 'abyss' ), 'type' => 'text' ),
		'url'      => array( 'label' => __( 'Affiliate URL', 'abyss' ), 'type' => 'url' ),
		'review'   => array( 'label' => __( 'Review post ID (optional)', 'abyss' ), 'type' => 'text' ),
	);
}

function abyss_meta_boxes() {
	add_meta_box( 'abyss-offer', __( 'Offer details', 'abyss' ), 'abyss_offer_box', 'abyss_offer', 'normal', 'high' );
	add_meta_box( 'abyss-pick', __( 'Pick details', 'abyss' ), 'abyss_pick_box', 'abyss_pick', 'normal', 'high' );
	add_meta_box( 'abyss-article', __( 'Article extras', 'abyss' ), 'abyss_article_box', 'post', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'abyss_meta_boxes' );

function abyss_render_fields( $post, $fields, $prefix ) {
	wp_nonce_field( 'abyss_save_meta', 'abyss_meta_nonce' );

	echo '<div style="display:grid;gap:16px;max-width:720px">';

	foreach ( $fields as $key => $field ) {
		$name  = '_abyss_' . $prefix . '_' . $key;
		$value = get_post_meta( $post->ID, $name, true );

		echo '<p style="margin:0"><label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $field['label'] ) . '</strong></label><br />';

		if ( 'textarea' === $field['type'] ) {
			echo '<textarea class="widefat" rows="2" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
		} elseif ( 'select' === $field['type'] ) {
			echo '<select class="widefat" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( $field['choices'] as $choice => $label ) {
				echo '<option value="' . esc_attr( $choice ) . '" ' . selected( $value, $choice, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		} elseif ( 'checkbox' === $field['type'] ) {
			echo '<input type="checkbox" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( $value, '1', false ) . ' />';
		} else {
			echo '<input class="widefat" type="text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
		}

		echo '</p>';
	}

	echo '</div>';
}

function abyss_offer_box( $post ) {
	abyss_render_fields( $post, abyss_offer_fields(), 'offer' );
}

function abyss_pick_box( $post ) {
	abyss_render_fields( $post, abyss_pick_fields(), 'pick' );
}

function abyss_article_box( $post ) {
	wp_nonce_field( 'abyss_save_meta', 'abyss_meta_nonce' );

	$dek = get_post_meta( $post->ID, '_abyss_post_dek', true );
	$aff = get_post_meta( $post->ID, '_abyss_post_affiliate', true );

	echo '<p><label for="_abyss_post_dek"><strong>' . esc_html__( 'Standfirst / dek', 'abyss' ) . '</strong></label>';
	echo '<textarea class="widefat" rows="3" id="_abyss_post_dek" name="_abyss_post_dek">' . esc_textarea( $dek ) . '</textarea>';
	echo '<span class="description">' . esc_html__( 'Falls back to the excerpt.', 'abyss' ) . '</span></p>';

	echo '<p><label><input type="checkbox" name="_abyss_post_affiliate" value="1" ' . checked( $aff, '1', false ) . ' /> ';
	echo esc_html__( 'This article contains affiliate links', 'abyss' ) . '</label></p>';
}

function abyss_save_meta( $post_id ) {
	if ( ! isset( $_POST['abyss_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['abyss_meta_nonce'] ), 'abyss_save_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$map = array(
		'offer' => abyss_offer_fields(),
		'pick'  => abyss_pick_fields(),
	);

	foreach ( $map as $prefix => $fields ) {
		foreach ( $fields as $key => $field ) {
			$name = '_abyss_' . $prefix . '_' . $key;

			if ( 'checkbox' === $field['type'] ) {
				update_post_meta( $post_id, $name, isset( $_POST[ $name ] ) ? '1' : '' );
				continue;
			}

			// A select is only ever one of its own choices. Anything else is a
			// forged POST, and is dropped rather than stored.
			if ( 'select' === $field['type'] ) {
				$choice = isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';

				if ( isset( $field['choices'][ $choice ] ) ) {
					update_post_meta( $post_id, $name, $choice );
				}

				continue;
			}

			if ( ! isset( $_POST[ $name ] ) ) {
				continue;
			}

			$raw = wp_unslash( $_POST[ $name ] );

			if ( 'url' === $field['type'] ) {
				update_post_meta( $post_id, $name, esc_url_raw( $raw ) );
			} elseif ( 'textarea' === $field['type'] ) {
				update_post_meta( $post_id, $name, sanitize_textarea_field( $raw ) );
			} else {
				update_post_meta( $post_id, $name, sanitize_text_field( $raw ) );
			}
		}
	}

	if ( isset( $_POST['_abyss_post_dek'] ) ) {
		update_post_meta( $post_id, '_abyss_post_dek', sanitize_textarea_field( wp_unslash( $_POST['_abyss_post_dek'] ) ) );
	}

	if ( 'post' === get_post_type( $post_id ) ) {
		update_post_meta( $post_id, '_abyss_post_affiliate', isset( $_POST['_abyss_post_affiliate'] ) ? '1' : '' );
	}
}
add_action( 'save_post', 'abyss_save_meta' );

/* -------------------------------------------------------------------------
 * Customizer
 * ---------------------------------------------------------------------- */

function abyss_customize( $wp_customize ) {
	$wp_customize->add_panel( 'abyss', array(
		'title'    => __( 'Abyss theme', 'abyss' ),
		'priority' => 20,
	) );

	/* Brand ------------------------------------------------------------- */
	$wp_customize->add_section( 'abyss_brand', array(
		'title' => __( 'Brand &amp; palette', 'abyss' ),
		'panel' => 'abyss',
	) );

	/*
	 * The palette picker was removed 2026-08-01 when the theme was normalised to
	 * one scheme. See abyss_palettes() for why a selectable palette and a
	 * contrast guarantee cannot both be true.
	 */

	$wp_customize->add_setting( 'abyss_tagline', array(
		'default'           => __( 'AI and money, explained plainly. We buy what we test, we say what we earn, and we write for people who read both lanes.', 'abyss' ),
		'sanitize_callback' => 'wp_kses_post',
	) );
	$wp_customize->add_control( 'abyss_tagline', array(
		'label'   => __( 'Footer description', 'abyss' ),
		'section' => 'abyss_brand',
		'type'    => 'textarea',
	) );

	/* Header CTA -------------------------------------------------------- */
	$wp_customize->add_section( 'abyss_cta', array(
		'title' => __( 'Header CTA', 'abyss' ),
		'panel' => 'abyss',
	) );

	$wp_customize->add_setting( 'abyss_cta_label', array(
		'default'           => __( 'Get the brief', 'abyss' ),
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'abyss_cta_label', array(
		'label'   => __( 'Button label', 'abyss' ),
		'section' => 'abyss_cta',
	) );

	$wp_customize->add_setting( 'abyss_cta_url', array(
		'default'           => '#newsletter',
		'sanitize_callback' => 'abyss_sanitize_url_or_hash',
	) );
	$wp_customize->add_control( 'abyss_cta_url', array(
		'label'   => __( 'Button URL', 'abyss' ),
		'section' => 'abyss_cta',
	) );

	/* Ticker ------------------------------------------------------------ */
	$wp_customize->add_section( 'abyss_ticker', array(
		'title'       => __( 'Live ticker', 'abyss' ),
		'description' => __( 'One item per line: label | value | change | up or down', 'abyss' ),
		'panel'       => 'abyss',
	) );

	$wp_customize->add_setting( 'abyss_ticker_on', array(
		'default'           => true,
		'sanitize_callback' => 'abyss_sanitize_bool',
	) );
	$wp_customize->add_control( 'abyss_ticker_on', array(
		'label'   => __( 'Show the ticker', 'abyss' ),
		'section' => 'abyss_ticker',
		'type'    => 'checkbox',
	) );

	$wp_customize->add_setting( 'abyss_ticker_items', array(
		'default'           => abyss_default_ticker(),
		'sanitize_callback' => 'sanitize_textarea_field',
	) );
	$wp_customize->add_control( 'abyss_ticker_items', array(
		'label'   => __( 'Ticker items', 'abyss' ),
		'section' => 'abyss_ticker',
		'type'    => 'textarea',
	) );

	/* Disclosure -------------------------------------------------------- */
	$wp_customize->add_section( 'abyss_disclosure', array(
		'title' => __( 'Affiliate disclosure', 'abyss' ),
		'panel' => 'abyss',
	) );

	$wp_customize->add_setting( 'abyss_disclosure_style', array(
		'default'           => 'minimal',
		'sanitize_callback' => 'sanitize_key',
	) );
	$wp_customize->add_control( 'abyss_disclosure_style', array(
		'label'   => __( 'Style', 'abyss' ),
		'section' => 'abyss_disclosure',
		'type'    => 'select',
		'choices' => array(
			'minimal' => __( 'Minimal legal line', 'abyss' ),
			'loud'    => __( 'Prominent "how we make money" bar', 'abyss' ),
			'off'     => __( 'Hidden (not recommended)', 'abyss' ),
		),
	) );

	$wp_customize->add_setting( 'abyss_disclosure_text', array(
		'default'           => __( 'Some links on this page are affiliate links. If you open an account or buy through one, we may earn a commission. It never changes what we recommend or the order we list it in.', 'abyss' ),
		'sanitize_callback' => 'wp_kses_post',
	) );
	$wp_customize->add_control( 'abyss_disclosure_text', array(
		'label'   => __( 'Disclosure text', 'abyss' ),
		'section' => 'abyss_disclosure',
		'type'    => 'textarea',
	) );

	$wp_customize->add_setting( 'abyss_disclosure_link', array(
		'default'           => '',
		'sanitize_callback' => 'abyss_sanitize_url_or_hash',
	) );
	$wp_customize->add_control( 'abyss_disclosure_link', array(
		'label'   => __( 'Full policy page URL', 'abyss' ),
		'section' => 'abyss_disclosure',
	) );

	/* Newsletter -------------------------------------------------------- */
	$wp_customize->add_section( 'abyss_newsletter', array(
		'title' => __( 'Newsletter block', 'abyss' ),
		'panel' => 'abyss',
	) );

	$wp_customize->add_setting( 'abyss_news_title', array(
		'default'           => __( 'One email. Both lanes. Six minutes, before the open.', 'abyss' ),
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'abyss_news_title', array(
		'label'   => __( 'Headline', 'abyss' ),
		'section' => 'abyss_newsletter',
	) );

	$wp_customize->add_setting( 'abyss_news_copy', array(
		'default'           => __( 'The five things in AI and money that moved overnight, what each one costs or saves you, and every term explained the first time we use it.', 'abyss' ),
		'sanitize_callback' => 'wp_kses_post',
	) );
	$wp_customize->add_control( 'abyss_news_copy', array(
		'label'   => __( 'Supporting copy', 'abyss' ),
		'section' => 'abyss_newsletter',
		'type'    => 'textarea',
	) );

	$wp_customize->add_setting( 'abyss_news_shortcode', array(
		'default'           => '',
		'sanitize_callback' => 'wp_kses_post',
	) );
	$wp_customize->add_control( 'abyss_news_shortcode', array(
		'label'       => __( 'Signup form shortcode', 'abyss' ),
		'description' => __( 'Paste a Mailchimp / MailPoet / Kit shortcode. Leave blank to show the built-in placeholder form.', 'abyss' ),
		'section'     => 'abyss_newsletter',
		'type'        => 'textarea',
	) );

	$wp_customize->add_setting( 'abyss_news_fine', array(
		'default'           => __( 'Free, weekdays at 7:30 ET. One click to leave. We never sell the list.', 'abyss' ),
		'sanitize_callback' => 'wp_kses_post',
	) );
	$wp_customize->add_control( 'abyss_news_fine', array(
		'label'   => __( 'Small print', 'abyss' ),
		'section' => 'abyss_newsletter',
		'type'    => 'textarea',
	) );

	/* Homepage ---------------------------------------------------------- */
	$wp_customize->add_section( 'abyss_home', array(
		'title' => __( 'Homepage sections', 'abyss' ),
		'panel' => 'abyss',
	) );

	$wp_customize->add_setting( 'abyss_home_snapshot', array(
		'default'           => true,
		'sanitize_callback' => 'abyss_sanitize_bool',
	) );
	$wp_customize->add_control( 'abyss_home_snapshot', array(
		'label'   => __( 'Show rate snapshot beside the lead story', 'abyss' ),
		'section' => 'abyss_home',
		'type'    => 'checkbox',
	) );

	$wp_customize->add_setting( 'abyss_home_lanes', array(
		'default'           => true,
		'sanitize_callback' => 'abyss_sanitize_bool',
	) );
	$wp_customize->add_control( 'abyss_home_lanes', array(
		'label'   => __( 'Show the "what are you trying to decide?" lanes', 'abyss' ),
		'section' => 'abyss_home',
		'type'    => 'checkbox',
	) );

	$wp_customize->add_setting( 'abyss_home_table', array(
		'default'           => true,
		'sanitize_callback' => 'abyss_sanitize_bool',
	) );
	$wp_customize->add_control( 'abyss_home_table', array(
		'label'   => __( 'Show the comparison table', 'abyss' ),
		'section' => 'abyss_home',
		'type'    => 'checkbox',
	) );

	$wp_customize->add_setting( 'abyss_table_title', array(
		'default'           => __( 'Best high-yield savings accounts', 'abyss' ),
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'abyss_table_title', array(
		'label'   => __( 'Comparison table heading', 'abyss' ),
		'section' => 'abyss_home',
	) );

	$wp_customize->add_setting( 'abyss_table_lede', array(
		'default'           => __( 'APY means annual percentage yield &mdash; the rate after compounding, which is what you actually earn in a year. Sort by whichever column decides it for you.', 'abyss' ),
		'sanitize_callback' => 'wp_kses_post',
	) );
	$wp_customize->add_control( 'abyss_table_lede', array(
		'label'   => __( 'Comparison table intro', 'abyss' ),
		'section' => 'abyss_home',
		'type'    => 'textarea',
	) );

	$wp_customize->add_setting( 'abyss_home_picks', array(
		'default'           => true,
		'sanitize_callback' => 'abyss_sanitize_bool',
	) );
	$wp_customize->add_control( 'abyss_home_picks', array(
		'label'   => __( 'Show product picks', 'abyss' ),
		'section' => 'abyss_home',
		'type'    => 'checkbox',
	) );
}
add_action( 'customize_register', 'abyss_customize' );

function abyss_sanitize_bool( $value ) {
	return (bool) $value;
}

function abyss_sanitize_url_or_hash( $value ) {
	$value = trim( (string) $value );

	if ( '' !== $value && '#' === $value[0] ) {
		return sanitize_text_field( $value );
	}

	return esc_url_raw( $value );
}

function abyss_default_ticker() {
	$lines = array(
		'S&P 500 | 6,842.19 | +0.4% | up',
		'Nasdaq | 23,118.60 | +0.7% | up',
		'NVDA | 214.35 | -1.2% | down',
		'10-yr Treasury | 4.12% | +3 bp | up',
		'Shipped today | Opus 5 | general availability | up',
		'Best savings APY | 4.35% | today | up',
		'API price / Mtok | $2.40 | -20% | down',
		'SWE-bench top score | 78.4% | +1.9 pts | up',
		'H200 spot | $2.94/hr | -8.0% | down',
		'30-yr fixed | 6.48% | -6 bp | down',
	);

	return implode( "\n", $lines );
}

function abyss_ticker_items() {
	$raw   = (string) get_theme_mod( 'abyss_ticker_items', abyss_default_ticker() );
	$items = array();

	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );

		if ( '' === $line ) {
			continue;
		}

		$parts = array_map( 'trim', explode( '|', $line ) );

		$items[] = array(
			'label' => isset( $parts[0] ) ? $parts[0] : '',
			'value' => isset( $parts[1] ) ? $parts[1] : '',
			'delta' => isset( $parts[2] ) ? $parts[2] : '',
			'down'  => isset( $parts[3] ) && 'down' === strtolower( $parts[3] ),
		);
	}

	return $items;
}

/* -------------------------------------------------------------------------
 * Template helpers
 * ---------------------------------------------------------------------- */

function abyss_read_time( $post = null ) {
	$post    = get_post( $post );
	$words   = str_word_count( wp_strip_all_tags( (string) $post->post_content ) );
	$minutes = max( 1, (int) ceil( $words / 220 ) );

	/* translators: %d: reading time in minutes. */
	return sprintf( _n( '%d min read', '%d min read', $minutes, 'abyss' ), $minutes );
}

function abyss_dek( $post = null ) {
	$post = get_post( $post );
	$dek  = get_post_meta( $post->ID, '_abyss_post_dek', true );

	if ( ! $dek ) {
		$dek = get_the_excerpt( $post );
	}

	return $dek;
}

function abyss_kicker( $post = null ) {
	$post       = get_post( $post );
	$categories = get_the_category( $post->ID );

	if ( empty( $categories ) ) {
		return '';
	}

	return $categories[0]->name;
}

function abyss_affiliate_link( $url, $label, $classes = 'btn btn--primary btn--small' ) {
	if ( ! $url ) {
		return '';
	}

	return sprintf(
		'<a class="%1$s" href="%2$s" rel="sponsored nofollow noopener" target="_blank">%3$s</a>',
		esc_attr( $classes ),
		esc_url( $url ),
		esc_html( $label )
	);
}

function abyss_offers( $limit = 8 ) {
	return get_posts( array(
		'post_type'      => 'abyss_offer',
		'posts_per_page' => $limit,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
	) );
}

function abyss_offer_rows( $limit = 8 ) {
	$rows = array();

	foreach ( abyss_offers( $limit ) as $offer ) {
		$apy = (float) get_post_meta( $offer->ID, '_abyss_offer_apy', true );
		$min = (float) get_post_meta( $offer->ID, '_abyss_offer_minimum', true );
		$fee = (float) get_post_meta( $offer->ID, '_abyss_offer_fee', true );

		$rows[] = array(
			'id'       => $offer->ID,
			'kind'     => get_post_meta( $offer->ID, '_abyss_offer_kind', true ) ?: 'savings',
			'name'     => get_the_title( $offer ),
			'apy'      => $apy,
			'unit'     => get_post_meta( $offer->ID, '_abyss_offer_unit', true ),
			'minimum'  => $min,
			'fee'      => $fee,
			'note'     => get_post_meta( $offer->ID, '_abyss_offer_note', true ),
			'url'      => get_post_meta( $offer->ID, '_abyss_offer_url', true ),
			'cta'      => get_post_meta( $offer->ID, '_abyss_offer_cta', true ),
			'snapshot' => '1' === get_post_meta( $offer->ID, '_abyss_offer_snapshot', true ),
			'snapkey'  => get_post_meta( $offer->ID, '_abyss_offer_snapkey', true ),
		);
	}

	usort( $rows, function ( $a, $b ) {
		if ( $a['apy'] === $b['apy'] ) {
			return 0;
		}

		return ( $a['apy'] < $b['apy'] ) ? 1 : -1;
	} );

	return $rows;
}

function abyss_money( $value ) {
	if ( 0.0 === (float) $value ) {
		return __( 'None', 'abyss' );
	}

	return '$' . number_format_i18n( (float) $value, ( (float) $value == (int) $value ) ? 0 : 2 );
}

function abyss_excerpt_words( $post = null, $words = 26 ) {
	return wp_trim_words( get_the_excerpt( $post ), $words, '&hellip;' );
}

/* -------------------------------------------------------------------------
 * Housekeeping
 * ---------------------------------------------------------------------- */

function abyss_excerpt_more() {
	return '&hellip;';
}
add_filter( 'excerpt_more', 'abyss_excerpt_more' );

function abyss_body_classes( $classes ) {
	$classes[] = abyss_is_dark() ? 'is-dark' : 'is-light';

	return $classes;
}
add_filter( 'body_class', 'abyss_body_classes' );

function abyss_pagination() {
	the_posts_pagination( array(
		'mid_size'  => 1,
		'prev_text' => __( 'Previous', 'abyss' ),
		'next_text' => __( 'Next', 'abyss' ),
		'class'     => 'pagination',
	) );
}

/**
 * Drop core's archive title prefix.
 *
 * archive.php prints the archive kind as its own kicker directly above the
 * title, so the built-in prefix produced "Category" over "Category: Finance".
 * Removed here rather than string-replaced in the template, because the prefix
 * is translated and matching it by hand breaks in every locale but this one.
 */
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
