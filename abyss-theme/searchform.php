<?php
/**
 * Search form.
 *
 * @package Abyss
 */

?>
<form class="searchform" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="abyss-search"><?php esc_html_e( 'Search', 'abyss' ); ?></label>
	<input class="input" type="search" id="abyss-search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search stories and tools', 'abyss' ); ?>" />
	<button class="btn btn--primary" type="submit"><?php esc_html_e( 'Search', 'abyss' ); ?></button>
</form>
