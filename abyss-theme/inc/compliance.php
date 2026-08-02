<?php
/**
 * Compliance, schema, and accessibility carried over from the previous theme.
 *
 * Ported 2026-08-01 when `abyss-theme` replaced `the-abyss`. Kept in its own
 * file rather than merged into functions.php so it stays reviewable as a unit
 * and so a future theme change can carry it forward the same way.
 *
 * Six things were in the old theme and not in this one. Five are requirements
 * or fixes rather than preferences:
 *
 * 1. `rel="sponsored nofollow"` on affiliate links written into article prose.
 *    This theme already handles links built by abyss_affiliate_link(), which
 *    covers offers and picks, but not a link an author types into a paragraph.
 *    PLAN.md requires this be structural rather than a convention to remember.
 * 2. A per-article disclosure above the first paragraph. This theme has a
 *    site-wide footer bar, which is good and stays. It is not a substitute:
 *    both the FTC and the Competition Bureau ask for a disclosure the reader
 *    meets *before* acting on a link, and a bar below the fold is after every
 *    link on the page.
 * 3. Article JSON-LD. The visible byline and reviewed date are readable by a
 *    person and invisible to a search engine.
 * 4. The comment-reply script, without which threaded replies are filed as new
 *    top-level comments.
 * 5. filemtime asset versioning, so an edit is never served from cache.
 *
 * Both 1 and 2 key off one filterable list of monetised domains rather than off
 * "any outbound link". That distinction was learned the expensive way: the first
 * version tagged editorial citations as paid placements and printed a
 * disclosure on articles that earned nothing.
 *
 * @package Abyss
 */

defined( 'ABSPATH' ) || exit;


/**
 * Domains whose links are monetised.
 *
 * Empty by default and filterable, so the list is configuration rather than a
 * code edit. Add hosts through the `abyss_compliance_affiliate_domains` filter as
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
function abyss_compliance_affiliate_domains() {
	$domains = (array) apply_filters( 'abyss_compliance_affiliate_domains', array() );

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
function abyss_compliance_is_affiliate_host( $host ) {
	$host = strtolower( (string) $host );

	if ( '' === $host ) {
		return false;
	}

	foreach ( abyss_compliance_affiliate_domains() as $domain ) {
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
function abyss_compliance_sponsor_outbound_links( $content ) {
	if ( empty( $content ) || false === strpos( $content, '<a ' ) ) {
		return $content;
	}

	// Nothing to do until at least one programme is configured.
	if ( ! abyss_compliance_affiliate_domains() ) {
		return $content;
	}

	// Tags are stripped from an excerpt anyway, so rewriting rel attributes
	// there is wasted work on every card in every listing.
	if ( doing_filter( 'get_the_excerpt' ) ) {
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

			if ( ! abyss_compliance_is_affiliate_host( $host ) ) {
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
add_filter( 'the_content', 'abyss_compliance_sponsor_outbound_links', 20 );

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
function abyss_compliance_affiliate_disclosure( $content ) {
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
	 * Not while an excerpt is being built. wp_trim_excerpt() runs the_content
	 * through this filter and then strips the tags, so without this the notice
	 * is flattened into plain text and glued to the front of the excerpt.
	 *
	 * The three conditions above were written for the previous theme, where no
	 * excerpt was ever generated on a singular page. abyss-theme generates one
	 * for the article dek, so this passed every guard and printed the disclosure
	 * directly under the headline, running into the first sentence with no space
	 * between them. Observed 2026-08-01 by screenshot.
	 */
	if ( doing_filter( 'get_the_excerpt' ) ) {
		return $content;
	}

	/*
	 * Keyed off the affiliate domain list, not off "any outbound link". An
	 * article that cites a news site earns nothing from it, and a disclosure
	 * claiming otherwise is inaccurate in the direction that costs trust: it
	 * trains readers to skip the notice on the articles where it is true.
	 */
	/*
	 * The theme also carries a manual "this post has affiliate links" checkbox
	 * (_abyss_post_affiliate). Honouring it here rather than rendering a second
	 * notice in single.php: an author who ticks the box should get the same
	 * disclosure, in the same place, not a second differently-worded one under
	 * the first. It also covers the case the domain list cannot see — a post
	 * monetised by a discount code rather than by a link.
	 */
	if ( '1' === get_post_meta( get_the_ID(), '_abyss_post_affiliate', true ) ) {
		return abyss_compliance_disclosure_markup() . $content;
	}

	$found = false;

	if ( preg_match_all( '/<a\s[^>]*href=(["\'])(.*?)\1/i', $content, $matches ) ) {
		foreach ( $matches[2] as $href ) {
			if ( abyss_compliance_is_affiliate_host( wp_parse_url( $href, PHP_URL_HOST ) ) ) {
				$found = true;
				break;
			}
		}
	}

	if ( ! $found ) {
		return $content;
	}

	return abyss_compliance_disclosure_markup() . $content;
}
add_filter( 'the_content', 'abyss_compliance_affiliate_disclosure', 21 );


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
function abyss_compliance_structured_data() {
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
		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'abyss-hero' );

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
add_action( 'wp_head', 'abyss_compliance_structured_data' );

/**
 * The disclosure markup itself, in one place.
 *
 * Separated so the automatic path (a link to a monetised domain) and the manual
 * path (the author's checkbox) cannot drift into two different wordings.
 *
 * @return string
 */
function abyss_compliance_disclosure_markup() {
	return sprintf(
		'<aside class="art-disclose"><p><strong>%1$s</strong> %2$s</p></aside>',
		esc_html__( 'Disclosure:', 'abyss' ),
		esc_html__( 'Some links in this article are affiliate links. If you buy through one, this site may earn a commission at no extra cost to you. Commissions never determine which products are covered or what is said about them.', 'abyss' )
	);
}
