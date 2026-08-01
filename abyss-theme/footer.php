<?php
/**
 * Footer.
 *
 * @package Abyss
 */

?>
</main>

<?php get_template_part( 'template-parts/disclosure' ); ?>

<footer class="ftr">
	<div class="wrap" style="padding-top:60px;padding-bottom:56px">
		<div class="ftr__grid">
			<div>
				<?php if ( has_custom_logo() ) : ?>
					<div class="ftr__logo"><?php the_custom_logo(); ?></div>
				<?php else : ?>
					<a class="hdr__logo-text" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
				<?php endif; ?>

				<p class="ftr__about"><?php echo wp_kses_post( get_theme_mod( 'abyss_tagline', get_bloginfo( 'description' ) ) ); ?></p>
			</div>

			<?php
			$columns = array(
				'footer-one'   => __( 'Sections', 'abyss' ),
				'footer-two'   => __( 'Tools', 'abyss' ),
				'footer-three' => __( 'About', 'abyss' ),
			);

			foreach ( $columns as $location => $heading ) :
				if ( ! has_nav_menu( $location ) ) {
					continue;
				}
				?>
				<div class="ftr__col">
					<p class="ftr__h"><?php echo esc_html( $heading ); ?></p>
					<?php
					wp_nav_menu( array(
						'theme_location' => $location,
						'container'      => false,
						'depth'          => 1,
						'fallback_cb'    => false,
					) );
					?>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="ftr__legal">
			<span><?php
				/* translators: %1$s: year, %2$s: site name. */
				printf( esc_html__( '&copy; %1$s %2$s', 'abyss' ), esc_html( gmdate( 'Y' ) ), esc_html( get_bloginfo( 'name' ) ) );
			?></span>

			<?php
			if ( has_nav_menu( 'legal' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'legal',
					'container'      => false,
					'depth'          => 1,
					'fallback_cb'    => false,
				) );
			}
			?>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
