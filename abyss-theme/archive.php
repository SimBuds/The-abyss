<?php
/**
 * Category, tag, author and date archives.
 *
 * @package Abyss
 */

get_header();
?>
<section class="wrap arch__head">
	<p class="kick"><?php esc_html_e( 'Section', 'abyss' ); ?></p>
	<h1 class="arch__title" style="margin-top:12px"><?php echo esc_html( get_the_archive_title() ); ?></h1>

	<?php if ( get_the_archive_description() ) : ?>
		<div class="sec-lede"><?php echo wp_kses_post( get_the_archive_description() ); ?></div>
	<?php endif; ?>
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
		<p><?php esc_html_e( 'Nothing here yet.', 'abyss' ); ?></p>
	<?php endif; ?>
</section>

<?php
get_template_part( 'template-parts/newsletter' );
get_footer();
