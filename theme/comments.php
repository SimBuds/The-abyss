<?php
/**
 * Comments and the comment form.
 *
 * Included by single.php through comments_template(). Pages do not call it: an
 * About page or an FTC disclosure is not a discussion, and page.php is
 * deliberately not single.php with parts removed.
 *
 * WordPress loads this file for every post whose comments are open OR that
 * already has approved comments, so a post with comments later closed still
 * shows the conversation it had.
 *
 * @package The_abyss
 */
defined( 'ABSPATH' ) || exit;

/*
 * A password-protected post must not leak its discussion. WordPress calls this
 * file before checking, so the guard belongs here and has to be an early return
 * rather than a wrapper: nothing below may run.
 */
if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="abyss-comments">

	<?php if ( have_comments() ) : ?>

		<h2 class="abyss-comments__title">
			<?php
			$the_abyss_count = get_comments_number();

			printf(
				/* translators: %s: comment count */
				esc_html( _n( '%s comment', '%s comments', $the_abyss_count, 'the-abyss' ) ),
				esc_html( number_format_i18n( $the_abyss_count ) )
			);
			?>
		</h2>

		<ol class="abyss-comments__list">
			<?php
			/*
			 * The default walker, styled rather than replaced. Its markup is
			 * WordPress's, the same reasoning as binding .abyss-table to the core
			 * table block: a custom callback here would be more code to keep in
			 * step with core for markup nobody looks at.
			 *
			 * short_ping renders trackbacks as a bare line instead of a full
			 * comment body, which is what they are.
			 */
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 40,
				)
			);
			?>
		</ol>

		<?php
		/*
		 * Only renders when the discussion is actually paged, so it costs nothing
		 * on a post with four comments and is present on one with four hundred.
		 */
		the_comments_navigation(
			array(
				'prev_text' => esc_html__( 'Older comments', 'the-abyss' ),
				'next_text' => esc_html__( 'Newer comments', 'the-abyss' ),
			)
		);
		?>

	<?php endif; ?>

	<?php
	/*
	 * Said only when there is something to say: comments are closed AND the post
	 * has some. On a post that never accepted comments this would be noise.
	 */
	if ( ! comments_open() && get_comments_number() ) :
		?>
		<p class="abyss-comments__closed">
			<?php esc_html_e( 'Comments are closed on this article.', 'the-abyss' ); ?>
		</p>
		<?php
	endif;

	/*
	 * The form is built from the design system rather than left to core's
	 * defaults. Every field is a .abyss-field wrapping a real <label> and an
	 * .abyss-input: core's defaults label some fields by placeholder alone, which
	 * is not an accessible name and vanishes as soon as the reader types.
	 *
	 * aria-required is set alongside the native required attribute because the
	 * two are not redundant here. Core's own markup carries it, and older
	 * assistive technology reads it where it does not yet act on the HTML5
	 * attribute.
	 */
	$the_abyss_commenter = wp_get_current_commenter();
	$the_abyss_req       = get_option( 'require_name_email' );
	$the_abyss_aria      = $the_abyss_req ? " required aria-required='true'" : '';

	comment_form(
		array(
			'class_form'           => 'abyss-comment-form',
			'class_submit'         => 'abyss-btn abyss-btn--primary',
			'title_reply'          => esc_html__( 'Leave a comment', 'the-abyss' ),
			'title_reply_to'       => esc_html__( 'Reply to %s', 'the-abyss' ),
			'label_submit'         => esc_html__( 'Post comment', 'the-abyss' ),
			'title_reply_before'   => '<h2 id="reply-title" class="abyss-comments__title">',
			'title_reply_after'    => '</h2>',
			'comment_notes_before' => '<p class="abyss-comments__notes">' . esc_html__( 'Your email address is not published.', 'the-abyss' ) . '</p>',

			'comment_field' => sprintf(
				'<div class="abyss-field"><label for="comment">%1$s</label>'
				. '<textarea id="comment" name="comment" class="abyss-input" rows="6" required aria-required="true"></textarea></div>',
				esc_html__( 'Comment', 'the-abyss' )
			),

			'fields' => array(
				'author' => sprintf(
					'<div class="abyss-field"><label for="author">%1$s</label>'
					. '<input id="author" name="author" type="text" class="abyss-input" value="%2$s" maxlength="245"%3$s /></div>',
					esc_html__( 'Name', 'the-abyss' ),
					esc_attr( $the_abyss_commenter['comment_author'] ),
					$the_abyss_aria
				),
				'email'  => sprintf(
					'<div class="abyss-field"><label for="email">%1$s</label>'
					. '<input id="email" name="email" type="email" class="abyss-input" value="%2$s" maxlength="100"%3$s /></div>',
					esc_html__( 'Email', 'the-abyss' ),
					esc_attr( $the_abyss_commenter['comment_author_email'] ),
					$the_abyss_aria
				),
				/*
				 * The website field is deliberately absent. It is the single most
				 * abused field in WordPress comments, it exists to collect a link,
				 * and PLAN.md commits this site to controlling its outbound links.
				 * Dropping it removes the incentive rather than fighting the
				 * symptom with moderation.
				 */
				/*
				 * .abyss-check, NOT .abyss-radio. The radio component hides its
				 * native input with `position: absolute; opacity: 0` because a
				 * styled .abyss-radio__dot stands in for it. There is no dot here,
				 * so borrowing that class would hide the checkbox and leave a
				 * label the reader cannot tick.
				 */
				'cookies' => sprintf(
					'<p class="abyss-comments__consent"><label class="abyss-check" for="wp-comment-cookies-consent">'
					. '<input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"%1$s />'
					. '<span>%2$s</span></label></p>',
					empty( $the_abyss_commenter['comment_author_email'] ) ? '' : ' checked="checked"',
					esc_html__( 'Save my name and email in this browser for next time.', 'the-abyss' )
				),
			),
		)
	);
	?>
</section>
