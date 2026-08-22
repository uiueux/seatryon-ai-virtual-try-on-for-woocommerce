<?php
/**
 * Accessible frontend modal shell.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Frontend;

use SeaTryOn\Domain\ExperienceType;

defined( 'ABSPATH' ) || exit;

/**
 * Renders one form-independent dialog mount per page.
 */
final class ModalRenderer {

	/**
	 * Whether the single root was emitted.
	 *
	 * @var bool
	 */
	private $rendered = false;

	/**
	 * Render the modal root for the first eligible product on the page.
	 *
	 * @param array<int,string> $products Product IDs mapped to experience types.
	 */
	public function render( array $products ): string {
		if ( $this->rendered || array() === $products ) {
			return '';
		}

		$product_id = (int) array_key_first( $products );
		$experience = (string) $products[ $product_id ];
		$is_scene   = in_array( $experience, ExperienceType::scene_values(), true );
		$is_person  = in_array( $experience, ExperienceType::person_values(), true );
		$mode       = $is_scene ? 'scene' : ( $is_person ? 'person' : 'auto' );

		if ( $is_scene ) {
			$upload_label = __( 'Upload your room or scene', 'seatryon-ai-virtual-try-on-for-woocommerce' );
			$helper       = __( 'Use a clear, well-lit image that shows where you want to place the product.', 'seatryon-ai-virtual-try-on-for-woocommerce' );
		} elseif ( $is_person ) {
			$upload_label = __( 'Upload your photo', 'seatryon-ai-virtual-try-on-for-woocommerce' );
			$helper       = __( 'Use a clear, well-lit photo. Keep your face and the relevant body area visible for best results.', 'seatryon-ai-virtual-try-on-for-woocommerce' );
		} else {
			$upload_label = __( 'Upload your photo or scene', 'seatryon-ai-virtual-try-on-for-woocommerce' );
			$helper       = __( 'Use a clear, well-lit image that shows the person or place for this product.', 'seatryon-ai-virtual-try-on-for-woocommerce' );
		}

		$this->rendered = true;
		$prefix         = 'sea-tryon-' . $product_id;

		ob_start();
		?>
		<div class="sea-tryon-modal" data-sea-tryon-root data-product-id="<?php echo esc_attr( (string) $product_id ); ?>" data-experience-type="<?php echo esc_attr( $experience ); ?>" data-experience-mode="<?php echo esc_attr( $mode ); ?>" data-has-file="false" hidden>
			<div class="sea-tryon-modal__backdrop" data-sea-tryon-backdrop>
				<div id="<?php echo esc_attr( 'sea-tryon-dialog-' . $product_id ); ?>" class="sea-tryon-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $prefix . '-title' ); ?>" aria-describedby="<?php echo esc_attr( $prefix . '-description ' . $prefix . '-helper' ); ?>" tabindex="-1">
					<div class="sea-tryon-modal__header">
						<h2 id="<?php echo esc_attr( $prefix . '-title' ); ?>" class="sea-tryon-modal__title">
							<span class="dashicons dashicons-star-filled sea-tryon-modal__brand-icon" aria-hidden="true"></span>
							<span><?php esc_html_e( 'Virtual Try-On', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
						</h2>
						<button type="button" class="sea-tryon-modal__close" data-sea-tryon-close aria-label="<?php esc_attr_e( 'Close Virtual Try-On', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
					</div>

					<p id="<?php echo esc_attr( $prefix . '-description' ); ?>" class="sea-tryon-modal__description"><?php esc_html_e( 'Create an AI preview using this product and an image you choose.', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></p>
					<div class="sea-tryon-login" data-sea-tryon-login hidden>
						<p><?php esc_html_e( 'Please log in to use Virtual Try-On.', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></p>
						<a class="sea-tryon__button" data-sea-tryon-login-link href="#"><?php esc_html_e( 'Log in', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></a>
					</div>

					<div class="sea-tryon-workflow" data-sea-tryon-workflow>
						<section class="sea-tryon-upload" aria-labelledby="<?php echo esc_attr( $prefix . '-upload-heading' ); ?>">
							<h3 id="<?php echo esc_attr( $prefix . '-upload-heading' ); ?>" class="sea-tryon-step-heading">
								<span class="sea-tryon-step-heading__number" aria-hidden="true">1</span>
								<span class="sea-tryon-step-heading__label sea-tryon-step-heading__label--upload"><?php echo esc_html( $upload_label ); ?></span>
								<span class="sea-tryon-step-heading__label sea-tryon-step-heading__label--uploaded"><?php esc_html_e( 'Uploaded photo', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
							</h3>

							<div class="sea-tryon-upload__sources" data-sea-tryon-sources>
								<label for="<?php echo esc_attr( $prefix . '-file' ); ?>" class="sea-tryon-upload__dropzone">
									<span class="dashicons dashicons-upload sea-tryon-upload__icon" aria-hidden="true"></span>
									<span class="sea-tryon-upload__prompt"><?php esc_html_e( 'Click to upload or drag and drop', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
									<span class="sea-tryon-upload__formats"><?php esc_html_e( 'JPG, PNG, or WebP · Max 10MB', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
									<input id="<?php echo esc_attr( $prefix . '-file' ); ?>" class="sea-tryon-upload__input" type="file" accept="image/jpeg,image/png,image/webp" data-sea-tryon-file aria-describedby="<?php echo esc_attr( $prefix . '-helper ' . $prefix . '-upload-error' ); ?>">
								</label>
								<button type="button" class="sea-tryon-camera__open" data-sea-tryon-camera-open aria-describedby="<?php echo esc_attr( $prefix . '-helper ' . $prefix . '-upload-error' ); ?>">
									<span class="dashicons dashicons-camera" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Take a photo', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
								</button>
							</div>

							<div class="sea-tryon-camera" data-sea-tryon-camera hidden>
								<video class="sea-tryon-camera__video" data-sea-tryon-camera-video autoplay muted playsinline aria-label="<?php esc_attr_e( 'Live camera preview', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?>"></video>
								<div class="sea-tryon-camera__actions">
									<button type="button" class="sea-tryon__button" data-sea-tryon-camera-capture>
										<span class="dashicons dashicons-camera" aria-hidden="true"></span>
										<span><?php esc_html_e( 'Capture photo', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
									</button>
									<button type="button" class="sea-tryon-modal__cancel" data-sea-tryon-camera-cancel><?php esc_html_e( 'Cancel camera', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></button>
								</div>
							</div>

							<div class="sea-tryon-preview" data-sea-tryon-preview hidden>
								<img class="sea-tryon-preview__image" data-sea-tryon-preview-image alt="<?php esc_attr_e( 'Preview of your selected image', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?>">
								<div class="sea-tryon-preview__details">
									<p class="sea-tryon-preview__name" data-sea-tryon-file-name></p>
									<p class="sea-tryon-preview__meta" data-sea-tryon-file-meta></p>
									<button type="button" class="sea-tryon-preview__remove" data-sea-tryon-remove>
										<span class="dashicons dashicons-edit" aria-hidden="true"></span>
										<span><?php esc_html_e( 'Change photo', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
									</button>
								</div>
							</div>

							<div id="<?php echo esc_attr( $prefix . '-helper' ); ?>" class="sea-tryon-upload__helper">
								<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
								<span><?php echo esc_html( $helper ); ?></span>
							</div>
							<p id="<?php echo esc_attr( $prefix . '-upload-error' ); ?>" class="sea-tryon-upload__error" data-sea-tryon-upload-error role="alert" hidden></p>
						</section>

						<label class="sea-tryon-consent">
							<input type="checkbox" data-sea-tryon-consent>
							<span><?php esc_html_e( 'I agree that my uploaded image will be sent to the selected AI provider to generate this preview.', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
						</label>

						<div class="sea-tryon-progress" data-sea-tryon-progress aria-hidden="true">
							<span class="dashicons dashicons-update sea-tryon-progress__spinner" aria-hidden="true"></span>
							<div>
								<p class="sea-tryon-progress__title"><?php esc_html_e( 'Generating your preview…', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></p>
								<p class="sea-tryon-progress__copy"><?php esc_html_e( "This may take a moment. Please don't close this window.", 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></p>
							</div>
						</div>

						<p class="sea-tryon-status" data-sea-tryon-status role="status" aria-live="polite" aria-atomic="true"></p>

						<div class="sea-tryon-result" data-sea-tryon-result hidden>
							<h3 class="sea-tryon-step-heading">
								<span class="sea-tryon-step-heading__number" aria-hidden="true">2</span>
								<span class="sea-tryon-step-heading__label"><?php esc_html_e( 'Your preview', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
							</h3>
							<div class="sea-tryon-result__media">
								<img class="sea-tryon-result__image" data-sea-tryon-result-image alt="<?php esc_attr_e( 'AI-generated virtual try-on preview', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?>">
								<span class="sea-tryon-result__badge"><?php esc_html_e( 'AI Preview', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
								<button type="button" class="sea-tryon-result__zoom" data-sea-tryon-zoom aria-label="<?php esc_attr_e( 'View full-size preview', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?>" disabled>
									<span class="dashicons dashicons-search" aria-hidden="true"></span>
								</button>
							</div>
							<div class="sea-tryon-result__actions">
								<a class="sea-tryon__button" data-sea-tryon-download download>
									<span class="dashicons dashicons-download" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Download', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
								</a>
								<button type="button" class="sea-tryon-modal__cancel" data-sea-tryon-try-again>
									<span class="dashicons dashicons-image-rotate" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Try Again', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
								</button>
								<button type="button" class="sea-tryon-modal__cancel sea-tryon-result__delete" data-sea-tryon-delete>
									<span class="dashicons dashicons-trash" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Delete preview', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
								</button>
							</div>
						</div>

						<div class="sea-tryon-modal__actions">
							<button type="button" class="sea-tryon__button sea-tryon-modal__generate" data-sea-tryon-generate disabled>
								<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
								<span><?php esc_html_e( 'Generate Try-On', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
							</button>
							<button type="button" class="sea-tryon-modal__cancel" data-sea-tryon-error-retry hidden><?php esc_html_e( 'Retry', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></button>
							<button type="button" class="sea-tryon-modal__cancel" data-sea-tryon-close><?php esc_html_e( 'Cancel', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></button>
						</div>

						<p class="sea-tryon-disclaimer">
							<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
							<span><?php esc_html_e( 'AI-generated previews may be inaccurate and do not guarantee fit, size, color, or appearance.', 'seatryon-ai-virtual-try-on-for-woocommerce' ); ?></span>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
