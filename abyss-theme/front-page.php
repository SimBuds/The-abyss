<?php
/**
 * Homepage.
 *
 * @package Abyss
 */

get_header();

get_template_part( 'template-parts/hero' );
get_template_part( 'template-parts/lanes' );
get_template_part( 'template-parts/rates-table' );
?>

<section class="section section--ruled">
	<div class="wrap">
		<div class="sec-head">
			<div>
				<h2 class="sec-title"><?php esc_html_e( 'Latest', 'abyss' ); ?></h2>
				<p class="sec-lede"><?php esc_html_e( 'Everything we published this week, newest first.', 'abyss' ); ?></p>
			</div>

			<?php
			$lanes = get_categories( array( 'number' => 3, 'orderby' => 'count', 'order' => 'DESC' ) );

			if ( $lanes ) :
				?>
				<div class="pill-row">
					<span class="pill is-active"><?php esc_html_e( 'Everything', 'abyss' ); ?></span>
					<?php foreach ( $lanes as $lane ) : ?>
						<a class="pill" href="<?php echo esc_url( get_category_link( $lane ) ); ?>"><?php echo esc_html( $lane->name ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="g3" style="margin-top:40px">
			<?php
			$feed = new WP_Query( array(
				'posts_per_page'      => 6,
				'ignore_sticky_posts' => true,
			) );

			while ( $feed->have_posts() ) :
				$feed->the_post();
				get_template_part( 'template-parts/card' );
			endwhile;

			wp_reset_postdata();
			?>
		</div>
	</div>
</section>

<?php
get_template_part( 'template-parts/picks' );
get_template_part( 'template-parts/newsletter' );

get_footer();
