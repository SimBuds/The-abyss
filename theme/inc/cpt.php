<?php
/**
 * Custom post type + taxonomies for the building catalog.
 *
 * @package FutureBuild
 */
defined( 'ABSPATH' ) || exit;

/**
 * Register the building_model post type.
 */
function fb_register_building_model() {
	$labels = array(
		'name'          => __( 'Building Models', 'futurebuild' ),
		'singular_name' => __( 'Building Model', 'futurebuild' ),
		'add_new_item'  => __( 'Add New Model', 'futurebuild' ),
		'edit_item'     => __( 'Edit Model', 'futurebuild' ),
		'search_items'  => __( 'Search Models', 'futurebuild' ),
		'not_found'     => __( 'No models found.', 'futurebuild' ),
		'menu_name'     => __( 'Buildings', 'futurebuild' ),
	);

	register_post_type( 'building_model', array(
		'labels'              => $labels,
		'public'              => true,
		'has_archive'         => true,
		'menu_icon'           => 'dashicons-building',
		'menu_position'       => 20,
		'rewrite'             => array( 'slug' => 'buildings', 'with_front' => false ),
		'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
		'show_in_rest'        => true,
		'rest_base'           => 'building_model',
		'show_in_graphql'     => true,
		'graphql_single_name' => 'buildingModel',
		'graphql_plural_name' => 'buildingModels',
	) );
}
add_action( 'init', 'fb_register_building_model' );

/**
 * Register the building_type and application taxonomies.
 */
function fb_register_taxonomies() {
	register_taxonomy( 'building_type', 'building_model', array(
		'labels'              => array(
			'name'          => __( 'Building Types', 'futurebuild' ),
			'singular_name' => __( 'Building Type', 'futurebuild' ),
			'menu_name'     => __( 'Types', 'futurebuild' ),
		),
		'hierarchical'        => true,
		'public'              => true,
		'show_admin_column'   => true,
		'show_in_rest'        => true,
		'show_in_graphql'     => true,
		'graphql_single_name' => 'buildingType',
		'graphql_plural_name' => 'buildingTypes',
		'rewrite'             => array( 'slug' => 'building-type' ),
	) );

	register_taxonomy( 'application', 'building_model', array(
		'labels'              => array(
			'name'          => __( 'Applications', 'futurebuild' ),
			'singular_name' => __( 'Application', 'futurebuild' ),
			'menu_name'     => __( 'Applications', 'futurebuild' ),
		),
		'hierarchical'        => true,
		'public'              => true,
		'show_admin_column'   => true,
		'show_in_rest'        => true,
		'show_in_graphql'     => true,
		'graphql_single_name' => 'application',
		'graphql_plural_name' => 'applications',
		'rewrite'             => array( 'slug' => 'application' ),
	) );
}
add_action( 'init', 'fb_register_taxonomies' );
