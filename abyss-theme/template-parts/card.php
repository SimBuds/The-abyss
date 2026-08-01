<?php
/**
 * Story card used in grids.
 *
 * @package Abyss
 */

$kicker = abyss_kicker();
?>
<article class="story">
	<a class="story" href="<?php the_permalink(); ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="thumb"><?php the_post_thumbnail( 'abyss-card', array( 'alt' => '' ) ); ?></div>
		<?php endif; ?>

		<?php if ( $kicker ) : ?>
			<p class="kick"><?php echo esc_html( $kicker ); ?></p>
		<?php endif; ?>

		<h3 class="story__title"><?php the_title(); ?></h3>
		<p class="story__dek"><?php echo esc_html( abyss_excerpt_words( null, 24 ) ); ?></p>
	</a>
	<p class="story__meta">
		<?php echo esc_html( get_the_author() ); ?> &middot; <?php echo esc_html( abyss_read_time() ); ?>
	</p>
</article>
