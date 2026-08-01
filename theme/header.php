<?php
/**
 * Site header and the opening of the document.
 *
 * @package The_abyss
 */
defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'the-abyss' ); ?></a>

<header class="site-header">
	<div class="site-header__inner">
		<nav class="abyss-nav" aria-label="<?php esc_attr_e( 'Primary', 'the-abyss' ); ?>">
			<?php
			/*
			 * The theme declares custom-logo support, so the Customizer offers a
			 * logo upload. Until this call existed, setting one did nothing and
			 * the header kept showing the site title, which reads as a bug in the
			 * Customizer rather than a missing template call. Declaring a feature
			 * and never rendering it is worse than not declaring it.
			 *
			 * Falls back to the site name as text, which is also the correct
			 * result for a site that never uploads a logo.
			 */
			if ( has_custom_logo() ) :
				?>
				<div class="abyss-nav__brand abyss-nav__brand--logo">
					<?php the_custom_logo(); ?>
				</div>
				<?php
			else :
				?>
				<a class="abyss-nav__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php bloginfo( 'name' ); ?>
				</a>
				<?php
			endif;
			?>

			<?php
			/*
			 * fallback_cb is false on purpose. WordPress otherwise renders a
			 * list of every published page when no menu is assigned, which is
			 * not a design decision anyone made. With no menu the header simply
			 * shows the brand, which is a valid layout rather than a broken one.
			 */
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => 'div',
					'container_class' => 'abyss-nav__menu',
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>
	</div>
</header>
