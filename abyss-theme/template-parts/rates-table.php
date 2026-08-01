<?php
/**
 * Sortable affiliate comparison table.
 *
 * @package Abyss
 */

if ( ! get_theme_mod( 'abyss_home_table', true ) ) {
	return;
}

/*
 * Savings accounts only, and not CDs: a term deposit is not the same product and
 * listing one here put the same bank in the table twice at two different rates. The heading says "Best high-yield savings accounts" and
 * the table sorts by rate descending, so an unfiltered list put a mortgage at the
 * top of it: the highest number, and the worst outcome. Filtering here rather
 * than changing the sort keeps the table honest about what it ranks.
 */
$rows = array_values( array_filter(
	abyss_offer_rows( 12 ),
	function ( $row ) {
		return 'savings' === $row['kind'];
	}
) );

if ( empty( $rows ) ) {
	return;
}

$best = $rows[0]['apy'];
?>
<section class="wrap section" id="rates">
	<p class="kick"><?php esc_html_e( 'Compare', 'abyss' ); ?></p>
	<h2 class="sec-title" style="margin-top:12px"><?php echo esc_html( get_theme_mod( 'abyss_table_title', __( 'Best high-yield savings accounts', 'abyss' ) ) ); ?></h2>
	<p class="sec-lede" style="max-width:64ch"><?php echo wp_kses_post( get_theme_mod( 'abyss_table_lede', '' ) ); ?></p>

	<div class="card tablewrap">
		<div class="tablescroll">
			<table class="rates" data-abyss-sortable>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Account', 'abyss' ); ?></th>
						<th scope="col" data-sort="apy" aria-sort="descending">
							<button class="sortbtn" type="button"><?php esc_html_e( 'Rate', 'abyss' ); ?><span class="sortbtn__arrow" aria-hidden="true">&darr;</span></button>
						</th>
						<th scope="col" data-sort="minimum">
							<button class="sortbtn" type="button"><?php esc_html_e( 'Minimum', 'abyss' ); ?><span class="sortbtn__arrow" aria-hidden="true"></span></button>
						</th>
						<th scope="col" data-sort="fee">
							<button class="sortbtn" type="button"><?php esc_html_e( 'Monthly fee', 'abyss' ); ?><span class="sortbtn__arrow" aria-hidden="true"></span></button>
						</th>
						<th scope="col"><?php esc_html_e( 'Worth knowing', 'abyss' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Apply', 'abyss' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td class="rates__bank"><?php echo esc_html( $row['name'] ); ?></td>
							<td class="rates__apy num<?php echo ( $row['apy'] >= $best ) ? ' is-best' : ''; ?>" data-value="<?php echo esc_attr( $row['apy'] ); ?>">
								<?php echo esc_html( number_format_i18n( $row['apy'], 2 ) . ( $row['unit'] ? $row['unit'] : '%' ) ); ?>
							</td>
							<td class="num" data-value="<?php echo esc_attr( $row['minimum'] ); ?>"><?php echo esc_html( abyss_money( $row['minimum'] ) ); ?></td>
							<td class="num" data-value="<?php echo esc_attr( $row['fee'] ); ?>"><?php echo esc_html( abyss_money( $row['fee'] ) ); ?></td>
							<td class="rates__note"><?php echo esc_html( $row['note'] ); ?></td>
							<td><?php echo abyss_affiliate_link( $row['url'], $row['cta'] ? $row['cta'] : __( 'Open account', 'abyss' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<p class="smallprint"><?php esc_html_e( 'We may be paid if you open an account through this table. Ranking is by rate and terms only &mdash; placement is never sold. Rates are variable and can change without notice.', 'abyss' ); ?></p>
</section>
