<?php
/**
 * Header.
 *
 * @package Abyss
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="profile" href="https://gmpg.org/xfn/11" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'abyss' ); ?></a>

<header class="hdr">
	<div class="hdr__in">
		<?php if ( has_custom_logo() ) : ?>
			<div class="hdr__logo"><?php the_custom_logo(); ?></div>
		<?php else : ?>
			<a class="hdr__logo hdr__logo-text" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
		<?php endif; ?>

		<nav class="hdr__nav" id="site-nav" aria-label="<?php esc_attr_e( 'Primary', 'abyss' ); ?>">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'depth'          => 1,
				'fallback_cb'    => false,
			) );
			?>
		</nav>

		<button class="hdr__toggle" type="button" data-abyss-nav-toggle aria-expanded="false" aria-controls="site-nav">
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'abyss' ); ?></span>
			<svg width="20" height="14" viewBox="0 0 20 14" aria-hidden="true" focusable="false">
				<rect width="20" height="2" y="0" fill="currentColor" />
				<rect width="20" height="2" y="6" fill="currentColor" />
				<rect width="20" height="2" y="12" fill="currentColor" />
			</svg>
		</button>

		<?php
		$cta_label = get_theme_mod( 'abyss_cta_label', __( 'Get the brief', 'abyss' ) );
		$cta_url   = get_theme_mod( 'abyss_cta_url', '#newsletter' );

		if ( $cta_label ) :
			?>
			<a class="btn btn--primary hdr__cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
		<?php endif; ?>
	</div>
</header>

<?php get_template_part( 'template-parts/ticker' ); ?>

<main id="main">
