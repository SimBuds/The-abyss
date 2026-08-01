<?php
/**
 * Affiliate disclosure bar. Sits directly above the footer.
 *
 * @package Abyss
 */

$style = get_theme_mod( 'abyss_disclosure_style', 'minimal' );

if ( 'off' === $style ) {
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
