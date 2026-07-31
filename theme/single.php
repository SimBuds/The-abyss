<?php
/**
 * Single post.
 *
 * @package The_abyss
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class( 'abyss-article' ); ?>>
			<header class="abyss-article__header">
				<?php
				$the_abyss_terms = get_the_category();

				if ( ! empty( $the_abyss_terms ) ) :
					?>
					<p class="abyss-article__kicker"><?php echo esc_html( $the_abyss_terms[0]->name ); ?></p>
					<?php
				endif;
				?>

				<?php the_title( '<h1>', '</h1>' ); ?>

				<p class="abyss-article__meta">
					<?php
					printf(
						/* translators: 1: publication date, 2: author name */
						esc_html__( 'Published %1$s by %2$s', 'the-abyss' ),
						'<time datetime="' . esc_attr( get_the_date( DATE_W3C ) ) . '">' . esc_html( get_the_date() ) . '</time>',
						esc_html( get_the_author() )
					);

					/*
					 * PLAN.md requires an author and review date on finance
					 * content. The review date shows only when the post has been
					 * edited after publication, so it never claims a review that
					 * did not happen.
					 */
					if ( get_the_modified_date( 'Ymd' ) > get_the_date( 'Ymd' ) ) {
						echo ' &middot; ';
						printf(
							/* translators: %s: last reviewed date */
							esc_html__( 'Reviewed %s', 'the-abyss' ),
							'<time datetime="' . esc_attr( get_the_modified_date( DATE_W3C ) ) . '">' . esc_html( get_the_modified_date() ) . '</time>'
						);
					}
					?>
				</p>
			</header>

			<?php
			/*
			 * Every content photograph goes through the grayscale wrapper, per
			 * the design's do-list. The wrapper carries an aspect ratio so the
			 * layout does not shift while the image loads. Decorative here
			 * because the headline already carries the meaning, so the alt text
			 * is empty and the wrapper is hidden from assistive technology.
			 */
			if ( has_post_thumbnail() ) :
				?>
				<figure class="abyss-article__media grayscale" aria-hidden="true">
					<?php the_post_thumbnail( 'the-abyss-hero', array( 'alt' => '' ) ); ?>
				</figure>
				<?php
			endif;
			?>

			<div class="abyss-article__content">
				<?php
				the_content();

				/*
				 * Same reason as page.php. A long comparison split with
				 * <!--nextpage--> otherwise ends at part one with no way forward
				 * and nothing saying there is more, and long comparisons are
				 * exactly what this site publishes.
				 */
				wp_link_pages(
					array(
						'before' => '<nav class="abyss-page-links" aria-label="' . esc_attr__( 'Article sections', 'the-abyss' ) . '">',
						'after'  => '</nav>',
					)
				);
				?>
			</div>

			<?php
			/*
			 * Post tags, as the first real consumer of .abyss-tag. Categories are
			 * already spoken for by the kicker above, so tags are the finer-grained
			 * axis and belong at the foot of the article rather than the head.
			 *
			 * The list is labelled rather than left as a bare row of links, because
			 * a screen reader reaching it otherwise announces a run of unexplained
			 * link text with no indication of what the words are.
			 */
			$the_abyss_tags = get_the_tags();

			if ( ! empty( $the_abyss_tags ) && ! is_wp_error( $the_abyss_tags ) ) :
				?>
				<footer class="abyss-article__footer">
					<h2 class="screen-reader-text"><?php esc_html_e( 'Tagged', 'the-abyss' ); ?></h2>
					<ul class="abyss-tag-list">
						<?php foreach ( $the_abyss_tags as $the_abyss_tag ) : ?>
							<li>
								<a class="abyss-tag abyss-tag--neutral" href="<?php echo esc_url( get_tag_link( $the_abyss_tag ) ); ?>">
									<?php echo esc_html( $the_abyss_tag->name ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</footer>
				<?php
			endif;
			?>
		</article>

		<?php
		/*
		 * Guarded, because comments_template() would otherwise load and render
		 * the form on posts where the discussion was never opened. The condition
		 * matches WordPress's own: show it when comments are open, or when the
		 * post already has some and they were closed later.
		 */
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
		<?php
	endwhile;
	?>
</main>
<?php
get_footer();
