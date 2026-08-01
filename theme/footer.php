<?php
/**
 * Site footer and the close of the document.
 *
 * @package The_abyss
 */
defined( 'ABSPATH' ) || exit;
?>
<?php
/*
 * The inner wrapper mirrors .site-header__inner and is not decoration. The 2px
 * rule belongs to the outer element so it runs the full width of the viewport,
 * exactly as the header's does; the inner element carries the wide-width cap and
 * the padding. Without the split, the footer was both the bordered box and the
 * constrained box, so its rule stopped at 1440px while the header's ran edge to
 * edge. Two rules of the same weight disagreeing about where a page ends is the
 * kind of thing Modernist's "let the grid show" is against.
 */
?>
<footer class="site-footer">
	<div class="site-footer__inner">
		<?php
		/*
		 * The `footer` location has been registered since step 6 and was never
		 * rendered, so assigning a menu to it in the Customizer did nothing at
		 * all. This is where the privacy policy, affiliate disclosure, and
		 * contact pages belong, and every one of those is a live-site
		 * requirement in PLAN.md rather than a nicety.
		 *
		 * fallback_cb is false for the same reason as the primary menu: with no
		 * menu assigned WordPress would otherwise list every published page.
		 */
		wp_nav_menu(
			array(
				'theme_location'  => 'footer',
				'container'       => 'nav',
				'container_class' => 'abyss-footer-nav',
				'container_aria_label' => __( 'Footer', 'the-abyss' ),
				'depth'           => 1,
				'fallback_cb'     => false,
			)
		);
		?>

		<p>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
