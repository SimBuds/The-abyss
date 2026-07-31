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

	<?php
	/*
	 * Archives and search results get a heading so the page has an H1 and the
	 * listing is not an unlabelled grid. The blog home does not, because the site
	 * title in the header already serves that role there.
	 *
	 * Tested on is_archive() specifically, not on "everything that is not the
	 * home page". This template is also the fallback for search results, where
	 * get_the_archive_title() falls through its whole conditional chain and
	 * returns the literal string "Archives", so a search for "bitcoin" would
	 * have been titled "Archives". Not-found pages used to land here too and
	 * hit the same bug; 404.php now takes them.
	 *
	 * Deliberately OUTSIDE the have_posts() check. A search that matches nothing
	 * is the case a reader most needs the heading for: it is the only thing that
	 * tells them which query returned nothing, and without it the page has no H1
	 * at all.
	 */
	if ( is_archive() ) :
		?>
		<header class="abyss-article__header">
			<?php the_archive_title( '<h1>', '</h1>' ); ?>
			<?php the_archive_description( '<p class="abyss-article__meta">', '</p>' ); ?>
		</header>
		<?php
	elseif ( is_search() ) :
		?>
		<header class="abyss-article__header">
			<h1>
				<?php
				printf(
					/* translators: %s: the search query. */
					esc_html__( 'Search results for: %s', 'the-abyss' ),
					'<em>' . esc_html( get_search_query() ) . '</em>'
				);
				?>
			</h1>
		</header>
		<?php
	endif;
	?>

	<?php if ( have_posts() ) : ?>

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

		<?php
		/*
		 * "Nothing here yet" is true of an empty blog and misleading of a search
		 * that matched nothing, where the reader wants to know the query ran and
		 * failed rather than that the site is empty. The search form is offered
		 * back so the next attempt does not need the back button.
		 */
		if ( is_search() ) :
			?>
			<p><?php esc_html_e( 'No posts matched that search. Try a different term.', 'the-abyss' ); ?></p>
			<?php
			get_search_form();
		else :
			?>
			<p><?php esc_html_e( 'Nothing here yet.', 'the-abyss' ); ?></p>
			<?php
		endif;
		?>

	<?php endif; ?>

</main>
<?php
get_footer();
