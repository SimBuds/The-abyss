<?php
/**
 * Fallback template — the base of the template hierarchy.
 *
 * @package FutureBuild
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<?php the_excerpt(); ?>
			</article>
		<?php endwhile; ?>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing here yet.', 'futurebuild' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
