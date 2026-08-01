<?php
/**
 * Comments.
 *
 * @package Abyss
 */

if ( post_password_required() ) {
	return;
}
?>
<div id="comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="sec-title" style="font-size:24px"><?php
			printf(
				/* translators: %s: comment count. */
				esc_html( _n( '%s response', '%s responses', get_comments_number(), 'abyss' ) ),
				esc_html( number_format_i18n( get_comments_number() ) )
			);
		?></h2>

		<ol style="list-style:none;margin:24px 0 0;padding:0">
			<?php
			wp_list_comments( array(
				'style'      => 'ol',
				'short_ping' => true,
				'avatar_size' => 44,
			) );
			?>
		</ol>

		<?php the_comments_pagination( array( 'class' => 'pagination' ) ); ?>
	<?php endif; ?>

	<?php
	comment_form( array(
		'class_submit'  => 'btn btn--primary',
		'title_reply'   => __( 'Leave a comment', 'abyss' ),
	) );
	?>
</div>
