<?php
/**
 * Affiliate disclosure bar. Sits directly above the footer.
 *
 * @package Abyss
 */

$style = get_theme_mod( 'abyss_disclosure_style', 'auto' );

if ( 'off' === $style ) {
	return;
}

/*
 * 'auto' means "say it when it is true". The bar claims that some links on the
 * page are affiliate links, and until a programme is configured there are none,
 * anywhere. Asserting a paid relationship that does not exist is a small
 * falsehood, but it sits in the one piece of furniture whose entire job is to be
 * accurate about money.
 *
 * This is the site-wide statement. The per-article disclosure, which is the one
 * that has to sit next to the actual link, is prepended to the content by
 * inc/compliance.php and is unaffected by this setting.
 */
if ( 'auto' === $style && ! abyss_compliance_affiliate_domains() ) {
	return;
}

$text = get_theme_mod( 'abyss_disclosure_text', __( 'Some links on this page are affiliate links. If you open an account or buy through one, we may earn a commission. It never changes what we recommend or the order we list it in.', 'abyss' ) );
$link = get_theme_mod( 'abyss_disclosure_link', '' );
?>
<aside class="disclose<?php echo ( 'loud' === $style ) ? ' disclose--loud' : ''; ?>">
	<div class="wrap disclose__in">
		<?php if ( 'loud' === $style ) : ?>
			<span class="disclose__badge"><?php esc_html_e( 'How we make money', 'abyss' ); ?></span>
		<?php endif; ?>

		<span><?php echo wp_kses_post( $text ); ?></span>

		<?php if ( $link ) : ?>
			<a href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Read the policy', 'abyss' ); ?></a>
		<?php endif; ?>
	</div>
</aside>
