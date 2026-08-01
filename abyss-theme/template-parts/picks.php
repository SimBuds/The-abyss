<?php
/**
 * Tested product picks.
 *
 * @package Abyss
 */

if ( ! get_theme_mod( 'abyss_home_picks', true ) ) {
	return;
}

$picks = get_posts( array(
	'post_type'      => 'abyss_pick',
	'posts_per_page' => 4,
	'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
) );

if ( empty( $picks ) ) {
	return;
}
?>
<section class="section section--tint">
	<div class="wrap">
		<div class="sec-head">
			<h2 class="sec-title"><?php esc_html_e( 'Tested this month', 'abyss' ); ?></h2>
		</div>

		<div class="g4" style="margin-top:36px">
			<?php
			foreach ( $picks as $pick ) :
				$kicker   = get_post_meta( $pick->ID, '_abyss_pick_kicker', true );
				$price    = get_post_meta( $pick->ID, '_abyss_pick_price', true );
				$merchant = get_post_meta( $pick->ID, '_abyss_pick_merchant', true );
				$url      = get_post_meta( $pick->ID, '_abyss_pick_url', true );
				$review   = (int) get_post_meta( $pick->ID, '_abyss_pick_review', true );
				$href     = $review ? get_permalink( $review ) : $url;
				?>
				<article class="card card--lift pick">
					<a class="story" href="<?php echo esc_url( $href ? $href : '#' ); ?>" <?php echo $review ? '' : 'rel="sponsored nofollow noopener" target="_blank"'; ?>>
						<?php if ( has_post_thumbnail( $pick ) ) : ?>
							<div class="thumb thumb--pick"><?php echo get_the_post_thumbnail( $pick, 'abyss-pick', array( 'alt' => '' ) ); ?></div>
						<?php endif; ?>

						<span class="pick__body">
							<?php if ( $kicker ) : ?>
								<span class="kick" style="display:block"><?php echo esc_html( $kicker ); ?></span>
							<?php endif; ?>

							<span class="pick__title story__title" style="display:block"><?php echo esc_html( get_the_title( $pick ) ); ?></span>
							<span class="pick__dek" style="display:block"><?php echo esc_html( wp_trim_words( get_the_excerpt( $pick ), 18 ) ); ?></span>

							<span class="pick__price">
								<?php if ( $price ) : ?>
									<b class="num"><?php echo esc_html( $price ); ?></b>
								<?php endif; ?>
								<?php if ( $merchant ) : ?>
									<span><?php echo esc_html( $merchant ); ?></span>
								<?php endif; ?>
							</span>
						</span>
					</a>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
