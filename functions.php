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
 * Which templates should render full-width, without Storefront's sidebar.
 *
 * Central list so the sidebar removal and the body class below always agree.
 * Add more conditional tags here (e.g. is_cart()) to extend the behavior.
 * Guarded with function_exists() because these are WooCommerce conditionals.
 *
 * @return bool True on pages that should drop the sidebar.
 */
function storefront_child_is_no_sidebar_page() {
	if ( ! function_exists( 'is_product' ) ) {
		return false; // WooCommerce inactive: nothing to do.
	}

	return is_product() || is_checkout() || is_cart();
}

/**
 * Remove the sidebar on full-width templates.
 *
 * Storefront attaches its sidebar to the `storefront_sidebar` action (see
 * inc/storefront-template-hooks.php: add_action( 'storefront_sidebar',
 * 'storefront_get_sidebar', 10 )). Detaching that callback drops the sidebar
 * entirely. The companion body class below then lets the child theme CSS
 * stretch the content column to full width — Storefront's content area is
 * otherwise a fixed ~74% and would leave the freed space empty.
 *
 * We hook `wp` (not an earlier hook) because conditional tags like is_checkout()
 * are only reliable once the main query has run.
 */
add_action( 'wp', 'storefront_child_remove_sidebar' );
function storefront_child_remove_sidebar() {
	if ( storefront_child_is_no_sidebar_page() ) {
		remove_action( 'storefront_sidebar', 'storefront_get_sidebar', 10 );
	}
}

/**
 * Tag those templates so the CSS can widen the content column once the sidebar
 * has been removed above.
 */
add_filter( 'body_class', 'storefront_child_no_sidebar_body_class' );
function storefront_child_no_sidebar_body_class( $classes ) {
	if ( storefront_child_is_no_sidebar_page() ) {
		$classes[] = 'storefront-child-no-sidebar';
	}
	return $classes;
}

/**
 * Render the "footer top" template part as the first thing in the footer.
 *
 * Hooked to `storefront_footer` rather than dropped into a page template, so
 * it renders on every URL — front page, shop, category archives, single
 * products, cart, checkout, search and 404 alike — without touching any
 * template file or duplicating markup.
 *
 * Priority 5 puts it ahead of everything Storefront hooks to the same action
 * (see inc/storefront-template-hooks.php): storefront_footer_widgets at 10,
 * storefront_credit at 20, and storefront_handheld_footer_bar at 999. It
 * therefore lands inside <footer id="colophon"> and its .col-full wrapper,
 * above the footer widget columns.
 *
 * To render it ABOVE the footer element instead — outside .col-full, so it can
 * span the full viewport width with its own background — swap the hook for
 * `storefront_before_footer` and drop the priority argument.
 */
add_action( 'storefront_footer', 'storefront_child_footer_top', 5 );
function storefront_child_footer_top() {
	get_template_part( 'template-parts/footer-top' );
}





/**
 * Remove "Raty Przelewy24" from the checkout payment list.
 *
 * The installments gateway (`p24-online-payments-303`) is a virtual gateway the
 * Przelewy24 plugin injects whenever the `p24_installments_show_as_payment_method`
 * option is 'yes' — see Installments::add_as_gateway() for the classic checkout
 * and Installments::add_as_gateway_in_block() for the block checkout.
 *
 * There is no admin toggle for it: the plugin declares that field (and the module
 * enable flag) as disabled + force_default + hide in
 * includes/Installments/Settings.php, and Module_Settings::set_defaults() re-forces
 * it to 'yes' on plugin activation, on every version update, and every time the
 * wc-settings section=installments screen is saved. Overwriting the option in the
 * database therefore does not stick, and patching the plugin would both be undone
 * by updates and break its own checksum integrity check
 * (includes/Integrity/checksums.php).
 *
 * So we short-circuit the read instead. A `pre_option_` filter makes every
 * get_option() call for that key return 'no', which is what both gateway
 * registration paths gate on.
 *
 * Deliberately scoped to the payment method only: `p24_installments_enabled`
 * is left alone, so the installments widget / simulator on product pages keeps
 * working.
 */
add_filter( 'pre_option_p24_installments_show_as_payment_method', 'storefront_child_disable_p24_installments' );
function storefront_child_disable_p24_installments() {
	return 'no';
}

/**
 * Belt and braces: drop the gateway by ID as well, in case it ever gets
 * registered by another path (e.g. restored from the cached payment methods
 * list). Priority 100 runs after the plugin's own filters, which sit at
 * default priority and at 1 respectively.
 */
add_filter( 'woocommerce_payment_gateways', 'storefront_child_unregister_p24_installments', 100 );
function storefront_child_unregister_p24_installments( $gateways ) {
	if ( ! is_array( $gateways ) ) {
		return $gateways;
	}

	return array_values( array_filter( $gateways, function( $gateway ) {
		return ! ( is_object( $gateway ) && isset( $gateway->id ) && 'p24-online-payments-303' === $gateway->id );
	} ) );
}

add_filter( 'woocommerce_available_payment_gateways', 'storefront_child_remove_p24_installments', 100 );
function storefront_child_remove_p24_installments( $gateways ) {
	if ( is_array( $gateways ) ) {
		unset( $gateways['p24-online-payments-303'] );
	}
	return $gateways;
}




/**
 * Add your custom functions below this line.
 */
