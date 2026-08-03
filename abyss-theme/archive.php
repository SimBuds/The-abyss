<?php
/**
 * Category, tag, author and date archives.
 *
 * @package Abyss
 */

get_header();

/*
 * The kicker names the kind of archive, so the title does not have to. Core's
 * own prefix is dropped in functions.php for the same reason: with it, the
 * heading read "Category: Finance" directly under a kicker already reading
 * "Category".
 */
if ( is_category() ) {
	$abyss_arch_kind = __( 'Category', 'abyss' );
} elseif ( is_tag() ) {
	$abyss_arch_kind = __( 'Tag', 'abyss' );
} elseif ( is_author() ) {
	$abyss_arch_kind = __( 'Author', 'abyss' );
} elseif ( is_date() ) {
	$abyss_arch_kind = __( 'Archive', 'abyss' );
} else {
	$abyss_arch_kind = __( 'Section', 'abyss' );
}
?>
<section class="wrap arch__head">
	<?php abyss_breadcrumbs(); ?>
	<p class="kick"><?php echo esc_html( $abyss_arch_kind ); ?></p>
	<?php
	/*
	 * Not esc_html(). get_the_archive_title() returns markup — core wraps the
	 * term name in a <span> — so escaping it printed the tags as visible text
	 * and every category page read "Category: <span>Finance</span>".
	 */
	?>
	<h1 class="arch__title" style="margin-top:12px"><?php echo wp_kses_post( get_the_archive_title() ); ?></h1>

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
