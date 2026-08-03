<?php
/**
 * Search results.
 *
 * @package Abyss
 */

get_header();
?>
<section class="wrap arch__head">
	<?php abyss_breadcrumbs(); ?>
	<h1 class="arch__title"><?php
		/* translators: %s: search query. */
		printf( esc_html__( 'Results for %s', 'abyss' ), '&ldquo;' . esc_html( get_search_query() ) . '&rdquo;' );
	?></h1>
	<div style="margin-top:24px;max-width:520px"><?php get_search_form(); ?></div>
</section>

<section class="wrap section">
	<?php if ( have_posts() ) : ?>
		<div class="g3">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/card' );
			endwhile;
			?>
		</div>

		<?php abyss_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'No matches. Try a broader term.', 'abyss' ); ?></p>
	<?php endif; ?>
</section>

<?php get_footer();
