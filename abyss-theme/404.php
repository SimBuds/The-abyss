<?php
/**
 * 404.
 *
 * @package Abyss
 */

get_header();
?>
<section class="wrap section">
	<p class="kick"><?php esc_html_e( 'Error 404', 'abyss' ); ?></p>
	<h1 class="art__title" style="margin-top:12px"><?php esc_html_e( 'That page is not here.', 'abyss' ); ?></h1>
	<p class="sec-lede"><?php esc_html_e( 'It may have moved. Search, or start from the homepage.', 'abyss' ); ?></p>
	<div style="margin-top:28px;max-width:520px"><?php get_search_form(); ?></div>
</section>
<?php get_footer();
