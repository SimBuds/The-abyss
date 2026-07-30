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
				<?php the_content(); ?>
			</div>
		</article>
		<?php
	endwhile;
	?>
</main>
<?php
get_footer();
