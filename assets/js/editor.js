/**
 * Advanced Image Editor - Editor JavaScript
 * Handles image selection, filter previews, and saving
 */

jQuery(function($){
    'use strict';

    let frame;
    let previewTimeout = null;
    let isDestroyed = false;
    
    // DOM elements
    const $loading = $('#aie-loading');
    const $imageId = $('#aie-image-id');
    const $preview = $('#aie-preview');
    const $originalPreview = $('#aie-original-preview');
    const $noPreview = $('#aie-no-preview');
    const $editor = $('#aie-editor');
    const $selectedImage = $('#aie-selected-image');
    const $previewToggle = $('#aie-preview-toggle');
    const $compareSlider = $('#aie-compare-slider');
    const $sliderHandle = $('#aie-slider-handle');
    
    // Value displays (legacy - keeping for compatibility)
    const $contrastValue = $('#aie-contrast-value');
    const $amountValue = $('#aie-amount-value');
    const $radiusValue = $('#aie-radius-value');
    const $thresholdValue = $('#aie-threshold-value');

    // Sliders
    const $contrast = $('#aie-contrast');
    const $amount = $('#aie-amount');
    const $radius = $('#aie-radius');
    const $threshold = $('#aie-threshold');

    // Number inputs
    const $contrastInput = $('#aie-contrast-input');
    const $amountInput = $('#aie-amount-input');
    const $radiusInput = $('#aie-radius-input');
    const $thresholdInput = $('#aie-threshold-input');
    
    // Create loading indicator if not present
    if (!$loading.length) {
        $('body').append(
            '<div id="aie-loading" style="display:none;" role="alert" aria-live="assertive">' +
            '<span>' + ADVAIMG_AJAX.i18n.processing + '</span>' +
            '<div id="aie-loading-progress"><div id="aie-loading-progress-bar"></div></div>' +
            '</div>'
        );
    }
    
    // Update value displays and accessibility attributes in real-time
    function updateValueDisplays() {
        const contrastVal = $contrast.val();
        const amountVal = $amount.val();
        const radiusVal = $radius.val();
        const thresholdVal = $threshold.val();

        // Update legacy value displays
        $contrastValue.text(contrastVal);
        $amountValue.text(amountVal);
        $radiusValue.text(radiusVal);
        $thresholdValue.text(thresholdVal);

        // Update number inputs
        $contrastInput.val(contrastVal);
        $amountInput.val(amountVal);
        $radiusInput.val(radiusVal);
        $thresholdInput.val(thresholdVal);

        // Update ARIA attributes for accessibility
        $contrast.attr('aria-valuenow', contrastVal);
        $amount.attr('aria-valuenow', amountVal);
        $radius.attr('aria-valuenow', radiusVal);
        $threshold.attr('aria-valuenow', thresholdVal);
    }
    
    // Initialize value displays
    updateValueDisplays();
    
    // Update displays when sliders change
    $contrast.add($amount).add($radius).add($threshold).on('input', updateValueDisplays);

    // Sync number inputs with sliders
    $contrastInput.on('input', function() {
        const value = $(this).val();
        $contrast.val(value).trigger('input');
    });
    $amountInput.on('input', function() {
        const value = $(this).val();
        $amount.val(value).trigger('input');
    });
    $radiusInput.on('input', function() {
        const value = $(this).val();
        $radius.val(value).trigger('input');
    });
    $thresholdInput.on('input', function() {
        const value = $(this).val();
        $threshold.val(value).trigger('input');
    });

    // Preview toggle functionality
    $previewToggle.on('change', function() {
        const isChecked = $(this).is(':checked');
        if (isChecked) {
            $preview.show();
            $originalPreview.show();
            $sliderHandle.show();
            updateSliderPosition();
        } else {
            $preview.hide();
            $originalPreview.hide();
            $sliderHandle.hide();
        }
    });

    // Comparison slider functionality
    $compareSlider.on('input', function() {
        updateSliderPosition();
    });

    function updateSliderPosition() {
        const sliderValue = $compareSlider.val();
        const containerWidth = $('.aie-preview-wrapper').width();
        const handlePosition = (sliderValue / 100) * containerWidth;

        $sliderHandle.css('left', handlePosition + 'px');
        // Clip the edited image (on top) to reveal the original (behind)
        $preview.css('clip-path', `inset(0 ${100 - sliderValue}% 0 0)`);
    }

    // Handle image loading for proper slider positioning
    $preview.add($originalPreview).on('load', function() {
        if ($previewToggle.is(':checked')) {
            updateSliderPosition();
        }
    });

    // Add keyboard navigation support for sliders
    $contrast.add($amount).add($radius).add($threshold).on('keydown', function(e) {
        const $slider = $(this);
        const step = parseFloat($slider.attr('step')) || 0.01;
        const min = parseFloat($slider.attr('min')) || 0;
        const max = parseFloat($slider.attr('max')) || 1;
        let currentValue = parseFloat($slider.val());

        switch(e.key) {
            case 'ArrowUp':
            case 'ArrowRight':
                e.preventDefault();
                currentValue = Math.min(max, currentValue + step);
                break;
            case 'ArrowDown':
            case 'ArrowLeft':
                e.preventDefault();
                currentValue = Math.max(min, currentValue - step);
                break;
            case 'PageUp':
                e.preventDefault();
                currentValue = Math.min(max, currentValue + step * 10);
                break;
            case 'PageDown':
                e.preventDefault();
                currentValue = Math.max(min, currentValue - step * 10);
                break;
            case 'Home':
                e.preventDefault();
                currentValue = max;
                break;
            case 'End':
                e.preventDefault();
                currentValue = min;
                break;
            default:
                return; // Let other keys work normally
        }

        $slider.val(currentValue).trigger('input');
    });
    
    // Loading functions
    function showLoading(message = ADVAIMG_AJAX.i18n.processing) {
        $loading.find('span').text(message);
        $loading.show();
        $('#aie-preview').addClass('loading');
        $('#aie-save, #aie-reset').prop('disabled', true).attr('aria-disabled', 'true');
    }

    function hideLoading() {
        $loading.hide();
        $('#aie-preview').removeClass('loading');
        // Re-enable buttons if we have a valid image
        if (validateImageID()) {
            $('#aie-save, #aie-reset').prop('disabled', false).attr('aria-disabled', 'false');
        }
    }

    function showButtonLoading(button, message = ADVAIMG_AJAX.i18n.saving) {
        button.addClass('loading').prop('disabled', true).attr('aria-disabled', 'true');
        button.data('original-text', button.text());
        button.text(message);
    }

    function hideButtonLoading(button) {
        button.removeClass('loading').prop('disabled', false).attr('aria-disabled', 'false');
        const originalText = button.data('original-text');
        if (originalText) {
            button.text(originalText);
        }
    }
    
    // Validation
    function validateImageID() {
        const imageId = $imageId.val();
        return imageId && parseInt(imageId) > 0;
    }
    
    // Reset sliders to defaults
    function resetToDefaults() {
        $contrast.val(0.5).trigger('input');
        $amount.val(0.5).trigger('input');
        $radius.val(1).trigger('input');
        $threshold.val(0).trigger('input');
        
        // Trigger preview after reset
        if (validateImageID()) {
            sendPreview();
        }
    }
    
    // Send preview request to server
    function sendPreview() {
        // Prevent operations after cleanup
        if (isDestroyed) {
            return;
        }

        // Validate image ID exists
        if (!validateImageID()) {
            alert(ADVAIMG_AJAX.i18n.no_image);
            return;
        }

        showLoading();

        const data = {
            action: "advaimg_preview",
            _ajax_nonce: ADVAIMG_AJAX.nonce,
            image_id: $imageId.val(),
            contrast: $contrast.val(),
            amount: $amount.val(),
            radius: $radius.val(),
            threshold: $threshold.val(),
        };

        $.post(ADVAIMG_AJAX.ajax_url, data, function(resp){
            if (resp.success) {
                $preview.attr('src', resp.data.preview);
                if ($previewToggle.is(':checked')) {
                    $preview.show();
                }
                $noPreview.hide();

                // Update slider position when new preview loads
                updateSliderPosition();
            } else {
                console.error('Preview failed:', resp.data);
                alert(ADVAIMG_AJAX.i18n.preview_failed + ': ' + (resp.data || ADVAIMG_AJAX.i18n.unknown_error));
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            alert(ADVAIMG_AJAX.i18n.network_error);
        })
        .always(function() {
            hideLoading();
        });
    }
    
    // Debounced preview function
    function debouncedPreview() {
        clearTimeout(previewTimeout);
        if (!isDestroyed) {
            previewTimeout = setTimeout(sendPreview, 300);
        }
    }

    // Cleanup function to prevent memory leaks
    function cleanup() {
        isDestroyed = true;

        // Clear any pending timeouts
        if (previewTimeout) {
            clearTimeout(previewTimeout);
            previewTimeout = null;
        }

        // Remove event listeners
        $(document).off('keydown', '#aie-contrast, #aie-amount, #aie-radius, #aie-threshold');
        $(document).off('input', '#aie-contrast-input, #aie-amount-input, #aie-radius-input, #aie-threshold-input');
        $('#aie-reset').off('click');
        $('#aie-save').off('click');
        $('#aie-select-image').off('click');

        // Clear references
        if (frame) {
            frame = null;
        }
    }
    
    // Event Listeners
    
    // Reset button
    $('#aie-reset').on('click', function(e) {
        e.preventDefault();

        if (!confirm(ADVAIMG_AJAX.i18n.reset_confirm)) {
            return;
        }

        resetToDefaults();
    });
    
    // Slider inputs with debounce
    $contrast.add($amount).add($radius).add($threshold).on('input', debouncedPreview);
    
    // Save button
    $('#aie-save').on('click', function(){
        // Prevent operations after cleanup
        if (isDestroyed) {
            return;
        }

        if (!validateImageID()) {
            alert(ADVAIMG_AJAX.i18n.no_image);
            return;
        }
        
        const previewSrc = $preview.attr('src');
        if (!previewSrc) {
            alert(ADVAIMG_AJAX.i18n.no_image);
            return;
        }
        
        if (!confirm(ADVAIMG_AJAX.i18n.confirm_save)) {
            return;
        }

        showButtonLoading($('#aie-save'));

        const data = {
            action: "advaimg_save",
            _ajax_nonce: ADVAIMG_AJAX.nonce,
            image_id: $imageId.val(),
            image_data: previewSrc
        };
        
        $.post(ADVAIMG_AJAX.ajax_url, data, function(resp){
            if (resp.success) {
                alert(resp.data.message);

                // Optional: Offer to go to the edited image
                if (resp.data.edit_link && confirm(ADVAIMG_AJAX.i18n.view_edited)) {
                    window.open(resp.data.edit_link, '_blank');
                }
            } else {
                console.error('Save failed:', resp.data);
                alert(ADVAIMG_AJAX.i18n.save_failed + ': ' + (resp.data.message || ADVAIMG_AJAX.i18n.unknown_error));
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            alert(ADVAIMG_AJAX.i18n.network_error);
        })
        .always(function() {
            hideButtonLoading($('#aie-save'));
        });
    });
    
    // Image selection
    $('#aie-select-image').on('click', function(e) {
        e.preventDefault();
        
        // If the media frame already exists, reopen it.
        if (frame) {
            frame.open();
            return;
        }
        
        // Create new media frame
        frame = wp.media({
            title: ADVAIMG_AJAX.i18n.select_image,
            button: { text: ADVAIMG_AJAX.i18n.use_this_image },
            library: { type: "image" },
            multiple: false
        });
        
        // When image is selected
        frame.on('select', function(){
            const attachment = frame.state().get('selection').first().toJSON();

            $imageId.val(attachment.id);
            $('#aie-image-title').text(attachment.filename || attachment.title);
            $selectedImage.show();

            // Show editor
            $editor.show();

            // Enable buttons
            $('#aie-save, #aie-reset').prop('disabled', false).attr('aria-disabled', 'false');

            // Update status messages
            $('#save-status').text(ADVAIMG_AJAX.i18n.save_status_enabled || 'Ready to save edited image');
            $('#reset-status').text(ADVAIMG_AJAX.i18n.reset_status_enabled || 'Ready to reset filters');

            // Load original image for comparison
            $originalPreview.attr('src', attachment.url).show();
            $compareSlider.val(100); // Start with full edited view

            // Reset to defaults and send preview
            resetToDefaults();
        });
        
        frame.open();
    });
    
    // Initialize
    $(document).ready(function() {
        // Check if there's already an image ID (for page refresh scenarios)
        if (validateImageID()) {
            $editor.show();
            $selectedImage.show();
            $('#aie-save, #aie-reset').prop('disabled', false).attr('aria-disabled', 'false');

            // Load original image for comparison
            loadOriginalImage();
            sendPreview();
        }
    });

    // Load original image for comparison
    function loadOriginalImage() {
        if (!validateImageID()) {
            return;
        }

        const data = {
            action: "advaimg_get_original",
            _ajax_nonce: ADVAIMG_AJAX.nonce,
            image_id: $imageId.val(),
        };

        $.post(ADVAIMG_AJAX.ajax_url, data, function(resp){
            if (resp.success) {
                $originalPreview.attr('src', resp.data.original_url).show();
                $compareSlider.val(100);
                updateSliderPosition();
            }
        });
    }

    // Cleanup on page unload to prevent memory leaks
    $(window).on('beforeunload', cleanup);

});