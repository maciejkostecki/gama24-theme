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

	// Homepage slider: front page only, and only when there are slides to show.
	if ( storefront_child_show_homepage_slider() ) {
		wp_enqueue_script(
			'storefront-child-homepage-slider',
			get_stylesheet_directory_uri() . '/assets/js/homepage-slider.js',
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);
	}

	// Phone-order button: single product pages only.
	if ( function_exists( 'is_product' ) && is_product() ) {
		wp_enqueue_script(
			'storefront-child-phone-order',
			get_stylesheet_directory_uri() . '/assets/js/phone-order.js',
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);

		wp_add_inline_script(
			'storefront-child-phone-order',
			'window.storefrontChildPhoneOrder = ' . wp_json_encode( storefront_child_phone_order_js_data() ) . ';',
			'before'
		);
	}
}

/**
 * Data handed to assets/js/phone-order.js so it can repeat the open/closed
 * decision client-side. Same hours PHP used, so the two agree.
 *
 * The timezone is the site's own setting, but only when it is a real IANA
 * name: WordPress also allows a bare UTC offset ("+02:00"), which older
 * Intl.DateTimeFormat implementations reject as a timeZone. In that case fall
 * back to Europe/Warsaw, which is what the shop actually runs on.
 *
 * @return array
 */
function storefront_child_phone_order_js_data() {
	$tz = wp_timezone()->getName();

	return array(
		'timezone' => false !== strpos( $tz, '/' ) ? $tz : 'Europe/Warsaw',
		'hours'    => storefront_child_phone_order_hours(),
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

/* ==========================================================================
   Phone orders on single product pages.

   Renders a "Zamów przez telefon" button beside the add-to-cart button. During
   business hours it is a tel: link; outside them it opens a Contact Form 7
   dialog that collects a callback number.

   Both states are always rendered. PHP picks the correct one up front (using
   the site's own timezone, so there is no flash of the wrong button and remote
   visitors are not judged by their own clock), and assets/js/phone-order.js
   re-evaluates on load against the shop's timezone. That second check exists
   so a full-page cache or CDN in front of the site cannot freeze the button in
   whichever state happened to be cached, and so a tab left open across 18:00
   corrects itself.
   ========================================================================== */

define( 'STOREFRONT_CHILD_PHONE_NUMBER', '+48173070844' );
define( 'STOREFRONT_CHILD_PHONE_FORM', '[contact-form-7 id="4713ba3" title="Zamowienie-telefoniczne"]' );

/**
 * Business hours, keyed by ISO-8601 day number (1 = Monday ... 7 = Sunday).
 *
 * Each value is array( open, close ) in 24h HH:MM, or null for a closed day.
 * Single source of truth: PHP reads this directly and the same array is handed
 * to the front-end script, so the two can never drift apart.
 *
 * @return array
 */
function storefront_child_phone_order_hours() {
	return apply_filters( 'storefront_child_phone_order_hours', array(
		1 => array( '10:00', '18:00' ),
		2 => array( '10:00', '18:00' ),
		3 => array( '10:00', '18:00' ),
		4 => array( '10:00', '18:00' ),
		5 => array( '10:00', '18:00' ),
		6 => array( '10:00', '13:00' ),
		7 => null,
	) );
}

/**
 * Are phone orders being taken right now?
 *
 * @return bool
 */
function storefront_child_phone_order_is_open() {
	$now   = new DateTimeImmutable( 'now', wp_timezone() );
	$hours = storefront_child_phone_order_hours();
	$today = (int) $now->format( 'N' );

	if ( empty( $hours[ $today ] ) ) {
		return false;
	}

	list( $open, $close ) = $hours[ $today ];
	$minutes              = (int) $now->format( 'G' ) * 60 + (int) $now->format( 'i' );

	return $minutes >= storefront_child_hhmm_to_minutes( $open )
		&& $minutes < storefront_child_hhmm_to_minutes( $close );
}

/**
 * "10:00" -> 600. Minutes since midnight.
 *
 * @param string $hhmm 24h time.
 * @return int
 */
function storefront_child_hhmm_to_minutes( $hhmm ) {
	$parts = explode( ':', $hhmm );
	return (int) $parts[0] * 60 + (int) ( isset( $parts[1] ) ? $parts[1] : 0 );
}

/**
 * Tracks whether the button has already been output for this request, so the
 * inline hook and the fallback hook below never both fire.
 *
 * @param bool|null $set Pass true to mark as rendered; null to read.
 * @return bool
 */
function storefront_child_phone_order_rendered( $set = null ) {
	static $rendered = false;
	if ( true === $set ) {
		$rendered = true;
	}
	return $rendered;
}

/**
 * Preferred position: inside <form class="cart">, immediately after the
 * add-to-cart button, which puts the two in the same flex row.
 *
 * Restricted to simple and external products on purpose:
 *
 * - variable: this hook lives in variation-add-to-cart-button.php, inside
 *   .woocommerce-variation-add-to-cart, which WooCommerce keeps hidden until a
 *   variation is selected — the phone button would vanish with it.
 * - grouped: the form wraps a product table, and turning it into a flex row
 *   would drag the table and the button side by side.
 *
 * Both fall through to the standalone position below instead.
 */
add_action( 'woocommerce_after_add_to_cart_button', 'storefront_child_phone_order_inline' );
function storefront_child_phone_order_inline() {
	global $product;

	if ( ! is_a( $product, 'WC_Product' ) || ! $product->is_type( array( 'simple', 'external' ) ) ) {
		return;
	}

	storefront_child_phone_order_rendered( true );
	get_template_part( 'template-parts/phone-order-button' );
}

/**
 * Fallback position: its own row directly under the add-to-cart area.
 *
 * Priority 31 sits between woocommerce_template_single_add_to_cart (30) and
 * woocommerce_template_single_meta (40). This is what renders the button for
 * variable and grouped products, and — the case that matters most — for
 * out-of-stock products, where there is no add-to-cart form at all and calling
 * is the only way left to order.
 */
add_action( 'woocommerce_single_product_summary', 'storefront_child_phone_order_standalone', 31 );
function storefront_child_phone_order_standalone() {
	if ( storefront_child_phone_order_rendered() ) {
		return;
	}

	storefront_child_phone_order_rendered( true );
	get_template_part( 'template-parts/phone-order-button', null, array( 'standalone' => true ) );
}

/**
 * The dialog itself, rendered after the product summary.
 *
 * Two constraints pick this hook:
 *
 * 1. It must sit outside <form class="cart">. Contact Form 7 outputs a <form>
 *    of its own, and nested forms are invalid HTML — the browser drops the
 *    inner one, which would break submission entirely.
 * 2. It must sit inside the loop. Contact Form 7 records which post a form was
 *    embedded in via its _wpcf7_container_post hidden field, and
 *    WPCF7_ContactForm::form_hidden_fields() only fills that in when
 *    in_the_loop() is true. Rendered on wp_footer — after the loop has ended —
 *    it would be 0, and the [_post_title] / [_post_url] special mail tags
 *    would silently resolve to nothing.
 *
 * woocommerce_after_single_product_summary satisfies both: it fires inside
 * content-single-product.php's loop, after the .summary div has closed.
 * Priority 5 keeps it ahead of the upsell (15) and related products (20)
 * blocks.
 */
add_action( 'woocommerce_after_single_product_summary', 'storefront_child_phone_order_dialog', 5 );
function storefront_child_phone_order_dialog() {
	if ( ! storefront_child_phone_order_rendered() ) {
		return;
	}

	get_template_part( 'template-parts/phone-order-dialog' );
}

/**
 * Give the form's submit button Storefront's primary-button styling.
 *
 * Sending the callback request is the primary action inside the dialog, so it
 * should look exactly like "Dodaj do koszyka". Those colours are Customizer
 * settings (storefront_button_alt_background_color and friends) rather than
 * fixed values in the stylesheet, so copying hex codes into our SCSS would
 * drift the moment anyone changes the theme colours. Borrowing the classes
 * keeps the two in step permanently.
 *
 * Applied only around our own do_shortcode() call — see
 * template-parts/phone-order-dialog.php — so other Contact Form 7 forms on the
 * site keep their default appearance.
 *
 * @param string $html Rendered form markup.
 * @return string
 */
function storefront_child_phone_order_submit_classes( $html ) {
	return str_replace( 'wpcf7-submit', 'wpcf7-submit button alt', $html );
}


/* ==========================================================================
   Homepage slider.

   A banner carousel that sits directly below the primary navigation on the
   front page only, spanning the full container width — i.e. across both the
   content column and the sidebar, which the homepage keeps.

   Slides are a small custom post type rather than a hardcoded array or a
   Customizer section, so the shop owner can add, remove and reorder banners in
   wp-admin without a code change. Each slide is a featured image plus an
   optional destination URL; the artwork carries its own text, so there is no
   overlaid markup to fight the image on narrow screens.
   ========================================================================== */

/**
 * Register the "Slajdy" post type.
 *
 * `public => false` with `show_ui => true` is deliberate: slides are fragments
 * of the homepage, not pages of their own. That combination gives them an
 * admin screen while keeping them out of the front-end router, out of search
 * results, and out of the nav-menu picker — a visitor can never land on
 * /sc_slide/whatever.
 *
 * `page-attributes` is what supplies the numeric "Kolejność" field. The query
 * below orders by menu_order, so that field is how the banners are sequenced.
 *
 * The post type is left out of the REST API, which means WordPress serves it
 * with the classic editor. That is the desired screen here: a title, a
 * featured image and a URL, with no block canvas inviting body content that
 * the template would silently ignore.
 */
add_action( 'init', 'storefront_child_register_slide_post_type' );
function storefront_child_register_slide_post_type() {
	register_post_type(
		'sc_slide',
		array(
			'labels'               => array(
				'name'                  => __( 'Slajdy', 'storefront-child' ),
				'singular_name'         => __( 'Slajd', 'storefront-child' ),
				'menu_name'             => __( 'Slajdy', 'storefront-child' ),
				'add_new'               => __( 'Dodaj nowy', 'storefront-child' ),
				'add_new_item'          => __( 'Dodaj nowy slajd', 'storefront-child' ),
				'edit_item'             => __( 'Edytuj slajd', 'storefront-child' ),
				'new_item'              => __( 'Nowy slajd', 'storefront-child' ),
				'view_item'             => __( 'Zobacz slajd', 'storefront-child' ),
				'search_items'          => __( 'Szukaj slajdów', 'storefront-child' ),
				'all_items'             => __( 'Wszystkie slajdy', 'storefront-child' ),
				'not_found'             => __( 'Nie znaleziono slajdów.', 'storefront-child' ),
				'not_found_in_trash'    => __( 'Brak slajdów w koszu.', 'storefront-child' ),
				'featured_image'        => __( 'Grafika slajdu', 'storefront-child' ),
				'set_featured_image'    => __( 'Ustaw grafikę slajdu', 'storefront-child' ),
				'remove_featured_image' => __( 'Usuń grafikę slajdu', 'storefront-child' ),
				'use_featured_image'    => __( 'Użyj jako grafiki slajdu', 'storefront-child' ),
			),
			'public'               => false,
			'show_ui'              => true,
			'show_in_menu'         => true,
			'show_in_nav_menus'    => false,
			'publicly_queryable'   => false,
			'exclude_from_search'  => true,
			'has_archive'          => false,
			'rewrite'              => false,
			'query_var'            => false,
			'hierarchical'         => false,
			'menu_position'        => 21,
			'menu_icon'            => 'dashicons-images-alt2',
			'supports'             => array( 'title', 'thumbnail', 'page-attributes' ),
			'register_meta_box_cb' => 'storefront_child_slide_meta_boxes',
		)
	);
}

/**
 * The 3:1 banner crop the slider renders.
 *
 * Hard-cropped on purpose, so every slide is exactly the same shape and the
 * page does not change height as the carousel advances. This does not conflict
 * with storefront_child_uncropped_thumbnail() above: that filter targets
 * WooCommerce's `woocommerce_thumbnail` size for product images, which is a
 * different size entirely.
 *
 * Registered at `after_setup_theme` because the parent theme's
 * add_theme_support( 'post-thumbnails' ) runs there (see
 * storefront/inc/class-storefront.php); image sizes are only meaningful once
 * thumbnail support exists.
 */
add_action( 'after_setup_theme', 'storefront_child_register_slide_image_size', 20 );
function storefront_child_register_slide_image_size() {
	add_image_size( 'storefront_child_slide', 1200, 400, true );
}

/**
 * Offer the slide crop in the media library's size dropdown, so the size is
 * visible (and pickable) rather than existing only in code.
 */
add_filter( 'image_size_names_choose', 'storefront_child_slide_image_size_name' );
function storefront_child_slide_image_size_name( $sizes ) {
	$sizes['storefront_child_slide'] = __( 'Slajd (1200×400)', 'storefront-child' );
	return $sizes;
}

/**
 * Meta boxes for the slide edit screen.
 *
 * Wired through register_post_type()'s `register_meta_box_cb` rather than the
 * add_meta_boxes hook — same result, but it keeps the box's existence tied to
 * the post type that owns it.
 *
 * @param WP_Post $post Slide being edited.
 */
function storefront_child_slide_meta_boxes( $post ) {
	add_meta_box(
		'storefront-child-slide-url',
		__( 'Odnośnik slajdu', 'storefront-child' ),
		'storefront_child_slide_url_meta_box',
		'sc_slide',
		'normal',
		'high'
	);
}

/**
 * The destination-URL field.
 *
 * Optional: a slide with no URL renders as a plain image rather than as an
 * anchor with an empty href, which would be a focusable control that goes
 * nowhere.
 *
 * @param WP_Post $post Slide being edited.
 */
function storefront_child_slide_url_meta_box( $post ) {
	$url = get_post_meta( $post->ID, '_storefront_child_slide_url', true );

	wp_nonce_field( 'storefront_child_slide_url', 'storefront_child_slide_url_nonce' );
	?>
	<p>
		<label for="storefront-child-slide-url">
			<?php esc_html_e( 'Adres, do którego prowadzi slajd (opcjonalnie):', 'storefront-child' ); ?>
		</label>
	</p>
	<p>
		<input type="url" class="large-text code" id="storefront-child-slide-url"
			name="storefront_child_slide_url"
			value="<?php echo esc_attr( $url ); ?>"
			placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
	</p>
	<p class="description">
		<?php esc_html_e( 'Zostaw puste, aby slajd nie był klikalny.', 'storefront-child' ); ?>
	</p>
	<?php
}

/**
 * Persist the destination URL.
 *
 * Bails on autosave and on a missing/invalid nonce, and re-checks the
 * capability against this specific post rather than trusting that only editors
 * reach the screen.
 *
 * @param int     $post_id Slide ID.
 * @param WP_Post $post    Slide.
 */
add_action( 'save_post_sc_slide', 'storefront_child_save_slide_url', 10, 2 );
function storefront_child_save_slide_url( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['storefront_child_slide_url_nonce'] ) ) {
		return; // Not our form (quick edit, REST, programmatic insert).
	}

	$nonce = sanitize_key( wp_unslash( $_POST['storefront_child_slide_url_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'storefront_child_slide_url' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$url = isset( $_POST['storefront_child_slide_url'] )
		? esc_url_raw( wp_unslash( $_POST['storefront_child_slide_url'] ) )
		: '';

	if ( '' === $url ) {
		delete_post_meta( $post_id, '_storefront_child_slide_url' );
	} else {
		update_post_meta( $post_id, '_storefront_child_slide_url', $url );
	}
}

/**
 * Show the banner itself in the Slajdy list table.
 *
 * The title alone is a poor index of a list of images, and the point of the
 * screen is picking which artwork goes where.
 */
add_filter( 'manage_sc_slide_posts_columns', 'storefront_child_slide_columns' );
function storefront_child_slide_columns( $columns ) {
	$new = array();

	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$new['storefront_child_slide_image'] = __( 'Grafika', 'storefront-child' );
		}
		$new[ $key ] = $label;
	}

	return $new;
}

add_action( 'manage_sc_slide_posts_custom_column', 'storefront_child_slide_column_content', 10, 2 );
function storefront_child_slide_column_content( $column, $post_id ) {
	if ( 'storefront_child_slide_image' !== $column ) {
		return;
	}

	if ( has_post_thumbnail( $post_id ) ) {
		echo get_the_post_thumbnail( $post_id, array( 120, 40 ) );
	} else {
		echo '<em>' . esc_html__( 'Brak grafiki', 'storefront-child' ) . '</em>';
	}
}

/**
 * The slides to render, in display order.
 *
 * Slides without a featured image are dropped rather than rendered as an empty
 * frame — a half-filled draft in the list should not punch a hole in the
 * homepage.
 *
 * The result is cached for the request because two callers need it: the
 * enqueue below (to decide whether the script is needed at all) and the render
 * hook. It is also filterable, which is what makes the markup testable without
 * writing slides to the database.
 *
 * @return array List of array( image_id, title, url ).
 */
function storefront_child_get_homepage_slides() {
	static $slides = null;

	if ( null !== $slides ) {
		return $slides;
	}

	$slides = array();

	$posts = get_posts(
		array(
			'post_type'        => 'sc_slide',
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'orderby'          => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
			'suppress_filters' => false,
		)
	);

	foreach ( $posts as $slide ) {
		$image_id = (int) get_post_thumbnail_id( $slide );

		if ( ! $image_id ) {
			continue;
		}

		$slides[] = array(
			'image_id' => $image_id,
			'title'    => $slide->post_title,
			'url'      => (string) get_post_meta( $slide->ID, '_storefront_child_slide_url', true ),
		);
	}

	/**
	 * Filter the homepage slides.
	 *
	 * @param array $slides List of array( image_id, title, url ).
	 */
	$slides = apply_filters( 'storefront_child_homepage_slides', $slides );

	return $slides;
}

/**
 * Should the slider render on this request?
 *
 * is_front_page() is true only for the static page set as the front page, so
 * the slider never appears on the blog index, the shop archive or anywhere
 * else. is_paged() excludes paginated views of that page, where a banner
 * repeated above page 2 of a listing is just noise.
 *
 * @return bool
 */
function storefront_child_show_homepage_slider() {
	return is_front_page()
		&& ! is_paged()
		&& (bool) storefront_child_get_homepage_slides();
}

/**
 * Print the slider directly below the primary navigation.
 *
 * `storefront_before_content` fires in the parent theme's header.php between
 * </header> and <div id="content">, which is exactly the gap under the nav bar.
 * Priority 5 puts the banner above the two callbacks the parent already hooks
 * there at 10 (storefront_header_widget_region and woocommerce_breadcrumb), so
 * it stays flush with the navigation.
 *
 * The template wraps itself in .col-full, so the slider spans the same width
 * as the content + sidebar row beneath it rather than the narrower content
 * column.
 */
add_action( 'storefront_before_content', 'storefront_child_homepage_slider', 5 );
function storefront_child_homepage_slider() {
	if ( ! storefront_child_show_homepage_slider() ) {
		return;
	}

	get_template_part(
		'template-parts/homepage-slider',
		null,
		array( 'slides' => storefront_child_get_homepage_slides() )
	);
}


/*
 * Hide parcel-machine shipping for big parcels.
 */
add_filter( 'woocommerce_package_rates', 'gama_ukryj_paczkomaty_dla_duzych_paczek', 100, 2 );
function gama_ukryj_paczkomaty_dla_duzych_paczek( $rates, $package ) {
	$klasa = 'duze-wymiary';
	$ukryj = array( 'easypack_parcel_machines', 'inpost_paczkomaty' ); // uzupełnij wg kroku 3

	$duze = false;
	foreach ( $package['contents'] as $item ) {
		if ( $item['data']->get_shipping_class() === $klasa ) {
			$duze = true;
			break;
		}
	}

	if ( ! $duze ) {
		return $rates;
	}

	foreach ( $rates as $id => $rate ) {
		if ( in_array( $rate->get_method_id(), $ukryj, true ) ) {
			unset( $rates[ $id ] );
		}
	}

	return $rates;
}