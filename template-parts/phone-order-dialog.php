<?php
/**
 * Phone-order dialog: the callback-request form shown outside business hours.
 *
 * Printed once per request on woocommerce_after_single_product_summary —
 * outside <form class="cart"> because Contact Form 7 renders its own <form>
 * and nested forms are invalid HTML, and inside the loop because that is the
 * only way Contact Form 7 records the container post. See
 * storefront_child_phone_order_dialog() in functions.php.
 *
 * Always rendered when the button is on the page, including during business
 * hours, so the client-side hours re-check in assets/js/phone-order.js has
 * something to open if it decides the shop has just closed.
 *
 * The form knows which product it was opened from without any hidden fields:
 * it is embedded on the product page, so Contact Form 7's special mail tags
 * [_post_title], [_post_url] and [_post_id] resolve to the product. Add them
 * to the form's Mail tab.
 *
 * @package storefront-child
 */

defined( 'ABSPATH' ) || exit;

?>
<dialog class="phone-order-dialog" data-phone-order-dialog aria-label="<?php esc_attr_e( 'Zamówienie telefoniczne', 'storefront-child' ); ?>">
	<div class="phone-order-dialog__inner">
		<button type="button" class="phone-order-dialog__close" data-phone-order-close aria-label="<?php esc_attr_e( 'Zamknij', 'storefront-child' ); ?>">&times;</button>

		<h2 class="phone-order-dialog__title"><?php esc_html_e( 'Zamów przez telefon', 'storefront-child' ); ?></h2>

		<?php
		// Primary-action styling for the submit button, this form only.
		add_filter( 'wpcf7_form_elements', 'storefront_child_phone_order_submit_classes' );
		echo do_shortcode( STOREFRONT_CHILD_PHONE_FORM );
		remove_filter( 'wpcf7_form_elements', 'storefront_child_phone_order_submit_classes' );
		?>
	</div>
</dialog><!-- .phone-order-dialog -->
