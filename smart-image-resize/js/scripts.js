// Utils

var WP_SIR_UTIL = {
  setCookie: function (cname, cvalue, exdays) {
    var d = new Date();
    d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
    var expires = "expires=" + d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
  }
};

(function ($) {
  'use strict';

  // ELEMENTS
  var $colorPicker = $('#wpSirColorPicker');
  var $compressImageSlider = $('.wpSirSlider');

  // ------------------------------------------------------------------------------------------
  // INITILIAZE COLOR PICKER
  // ------------------------------------------------------------------------------------------

  $colorPicker.wpColorPicker();

  // ------------------------------------------------------------------------------------------
  // INITILIAZE COMPRESSION SLIDER.
  // ------------------------------------------------------------------------------------------

  $compressImageSlider.each(function () {
    var handle = $(this).find('.wpSirSliderHandler');
    var inputElement = $('.' + $(this).data('input'));
    $(this).slider({
      create: function () {
        $(this).slider('value', inputElement.val());
        handle.text($(this).slider('value') + '%');
      },
      slide: function (event, ui) {
        handle.text(ui.value + '%');
        inputElement.val(ui.value);
      },
      change: function (event, ui) {
        handle.text(ui.value + '%');
      },
    });
  });

  // we'll wait until the box is rendered, so we can move it to the top.
  var wpsirLoadIntervalId = setInterval(() => {
    if ($('.wpsirProcessMediaLibraryImageWraper').length) {
      clearInterval(wpsirLoadIntervalId);

      $('.wpsirProcessMediaLibraryImageWraper')
        .insertBefore($('#wp-media-grid > .media-frame'));

      handleProcessMediaLibraryChange($('#processMediaLibraryImage'));

      $(document).on('change', '#processMediaLibraryImage', function () {
        handleProcessMediaLibraryChange($(this));
      });

    }
  }, 100);


  /**
   * Allow user to decide whether to process image being uploaded.
   * We'll place a checkbox input where we cannot determine image attachment parent
   * under "Media > Library" and "Media > Add" new pages.
   */


  function handleProcessMediaLibraryChange($input) {
    var isProcessable = $input.is(':checked');

    WP_SIR_UTIL.setCookie(wp_sir_object.process_ml_upload_cookie, isProcessable.toString(), 365);
    // Normal HTML uploader.
    if ($('#html-upload-ui').length) {
      var $htmlProcessableInput = $('input[name="_processable_image"]');

      if ($htmlProcessableInput.length === 0) {
        $('#html-upload-ui').append(
          '<input type="hidden"  name="_processable_image" >'
        );
        $htmlProcessableInput = $($htmlProcessableInput.selector);
      }
      $htmlProcessableInput.val(isProcessable);
    }

    // Drag-and-drop uploader box.
    if (
      typeof wpUploaderInit === 'object' &&
      wpUploaderInit.hasOwnProperty('multipart_params')
    ) {
      wpUploaderInit.multipart_params._processable_image = isProcessable;
    }

    // Media library modal.
    if (
      wp.media &&
      wp.media.frame &&
      wp.media.frame.uploader &&
      wp.media.frame.uploader.uploader
    ) {
      wp.media.frame.uploader.uploader.param('_processable_image', isProcessable);
    }
  }



  // Reset "Image sizes" to default ones.
  $(document).on('click', '#wpsirResetDefaultSizes', function () {
    var preselectedSizes = $('#wp-sir-sizes-selector').data('defaults').split(',');
    $('.wpSirSelectSize').each(function () {
      if (preselectedSizes.indexOf($(this).val()) >= 0) {
        $(this).prop('checked', true).change();
      } else {
        $(this).prop('checked', false).change();
      }
    });
  });


  // Add filter to Media Library (grid view)

  if (typeof sir_vars != 'undefined') {
    var SIR_MediaLibraryTaxonomyFilter = wp.media.view.AttachmentFilters.extend({
      id: 'media-attachment-sir-filter',
      createFilters() {
        this.filters = {
          all: {
            text: sir_vars.filter_strings.all,
            props: { _filter: 'all' },
            priority: 10,
          },
          processed: {
            text: sir_vars.filter_strings.processed,
            props: { _filter: 'processed' },
            priority: 20,
          },
          unprocessed: {
            text: sir_vars.filter_strings.unprocessed,
            props: { _filter: 'unprocessed' },
            priority: 30,
          },
        };
      },
    });

    var SIR_AttachmentsBrowser = wp.media.view.AttachmentsBrowser;
    wp.media.view.AttachmentsBrowser = wp.media.view.AttachmentsBrowser.extend({
      createToolbar() {
        // Make sure to load the original toolbar
        SIR_AttachmentsBrowser.prototype.createToolbar.call(this);
        this.toolbar.set(
          'SIR_MediaLibraryTaxonomyFilter',
          new SIR_MediaLibraryTaxonomyFilter({
            controller: this.controller,
            model: this.collection.props,
            priority: -75,
          }).render()
        );
      },
    });

  }

  // Handle the "Clear" button display.
  $('#wp-sir-clear-bg-color').on('click', function (e) {
    $colorPicker.find('.wp-picker-clear').click();
    $colorPicker.val('').trigger('change');
    $colorPicker.find('.wp-color-result').css({ 'background-color': '' });
    e.preventDefault();
    e.stopPropagation();
    $(this).hide();
  });

  if (!$colorPicker.val()) {
    $('#wp-sir-clear-bg-color').hide();
  }

  $('.wp-picker-container').on('click', function () {
    if ($(this).hasClass('wp-picker-active')) {
      $('#wp-sir-clear-bg-color').hide();
    } else if ($colorPicker.val()) {
      $('#wp-sir-clear-bg-color').show();
    }
  });


  $(document).on('click', function (e) {
    if ($colorPicker.val()) {
      $('#wp-sir-clear-bg-color').show();
    }
  });


  $(document).on('change', '.wpSirSelectSize', function () {
    
    $(this).closest('tr').find('input[type="number"]').prop('disabled', !$(this).is(':checked'));
    $(this).closest('tr').find('.wp-sir-fit-mode').prop('disabled', !$(this).is(':checked'));
    if ($(this).closest('tr').find('.wp-sir-fit-mode').is(':checked')) {
      $(this).closest('tr').find('input[type="number"]').prop('disabled', true);
    }
    var isAllSizesSelected = $('.wpSirSelectSize:checked').length === $('.wpSirSelectSize').length;
    $('#wp-sir-toggle-all-sizes').prop('checked', isAllSizesSelected);

    // Check if current selection matches defaults
    const defaultSizes = $('#wp-sir-sizes-selector').data('defaults').split(',');
    const currentSizes = $('.wpSirSelectSize:checked').map(function() {
        return $(this).val();
    }).get();
    
    const hasChanges = defaultSizes.length !== currentSizes.length || 
        defaultSizes.some(size => !currentSizes.includes(size)) ||
        currentSizes.some(size => !defaultSizes.includes(size));
    
    // Show/hide reset button based on changes
    $('#wpsirResetDefaultSizes').toggle(hasChanges);
  });

  $('.wpSirSelectSize').each(function () {
   
    $(this).closest('tr').find('input[type=number]').prop('disabled', !$(this).is(':checked')).change();
    $(this).closest('tr').find('.wp-sir-fit-mode').prop('disabled', !$(this).is(':checked')).change();
    $(this).closest('tr').find('.wp-sir-fit-mode').each(function () {
      if ($(this).is(':checked')) {
        $(this).closest('tr').find('input[type=number]').prop('disabled', true);
      }
    });
  });

  $('#wp-sir-toggle-all-sizes').on('change', function () {
    var $toggle = $(this);
    $('.wpSirSelectSize').each(function(){
      if(! $(this).is(':disabled')){
        $(this).prop('checked', $toggle.is(':checked'));

      }
    });
    $('.wpSirSelectSize').change();
  });

  $('.wp-sir-fit-mode').on('change', function () {
    if ($(this).is(':checked') ) {
      $(this).closest('tr').find('.wp-sir-custom-dimensions').find('input').prop('disabled', true);
    } else {
      $(this).closest('tr').find('.wp-sir-custom-dimensions').find('input').prop('disabled', false);
    }
  });


  $(document).on('click', '#wp-sir-open-media-uploader', function (e) {
    var frame;

    if (frame) {
      frame.open();
      return;
    }

    frame = wp.media({
      title: 'Select or Upload Watermark image',
      multiple: false,
    });

    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      var $watermarkImageInput = $('input[name="wp_sir_settings[watermark_image]"]');
      $watermarkImageInput.val(attachment.id);
      $watermarkImageInput.data('size', { w: attachment.width, h: attachment.height });

      var $watermarkPreview = $('.wp-sir-watermark-preview-container');

      if ($watermarkPreview.find('img').length) {
        $watermarkPreview.find('img').attr('src', attachment.url);
      } else {
        $watermarkPreview.append('<img src="' + attachment.url + '"/>');
        $watermarkPreview.find('img').css('position', 'absolute');
      }

      var previewSize = { w: $watermarkPreview.width(), h: $watermarkPreview.height() };
      var h,w;
      var size = +$('.wp-sir-watermark-size').val();

      if (attachment.width >= attachment.height) {
        w = previewSize.w * size /100;
        if (w >= previewSize.w) {
          w = previewSize.w;
        }
        h = attachment.height * w / attachment.width;
      } else {
        h = previewSize.h * size / 100;
        if (h >= previewSize.h) {
          h = previewSize.h
        }
        w = attachment.width * h / attachment.height;
      }

      if(w >= previewSize.w) {
        w = previewSize.w;
        h = attachment.height * w / attachment.width;
      }

      if(h >= previewSize.h) {
        h = previewSize.h;
        w = attachment.width * h / attachment.height;
      }
      
      $watermarkPreview.find('img').css({ width: w + 'px', height: h + 'px' });
      $watermarkPreview.find('img').css({ opacity: +$('.wp-sir-watermark-opacity').val()/100 });
      $('#wp-sir-watermark-position').trigger('change');
    });

    frame.open();

  });


  // Handle watermark slider change.
  $('.wp-sir-watermark-size').on('input', function() {
    var $slider = $(this);
    var $previewImageContainer = $('.wp-sir-watermark-preview-container');
    var $watermark = $previewImageContainer.find('img');
    var value = $slider.val();
    
    // Update the size input value
    
    if (!$watermark.length) {
      return;
    }

    var previewSize = { 
      w: $previewImageContainer.width(), 
      h: $previewImageContainer.height() 
    };
    var watermarkSize = {
      w: $watermark.width(), 
      h: $watermark.height()
    };

    var watermarkNewWidth, watermarkNewHeight;

    if (watermarkSize.w >= watermarkSize.h) {
      watermarkNewWidth = previewSize.w * value / 100;
      if (watermarkNewWidth >= previewSize.w) {
        watermarkNewWidth = previewSize.w;
      }
      watermarkNewHeight = watermarkSize.h * watermarkNewWidth / watermarkSize.w;
    } else {
      watermarkNewHeight = previewSize.h * value / 100;
      if (watermarkNewHeight >= previewSize.h) {
        watermarkNewHeight = previewSize.h;
      }
      watermarkNewWidth = watermarkSize.w * watermarkNewHeight / watermarkSize.h;
    }

    if (watermarkNewWidth >= previewSize.w) {
      watermarkNewWidth = previewSize.w;
      watermarkNewHeight = watermarkSize.h * watermarkNewWidth / watermarkSize.w;
    }

    if (watermarkNewHeight >= previewSize.h) {
      watermarkNewHeight = previewSize.h;
      watermarkNewWidth = watermarkSize.w * watermarkNewHeight / watermarkSize.h;
    }

    $watermark.css({ 
      width: watermarkNewWidth + 'px', 
      height: watermarkNewHeight + 'px' 
    });

  });

  $(document).on('change', '#wp-sir-watermark-position', function () {
    setWatermarkPosition($(this));
  });


  $('.wp-sir-watermark-opacity').on('input', function() {
    var $slider = $(this);
    var $opacityInput = $('.' + $slider.data('input'));
    var value = $slider.val();
    var $watermark = $('.wp-sir-watermark-preview-container').find('img');

    // Update the opacity input value and display
    $opacityInput.val(value);
    $('.wp-sir-watermark-opacity-slider-handler').text(value + '%');

    // Update watermark opacity if it exists
    if ($watermark.length) {
      $watermark.css({ opacity: value / 100 });
    }
  });

  // Initialize opacity on page load
  $(document).ready(function() {
    $('.wp-sir-watermark-opacity').each(function() {
      $(this).trigger('input');
    });
  });

  function setWatermarkPosition($element) {
    var $img = $('.wp-sir-watermark-preview-container').find('img');
    var $offset_y = $('#wp-sir-watermark-offset-y');
    var $offset_x = $('#wp-sir-watermark-offset-x');
    var offset_x = parseInt($offset_x.val()) || 0;
    var offset_y = parseInt($offset_y.val()) || 0;
    
    // Reset any previous positioning
    $img.css({
      'top': '',
      'left': '',
      'right': '',
      'bottom': '',
      'transform': ''
    });

    // Enable both inputs by default
    $offset_x.prop('disabled', false);
    $offset_y.prop('disabled', false);

    switch ($element.val()) {
      case 'top-left':
        $img.css({
          'top': offset_y + 'px',
          'left': offset_x + 'px'
        });
        break;
      case 'top-right':
        $img.css({
          'top': offset_y + 'px',
          'right': offset_x + 'px'
        });
        break;
      case 'bottom-left':
        $img.css({
          'bottom': offset_y + 'px',
          'left': offset_x + 'px'
        });
        break;
      case 'bottom-right':
        $img.css({
          'bottom': offset_y + 'px',
          'right': offset_x + 'px'
        });
        break;
      case 'center':
        $img.css({
          'top': '50%',
          'left': '50%',
          'transform': 'translate(-50%, -50%)'
        });
        // Disable both offsets for center position
        $offset_x.prop('disabled', true);
        $offset_y.prop('disabled', true);
        break;
      case 'left':
        $img.css({
          'top': '50%',
          'left': offset_x + 'px',
          'transform': 'translateY(-50%)'
        });
        // Disable Y offset for left position
        $offset_y.prop('disabled', true);
        break;
      case 'right':
        $img.css({
          'top': '50%',
          'right': offset_x + 'px',
          'transform': 'translateY(-50%)'
        });
        // Disable Y offset for right position
        $offset_y.prop('disabled', true);
        break;
      case 'top':
        $img.css({
          'top': offset_y + 'px',
          'left': '50%',
          'transform': 'translateX(-50%)'
        });
        // Disable X offset for top position
        $offset_x.prop('disabled', true);
        break;
      case 'bottom':
        $img.css({
          'bottom': offset_y + 'px',
          'left': '50%',
          'transform': 'translateX(-50%)'
        });
        // Disable X offset for bottom position
        $offset_x.prop('disabled', true);
        break;
    }

    // Add visual feedback for disabled inputs
    $('.wp-sir-offset-input').each(function() {
      $(this).closest('.wp-sir-offset-field').toggleClass('disabled', $(this).prop('disabled'));
    });
  }

  setWatermarkPosition($('#wp-sir-watermark-position'));

  // Update offset handlers
  $(document).on('change keyup paste', '#wp-sir-watermark-offset-x, #wp-sir-watermark-offset-y', function() {
    setWatermarkPosition($('#wp-sir-watermark-position'));
  });

  $(document).on('change', '#wp-sir-enable-watermark', function () {
    if ($(this).is(':checked')) {
      $('.wp-sir-watermark-settings').css('display', 'flex');
    } else {
      $('.wp-sir-watermark-settings').css('display', 'none');

    }
  }).change();


  jQuery(document).ready(function($) {
   

    // Update tolerance value display
    $('.wp-sir-range-input').on('input', function() {
        $('#' + $(this).data('value-display')).text($(this).val() + '%');
    });

    // Trim toggle — show/hide advanced sub-fields
    $('#wp-sir-enable-trim').on('change', function() {
      $('.wp-sir-trim-advanced-settings').toggle($(this).prop('checked'));
    });

    // Tolerance slider — live feedback message
    $('#wp-sir-trim-tolerance').on('input', function() {
      var value    = parseInt( $(this).val(), 10 );
      var $feedback = $('.wp-sir-tolerance-feedback');
      if ( value > 50 ) {
        $feedback.html('<span class="wp-sir-feedback--danger">Warning: High tolerance may trim parts of your image that you want to keep.</span>');
      } else if ( value > 20 ) {
        $feedback.html('<span class="wp-sir-feedback--warning">Caution: Moderate-high tolerance — test on sample images first.</span>');
      } else if ( value > 10 ) {
        $feedback.text('Medium tolerance — will trim similar shades of white.');
      } else {
        $feedback.text('Default: 3%. Increase to trim more aggressively.');
      }
    }).trigger('input');
    
    $('.wp-sir-watermark-size').trigger('input');
    
    // Initialize tooltips
    if($.fn.tipTip)
    {
      $('.wp-sir-help-tip').tipTip({
        'attribute': 'title',
        'fadeIn': 50,
        'fadeOut': 50,
        'delay': 200
    });
    }

    // Handle sizes section toggle
    $('.wp-sir-toggle-sizes').on('click', function() {
        const $button = $(this);
        const $wrapper = $('#wp-sir-sizes-options');
        const isExpanded = $button.attr('aria-expanded') === 'true';
        
        $wrapper.slideToggle(200);
        $button.attr('aria-expanded', !isExpanded);
        
        // Update button text
        const $text = $button.find('.wp-sir-toggle-text');
        $text.text(isExpanded ? 'Customize image sizes' : 'Hide image sizes');
    });

    // Update sizes summary when selections change
    $('#wp-sir-sizes-selector input[type="checkbox"]').on('change', function() {
        const totalSizes = $('#wp-sir-sizes-selector .wpSirSelectSize').length;
        const selectedSizes = $('#wp-sir-sizes-selector .wpSirSelectSize:checked').length;
        
        $('.wp-sir-sizes-summary').text(
            `${selectedSizes} of ${totalSizes} size${totalSizes !== 1 ? 's' : ''} selected`
        );
    });
});

function createCurveControl() {
  const positions = [
    ['top-left', 'top', 'top-right'],
    ['left', 'center', 'right'], 
    ['bottom-left', 'bottom', 'bottom-right']
  ];

  let html = '<div class="wp-sir-curve-control">';
  positions.forEach((row) => {
    html += '<div class="wp-sir-curve-row">';
    row.forEach((pos) => {
      const label = pos.replace(/-/g, ' ');
      html += `<button type="button" class="wp-sir-curve-point" data-position="${pos}" title="${label}"><span class="screen-reader-text">${label}</span></button>`;
    });
    html += '</div>';
  });
  html += '</div>';

  $('#wp-sir-watermark-position').after(html);

  // Handle clicks
  $(document).on('click', '.wp-sir-curve-point', function() {
    $('.wp-sir-curve-point').removeClass('active');
    $(this).addClass('active');
    $('#wp-sir-watermark-position').val($(this).data('position')).trigger('change');
  });

  // Sync grid with dropdown
  function updateCurveFromDropdown() {
    const current = $('#wp-sir-watermark-position').val();
    $('.wp-sir-curve-point').removeClass('active');
    $(`.wp-sir-curve-point[data-position="${current}"]`).addClass('active');
  }

  updateCurveFromDropdown();
  $('#wp-sir-watermark-position').on('change', updateCurveFromDropdown);
}

// Initialize curve control when document is ready
$(document).ready(function() {
  createCurveControl();
});

$('.wp-sir-tabs div').on('click', function(e) {
  e.preventDefault();
  $('.wp-sir-tabs div').removeClass('active');
  $(this).addClass('active');
  if($(this).data('tab') === 'general'){
    $('.sir-settings-general >table>tbody>tr:not(.wp-sir-is-advanced)').removeClass('hidden');
    $('.sir-settings-general >table>tbody>tr.wp-sir-is-advanced').addClass('hidden');
  }else{
    $('.sir-settings-general >table>tbody>tr:not(.wp-sir-is-advanced)').addClass('hidden');
    $('.sir-settings-general >table>tbody>tr.wp-sir-is-advanced').removeClass('hidden');
  }
});

// ── Redesigned settings page interactions ────────────────────────────────────
(function ($) {
  'use strict';

  $(document).ready(function () {

    // Advanced settings toggle
    $('#wp-sir-toggle-advanced').on('click', function () {
      var $btn    = $(this);
      var $panel  = $('#wp-sir-advanced-fields');
      var expanded = $btn.attr('aria-expanded') === 'true';

      $panel.slideToggle(200);
      $btn.attr('aria-expanded', !expanded);
    });

    // Image Uniformity enable/disable — dim the card body when off.
    function toggleUniformityState() {
      var $card = $('#wp-sir-core-settings');
      if ($('#wp-sir-enable').is(':checked')) {
        $card.removeClass('wp-sir-card--disabled');
      } else {
        $card.addClass('wp-sir-card--disabled');
      }
    }
    toggleUniformityState();
    $('#wp-sir-enable').on('change', toggleUniformityState);

    // Add-on card expand / collapse via header click
    // The toggle checkbox itself is handled separately to avoid double-firing.
    $(document).on('click', '.wp-sir-addon-card__header', function (e) {
      // Don't collapse when clicking the toggle checkbox or the PRO pill link.
      if ($(e.target).is('input[type="checkbox"], a, .wp-sir-toggle-label, .wp-sir-toggle-label *')) {
        return;
      }

      var $header = $(this);
      var targetId = $header.attr('aria-controls');
      var $body    = $('#' + targetId);
      var expanded = $header.attr('aria-expanded') === 'true';

      $body.slideToggle(200);
      $header.attr('aria-expanded', !expanded);
    });

    // Keyboard accessibility for add-on card headers
    $(document).on('keydown', '.wp-sir-addon-card__header', function (e) {
      if (e.which === 13 || e.which === 32) {
        e.preventDefault();
        $(this).trigger('click');
      }
    });

    // When an add-on toggle is switched ON, also expand the body.
    $(document).on('change', '.wp-sir-addon-toggle', function () {
      var $checkbox = $(this);
      var targetId  = $checkbox.data('target');
      var $body     = $('#' + targetId);
      var $header   = $body.closest('.wp-sir-addon-card').find('.wp-sir-addon-card__header');

      if ($checkbox.is(':checked')) {
        $body.slideDown(200);
        $header.attr('aria-expanded', 'true');
      } else {
        $body.slideUp(200);
        $header.attr('aria-expanded', 'false');
      }
    });

  });
})(jQuery);

// Handle Regenerate Thumbnails plugin installation — removed (built-in bulk processor replaces RT)

// ─── Built-in Bulk Image Processor ───────────────────────────────────────────
(function ($) {
  'use strict';

  // Only run when the bulk-regenerate tab is present.
  if (typeof wp_sir_object === 'undefined' || !wp_sir_object.bulk) {
    return;
  }

  var bulk    = wp_sir_object.bulk;
  var ajaxUrl = wp_sir_object.ajax_url;
  var nonce   = wp_sir_object.nonce;

  // All errors accumulated across the entire session (survives ticks).
  var _allErrors = [];
  var _aborted   = false;
  var _started   = false; // True once user initiates a process — prevents stale fetchStatus data.
  var _done      = false; // True once done state is shown — prevents late callbacks overriding it.

  // DOM refs — resolved once the page is ready.
  var $wrap, $stateIdle, $stateActive, $stateDone;
  var $btnStart, $btnPause, $btnResume, $btnRestart, $btnAbort;
  var $statusLabel, $doneCount, $totalCount;
  var $progressBar, $progressBarWrap, $percentLabel;
  var $doneSummary;
  var $errorLog, $errorLogToggle, $errorLogTitle, $errorLogBody, $errorTableBody;
  var $idleMessage, $idleHint;

  function init() {
    $wrap = $('#wp-sir-bulk-wrap');
    if (!$wrap.length) return;

    $stateIdle   = $('#wp-sir-state-idle');
    $stateActive = $('#wp-sir-state-active');
    $stateDone   = $('#wp-sir-state-done');

    $btnStart   = $('#wp-sir-bulk-start');
    $btnPause   = $('#wp-sir-bulk-pause');
    $btnResume  = $('#wp-sir-bulk-resume');
    $btnRestart = $('#wp-sir-bulk-restart');
    $btnAbort   = $('#wp-sir-bulk-abort');

    $statusLabel     = $('#wp-sir-status-label');
    $doneCount       = $('#wp-sir-done-count');
    $totalCount      = $('#wp-sir-total-count');
    $progressBar     = $('#wp-sir-progress-bar');
    $progressBarWrap = $('#wp-sir-progress-bar-wrap');
    $percentLabel    = $('#wp-sir-percent-label');
    $doneSummary     = $('#wp-sir-done-summary');

    $errorLog        = $('#wp-sir-error-log');
    $errorLogToggle  = $('#wp-sir-error-log-toggle');
    $errorLogTitle   = $('#wp-sir-error-log-title');
    $errorLogBody    = $('#wp-sir-error-log-body');
    $errorTableBody  = $('#wp-sir-error-table-body');
    $idleMessage     = $('#wp-sir-idle-message');
    $idleHint        = $('#wp-sir-idle-hint');

    // Collapsible error log.
    $errorLogToggle.on('click keydown', function (e) {
      if (e.type === 'keydown' && e.which !== 13 && e.which !== 32) return;
      var expanded = $errorLogToggle.attr('aria-expanded') === 'true';
      $errorLogToggle.attr('aria-expanded', !expanded);
      $errorLogBody.slideToggle(150);
      $errorLogToggle.find('.wp-sir-error-log-chevron')
        .toggleClass('dashicons-arrow-down-alt2', expanded)
        .toggleClass('dashicons-arrow-up-alt2', !expanded);
    });

    $btnStart.on('click',   function () { startProcess(false); });
    $btnPause.on('click',   pauseProcess);
    $btnResume.on('click',  function () { resumeProcess(); });
    $btnRestart.on('click', function () {
      startProcess(true);
    });
    $btnAbort.on('click', function () {
      if (!window.confirm('Abort this process? Progress will be cleared and you can start fresh.')) return;
      _aborted = true;
      var $icon = $btnAbort.find('.dashicons');
      var $label = $btnAbort.contents().filter(function() { return this.nodeType === 3; }).last();
      $btnAbort.prop('disabled', true);
      $label[0].textContent = ' Aborting\u2026';
      ajax(bulk.action_reset, {}, function (res) {
        $label[0].textContent = ' Abort';
        $btnAbort.prop('disabled', false);
        _allErrors = [];
        $errorTableBody.empty();
        $errorLog.hide();
        if (res && res.success) {
          applyState({ status: 'idle' });
          fetchCount();
        }
      });
    });

    fetchStatus();

    // Refresh count link — manual recount.
    $('#wp-sir-refresh-count').on('click', function (e) {
      e.preventDefault();
      var $link = $(this);
      $link.addClass('wp-sir-refresh-count--spinning');
      ajax(bulk.action_count, {}, function (res) {
        $link.removeClass('wp-sir-refresh-count--spinning');
        if (res && res.success) {
          applyCount(parseInt(res.data.count, 10) || 0);
        }
      });
    });

    // If no cached count was available server-side, fetch it now.
    if ($btnStart.prop('disabled') && $idleMessage.text().indexOf('Checking') !== -1) {
      prefetchCount();
    }
  }

  // ── API ────────────────────────────────────────────────────────────────────

  var _prefetchedCount = null;

  function ajax(action, extraData, callback) {
    $.post(ajaxUrl, $.extend({ action: action, nonce: nonce }, extraData || {}), callback, 'json');
  }

  function fetchStatus() {
    ajax(bulk.action_status, {}, function (res) {
      if (res && res.success) {
        // Only restore errors from a previous session if we haven't already
        // started a new process (prevents race condition on fast clicks).
        if (!_started && res.data.new_errors && res.data.new_errors.length && res.data.status !== 'running') {
          appendErrors(res.data.new_errors);
        }

        // On a fresh page load there is no active tick loop.
        // If the server says "running" it means the previous session was
        // interrupted (tab closed, reload, crash). Treat it as paused so
        // the user sees Resume, not Pause with nothing actually processing.
        if (res.data.status === 'running') {
          res.data.status = 'paused';
          // Tell the server to record the paused state too, fire-and-forget.
          ajax(bulk.action_pause, {});
        }

        applyState(res.data);

        // If idle, apply the prefetched count (or wait for it).
        if (!res.data.status || res.data.status === 'idle') {
          applyCount(_prefetchedCount);
        }
      }
    });
  }

  // Fire the count request in parallel with fetchStatus for faster load.
  function prefetchCount() {
    ajax(bulk.action_count, {}, function (res) {
      if (res && res.success) {
        _prefetchedCount = parseInt(res.data.count, 10) || 0;
      } else {
        _prefetchedCount = -1; // error state
      }
      // If idle state is already shown, apply immediately.
      if ($stateIdle.is(':visible')) {
        applyCount(_prefetchedCount);
      }
    });
  }

  function applyCount(count) {
    if (count === null) return; // still loading
    if (count === -1) {
      $idleMessage.text('Ready to process your images.');
      $idleHint.show();
      $btnStart.prop('disabled', false);
      return;
    }
    if (count > 0) {
      $idleMessage.text(count + ' image' + (count !== 1 ? 's' : '') + ' ready to be processed.');
      $btnStart.prop('disabled', false);
    } else {
      $idleMessage.text('All your images are already up to date.');
      $btnStart.prop('disabled', true);
    }
    $idleHint.show();
  }

  function fetchCount() {
    // Used after abort — fires a fresh count request.
    ajax(bulk.action_count, {}, function (res) {
      if (res && res.success) {
        applyCount(parseInt(res.data.count, 10) || 0);
      } else {
        applyCount(-1);
      }
    });
  }

  function startProcess(restart) {
    _aborted = false;
    _started = true;
    _done = false;
    // Always clear the client-side error log before starting or restarting —
    // the server truncates the errors table on both paths too.
    _allErrors = [];
    $errorTableBody.empty();
    $errorLog.hide();
    // Instant feedback — disable button and show loading state.
    $btnStart.prop('disabled', true).addClass('wp-sir-btn-loading');
    ajax(bulk.action_start, { restart: restart ? 1 : 0 }, function (res) {
      $btnStart.prop('disabled', false).removeClass('wp-sir-btn-loading');
      if (res && res.success) {
        applyState(res.data);
        if (res.data.status === 'running') tick();
      }
    });
  }

  function resumeProcess() {
    _aborted = false;
    // Instant feedback — hide resume, show pause immediately.
    $btnResume.hide();
    $btnAbort.hide();
    $btnPause.css('display', 'inline-flex');
    $statusLabel.text('Processing\u2026');
    ajax(bulk.action_start, { restart: 0 }, function (res) {
      if (res && res.success) {
        applyState(res.data);
        if (res.data.status === 'running') tick();
      }
    });
  }

  function pauseProcess() {
    _aborted = true;
    // Instant feedback — disable button and show loading state.
    $btnPause.prop('disabled', true).addClass('wp-sir-btn-loading');
    ajax(bulk.action_pause, {}, function (res) {
      $btnPause.prop('disabled', false).removeClass('wp-sir-btn-loading');
      if (res && res.success) applyState(res.data);
    });
  }

  function tick() {
    if (_aborted) return;

    ajax(bulk.action_process, {}, function (res) {
      if (_aborted) return; // pause was clicked while this request was in-flight

      if (!res || !res.success) {
        _aborted = true;
        applyState({ status: 'paused', total: parseInt($totalCount.text(), 10), done: parseInt($doneCount.text(), 10), error_count: _allErrors.length });
        return;
      }

      // Append any new errors from this batch immediately.
      if (res.data.new_errors && res.data.new_errors.length) {
        appendErrors(res.data.new_errors);
      }

      applyState(res.data);

      if (res.data.status === 'running') {
        setTimeout(tick, 300);
      }
    });
  }

  // ── Error log helpers ──────────────────────────────────────────────────────

  function appendErrors(errors) {
    if (!errors || !errors.length) return;

    errors.forEach(function (err) {
      _allErrors.push(err);
      var $row = $('<tr>')
        .append($('<td class="wp-sir-error-file">').text(err.file))
        .append($('<td class="wp-sir-error-reason">').text(err.reason));
      $errorTableBody.append($row);
    });

    // Update header count and show the log.
    var count = _allErrors.length;
    $errorLogTitle.text(count + ' image' + (count !== 1 ? 's' : '') + ' skipped');
    $errorLog.show();
  }

  // ── UI state ───────────────────────────────────────────────────────────────

  function applyState(data) {
    var status = data.status || 'idle';
    var total  = parseInt(data.total, 10) || 0;
    var done   = parseInt(data.done,  10) || 0;
    var pct    = total > 0 ? Math.round((done / total) * 100) : 0;

    // Once done, don't allow late callbacks to revert to another state.
    if (_done && status !== 'done' && status !== 'idle') {
      return;
    }

    // Hide loading placeholder once real state is known.
    $('#wp-sir-state-loading').remove();

    $stateIdle.hide();
    $stateActive.hide();
    $stateDone.hide();

    if (status === 'idle') {
      _done = false;
      $stateIdle.show();
      return;
    }

    if (status === 'done') {
      _done = true;
      $stateDone.show();
      var skipped = _allErrors.length;
      var succeeded = done - skipped;
      var summary = succeeded + ' image' + (succeeded !== 1 ? 's' : '') + ' processed successfully.';
      if (skipped > 0) {
        summary += ' ' + skipped + ' image' + (skipped !== 1 ? 's' : '') + ' were skipped — see the log below.';
      }
      $doneSummary.text(summary);
      return;
    }

    // running or paused
    $stateActive.show();
    $doneCount.text(done);
    $totalCount.text(total);
    $percentLabel.text(pct + '%');
    $progressBar.css('width', pct + '%');
    $progressBarWrap.attr('aria-valuenow', pct);

    if (status === 'running') {
      $statusLabel.text('Processing\u2026');
      $btnPause.css('display', 'inline-flex');
      $btnResume.hide();
      $btnAbort.hide();
      $progressBar.removeClass('wp-sir-bulk-progress-bar--paused');
    } else {
      $statusLabel.text('Paused');
      $btnPause.hide();
      $btnResume.css('display', 'inline-flex');
      $btnAbort.css('display', 'inline-flex');
      $progressBar.addClass('wp-sir-bulk-progress-bar--paused');
    }
  }

  $(document).ready(init);

})(jQuery);

})(jQuery);






// ─── Reset images (settings page) ────────────────────────────────────────────
(function ($) {
  'use strict';

  $(document).ready(function () {

    if (!$('#wp-sir-reset-section').length) return;

    var ajaxUrl = wp_sir_object.ajax_url;
    var nonce   = wp_sir_object.restore_nonce;
    var BATCH   = 5;

    var $btnStart     = $('#wp-sir-reset-start');
    var $countLabel   = $('#wp-sir-reset-count');
    var $stateIdle    = $('#wp-sir-reset-state-idle');
    var $stateRunning = $('#wp-sir-reset-state-running');
    var $stateDone    = $('#wp-sir-reset-state-done');
    var $progressBar  = $('#wp-sir-reset-progress-bar');
    var $progressWrap = $('#wp-sir-reset-progress-wrap');
    var $pct          = $('#wp-sir-reset-percent');
    var $doneCount    = $('#wp-sir-reset-done');
    var $totalCount   = $('#wp-sir-reset-total');
    var $summary      = $('#wp-sir-reset-summary');
    var $errorsWrap   = $('#wp-sir-reset-errors');
    var $errorList    = $('#wp-sir-reset-error-list');

    var _ids    = [];
    var _total  = 0;
    var _done   = 0;
    var _errors = [];
    var _stopped = false;

    // Fetch count of processed images on page load.
    $.post(ajaxUrl, { action: 'wp_sir_bulk_restore_status', nonce: nonce }, function (res) {
      if (res && res.success && res.data.total > 0) {
        _ids   = res.data.ids;
        _total = res.data.total;
        $countLabel.text('(' + _total + ' image' + (_total !== 1 ? 's' : '') + ' processed)');
        $btnStart.prop('disabled', false);
      } else {
        $countLabel.text('(' + wp_sir_object.i18n.no_processed_images + ')');
      }
    }, 'json');

    $btnStart.on('click', function () {
      if (!_ids.length) return;
      if (!window.confirm(wp_sir_object.i18n.restore_confirm)) return;

      _done    = 0;
      _errors  = [];
      _stopped = false;
      $errorList.empty();
      $errorsWrap.hide();
      $stateIdle.hide();
      $stateRunning.show();
      $stateDone.hide();
      $totalCount.text(_total);
      processBatch();
    });

    $('#wp-sir-reset-stop').on('click', function () {
      _stopped = true;
      $(this).prop('disabled', true).text('Stopping…');
    });

    function processBatch() {
      if (_stopped) { finish(); return; }
      var batch = _ids.slice(_done, _done + BATCH);
      if (!batch.length) { finish(); return; }

      $.post(ajaxUrl, {
        action: 'wp_sir_bulk_restore_batch',
        nonce:  nonce,
        ids:    JSON.stringify(batch)
      }, function (res) {
        if (!res || !res.success) { finish(); return; }

        _done += batch.length;

        if (res.data.errors && res.data.errors.length) {
          res.data.errors.forEach(function (e) {
            _errors.push(e);
            $errorList.append('<li>ID ' + e.id + ': ' + $('<span>').text(e.reason).html() + '</li>');
          });
          $errorsWrap.show();
        }

        var percent = Math.round((_done / _total) * 100);
        $progressBar.css('width', percent + '%');
        $progressWrap.attr('aria-valuenow', percent);
        $pct.text(percent + '%');
        $doneCount.text(_done);

        if (_done >= _total) { finish(); } else if (!_stopped) { setTimeout(processBatch, 300); } else { finish(); }
      }, 'json');
    }

    function finish() {
      $stateRunning.hide();
      $stateDone.show();
      $('#wp-sir-reset-stop').prop('disabled', false).text('Stop');

      var succeeded = _done - _errors.length;
      var s = succeeded + ' image' + (succeeded !== 1 ? 's' : '') + ' restored to original state.';
      if (_stopped && _done < _total) { s += ' Stopped — ' + (_total - _done) + ' remaining.'; }
      if (_errors.length) { s += ' ' + _errors.length + ' failed — see above.'; }
      $summary.text(s);

      _ids = [];
      $btnStart.prop('disabled', true);
      $countLabel.text('(' + wp_sir_object.i18n.no_processed_images + ')');
    }

  });

})(jQuery);
