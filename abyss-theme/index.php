<?php
/**
 * Fallback archive / blog index.
 *
 * @package Abyss
 */

get_header();
?>
<section class="wrap arch__head">
	<h1 class="arch__title"><?php
		if ( is_home() && ! is_front_page() ) {
			single_post_title();
		} else {
			esc_html_e( 'Latest', 'abyss' );
		}
	?></h1>
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
		<p><?php esc_html_e( 'Nothing published here yet.', 'abyss' ); ?></p>
	<?php endif; ?>
</section>

<?php
get_template_part( 'template-parts/newsletter' );
get_footer();
