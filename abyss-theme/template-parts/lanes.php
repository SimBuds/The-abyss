<?php
/**
 * "What are you trying to decide?" lane cards, built from the three
 * highest-count categories (or the ones assigned to the primary menu).
 *
 * @package Abyss
 */

if ( ! get_theme_mod( 'abyss_home_lanes', true ) ) {
	return;
}

$categories = get_categories( array(
	'number'  => 3,
	'orderby' => 'count',
	'order'   => 'DESC',
) );

if ( empty( $categories ) ) {
	return;
}
?>
<section class="section section--tint">
	<div class="wrap">
		<h2 class="sec-title"><?php esc_html_e( 'What are you trying to decide?', 'abyss' ); ?></h2>
		<p class="sec-lede"><?php esc_html_e( 'Pick the lane you came for &mdash; nothing is hidden behind a topic you did not choose.', 'abyss' ); ?></p>

		<div class="g3" style="margin-top:40px">
			<?php foreach ( $categories as $category ) : ?>
				<a class="card card--lift lane" href="<?php echo esc_url( get_category_link( $category ) ); ?>">
					<p class="kick"><?php echo esc_html( $category->name ); ?></p>
					<h3 class="lane__title"><?php
						/* translators: %s: category name. */
						printf( esc_html__( 'Everything we publish on %s', 'abyss' ), esc_html( $category->name ) );
					?></h3>

					<?php if ( $category->description ) : ?>
						<p class="lane__copy"><?php echo esc_html( wp_trim_words( $category->description, 26 ) ); ?></p>
					<?php endif; ?>

					<?php
					$recent = get_posts( array(
						'posts_per_page' => 3,
						'category'       => $category->term_id,
					) );

					if ( $recent ) :
						?>
						<ul class="lane__links">
							<?php foreach ( $recent as $item ) : ?>
								<li><?php echo esc_html( wp_trim_words( get_the_title( $item ), 9 ) ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
