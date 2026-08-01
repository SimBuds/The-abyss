<?php
/**
 * Newsletter capture block.
 *
 * @package Abyss
 */

$shortcode = get_theme_mod( 'abyss_news_shortcode', '' );
?>
<section class="news" id="newsletter">
	<div class="wrap section news__in">
		<h2 class="news__title"><?php echo esc_html( get_theme_mod( 'abyss_news_title', __( 'One email. Both lanes. Six minutes, before the open.', 'abyss' ) ) ); ?></h2>
		<p class="news__copy"><?php echo wp_kses_post( get_theme_mod( 'abyss_news_copy', '' ) ); ?></p>

		<div class="news__form">
			<div class="news__card">
				<?php if ( $shortcode ) : ?>
					<?php echo do_shortcode( wp_kses_post( $shortcode ) ); ?>
				<?php else : ?>
					<form action="<?php echo esc_url( apply_filters( 'abyss_newsletter_action', '' ) ); ?>" method="post">
						<label class="news__label" for="abyss-email"><?php esc_html_e( 'Your email', 'abyss' ); ?></label>
						<input class="input" type="email" id="abyss-email" name="email" placeholder="you@work.com" required />
						<button class="btn btn--primary btn--block" style="margin-top:14px" type="submit"><?php esc_html_e( 'Subscribe free', 'abyss' ); ?></button>
					</form>
				<?php endif; ?>

				<p class="news__fine"><?php echo wp_kses_post( get_theme_mod( 'abyss_news_fine', '' ) ); ?></p>
			</div>
		</div>
	</div>
</section>
