<?php
/**
 * Homepage slider: the banner carousel below the primary navigation.
 *
 * Printed by storefront_child_homepage_slider() on the storefront_before_content
 * hook — see functions.php. Slides come from the "Slajdy" post type.
 *
 * The .col-full wrapper is what makes this full container width: it is the
 * parent theme's own container class, so the banner lines up with the content
 * + sidebar row below it instead of sitting inside the narrower content column.
 *
 * Structurally this is a scroll-snap track, not a transform-based carousel.
 * The consequence is that with JavaScript disabled — or before the script has
 * loaded — the slider is still a working, swipeable, keyboard-scrollable strip
 * of banners rather than a single frozen frame. assets/js/homepage-slider.js
 * only adds autoplay and the arrow/dot controls on top of that, which is why
 * those controls are printed `hidden` here and revealed by the script: a
 * control that cannot work should not be visible or focusable.
 *
 * @package storefront-child
 */

defined( 'ABSPATH' ) || exit;

$slides = isset( $args['slides'] ) ? (array) $args['slides'] : array();

if ( empty( $slides ) ) {
	return;
}

$slide_count = count( $slides );

?>
<div class="homepage-slider-wrapper">
	<div class="col-full">
		<section class="homepage-slider" data-homepage-slider
			aria-roledescription="<?php esc_attr_e( 'karuzela', 'storefront-child' ); ?>"
			aria-label="<?php esc_attr_e( 'Promocje i aktualności', 'storefront-child' ); ?>">

			<ul class="homepage-slider__track" data-homepage-slider-track>
				<?php foreach ( $slides as $index => $slide ) : ?>
					<?php
					$is_first = ( 0 === $index );

					// Respect the image's own alt text; fall back to the slide
					// title only when the media library has none, so editors
					// keep control of what screen readers announce.
					$image_alt = trim( (string) get_post_meta( $slide['image_id'], '_wp_attachment_image_alt', true ) );

					$image_attr = array(
						'class'   => 'homepage-slider__image',
						'loading' => $is_first ? 'eager' : 'lazy',
					);

					if ( '' === $image_alt ) {
						$image_attr['alt'] = $slide['title'];
					}

					// The first banner is almost always the LCP element, and a
					// lazy/low-priority hero is a measurable delay.
					if ( $is_first ) {
						$image_attr['fetchpriority'] = 'high';
					}

					$image = wp_get_attachment_image( $slide['image_id'], 'storefront_child_slide', false, $image_attr );

					if ( ! $image ) {
						continue;
					}
					?>
					<li class="homepage-slider__slide" role="group"
						aria-roledescription="<?php esc_attr_e( 'slajd', 'storefront-child' ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: 1: slide number, 2: total slides. */ __( '%1$d z %2$d', 'storefront-child' ), $index + 1, $slide_count ) ); ?>">
						<?php if ( '' !== $slide['url'] ) : ?>
							<a class="homepage-slider__link" href="<?php echo esc_url( $slide['url'] ); ?>">
								<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output. ?>
							</a>
						<?php else : ?>
							<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output. ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( $slide_count > 1 ) : ?>
				<button type="button" class="homepage-slider__arrow homepage-slider__arrow--prev"
					data-homepage-slider-prev hidden
					aria-label="<?php esc_attr_e( 'Poprzedni slajd', 'storefront-child' ); ?>">
					<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
						<path d="M15.4 3.6 7 12l8.4 8.4 1.6-1.6-6.8-6.8 6.8-6.8z" />
					</svg>
				</button>

				<button type="button" class="homepage-slider__arrow homepage-slider__arrow--next"
					data-homepage-slider-next hidden
					aria-label="<?php esc_attr_e( 'Następny slajd', 'storefront-child' ); ?>">
					<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
						<path d="M8.6 3.6 7 5.2l6.8 6.8-6.8 6.8 1.6 1.6L17 12z" />
					</svg>
				</button>

				<ul class="homepage-slider__dots" data-homepage-slider-dots hidden>
					<?php foreach ( $slides as $index => $slide ) : ?>
						<li class="homepage-slider__dots-item">
							<button type="button" class="homepage-slider__dot"
								data-homepage-slider-dot="<?php echo esc_attr( $index ); ?>"
								<?php echo 0 === $index ? ' aria-current="true"' : ''; ?>
								aria-label="<?php echo esc_attr( sprintf( /* translators: %d: slide number. */ __( 'Przejdź do slajdu %d', 'storefront-child' ), $index + 1 ) ); ?>">
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

		</section><!-- .homepage-slider -->
	</div><!-- .col-full -->
</div><!-- .homepage-slider-wrapper -->
