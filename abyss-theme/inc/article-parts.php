<?php
/**
 * Article furniture: breadcrumbs, author box, share links, table of contents.
 *
 * Kept out of functions.php because these are four separable features that all
 * render markup, and functions.php is already long enough that adding four more
 * blocks to it would make finding any of them harder.
 *
 * @package Abyss
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Breadcrumbs
 * ---------------------------------------------------------------------- */

/**
 * Build the trail as data, so the visible markup and the schema below cannot
 * disagree. They are generated from the same array rather than written twice.
 *
 * @return array List of [ 'label' => string, 'url' => string|'' ].
 */
function abyss_breadcrumb_trail() {
	$trail = array(
		array(
			'label' => __( 'Home', 'abyss' ),
			'url'   => home_url( '/' ),
		),
	);

	if ( is_singular( 'post' ) ) {
		$categories = get_the_category();

		if ( $categories ) {
			/*
			 * The primary category, chosen the same way WordPress chooses one
			 * for a permalink: lowest term_id. Picking by name or by array
			 * order would give a different answer on a post in two categories,
			 * and this trail would then disagree with the post's own URL.
			 */
			usort(
				$categories,
				function ( $a, $b ) {
					return $a->term_id <=> $b->term_id;
				}
			);

			$trail[] = array(
				'label' => $categories[0]->name,
				'url'   => get_category_link( $categories[0] ),
			);
		}

		$trail[] = array(
			'label' => get_the_title(),
			'url'   => '',
		);
	} elseif ( is_page() ) {
		foreach ( array_reverse( get_post_ancestors( get_the_ID() ) ) as $ancestor ) {
			$trail[] = array(
				'label' => get_the_title( $ancestor ),
				'url'   => get_permalink( $ancestor ),
			);
		}

		$trail[] = array(
			'label' => get_the_title(),
			'url'   => '',
		);
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$trail[] = array(
			'label' => wp_strip_all_tags( get_the_archive_title() ),
			'url'   => '',
		);
	} elseif ( is_search() ) {
		$trail[] = array(
			/* translators: %s: search query. */
			'label' => sprintf( __( 'Results for %s', 'abyss' ), get_search_query() ),
			'url'   => '',
		);
	} elseif ( is_404() ) {
		$trail[] = array(
			'label' => __( 'Not found', 'abyss' ),
			'url'   => '',
		);
	}

	return $trail;
}

/**
 * Visible breadcrumbs.
 *
 * A single-item trail is just a link to the home page from the home page, so it
 * renders nothing rather than a stub.
 */
function abyss_breadcrumbs() {
	$trail = abyss_breadcrumb_trail();

	if ( count( $trail ) < 2 ) {
		return;
	}

	echo '<nav class="crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'abyss' ) . '"><ol>';

	foreach ( $trail as $index => $crumb ) {
		$last = ( count( $trail ) - 1 ) === $index;

		echo '<li>';

		if ( $crumb['url'] && ! $last ) {
			printf( '<a href="%s">%s</a>', esc_url( $crumb['url'] ), esc_html( $crumb['label'] ) );
		} else {
			/* The current page is not a link, and says so to a screen reader. */
			printf( '<span aria-current="page">%s</span>', esc_html( $crumb['label'] ) );
		}

		echo '</li>';
	}

	echo '</ol></nav>';
}

/**
 * BreadcrumbList schema, from the same trail as the visible markup.
 *
 * Rank Math's breadcrumb module is off, so nothing else emits this. If that
 * module is ever switched on, turn this off — two BreadcrumbList blocks on one
 * page is an error rather than a tie-break, the same way two Article blocks are.
 */
function abyss_breadcrumb_schema() {
	if ( is_front_page() ) {
		return;
	}

	$trail = abyss_breadcrumb_trail();

	if ( count( $trail ) < 2 ) {
		return;
	}

	$items = array();

	foreach ( $trail as $index => $crumb ) {
		$item = array(
			'@type'    => 'ListItem',
			'position' => $index + 1,
			'name'     => $crumb['label'],
		);

		if ( $crumb['url'] ) {
			$item['item'] = $crumb['url'];
		}

		$items[] = $item;
	}

	$schema = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	);

	echo '<script type="application/ld+json">'
		. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		. '</script>';
}
add_action( 'wp_footer', 'abyss_breadcrumb_schema' );

/* -------------------------------------------------------------------------
 * Author box
 * ---------------------------------------------------------------------- */

/**
 * The author's avatar, without calling Gravatar.
 *
 * get_avatar() fetches from gravatar.com, which would make every article view a
 * request to a third party carrying the reader's IP and referrer. This theme
 * currently makes no third-party requests at all — the fonts were self-hosted
 * for the same reason — and an author photo is not worth giving that up,
 * particularly on a site that will need a cookie banner.
 *
 * So: initials, rendered locally. Filterable, so a real photo added to the
 * media library later can replace it without touching this file.
 *
 * @param int $user_id User ID.
 * @return string HTML.
 */
function abyss_author_avatar( $user_id ) {
	$custom = apply_filters( 'abyss_author_avatar', '', $user_id );

	if ( $custom ) {
		return $custom;
	}

	$name = get_the_author_meta( 'display_name', $user_id );
	$bits = preg_split( '/\s+/', trim( $name ) );
	$initials = '';

	foreach ( array_slice( $bits, 0, 2 ) as $bit ) {
		$initials .= function_exists( 'mb_substr' ) ? mb_substr( $bit, 0, 1 ) : substr( $bit, 0, 1 );
	}

	return '<span class="authorbox__initials" aria-hidden="true">'
		. esc_html( strtoupper( $initials ) )
		. '</span>';
}

/**
 * Author box.
 *
 * Renders nothing when the profile has no biography. An author box that says
 * only the person's name adds a bordered box and no information, and the point
 * of the box is the credential, not the decoration.
 */
function abyss_author_box() {
	$user_id = (int) get_the_author_meta( 'ID' );
	$bio     = get_the_author_meta( 'description', $user_id );

	if ( ! $bio ) {
		return;
	}
	?>
	<aside class="authorbox">
		<div class="authorbox__avatar"><?php echo wp_kses_post( abyss_author_avatar( $user_id ) ); ?></div>

		<div class="authorbox__body">
			<p class="kick"><?php esc_html_e( 'Written by', 'abyss' ); ?></p>
			<p class="authorbox__name"><?php echo esc_html( get_the_author_meta( 'display_name', $user_id ) ); ?></p>
			<p class="authorbox__bio"><?php echo esc_html( $bio ); ?></p>
		</div>
	</aside>
	<?php
}

/* -------------------------------------------------------------------------
 * Share links
 * ---------------------------------------------------------------------- */

/**
 * Share links, as plain anchors.
 *
 * No third-party share widgets: each one is a script from another origin that
 * sees every reader of every article. These are ordinary links to the same
 * share endpoints those widgets use, which cost nothing and track nobody until
 * the reader actually clicks.
 */
function abyss_share_links() {
	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( get_the_title() );

	$targets = array(
		'Bluesky'  => "https://bsky.app/intent/compose?text={$title}%20{$url}",
		'X'        => "https://twitter.com/intent/tweet?url={$url}&text={$title}",
		'LinkedIn' => "https://www.linkedin.com/sharing/share-offsite/?url={$url}",
		'Reddit'   => "https://www.reddit.com/submit?url={$url}&title={$title}",
		'Email'    => "mailto:?subject={$title}&body={$url}",
	);
	?>
	<div class="share">
		<p class="share__label"><?php esc_html_e( 'Share', 'abyss' ); ?></p>
		<ul class="share__list">
			<?php foreach ( $targets as $label => $href ) : ?>
				<li>
					<a class="share__link" href="<?php echo esc_url( $href ); ?>"
						<?php if ( 'Email' !== $label ) : ?>
							target="_blank" rel="noopener noreferrer"
						<?php endif; ?>>
						<?php
						printf(
							/* translators: %s: network name. */
							esc_html__( 'Share on %s', 'abyss' ),
							esc_html( $label )
						);
						?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Table of contents
 * ---------------------------------------------------------------------- */

/**
 * Give every h2 and h3 in post content a stable id, so the contents list can
 * link to them.
 *
 * Runs on the_content at priority 9, before wpautop and before the compliance
 * disclosure is prepended, so it only ever sees the author's own headings.
 *
 * @param string $content Post content.
 * @return string
 */
function abyss_add_heading_ids( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	if ( doing_filter( 'get_the_excerpt' ) ) {
		return $content;
	}

	$used = array();

	return preg_replace_callback(
		'/<h([23])([^>]*)>(.*?)<\/h\1>/is',
		function ( $m ) use ( &$used ) {
			/* An id the author set by hand wins; this is a fallback, not a rule. */
			if ( preg_match( '/\sid=["\']/', $m[2] ) ) {
				return $m[0];
			}

			$slug = sanitize_title( wp_strip_all_tags( $m[3] ) );

			if ( ! $slug ) {
				return $m[0];
			}

			/* Two headings with the same words would otherwise share an id. */
			$base = $slug;
			$n    = 2;

			while ( in_array( $slug, $used, true ) ) {
				$slug = $base . '-' . $n;
				$n++;
			}

			$used[] = $slug;

			return '<h' . $m[1] . $m[2] . ' id="' . esc_attr( $slug ) . '">' . $m[3] . '</h' . $m[1] . '>';
		},
		$content
	);
}
add_filter( 'the_content', 'abyss_add_heading_ids', 9 );

/**
 * Contents list, built from the same headings.
 *
 * Below three headings there is nothing to navigate, and a contents list of two
 * items is furniture rather than a tool, so it renders nothing.
 */
function abyss_table_of_contents() {
	$content = get_the_content();

	if ( ! $content ) {
		return;
	}

	if ( ! preg_match_all( '/<h([23])([^>]*)>(.*?)<\/h\1>/is', apply_filters( 'the_content', $content ), $m, PREG_SET_ORDER ) ) {
		return;
	}

	if ( count( $m ) < 3 ) {
		return;
	}
	?>
	<nav class="toc" aria-labelledby="toc-title">
		<p class="kick" id="toc-title"><?php esc_html_e( 'On this page', 'abyss' ); ?></p>
		<ol class="toc__list">
			<?php
			foreach ( $m as $heading ) {
				if ( ! preg_match( '/\sid=["\']([^"\']+)["\']/', $heading[2], $id ) ) {
					continue;
				}

				printf(
					'<li class="toc__item toc__item--h%1$s"><a href="#%2$s">%3$s</a></li>',
					esc_attr( $heading[1] ),
					esc_attr( $id[1] ),
					esc_html( wp_strip_all_tags( $heading[3] ) )
				);
			}
			?>
		</ol>
	</nav>
	<?php
}
