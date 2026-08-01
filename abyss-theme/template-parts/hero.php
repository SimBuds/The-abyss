<?php
/**
 * Homepage hero: lead story plus rate snapshot.
 *
 * @package Abyss
 */

$lead = get_posts( array(
	'posts_per_page'      => 1,
	'ignore_sticky_posts' => false,
	'post__in'            => get_option( 'sticky_posts' ) ? get_option( 'sticky_posts' ) : array(),
) );

if ( empty( $lead ) ) {
	$lead = get_posts( array( 'posts_per_page' => 1 ) );
}

if ( empty( $lead ) ) {
	return;
}

$post = $lead[0];
setup_postdata( $post );

$snapshot = array();

if ( get_theme_mod( 'abyss_home_snapshot', true ) ) {
	foreach ( abyss_offer_rows( 12 ) as $row ) {
		if ( $row['snapshot'] ) {
			$snapshot[] = $row;
		}
	}

	$snapshot = array_slice( $snapshot, 0, 4 );
}
?>
<section class="wrap section">
	<div class="hero">
		<div>
			<a class="story" href="<?php the_permalink(); ?>">
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="thumb thumb--hero"><?php the_post_thumbnail( 'abyss-lead', array( 'alt' => '' ) ); ?></div>
				<?php endif; ?>

				<?php if ( abyss_kicker() ) : ?>
					<p class="kick" style="margin-top:26px"><?php echo esc_html( abyss_kicker() ); ?></p>
				<?php endif; ?>

				<h1 class="lead__title story__title" style="margin-top:14px"><?php the_title(); ?></h1>
				<p class="lead__dek"><?php echo esc_html( abyss_dek() ); ?></p>
			</a>

			<p class="byline meta" style="margin-top:20px">
				<strong><?php echo esc_html( get_the_author() ); ?></strong>
				<span><?php echo esc_html( get_the_date() ); ?></span>
				<span><?php echo esc_html( abyss_read_time() ); ?></span>
			</p>
		</div>

		<?php if ( $snapshot ) : ?>
			<aside class="card card--pad">
				<h2 style="font-weight:700;font-size:20px"><?php esc_html_e( "Today's best rates", 'abyss' ); ?></h2>
				<p class="meta" style="margin-top:8px"><?php esc_html_e( 'Checked this morning, 8:00 ET.', 'abyss' ); ?></p>

				<ul class="snap">
					<?php foreach ( $snapshot as $row ) : ?>
						<li>
							<span>
								<span class="snap__k"><?php echo esc_html( $row['snapkey'] ? $row['snapkey'] : $row['name'] ); ?></span>
								<span class="snap__sub"><?php echo esc_html( $row['snapkey'] ? $row['name'] : $row['note'] ); ?></span>
							</span>
							<span class="snap__v num"><?php echo esc_html( number_format_i18n( $row['apy'], 2 ) . ( $row['unit'] ? $row['unit'] : '%' ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>

				<a class="btn btn--secondary btn--block" style="margin-top:20px" href="#rates"><?php esc_html_e( 'Compare all accounts', 'abyss' ); ?></a>
			</aside>
		<?php endif; ?>
	</div>
</section>
<?php
wp_reset_postdata();
