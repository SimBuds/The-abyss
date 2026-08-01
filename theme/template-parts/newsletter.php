<?php
/**
 * Newsletter signup.
 *
 * Rendered only when an endpoint is configured. See the_abyss_newsletter_action()
 * in functions.php for why: a form that posts nowhere loses subscribers silently,
 * so an unconfigured theme shows nothing rather than something broken.
 *
 * Posts directly to the provider rather than through WordPress. That keeps
 * subscriber email addresses out of this database entirely, which is one fewer
 * store to secure, to back up, and to answer for under CASL and GDPR.
 *
 * The field is named `email` because that is what Kit, MailerLite, and Mailchimp
 * all accept. A provider wanting something else can filter the whole action URL
 * and point it at its own embed instead.
 *
 * @package The_abyss
 */
defined( 'ABSPATH' ) || exit;

$the_abyss_action = the_abyss_newsletter_action();

if ( '' === $the_abyss_action ) {
	return;
}
?>
<section class="abyss-newsletter" aria-labelledby="abyss-newsletter-title">
	<h2 class="abyss-newsletter__title" id="abyss-newsletter-title">
		<?php esc_html_e( 'Get the newsletter', 'the-abyss' ); ?>
	</h2>

	<p class="abyss-newsletter__body">
		<?php esc_html_e( 'Finance and AI, written up once a week. No spam, unsubscribe anytime.', 'the-abyss' ); ?>
	</p>

	<form class="abyss-newsletter__form" action="<?php echo esc_url( $the_abyss_action ); ?>" method="post" target="_blank">
		<div class="abyss-field">
			<label for="abyss-newsletter-email"><?php esc_html_e( 'Email address', 'the-abyss' ); ?></label>
			<input
				class="abyss-input"
				id="abyss-newsletter-email"
				type="email"
				name="email"
				autocomplete="email"
				required
				placeholder="you@example.com"
			>
		</div>

		<button class="abyss-btn abyss-btn--primary" type="submit">
			<?php esc_html_e( 'Subscribe', 'the-abyss' ); ?>
		</button>
	</form>
</section>
