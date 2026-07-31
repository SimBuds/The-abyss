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
	//
	// fonts.css is listed first and is not optional. main.css asks for 'Archivo'
	// but the @font-face rules that define it live in fonts.css, so loading main
	// alone left the editor rendering every heading in a system fallback while
	// the front end used the real face. An author would have been composing
	// against type the reader never sees, which is the specific failure editor
	// styles exist to prevent.
	add_theme_support( 'editor-styles' );
	add_editor_style( array( 'assets/css/fonts.css', 'assets/css/main.css' ) );

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
 * Cache-busting version for a theme asset, from its modification time.
 *
 * The theme version in style.css only changes at a release, so during a build
 * every edit to main.css shipped as `?ver=0.1.0` and browsers were entitled to
 * serve the cached copy. That turns "the fix did not work" and "you did not see
 * the fix" into the same symptom, which cost real debugging time at step 7b.
 *
 * filemtime mints a new URL on every save, so an ordinary reload is always
 * current. In production it settles to one stable value per deploy, which is the
 * correct caching behaviour there too, so this is not a development-only hack.
 *
 * Falls back to the theme version if the file is missing, because filemtime
 * warns and returns false on a bad path, and a warning printed before headers
 * would be a worse failure than a stale cache.
 *
 * @param string $rel Path relative to the theme root, with no leading slash.
 * @return string
 */
function the_abyss_asset_version( $rel ) {
	$path = THE_ABYSS_DIR . '/' . $rel;

	return file_exists( $path ) ? (string) filemtime( $path ) : THE_ABYSS_VERSION;
}

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
		the_abyss_asset_version( 'assets/css/fonts.css' )
	);

	wp_enqueue_style(
		'the-abyss-main',
		THE_ABYSS_URI . '/assets/css/main.css',
		array( 'the-abyss-fonts' ),
		the_abyss_asset_version( 'assets/css/main.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'the_abyss_assets' );

/**
 * Emit Article structured data on single posts and pages.
 *
 * PLAN.md lists author and review-date schema for finance content as a live-site
 * requirement. single.php already shows a publication line and, when a post has
 * been edited after publishing, a reviewed date. Those are readable by a person
 * and invisible to a search engine, which is the gap this closes.
 *
 * Deliberately conservative about what it claims:
 *
 * - `Article`, not `NewsArticle` or `Review`. The stricter types carry
 *   expectations this site does not meet, and a type that overstates what a page
 *   is invites a manual action rather than a rich result.
 * - `dateModified` is emitted only when the post really was modified after
 *   publication, matching the visible line exactly. A `dateModified` equal to
 *   `datePublished` on every post is noise that claims freshness nobody earned.
 * - No `aggregateRating`, no `Review`, no `priceRange`. This site will carry
 *   affiliate comparisons, and the temptation to mark them up as ratings is
 *   exactly what Google's structured-data policy on self-serving reviews
 *   prohibits.
 *
 * @return void
 */
function the_abyss_structured_data() {
	if ( ! is_singular( array( 'post', 'page' ) ) ) {
		return;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$data = array(
		'@context'         => 'https://schema.org',
		'@type'            => 'Article',
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id'   => get_permalink( $post ),
		),

		/*
		 * Google truncates headlines past 110 characters, and an over-long one is
		 * a documented reason for a page to be dropped from rich results.
		 * Truncating here rather than trusting every future title to be short.
		 */
		'headline'         => wp_html_excerpt( get_the_title( $post ), 110, '' ),
		'datePublished'    => get_the_date( DATE_W3C, $post ),
		'author'           => array(
			'@type' => 'Person',
			'name'  => get_the_author_meta( 'display_name', $post->post_author ),
		),
		'publisher'        => array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		),
	);

	// Same condition as the visible "Reviewed" line in single.php. If the two
	// ever disagree, the markup is claiming something the page does not say.
	if ( get_the_modified_date( 'Ymd', $post ) > get_the_date( 'Ymd', $post ) ) {
		$data['dateModified'] = get_the_modified_date( DATE_W3C, $post );
	}

	$description = wp_strip_all_tags( get_the_excerpt( $post ) );

	if ( $description ) {
		$data['description'] = $description;
	}

	if ( has_post_thumbnail( $post ) ) {
		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'the-abyss-hero' );

		if ( $image ) {
			$data['image'] = $image[0];
		}
	}

	/*
	 * No JSON_UNESCAPED_SLASHES on purpose. Leaving slashes escaped as \/ is what
	 * makes a literal </script> inside any string value harmless, so the block
	 * cannot be closed early by a post title or an excerpt.
	 */
	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode( $data )
	);
}
add_action( 'wp_head', 'the_abyss_structured_data' );

/**
 * Domains whose links are monetised.
 *
 * Empty by default and filterable, so the list is configuration rather than a
 * code edit. Add hosts through the `the_abyss_affiliate_domains` filter as
 * programmes are joined.
 *
 * This list is what separates a paid link from a citation, and both the `rel`
 * filter and the disclosure key off it. Before it existed, every outbound link
 * was treated as an affiliate link, which marked editorial citations as paid
 * placements and printed an affiliate disclosure on articles that earned
 * nothing. Both are misstatements, and on a site whose credibility is the
 * product they are the expensive kind.
 *
 * @return string[] Lowercased bare domains, no scheme and no leading dot.
 */
function the_abyss_affiliate_domains() {
	$domains = (array) apply_filters( 'the_abyss_affiliate_domains', array() );

	return array_filter( array_map( 'strtolower', $domains ) );
}

/**
 * Whether a host belongs to a monetised domain.
 *
 * Matches subdomains, so `amazon.com` on the list covers `www.amazon.com` and
 * `smile.amazon.com`. The boundary check on the dot is what stops `amazon.com`
 * from also matching a lookalike such as `notamazon.com`.
 *
 * @param string $host Host from a link's href.
 * @return bool
 */
function the_abyss_is_affiliate_host( $host ) {
	$host = strtolower( (string) $host );

	if ( '' === $host ) {
		return false;
	}

	foreach ( the_abyss_affiliate_domains() as $domain ) {
		if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Force rel="sponsored nofollow" on affiliate links in post content.
 *
 * PLAN.md requires this be structural rather than a rule authors remember. A
 * documented convention that depends on tagging every affiliate link by hand
 * will eventually be missed, so it is applied at render time instead.
 *
 * Only links to a domain on the affiliate list are touched. Internal links,
 * anchors, and outbound editorial citations are left exactly as written: a
 * citation is a link this site vouches for, which is the opposite of what
 * `sponsored nofollow` tells a search engine.
 *
 * Existing rel values are preserved and merged rather than overwritten, so a
 * hand-set rel is never silently discarded.
 *
 * @param string $content Post content.
 * @return string
 */
function the_abyss_sponsor_outbound_links( $content ) {
	if ( empty( $content ) || false === strpos( $content, '<a ' ) ) {
		return $content;
	}

	// Nothing to do until at least one programme is configured.
	if ( ! the_abyss_affiliate_domains() ) {
		return $content;
	}

	return preg_replace_callback(
		'/<a\s([^>]+)>/i',
		static function ( $matches ) {
			$attrs = $matches[1];

			if ( ! preg_match( '/href=(["\'])(.*?)\1/i', $attrs, $href ) ) {
				return $matches[0];
			}

			$host = wp_parse_url( $href[2], PHP_URL_HOST );

			if ( ! the_abyss_is_affiliate_host( $host ) ) {
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

/**
 * Prepend an affiliate disclosure to any article that carries an outbound link.
 *
 * PLAN.md requires visible FTC and Competition Bureau disclosures. Both regimes
 * ask for the same thing in substance: a disclosure a reader actually encounters
 * before acting on the link, not one filed at the foot of the page or on a
 * separate policy page.
 *
 * Structural for the same reason the rel filter is. A disclosure that depends on
 * an author remembering to paste it is a disclosure that will eventually be
 * missing from the one post that most needed it, and "we have a documented
 * convention" is not a defence anyone has ever won with.
 *
 * Runs at 21, after the rel filter at 20, so it inspects the same content the
 * reader gets.
 *
 * @param string $content Post content.
 * @return string
 */
function the_abyss_affiliate_disclosure( $content ) {
	/*
	 * Guarded hard. `the_content` runs far more often than a page render: it
	 * fires inside wp_trim_excerpt(), so without this the disclosure would be
	 * baked into every card excerpt on the blog home, and inside feeds and REST
	 * responses too. The three conditions together mean this only ever applies to
	 * the article body of the page actually being read.
	 */
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	/*
	 * Keyed off the affiliate domain list, not off "any outbound link". An
	 * article that cites a news site earns nothing from it, and a disclosure
	 * claiming otherwise is inaccurate in the direction that costs trust: it
	 * trains readers to skip the notice on the articles where it is true.
	 */
	$found = false;

	if ( preg_match_all( '/<a\s[^>]*href=(["\'])(.*?)\1/i', $content, $matches ) ) {
		foreach ( $matches[2] as $href ) {
			if ( the_abyss_is_affiliate_host( wp_parse_url( $href, PHP_URL_HOST ) ) ) {
				$found = true;
				break;
			}
		}
	}

	if ( ! $found ) {
		return $content;
	}

	$notice = sprintf(
		'<aside class="abyss-disclosure"><p><strong>%1$s</strong> %2$s</p></aside>',
		esc_html__( 'Disclosure:', 'the-abyss' ),
		esc_html__( 'Some links in this article are affiliate links. If you buy through one, this site may earn a commission at no extra cost to you. Commissions never determine which products are covered or what is said about them.', 'the-abyss' )
	);

	return $notice . $content;
}
add_filter( 'the_content', 'the_abyss_affiliate_disclosure', 21 );
