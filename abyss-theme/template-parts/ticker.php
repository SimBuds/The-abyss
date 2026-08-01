<?php
/**
 * Live data ticker.
 *
 * @package Abyss
 */

if ( ! get_theme_mod( 'abyss_ticker_on', true ) ) {
	return;
}

$items = abyss_ticker_items();

if ( empty( $items ) ) {
	return;
}
?>
<div class="ticker" aria-label="<?php esc_attr_e( 'Live market and model data', 'abyss' ); ?>">
	<div class="ticker__in">
		<p class="ticker__label"><span class="ticker__dot" aria-hidden="true"></span><?php esc_html_e( 'Live', 'abyss' ); ?></p>
		<div class="ticker__track">
			<div class="ticker__run" data-abyss-ticker>
				<?php foreach ( $items as $item ) : ?>
					<span class="ticker__item">
						<span class="ticker__k"><?php echo esc_html( $item['label'] ); ?></span>
						<span class="ticker__v num"><?php echo esc_html( $item['value'] ); ?></span>
						<span class="ticker__d num<?php echo $item['down'] ? ' is-down' : ''; ?>"><?php echo esc_html( $item['delta'] ); ?></span>
					</span>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>
