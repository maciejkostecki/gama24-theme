<?php
/**
 * Storefront Child theme functions.
 *
 * @package storefront-child
 */

/**
 * Note on stylesheet loading:
 * Storefront's parent theme already enqueues this child theme's style.css
 * automatically (see Storefront_inc/class-storefront.php -> child_scripts),
 * loading it AFTER the parent + WooCommerce CSS. So we do NOT enqueue it here
 * again — a second enqueue with the same handle would be a silent no-op.
 */

/**
 * Serve product catalog thumbnails UNCROPPED.
 *
 * By default WooCommerce hard-crops the `woocommerce_thumbnail` size (used in
 * shop/category loops) to a fixed aspect ratio, physically cutting pixels out
 * of the generated image file. Forcing crop => 0 makes WordPress scale each
 * image to fit inside the bounding box while preserving its aspect ratio, so
 * no part of the product image is lost — regardless of the original size.
 *
 * NOTE: this only affects images generated from now on. Existing thumbnails
 * must be regenerated once (see the README note / regenerate thumbnails).
 */
add_filter( 'woocommerce_get_image_size_thumbnail', 'storefront_child_uncropped_thumbnail' );
function storefront_child_uncropped_thumbnail( $size ) {
	$size['height'] = ''; // Let height follow each image's aspect ratio.
	$size['crop']   = 0;  // Scale to fit the width, never crop.
	return $size;
}

/*
 * Note: we deliberately do NOT touch the `gallery_thumbnail` size. It is a
 * fixed square that WordPress ratio-matches when picking an intermediate image
 * size; clearing its height triggers a fatal in wp_constrain_dimensions().
 * The small gallery nav thumbnails staying square is expected, and the child
 * theme CSS already fits their content with object-fit.
 */

/**
 * Force the WooCommerce "Product Categories" widget to render hierarchically.
 *
 * The accordion needs children nested inside their parent as <ul class="children">.
 * Without "Show hierarchy" the widget outputs a flat list of every category,
 * which is exactly what we're trying to avoid. Forcing it here means the widget
 * works as an accordion regardless of how the widget option is set.
 */
add_filter( 'widget_display_callback', 'storefront_child_force_category_hierarchy', 10, 2 );
function storefront_child_force_category_hierarchy( $instance, $widget ) {
	if ( isset( $widget->id_base ) && 'woocommerce_product_categories' === $widget->id_base ) {
		$instance['hierarchical'] = 1;
		$instance['dropdown']     = 0;
	}
	return $instance;
}

/**
 * Same intent for the block version (woocommerce/product-categories):
 * keep the hierarchy nested and always render the full top-level tree so the
 * accordion can collapse it, rather than only the current category's children.
 */
add_filter( 'render_block_data', 'storefront_child_force_category_block_hierarchy' );
function storefront_child_force_category_block_hierarchy( $block ) {
	if ( isset( $block['blockName'] ) && 'woocommerce/product-categories' === $block['blockName'] ) {
		$block['attrs']['isHierarchical']   = true;
		$block['attrs']['showChildrenOnly'] = false;
		$block['attrs']['isDropdown']       = false;
	}
	return $block;
}

/**
 * Enqueue the child theme's front-end script(s).
 */
add_action( 'wp_enqueue_scripts', 'storefront_child_scripts', 30 );
function storefront_child_scripts() {
	wp_enqueue_script(
		'storefront-child-category-accordion',
		get_stylesheet_directory_uri() . '/assets/js/category-accordion.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);
}

/**
 * Remove the sidebar on single product pages for a full-width layout.
 *
 * Storefront attaches its sidebar to the `storefront_sidebar` action (see
 * inc/storefront-template-hooks.php: add_action( 'storefront_sidebar',
 * 'storefront_get_sidebar', 10 )). Detaching that callback on product pages
 * drops the sidebar entirely. The companion body class below then lets the
 * child theme CSS stretch the content column to full width — Storefront's
 * content area is otherwise a fixed ~74% and would leave the freed space empty.
 *
 * We hook `wp` (not an earlier hook) because conditional tags like is_product()
 * are only reliable once the main query has run.
 */
add_action( 'wp', 'storefront_child_remove_product_sidebar' );
function storefront_child_remove_product_sidebar() {
	if ( function_exists( 'is_product' ) && is_product() ) {
		remove_action( 'storefront_sidebar', 'storefront_get_sidebar', 10 );
	}
}

/**
 * Tag product pages so the CSS can widen the content column once the sidebar
 * has been removed above.
 */
add_filter( 'body_class', 'storefront_child_product_body_class' );
function storefront_child_product_body_class( $classes ) {
	if ( function_exists( 'is_product' ) && is_product() ) {
		$classes[] = 'storefront-child-no-sidebar';
	}
	return $classes;
}

/**
 * Add your custom functions below this line.
 */
