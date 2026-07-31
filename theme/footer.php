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
		<p>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
