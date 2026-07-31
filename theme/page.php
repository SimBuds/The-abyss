<?php
/**
 * Static page.
 *
 * Deliberately not a copy of single.php with pieces deleted. A page is not a
 * dated article: it carries no category kicker, no publication line, no author
 * byline, and no tags, because none of those are true of an About or a
 * disclosure page. The affiliate and FTC disclosures PLAN.md requires will live
 * on templates rendered by this file, and a disclosure stamped with a byline and
 * a review date would misrepresent what it is.
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
				<?php the_title( '<h1>', '</h1>' ); ?>
			</header>

			<?php
			/*
			 * Decorative, on the same reasoning as single.php: the page title
			 * carries the meaning. See PLAN.md for the open question about
			 * charts and diagrams, which are not decorative and will need this
			 * revisited across every template at once.
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
				 * A long page split with the <!--nextpage--> block needs its own
				 * pagination. Without this the reader reaches the end of part one
				 * with no way forward and no indication there is more.
				 */
				wp_link_pages(
					array(
						'before' => '<nav class="abyss-page-links" aria-label="' . esc_attr__( 'Page sections', 'the-abyss' ) . '">',
						'after'  => '</nav>',
					)
				);
				?>
			</div>
		</article>
		<?php
	endwhile;
	?>
</main>
<?php
get_footer();
