<?php

namespace WP_Smart_Image_Resize;

class Process_Media_Library_Upload
{
    use Singleton_Trait;

    const COOKIE_NAME = 'wp_sir_process_ml_upload';

    function initialize(){
        add_action( 'pre-html-upload-ui', [ $this, 'render_form' ] );
        add_action( 'pre-plupload-upload-ui', [ $this, 'render_form' ] );

        // pre-html-upload-ui / pre-plupload-upload-ui fire from INSIDE one of
        // WordPress core's own Underscore.js templates (a
        // <script type="text/html">…</script> block in wp_print_media_templates()).
        // A real <script>…</script> tag output there is fatal to that
        // surrounding template: the browser's HTML parser closes the OUTER
        // script at our </script> (nesting doesn't apply — script content is
        // raw text until the first literal </script>, regardless of the
        // outer tag's type="text/html"), spilling the rest of that template's
        // markup onto the page as visible text. Confirmed empirically: this
        // is exactly what broke the "Suggested image dimensions…" template.
        // It also means script tags placed there never actually run as live
        // JS anyway (they're just inert template text) — so the checkbox's
        // cookie-wiring has to live in a real page script instead, using
        // event delegation since the checkbox may not exist in the DOM yet
        // when that script runs (Backbone renders the template later).
        add_action( 'admin_print_footer_scripts', [ $this, 'print_cookie_script' ] );
        add_action( 'wp_print_footer_scripts', [ $this, 'print_cookie_script' ] );

        add_action('begin_wcfm_products_manage_form', function(){
         ?>
        <script>
            window._is_wcfm_product_form = true;
        </script>
         <?php
        });

        add_action('wp_print_footer_scripts', function(){
           ?>
    <script>
       (function($){

        if(! window._is_wcfm_product_form){
                return;
        }

        var __uploaderOpen = null;

        function toggleProcessableState(ev){
            __uploadedOpen = setInterval(function(){
                if(wp.media.frame){
                    clearInterval(__uploaderOpen);
                    wp.media.frame.uploader.uploader.param('_processable_image', true);
                    wp.media.frame.on('close escape', function(){
                        wp.media.frame.uploader.uploader.param('_processable_image', false);
                    });
            }
            }, 100);
        }

            $('.wcfm-wp-fields-uploader').each(function(){
                $(this).find('img').on('click', toggleProcessableState);
            });

            $('#gallery_img').on('click', '.add_multi_input_block',function(){
                
                $('.wcfm-wp-fields-uploader').each(function(){
                    $(this).find('img').off('click', toggleProcessableState);
               });
               
               $('.wcfm-wp-fields-uploader').each(function(){
                     $(this).find('img').on('click', toggleProcessableState);
                });
            });
           
               
      
       })(jQuery);
    </script>
            <?php
        });
       
    }
    static function is_media_screen()
    {

        require_once(ABSPATH . 'wp-admin/includes/screen.php');

        if( ! function_exists('get_current_screen') ){
            return false;
        }

        $screen = get_current_screen();

        return $screen
            && is_object( $screen )
            && in_array( $screen->id , ['media', 'upload']);
    }

    function render_form()
    {
        if ( ! static::is_media_screen() ) {
            return;
        }

        $isOn = !empty( $_COOKIE[self::COOKIE_NAME] ) ? filter_var($_COOKIE[self::COOKIE_NAME], FILTER_VALIDATE_BOOLEAN) : true;
        $isOn = apply_filters('wp_sir_process_media_library_upload_by_default', $isOn );
        ?>
        <div class="wpsirProcessMediaLibraryImageWraper">
            <h3 class="wpsirProcessMediaLibraryImageTitle">Smart Image Resize:</h3>
            <label for="processMediaLibraryImage"><input
                        id="processMediaLibraryImage"
                        type="checkbox"
                        <?php checked($isOn, true, true) ?>
                        class="wp-sir-as-toggle">
                <span ><?php esc_html_e( 'When uploading an image, resize it to match your settings.',
                        'wp-smart-image-resize' ); ?></span></label>
        </div>
        <?php
    }

    /**
     * Keep the checkbox's cookie in sync with whatever's checked, via a real
     * script in the page footer (not inside the media templates — see the
     * note in initialize()). Event delegation, since the checkbox markup
     * render_form() outputs may be inert Underscore.js template text at the
     * time this script runs, only becoming a real, live element later when
     * Backbone renders that template — this still catches it whenever that
     * happens. Printed once per hook regardless of how many times
     * render_form() ran on the page (harmless if the checkbox never
     * existed there at all).
     */
    function print_cookie_script() {
        ?>
        <script>
        document.addEventListener( 'change', function ( ev ) {
            if ( ! ev.target || ev.target.id !== 'processMediaLibraryImage' ) {
                return;
            }
            // Server-side (wp_sir_is_media_library_upload()) reads this on the
            // actual upload request to decide whether to process it — the
            // checkbox has no name attribute (nothing to submit with the
            // upload form) so the cookie is the only thing connecting what's
            // checked here to what happens on upload.
            document.cookie = <?php echo wp_json_encode( self::COOKIE_NAME ); ?> + '=' + ( ev.target.checked ? '1' : '0' ) + '; path=/; SameSite=Lax';
        } );
        </script>
        <?php
    }
}

Process_Media_Library_Upload::instance()->initialize();
