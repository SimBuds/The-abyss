<?php
/**
 * Related posts, shown at the foot of a single post.
 *
 * Reuses the card family and the grid rather than introducing a second listing
 * style, so there is one way a post is represented across the whole site.
 *
 * Renders nothing when there is nothing to show, which on a new site is the
 * normal case and should not leave an empty heading behind.
 *
 * @package The_abyss
 */
defined( 'ABSPATH' ) || exit;

$the_abyss_related = the_abyss_related_posts( get_the_ID() );

if ( empty( $the_abyss_related ) ) {
	return;
}
?>
<section class="abyss-related" aria-labelledby="abyss-related-title">
	<h2 class="abyss-related__title" id="abyss-related-title">
		<?php esc_html_e( 'More on this', 'the-abyss' ); ?>
	</h2>

	<ul class="abyss-grid">
		<?php foreach ( $the_abyss_related as $the_abyss_rel ) : ?>
			<li>
				<article class="abyss-card">
					<?php if ( has_post_thumbnail( $the_abyss_rel ) ) : ?>
						<figure class="abyss-card__media grayscale" aria-hidden="true">
							<?php echo get_the_post_thumbnail( $the_abyss_rel, 'the-abyss-card', array( 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core returns escaped markup. ?>
						</figure>
					<?php endif; ?>

					<h3 class="abyss-card__title">
						<a href="<?php echo esc_url( get_permalink( $the_abyss_rel ) ); ?>">
							<?php echo esc_html( get_the_title( $the_abyss_rel ) ); ?>
						</a>
					</h3>

					<p class="abyss-card__meta">
						<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $the_abyss_rel ) ); ?>">
							<?php echo esc_html( get_the_date( '', $the_abyss_rel ) ); ?>
						</time>
					</p>
				</article>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
