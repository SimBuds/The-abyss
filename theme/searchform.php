<?php
/**
 * Search form.
 *
 * Overrides the markup `get_search_form()` would otherwise generate, so the
 * field and the button come from the design system rather than from core's
 * default HTML5 form.
 *
 * @package The_abyss
 */
defined( 'ABSPATH' ) || exit;

/*
 * The form can appear more than once on a page: the no-results branch of
 * index.php renders one, and a widget or a 404 template may render another. A
 * hard-coded id would then be duplicated, and every duplicate `for` attribute
 * would point at the first field on the page, so the second form's label would
 * silently belong to the first form's input. wp_unique_id() gives each instance
 * its own.
 */
$the_abyss_search_id = wp_unique_id( 'the-abyss-search-' );
?>
<form role="search" method="get" class="abyss-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<div class="abyss-field">
		<?php
		/*
		 * Visually hidden rather than absent. A placeholder is not an accessible
		 * name, and whatever name it does supply disappears the moment the reader
		 * starts typing, which is exactly when they may need to re-orient.
		 */
		?>
		<label class="screen-reader-text" for="<?php echo esc_attr( $the_abyss_search_id ); ?>">
			<?php esc_html_e( 'Search for:', 'the-abyss' ); ?>
		</label>

		<input
			type="search"
			id="<?php echo esc_attr( $the_abyss_search_id ); ?>"
			class="abyss-input"
			name="s"
			value="<?php echo esc_attr( get_search_query() ); ?>"
			placeholder="<?php esc_attr_e( 'Search articles', 'the-abyss' ); ?>"
		/>
	</div>

	<button type="submit" class="abyss-btn abyss-btn--primary">
		<?php esc_html_e( 'Search', 'the-abyss' ); ?>
	</button>
</form>
