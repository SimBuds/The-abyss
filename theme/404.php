<?php
/**
 * Not found.
 *
 * Until this file existed, index.php served 404s: it ran the main query, found
 * nothing, and printed "Nothing here yet." under no heading at all. A reader
 * following a dead link was told the site was empty rather than that the address
 * was wrong.
 *
 * @package The_abyss
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main">
	<article class="abyss-article">
		<header class="abyss-article__header">
			<h1><?php esc_html_e( 'That page is not here', 'the-abyss' ); ?></h1>
		</header>

		<div class="abyss-article__content">
			<p>
				<?php esc_html_e( 'The address may be mistyped, or the article may have been moved or renamed. Searching usually finds it.', 'the-abyss' ); ?>
			</p>

			<?php get_search_form(); ?>

			<?php
			/*
			 * Recent posts as a second route out. A search only helps a reader who
			 * knows what they were looking for, and someone arriving on a stale
			 * link from elsewhere often does not.
			 *
			 * WP_Query rather than the main query, which on a 404 has already run
			 * and found nothing. wp_reset_postdata() restores the global post
			 * afterwards so get_footer() and anything hooked into it are not left
			 * looking at the last item of this loop.
			 */
			$the_abyss_recent = new WP_Query(
				array(
					'posts_per_page'      => 5,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			);

			if ( $the_abyss_recent->have_posts() ) :
				?>
				<h2><?php esc_html_e( 'Recent articles', 'the-abyss' ); ?></h2>

				<ul>
					<?php
					while ( $the_abyss_recent->have_posts() ) :
						$the_abyss_recent->the_post();
						?>
						<li>
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</li>
						<?php
					endwhile;
					?>
				</ul>
				<?php
			endif;

			wp_reset_postdata();
			?>
		</div>
	</article>
</main>
<?php
get_footer();
