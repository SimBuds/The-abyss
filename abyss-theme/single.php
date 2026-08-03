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
		<?php abyss_breadcrumbs(); ?>

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
			<?php
			/*
			 * The column wrapper exists so the furniture below can sit outside
			 * .prose while staying in the same grid column. It was inside .prose
			 * at first, which meant `.prose a` styled the share links and the
			 * author bio as if they were links the author had written in the
			 * body: accent-coloured and underlined.
			 */
			?>
			<div class="artcol">
				<div class="prose">
				<?php
				/*
				 * The affiliate disclosure is not rendered here. It is prepended
				 * to the content by inc/compliance.php, which fires both on a
				 * link to a monetised domain and on this post's own affiliate
				 * checkbox. Rendering it here as well produced two disclosures,
				 * differently worded, one above the other.
				 */
				?>
				<?php the_content(); ?>

				<?php
				wp_link_pages( array(
					'before' => '<nav class="pagination">',
					'after'  => '</nav>',
				) );
				?>

				</div>

				<?php
				/* Tags below the article, not in the header: they are a way out
				   of the piece, not a label on it. */
				the_tags( '<p class="tags"><span class="tags__label">' . esc_html__( 'Filed under', 'abyss' ) . '</span>', '', '</p>' );
				?>

				<?php abyss_share_links(); ?>
				<?php abyss_author_box(); ?>
			</div>

			<aside class="rail">
				<?php abyss_table_of_contents(); ?>

				<?php
				/* An editable slot, so the rail is not code-only. Rendered above
				   the fixed blocks below, which stay because a brand-new site
				   with no widgets configured should still have a usable rail. */
				if ( is_active_sidebar( 'abyss-rail' ) ) {
					echo '<div class="rail__widgets">';
					dynamic_sidebar( 'abyss-rail' );
					echo '</div>';
				}
				?>

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
		<?php
		/*
		 * Prev/next by date. This is the one that always has somewhere to go —
		 * the "Keep reading" block in the rail is scoped to the post's own
		 * categories, and on a young site with one post in a category it
		 * renders nothing at all, leaving the article a dead end.
		 */
		the_post_navigation( array(
			'class'              => 'postnav',
			'prev_text'          => '<span class="postnav__dir">' . esc_html__( 'Previous', 'abyss' ) . '</span><span class="postnav__title">%title</span>',
			'next_text'          => '<span class="postnav__dir">' . esc_html__( 'Next', 'abyss' ) . '</span><span class="postnav__title">%title</span>',
			'screen_reader_text' => __( 'More articles', 'abyss' ),
		) );
		?>
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
