<?php

namespace WP_Smart_Image_Resize;

use WP_Smart_Image_Resize\Singleton_Trait;

/**
 * Shows a one-time "Get Started" admin notice to new installs.
 *
 * The notice is displayed on every admin screen until the user either:
 *  - dismisses it manually, or
 *  - visits the Bulk Regenerate tab (auto-dismissed).
 *
 * @package WP_Smart_Image_Resize
 */
class Get_Started_Notice {

	use Singleton_Trait;

	const OPTION_DISMISSED = 'wp_sir_get_started_dismissed';
	const NONCE            = 'wp_sir_get_started_dismiss';
	const AJAX_ACTION      = 'wp_sir_dismiss_get_started';

	public function load() {
		add_action( 'admin_notices',                [ $this, 'render' ] );
		add_action( 'admin_enqueue_scripts',        [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax_dismiss' ] );
	}

	// -------------------------------------------------------------------------
	// Visibility logic
	// -------------------------------------------------------------------------

	private function can_show(): bool {

		// Already dismissed.
		if ( get_option( self::OPTION_DISMISSED ) ) {
			return false;
		}

		// No need to show on the Bulk Regenerate tab itself.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] )  ? sanitize_text_field( wp_unslash( $_GET['tab'] ) )  : '';
		if ( $page === WP_SIR_NAME && $tab === 'bulk-regenerate' ) {
			return false;
		}

		// Only show to users who can manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Don't show if images have already been processed (not a fresh install).
		$processed = get_option( 'wp_sir_processed_attachments', [] );
		if ( ! empty( $processed ) ) {
			$this->mark_dismissed();
			return false;
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	public function render() {
		if ( ! $this->can_show() ) {
			return;
		}

		$bulk_url     = admin_url( 'admin.php?page=' . WP_SIR_NAME . '&tab=bulk-regenerate' );
		$settings_url = admin_url( 'admin.php?page=' . WP_SIR_NAME );
		?>
		<div class="notice wp-sir-get-started-notice" id="wp-sir-get-started-notice" style="padding: 16px 16px 16px 20px; border-left-color: #4f46e5; display: flex; align-items: flex-start; gap: 16px;">

			<div style="flex-shrink: 0; color: #4f46e5;" aria-hidden="true">
				<span class="dashicons dashicons-images-alt2" style="font-size: 36px; width: 36px; height: 36px;"></span>
			</div>

			<div style="flex: 1;">
				<p style="margin: 0 0 6px; font-size: 14px; font-weight: 600;">
					<?php esc_html_e( 'Smart Image Resize is ready!', 'wp-smart-image-resize' ); ?>
				</p>
				<p style="margin: 0 0 12px; color: #50575e;">
					<?php esc_html_e( 'New images will be processed automatically on upload. To apply your settings to existing images in your media library, run Bulk Regenerate.', 'wp-smart-image-resize' ); ?>
				</p>
				<p style="margin: 0; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
					<a href="<?php echo esc_url( $bulk_url ); ?>" class="button button-primary" style="background: #4f46e5; border-color: #4f46e5; box-shadow: 0 1px 0 #3730a3;">
						<span class="dashicons dashicons-update" style="margin-top: 3px;" aria-hidden="true"></span>
						<?php esc_html_e( 'Process Existing Images', 'wp-smart-image-resize' ); ?>
					</a>
					<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Review Settings', 'wp-smart-image-resize' ); ?>
					</a>
					<button type="button"
					        class="button-link"
					        id="wp-sir-get-started-dismiss"
					        style="color: #787c82; text-decoration: underline; cursor: pointer; background: none; border: none; padding: 0;"
					        data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>"
					        data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">
						<?php esc_html_e( "I'll do this later", 'wp-smart-image-resize' ); ?>
					</button>
				</p>
			</div>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Scripts (inline — keeps things self-contained)
	// -------------------------------------------------------------------------

	public function enqueue_scripts() {
		if ( ! $this->can_show() ) {
			return;
		}
		wp_add_inline_script( 'jquery', $this->dismiss_script() );
	}

	private function dismiss_script(): string {
		return "
jQuery( function( $ ) {
	$( document ).on( 'click', '#wp-sir-get-started-dismiss', function() {
		var btn = $( this );
		$.post( btn.data('ajax-url'), {
			action: '" . esc_js( self::AJAX_ACTION ) . "',
			nonce:  btn.data('nonce')
		} );
		$( '#wp-sir-get-started-notice' ).fadeOut( 300 );
	} );
} );
";
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	public function ajax_dismiss() {
		check_ajax_referer( self::NONCE, 'nonce' );
		$this->mark_dismissed();
		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function mark_dismissed() {
		update_option( self::OPTION_DISMISSED, '1', false );
	}
}
