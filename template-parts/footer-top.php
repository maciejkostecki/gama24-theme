<?php
/**
 * Footer top: the first block inside the site footer.
 *
 * Rendered on every page via the `storefront_footer` action at priority 5 —
 * see storefront_child_footer_top() in functions.php. Because it is hooked
 * rather than added to a specific template, it appears regardless of URL:
 * front page, shop, archives, single products, cart, checkout, 404, search.
 *
 * Markup sits inside Storefront's <footer id="colophon"> and its .col-full
 * wrapper, so it inherits the footer background and the site's max width.
 *
 * @package storefront-child
 */

defined( 'ABSPATH' ) || exit;

?>
<section class="storefront-child-footer-top">
	<a class="phone-order-link" href="tel:+48178521265">
		<h3>Zamówienia telefoniczne:</h3>
		<span>+48 17 852 12 65</span>
	</a>
</section><!-- .storefront-child-footer-top -->
