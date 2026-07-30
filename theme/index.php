<?php
/**
 * Fallback template, the base of the template hierarchy.
 *
 * Serves the blog home, archives, and search results until each gets its own
 * template. The post list is rendered as the design system's card grid.
 *
 * @package The_abyss
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main">
	<?php if ( have_posts() ) : ?>

		<?php
		/*
		 * A list rather than a bare stack of divs: this is a list of posts, and
		 * assistive technology announces the count. The grid is applied to the
		 * ul, so the semantics and the layout do not fight.
		 */
		?>
		<ul class="abyss-cards">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<li>
					<article <?php post_class( 'abyss-card' ); ?>>
						<?php
						$the_abyss_terms = get_the_category();

						// Omitted entirely when the post has no category, rather
						// than rendering an empty element that still takes a gap.
						if ( ! empty( $the_abyss_terms ) ) :
							?>
							<p class="abyss-card__kicker"><?php echo esc_html( $the_abyss_terms[0]->name ); ?></p>
							<?php
						endif;
						?>

						<h2 class="abyss-card__title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h2>

						<?php
						// has_excerpt() is deliberate. get_the_excerpt() would
						// auto-generate one from the content and never be empty,
						// so the card would always show body text even when the
						// author wrote none.
						if ( has_excerpt() ) :
							?>
							<p class="abyss-card__body"><?php echo esc_html( get_the_excerpt() ); ?></p>
							<?php
						endif;
						?>

						<p class="abyss-card__meta">
							<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
								<?php echo esc_html( get_the_date() ); ?>
							</time>
						</p>
					</article>
				</li>
				<?php
			endwhile;
			?>
		</ul>

		<?php
		the_posts_pagination(
			array(
				'mid_size'  => 1,
				'prev_text' => esc_html__( 'Previous', 'the-abyss' ),
				'next_text' => esc_html__( 'Next', 'the-abyss' ),
			)
		);
		?>

	<?php else : ?>
		<p><?php esc_html_e( 'Nothing here yet.', 'the-abyss' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
