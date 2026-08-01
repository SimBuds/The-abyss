<?php
/**
 * Author box, shown at the foot of a single post.
 *
 * PLAN.md lists a named author on finance content as a live-site requirement,
 * and functions.php already emits the author into Article schema. This is the
 * human-readable half of the same claim: a reader deciding whether to act on a
 * recommendation about their money should be able to see who made it without
 * leaving the page.
 *
 * Renders nothing when the post has no author or the author has no description,
 * because an empty box asserting nothing about credentials is worse than no box.
 *
 * @package The_abyss
 */
defined( 'ABSPATH' ) || exit;

$the_abyss_author_id = get_the_author_meta( 'ID' );

if ( ! $the_abyss_author_id ) {
	return;
}

$the_abyss_bio = get_the_author_meta( 'description', $the_abyss_author_id );

if ( '' === trim( (string) $the_abyss_bio ) ) {
	return;
}
?>
<section class="abyss-author" aria-labelledby="abyss-author-title">
	<h2 class="screen-reader-text" id="abyss-author-title">
		<?php esc_html_e( 'About the author', 'the-abyss' ); ?>
	</h2>

	<?php
	/*
	 * Decorative: the name sits beside it as real text and links to the same
	 * archive, so announcing the avatar would only repeat it.
	 */
	$the_abyss_avatar = get_avatar( $the_abyss_author_id, 96, '', '', array( 'class' => 'abyss-author__avatar' ) );

	if ( $the_abyss_avatar ) {
		printf( '<div class="abyss-author__media" aria-hidden="true">%s</div>', $the_abyss_avatar ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar() returns escaped markup.
	}
	?>

	<div class="abyss-author__body">
		<p class="abyss-author__kicker"><?php esc_html_e( 'Written by', 'the-abyss' ); ?></p>

		<p class="abyss-author__name">
			<a href="<?php echo esc_url( get_author_posts_url( $the_abyss_author_id ) ); ?>">
				<?php echo esc_html( get_the_author_meta( 'display_name', $the_abyss_author_id ) ); ?>
			</a>
		</p>

		<p class="abyss-author__bio"><?php echo esc_html( $the_abyss_bio ); ?></p>
	</div>
</section>
