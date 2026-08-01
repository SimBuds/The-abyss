<?php
/**
 * Single article.
 *
 * @package Abyss
 */

get_header();

while ( have_posts() ) :
	the_post();

	$affiliate = '1' === get_post_meta( get_the_ID(), '_abyss_post_affiliate', true );
	?>
	<article class="wrap art">
		<header class="art__head">
			<?php if ( abyss_kicker() ) : ?>
				<p class="kick"><?php echo esc_html( abyss_kicker() ); ?></p>
			<?php endif; ?>

			<h1 class="art__title"><?php the_title(); ?></h1>

			<?php if ( abyss_dek() ) : ?>
				<p class="art__dek"><?php echo esc_html( abyss_dek() ); ?></p>
			<?php endif; ?>

			<p class="byline meta art__byline">
				<strong><?php echo esc_html( get_the_author() ); ?></strong>
				<span><?php echo esc_html( get_the_date() ); ?></span>
				<span><?php echo esc_html( abyss_read_time() ); ?></span>
			</p>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure style="margin:36px 0 0">
				<div class="thumb thumb--wide"><?php the_post_thumbnail( 'full', array( 'alt' => '' ) ); ?></div>
				<?php if ( wp_get_attachment_caption( get_post_thumbnail_id() ) ) : ?>
					<figcaption><?php echo esc_html( wp_get_attachment_caption( get_post_thumbnail_id() ) ); ?></figcaption>
				<?php endif; ?>
			</figure>
		<?php endif; ?>

		<div class="artgrid" style="padding-top:48px">
			<div class="prose">
				<?php if ( $affiliate ) : ?>
					<p class="smallprint" style="margin-top:0"><?php esc_html_e( 'This article contains affiliate links. If you buy or open an account through one, we may earn a commission.', 'abyss' ); ?></p>
				<?php endif; ?>

				<?php the_content(); ?>

				<?php
				wp_link_pages( array(
					'before' => '<nav class="pagination">',
					'after'  => '</nav>',
				) );
				?>
			</div>

			<aside class="rail">
				<?php
				$related = new WP_Query( array(
					'posts_per_page'      => 4,
					'post__not_in'        => array( get_the_ID() ),
					'category__in'        => wp_get_post_categories( get_the_ID() ),
					'ignore_sticky_posts' => true,
				) );

				if ( $related->have_posts() ) :
					?>
					<div class="card card--pad">
						<p class="rail__title"><?php esc_html_e( 'Keep reading', 'abyss' ); ?></p>
						<ul class="rail__list">
							<?php
							while ( $related->have_posts() ) :
								$related->the_post();
								?>
								<li>
									<a class="story" href="<?php the_permalink(); ?>">
										<?php if ( abyss_kicker() ) : ?>
											<span class="kick" style="display:block"><?php echo esc_html( abyss_kicker() ); ?></span>
										<?php endif; ?>
										<span class="story__title" style="display:block"><?php the_title(); ?></span>
									</a>
								</li>
							<?php endwhile; ?>
						</ul>
					</div>
					<?php
					wp_reset_postdata();
				endif;
				?>

				<div class="rail__cta">
					<p class="kick"><?php esc_html_e( 'The brief', 'abyss' ); ?></p>
					<p style="font-family:var(--font-heading);font-weight:700;font-size:20px;line-height:1.3"><?php esc_html_e( 'Both lanes, 7:30 ET.', 'abyss' ); ?></p>
					<a class="btn btn--primary btn--block" style="margin-top:16px" href="#newsletter"><?php esc_html_e( 'Subscribe free', 'abyss' ); ?></a>
				</div>
			</aside>
		</div>
	</article>

	<?php
	get_template_part( 'template-parts/newsletter' );

	if ( comments_open() || get_comments_number() ) {
		echo '<div class="wrap section">';
		comments_template();
		echo '</div>';
	}

endwhile;

get_footer();
