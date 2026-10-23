<?php
/**
 * Admin settings page template — redesigned for clarity.
 *
 * Primary goal: make Image Uniformity the hero action.
 * Secondary features are presented as optional add-ons.
 *
 * @package WP_Smart_Image_Resize
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'bulk-regenerate';
$settings    = wp_sir_get_settings();
?>
<div class="wrap wp-sir-wrap">

	
	
	<h1><?php esc_html_e( 'Smart Image Resize', 'wp-smart-image-resize' ); ?> <span class="wp-sir-version">v<?php echo esc_html( WP_SIR_VERSION ); ?></span></h1>
	

	<nav class="nav-tab-wrapper wp-sir-nav" aria-label="<?php esc_attr_e( 'Plugin navigation', 'wp-smart-image-resize' ); ?>">
		<a href="?page=wp-smart-image-resize&amp;tab=bulk-regenerate"
		   class="nav-tab <?php echo $current_tab === 'bulk-regenerate' ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-images-alt2" aria-hidden="true"></span>
			<?php esc_html_e( 'Bulk Resize', 'wp-smart-image-resize' ); ?>
		</a>
		<a href="?page=wp-smart-image-resize&amp;tab=general"
		   class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
			<?php esc_html_e( 'Settings', 'wp-smart-image-resize' ); ?>
		</a>
		
		<a href="?page=wp-smart-image-resize&amp;tab=help"
		   class="nav-tab <?php echo $current_tab === 'help' ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
			<?php esc_html_e( 'Help', 'wp-smart-image-resize' ); ?>
		</a>
	</nav>

	<?php if ( $current_tab === 'general' ) : ?>

		<div class="wp-sir-layout">

			<!-- ── MAIN COLUMN ─────────────────────────────────────── -->
			<div class="wp-sir-main">

				<form method="post" action="options.php" id="wp-sir-settings-form">
					<?php
					// settings_fields() outputs the nonce, option_page, and _wp_http_referer hidden inputs.
					// We do NOT call do_settings_sections() — fields are rendered manually below
					// to give us full control over the layout.
					settings_fields( WP_SIR_NAME );
					?>

					<!-- ══ CORE SETTINGS ══════════════════════════════════ -->
					<div class="wp-sir-card" id="wp-sir-core-settings">
						<div class="wp-sir-card__header">
							<span class="dashicons dashicons-images-alt2 wp-sir-card__icon" aria-hidden="true"></span>
							<div>
								<h3><?php esc_html_e( 'Auto-Resize', 'wp-smart-image-resize' ); ?></h3>
								<span class="wp-sir-card__desc"><?php esc_html_e( 'Automatically resize uploaded images to ensure a consistent look across your store.', 'wp-smart-image-resize' ); ?></span>
							</div>
							<label class="wp-sir-big-toggle" for="wp-sir-enable" title="<?php esc_attr_e( 'Enable or disable image uniformity', 'wp-smart-image-resize' ); ?>">
								<input type="checkbox"
								       class="wp-sir-as-toggle wp-sir-as-toggle--large"
								       name="wp_sir_settings[enable]"
								       id="wp-sir-enable"
								       value="1"
								       <?php checked( $settings['enable'], 1 ); ?> />
								<span class="screen-reader-text"><?php esc_html_e( 'Enable Image Uniformity', 'wp-smart-image-resize' ); ?></span>
							</label>
						</div>
						<div class="wp-sir-card__body">

							<!-- Apply to -->
							<div class="wp-sir-field">
								<label class="wp-sir-field__label">
									<?php esc_html_e( 'Process Images from', 'wp-smart-image-resize' ); ?>
									<span class="wp-sir-help-tip" title="<?php esc_attr_e( 'Choose which image types the plugin should process images for. Only images attached to the selected content will be resized.', 'wp-smart-image-resize' ); ?>"></span>
								</label>
								<div class="wp-sir-field__control">
									<?php $this->settings_field_processable_images(); ?>
								</div>
							</div>

							<!-- Background Color -->
							<div class="wp-sir-field">
								<label class="wp-sir-field__label" for="wpSirColorPicker">
									<?php esc_html_e( 'Background Color', 'wp-smart-image-resize' ); ?>
									<span class="wp-sir-help-tip" title="<?php esc_attr_e( 'Color used to fill empty space when an image is padded to fit the target size. Leave empty to keep transparency.', 'wp-smart-image-resize' ); ?>"></span>
								</label>
								<div class="wp-sir-field__control">
									<?php $this->settings_field_bg_color( [] ); ?>
								</div>
							</div>

							<!-- Trim Whitespace -->
							<div class="wp-sir-field">
								<label class="wp-sir-field__label">
									<?php esc_html_e( 'Trim Whitespace', 'wp-smart-image-resize' ); ?>
									<span class="wp-sir-help-tip" title="<?php esc_attr_e( 'Remove excess white borders around images before resizing for a cleaner, more uniform look.', 'wp-smart-image-resize' ); ?>"></span>
								</label>
								<div class="wp-sir-field__control">
									<?php $this->settings_field_enable_trim(); ?>
								</div>
							</div>

						</div><!-- /.wp-sir-card__body -->

						<!-- Advanced toggle -->
						<div class="wp-sir-card__footer">
							<button type="button" class="wp-sir-link-btn" id="wp-sir-toggle-advanced" aria-expanded="false">
								<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
								<?php esc_html_e( 'Advanced settings', 'wp-smart-image-resize' ); ?>
								<span class="dashicons dashicons-arrow-down-alt2 wp-sir-chevron" aria-hidden="true"></span>
							</button>
						</div>

						<!-- Advanced fields (hidden by default) -->
						<div id="wp-sir-advanced-fields" class="wp-sir-card__body wp-sir-advanced-body" style="display:none; border-top:1px solid #f0f0f0;">

							<!-- Resize Original Image -->
							<div class="wp-sir-field">
								<label class="wp-sir-field__label">
									<?php esc_html_e( 'Resize Original Image', 'wp-smart-image-resize' ); ?>
									<span class="wp-sir-help-tip" title="<?php esc_attr_e( 'Add padding to the original image so it has the same aspect ratio as thumbnails. Useful if your theme displays the full-size image directly. Other features like watermark, compression, and format conversion are always applied to the original when enabled.', 'wp-smart-image-resize' ); ?>"></span>
								</label>
								<div class="wp-sir-field__control">
									<?php $this->settings_field_process_original(); ?>
								</div>
							</div>

							<!-- Disable Upscale -->
							<div class="wp-sir-field">
								<label class="wp-sir-field__label">
									<?php esc_html_e( 'Disable Upscaling', 'wp-smart-image-resize' ); ?>
									<span class="wp-sir-help-tip" title="<?php esc_attr_e( 'Keep small images at their original size instead of stretching them. Empty space is added around the image to fill the target dimensions.', 'wp-smart-image-resize' ); ?>"></span>
								</label>
								<div class="wp-sir-field__control">
									<?php $this->settings_field_disable_upscale(); ?>
								</div>
							</div>

							<!-- Image Sizes -->
							<div class="wp-sir-field">
								<label class="wp-sir-field__label">
									<?php esc_html_e( 'Image Sizes', 'wp-smart-image-resize' ); ?>
									<span class="wp-sir-help-tip" title="<?php esc_attr_e( 'Choose which WordPress image sizes the plugin should process. For most stores the defaults are fine.', 'wp-smart-image-resize' ); ?>"></span>
								</label>
								<div class="wp-sir-field__control">
									<?php $this->settings_field_sizes(); ?>
								</div>
							</div>

							<?php if ( apply_filters( 'enable_experimental_features/crop_mode', false ) ) : ?>
							<!-- Cropping Mode -->
							<div class="wp-sir-field">
								<label class="wp-sir-field__label">
									<?php esc_html_e( 'Cropping Mode', 'wp-smart-image-resize' ); ?>
									<span class="wp-sir-help-tip" title="<?php esc_attr_e( 'Choose how images are fitted to the target dimensions. (Experimental)', 'wp-smart-image-resize' ); ?>"></span>
								</label>
								<div class="wp-sir-field__control">
									<?php $this->settings_field_cropping_mode(); ?>
								</div>
							</div>
							<?php endif; ?>

						</div><!-- /#wp-sir-advanced-fields -->

					</div><!-- /.wp-sir-card #wp-sir-core-settings -->

					<!-- ══ IMAGE TOOLS ═════════════════════════════════════ -->
					<div class="wp-sir-addons-header">
						<span class="wp-sir-addons-header__line" aria-hidden="true"></span>
						<span class="wp-sir-addons-header__label"><?php esc_html_e( 'Image Tools', 'wp-smart-image-resize' ); ?></span>
						<span class="wp-sir-addons-header__line" aria-hidden="true"></span>
					</div>

					<!-- Tool: Optimization -->
					<div class="wp-sir-addon-card" id="wp-sir-addon-optimization">
						<div class="wp-sir-addon-card__header" role="button" tabindex="0"
						     aria-expanded="false"
						     aria-controls="wp-sir-addon-optimization-body">
							<div class="wp-sir-addon-card__title">
								<span class="dashicons dashicons-performance wp-sir-addon-card__icon" aria-hidden="true"></span>
								<div>
									<strong><?php esc_html_e( 'Optimization', 'wp-smart-image-resize' ); ?></strong>
									<span class="wp-sir-addon-card__desc"><?php esc_html_e( 'Compress images and convert to modern formats for faster page loads.', 'wp-smart-image-resize' ); ?></span>
								</div>
							</div>
							<div class="wp-sir-addon-card__toggle-wrap">
								<span class="dashicons dashicons-arrow-down-alt2 wp-sir-addon-chevron" aria-hidden="true"></span>
							</div>
						</div>
						<div class="wp-sir-addon-card__body" id="wp-sir-addon-optimization-body" style="display:none;">

							<!-- Image Compression -->
							<div class="wp-sir-field">
								<label class="wp-sir-field__label">
									<?php esc_html_e( 'Image Compression', 'wp-smart-image-resize' ); ?>
									<span class="wp-sir-help-tip" title="<?php esc_attr_e( 'Higher values = more compression = smaller files but lower quality. 0% means no extra compression is applied.', 'wp-smart-image-resize' ); ?>"></span>
								</label>
								<div class="wp-sir-field__control">
									<?php $this->settings_field_image_quality( [] ); ?>
								</div>
							</div>

							<!-- PNG → JPG -->
							<div class="wp-sir-field">
								<label class="wp-sir-field__label">
									<?php esc_html_e( 'PNG → JPG Conversion', 'wp-smart-image-resize' ); ?>
									<span class="wp-sir-help-tip" title="<?php esc_attr_e( 'Convert PNG images to JPG for smaller file sizes. Only use this if your images do not need transparency.', 'wp-smart-image-resize' ); ?>"></span>
								</label>
								<div class="wp-sir-field__control">
									<?php $this->settings_field_jpg_convert(); ?>
								</div>
							</div>

							<!-- WebP -->
							<div class="wp-sir-field">
								<label class="wp-sir-field__label">
									<?php esc_html_e( 'WebP Images', 'wp-smart-image-resize' ); ?>
									<span class="wp-sir-help-tip" title="<?php esc_attr_e( 'Serve images in WebP format — up to 90% smaller than PNG. Falls back to JPG/PNG for older browsers automatically.', 'wp-smart-image-resize' ); ?>"></span>
								</label>
								<div class="wp-sir-field__control">
									<?php $this->settings_field_enable_nextgen_format(); ?>
								</div>
							</div>

						</div><!-- /.wp-sir-addon-card__body optimization -->
					</div><!-- /.wp-sir-addon-card optimization -->

					

					
					<!-- Tool: Watermark (Lite — teaser showing locked Pro UI) -->
					<div class="wp-sir-addon-card wp-sir-addon-card--locked" id="wp-sir-addon-watermark-teaser">
						<div class="wp-sir-addon-card__header" role="button" tabindex="0" aria-expanded="false" aria-controls="wp-sir-addon-watermark-teaser-body">
							<div class="wp-sir-addon-card__title">
								<span class="dashicons dashicons-art wp-sir-addon-card__icon" aria-hidden="true"></span>
								<div>
									<strong><?php esc_html_e( 'Watermark', 'wp-smart-image-resize' ); ?> <a href="https://sirplugin.com/?utm_source=wp&amp;utm_medium=plugin&amp;utm_campaign=watermark_teaser" target="_blank" class="wp-sir-pro-pill"><?php esc_html_e( 'PRO', 'wp-smart-image-resize' ); ?></a></strong>
									<span class="wp-sir-addon-card__desc"><?php esc_html_e( 'Protect your images and build brand identity.', 'wp-smart-image-resize' ); ?></span>
								</div>
							</div>
							<div class="wp-sir-addon-card__toggle-wrap">
								<span class="dashicons dashicons-arrow-down-alt2 wp-sir-addon-chevron" aria-hidden="true"></span>
							</div>
						</div>
						<div class="wp-sir-addon-card__body wp-sir-addon-card__body--locked" id="wp-sir-addon-watermark-teaser-body" style="display:none;">
							<div class="wp-sir-teaser-locked-wrap">
								<!-- Fake controls (non-functional, for visual preview) -->
								<div class="wp-sir-teaser-locked-ui" aria-hidden="true">
									<div class="wp-sir-watermark-layout">
										<div class="wp-sir-watermark-controls">
											<div class="wp-sir-sub-field">
												<span class="wp-sir-sub-field__label"><?php esc_html_e( 'Watermark Image', 'wp-smart-image-resize' ); ?></span>
												<button type="button" class="button button-secondary" disabled>
													<span class="dashicons dashicons-upload" style="margin-top:3px"></span>
													<?php esc_html_e( 'Select / Upload Image', 'wp-smart-image-resize' ); ?>
												</button>
											</div>
											<div class="wp-sir-sub-field">
												<span class="wp-sir-sub-field__label"><?php esc_html_e( 'Size', 'wp-smart-image-resize' ); ?></span>
												<div class="wp-sir-range-wrapper">
													<input type="range" min="1" max="100" value="50" class="wp-sir-range-input" disabled />
													<span class="wp-sir-range-value">50%</span>
												</div>
											</div>
											<div class="wp-sir-sub-field">
												<span class="wp-sir-sub-field__label"><?php esc_html_e( 'Opacity', 'wp-smart-image-resize' ); ?></span>
												<div class="wp-sir-range-wrapper">
													<input type="range" min="0" max="100" value="50" class="wp-sir-range-input" disabled />
													<span class="wp-sir-range-value">50%</span>
												</div>
											</div>
											<div class="wp-sir-sub-field">
												<span class="wp-sir-sub-field__label"><?php esc_html_e( 'Position', 'wp-smart-image-resize' ); ?></span>
												<div class="wp-sir-curve-control">
													<div class="wp-sir-curve-row">
														<span class="wp-sir-curve-point"></span>
														<span class="wp-sir-curve-point"></span>
														<span class="wp-sir-curve-point"></span>
													</div>
													<div class="wp-sir-curve-row">
														<span class="wp-sir-curve-point"></span>
														<span class="wp-sir-curve-point active"></span>
														<span class="wp-sir-curve-point"></span>
													</div>
													<div class="wp-sir-curve-row">
														<span class="wp-sir-curve-point"></span>
														<span class="wp-sir-curve-point"></span>
														<span class="wp-sir-curve-point"></span>
													</div>
												</div>
											</div>
										</div>
										<div class="wp-sir-watermark-preview-wrap">
											<span class="wp-sir-sub-field__label"><?php esc_html_e( 'Preview', 'wp-smart-image-resize' ); ?></span>
											<div class="wp-sir-watermark-preview-container" style="background-image:url(<?php echo esc_url( WP_SIR_URL . 'images/watermark-preview.jpg' ); ?>)"></div>
										</div>
									</div>
								</div>
								<!-- Lock overlay -->
								<div class="wp-sir-teaser-overlay">
									<div class="wp-sir-teaser-overlay__content">
										<span class="dashicons dashicons-lock" aria-hidden="true"></span>
										<p><?php esc_html_e( 'Upgrade to Pro to unlock watermarking', 'wp-smart-image-resize' ); ?></p>
										<a href="https://sirplugin.com/?utm_source=wp&amp;utm_medium=plugin&amp;utm_campaign=watermark_teaser" target="_blank" class="wp-sir-teaser-btn">
											<?php esc_html_e( 'Unlock Watermarking', 'wp-smart-image-resize' ); ?>
										</a>
									</div>
								</div>
							</div>
						</div>
					</div><!-- /.wp-sir-addon-card watermark teaser -->
					


					<!-- ══ SAVE BUTTONS ════════════════════════════════════ -->
					<div class="wp-sir-form-actions">
						<?php submit_button( __( 'Save Settings', 'wp-smart-image-resize' ), 'primary wp-sir-btn-save', 'submit', false ); ?>
						<?php submit_button( __( 'Save & Bulk Resize', 'wp-smart-image-resize' ), 'secondary', 'submit_and_bulk_resize', false ); ?>
					</div>

				</form>

			</div><!-- /.wp-sir-main -->

			<!-- ── SIDEBAR ──────────────────────────────────────────── -->
			<?php include WP_SIR_DIR . 'templates/partials/sidebar.php'; ?>

		</div><!-- /.wp-sir-layout -->

	<?php endif; ?>

	<?php if ( $current_tab === 'bulk-regenerate' ) :
		include_once WP_SIR_DIR . 'templates/bulk-regenerate.php';
	endif; ?>

	

	<?php if ( $current_tab === 'help' ) : ?>
		<?php $this->render_help_tab(); ?>
	<?php endif; ?>

</div><!-- /.wrap.wp-sir-wrap -->
