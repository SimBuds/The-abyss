<?php
/**
 * Fallback template, the base of the template hierarchy.
 *
 * Serves the blog home, category and tag archives, author archives, and search
 * results until each gets its own template. The listing is a card grid, per the
 * design's modular-grid direction.
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
		 * Archives get a heading so the page has an H1 and the listing is not an
		 * unlabelled grid. The blog home does not, because the site title in the
		 * header already serves that role there.
		 */
		if ( ! is_front_page() && ! is_home() ) :
			?>
			<header class="abyss-article__header">
				<?php the_archive_title( '<h1>', '</h1>' ); ?>
				<?php the_archive_description( '<p class="abyss-article__meta">', '</p>' ); ?>
			</header>
			<?php
		endif;
		?>

		<ul class="abyss-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<li>
					<article <?php post_class( 'abyss-card' ); ?>>

						<?php
						/*
						 * Decorative: the card title carries the meaning and links
						 * to the same place, so a screen reader announcing the
						 * image would repeat it. Sized by aspect ratio so the grid
						 * does not reflow as thumbnails load.
						 */
						if ( has_post_thumbnail() ) :
							?>
							<figure class="abyss-card__media grayscale" aria-hidden="true">
								<?php the_post_thumbnail( 'the-abyss-card', array( 'alt' => '' ) ); ?>
							</figure>
							<?php
						endif;

						$the_abyss_cats = get_the_category();

						if ( ! empty( $the_abyss_cats ) ) :
							?>
							<p class="abyss-card__kicker"><?php echo esc_html( $the_abyss_cats[0]->name ); ?></p>
							<?php
						endif;
						?>

						<h2 class="abyss-card__title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h2>

						<?php if ( has_excerpt() || get_the_content() ) : ?>
							<p class="abyss-card__body"><?php echo esc_html( get_the_excerpt() ); ?></p>
						<?php endif; ?>

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

		<?php the_posts_pagination(); ?>

	<?php else : ?>

		<p><?php esc_html_e( 'Nothing here yet.', 'the-abyss' ); ?></p>

	<?php endif; ?>

</main>
<?php
get_footer();
