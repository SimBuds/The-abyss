<?php
/**
 * Static page.
 *
 * @package Abyss
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article class="wrap art">
		<header class="art__head">
			<h1 class="art__title"><?php the_title(); ?></h1>
		</header>

		<div class="prose" style="padding-top:40px"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;

get_footer();
