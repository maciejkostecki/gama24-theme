<?php
/**
 * Phone-order button: the control that sits beside the add-to-cart button.
 *
 * Rendered by storefront_child_phone_order_inline() inside <form class="cart">,
 * or by storefront_child_phone_order_standalone() as its own row for variable,
 * grouped and out-of-stock products. See functions.php.
 *
 * Both states are printed and one is hidden with CSS, driven by the
 * data-phone-order-state attribute. PHP sets that attribute to the correct
 * value; assets/js/phone-order.js may correct it on load. The two-state markup
 * is what makes the client-side correction possible without a second request.
 *
 * The dialog this button opens lives in template-parts/phone-order-dialog.php
 * and is printed in the footer — it contains a <form>, which cannot legally
 * nest inside the add-to-cart form.
 *
 * @package storefront-child
 */

defined( 'ABSPATH' ) || exit;

$phone_order_state = storefront_child_phone_order_is_open() ? 'open' : 'closed';
$phone_order_tel   = STOREFRONT_CHILD_PHONE_NUMBER;
$phone_order_class = ! empty( $args['standalone'] ) ? ' phone-order--standalone' : '';

?>
<div class="phone-order<?php echo esc_attr( $phone_order_class ); ?>" data-phone-order-state="<?php echo esc_attr( $phone_order_state ); ?>">
	<a class="button phone-order__button phone-order__button--call" href="tel:<?php echo esc_attr( $phone_order_tel ); ?>">
		<?php esc_html_e( 'Zamów przez telefon', 'storefront-child' ); ?>
	</a>
	<button type="button" class="button phone-order__button phone-order__button--form" data-phone-order-open aria-haspopup="dialog">
		<?php esc_html_e( 'Zamów przez telefon', 'storefront-child' ); ?>
	</button>
</div><!-- .phone-order -->
