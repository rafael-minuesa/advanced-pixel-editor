/**
 * Advanced Pixel Editor — Transform Module (Crop & Resize)
 *
 * Renders crop overlay with 8 drag handles, aspect ratio presets,
 * numeric inputs, resize width/height with aspect lock, and DPI control.
 *
 * UX flow:
 * - Selecting the crop tool shows the controls panel (presets, inputs, buttons).
 * - Crop overlay only appears when user clicks on the preview image.
 * - Clicking outside the preview image hides the overlay.
 * - Resize and DPI each have their own "Apply" button.
 */

/* global jQuery, ADVAIMG_AJAX */

(function($) {
    'use strict';

    var cropToolActive = false; // Whether the crop tool is the selected toolbar tool.
    var cropOverlayVisible = false; // Whether the crop selection overlay is shown.
    var aspectRatio = null; // null = free
    var aspectLocked = true;
    var originalWidth = 0;
    var originalHeight = 0;
    var isDragging = false;
    var dragHandle = null;
    var dragStart = {};

    // Store transform params to append to AJAX requests.
    window.aieTransformParams = {};

    function i18n(key) {
        return (ADVAIMG_AJAX.i18n && ADVAIMG_AJAX.i18n[key]) || key;
    }

    /**
     * Get scale factor: ratio of original image pixels to displayed preview pixels.
     */
    function getScaleFactor() {
        var $preview = $('#aie-preview');
        if (!$preview.length || !$preview[0].naturalWidth) return 1;
        return $preview[0].naturalWidth / $preview.width();
    }

    /**
     * Build the Crop & Resize panel.
     */
    function buildPanel() {
        var $panel = $('#aie-transform-panel');
        if (!$panel.length) return;

        var html = '';

        // === CROP SECTION ===
        html += '<h4 style="margin:0 0 8px;font-size:12px;">' + i18n('crop') + '</h4>';
        html += '<p class="aie-help-text" style="margin:0 0 8px;"><small>' + i18n('crop_hint') + '</small></p>';

        // Aspect presets.
        html += '<div class="aie-crop-presets">' +
            '<button type="button" class="active" data-ratio="free">' + i18n('aspect_free') + '</button>' +
            '<button type="button" data-ratio="1:1">1:1</button>' +
            '<button type="button" data-ratio="4:3">4:3</button>' +
            '<button type="button" data-ratio="16:9">16:9</button>' +
            '</div>';

        // Crop numeric inputs.
        html += '<div class="aie-crop-dims">' +
            'X <input type="number" id="aie-crop-x" min="0" value="0"> ' +
            'Y <input type="number" id="aie-crop-y" min="0" value="0">' +
            '</div>' +
            '<div class="aie-crop-dims">' +
            'W <input type="number" id="aie-crop-w" min="1" value="0"> ' +
            'H <input type="number" id="aie-crop-h" min="1" value="0">' +
            '</div>';

        // Crop action buttons.
        html += '<div class="aie-crop-actions">' +
            '<button type="button" class="button button-primary" id="aie-apply-crop">' + i18n('apply_crop') + '</button>' +
            '<button type="button" class="button" id="aie-clear-crop">' + i18n('clear_crop') + '</button>' +
            '</div>';

        // === RESIZE SECTION ===
        html += '<hr style="margin:16px 0 12px;border:0;border-top:1px solid #dcdcde;">';
        html += '<h4 style="margin:0 0 8px;font-size:12px;">' + i18n('resize') + '</h4>';

        html += '<div class="aie-resize-group">' +
            i18n('width') + ' <input type="number" id="aie-resize-w" min="1" value="0"> ' +
            '<span class="aie-aspect-lock locked" id="aie-aspect-lock" title="' + i18n('lock_aspect') + '">&#x1f512;</span> ' +
            i18n('height') + ' <input type="number" id="aie-resize-h" min="1" value="0">' +
            '</div>';

        html += '<div class="aie-crop-actions">' +
            '<button type="button" class="button button-primary" id="aie-apply-resize">' + i18n('apply_resize') + '</button>' +
            '<button type="button" class="button" id="aie-clear-resize">' + i18n('clear_resize') + '</button>' +
            '</div>';

        // === DPI SECTION ===
        html += '<hr style="margin:16px 0 12px;border:0;border-top:1px solid #dcdcde;">';
        html += '<h4 style="margin:0 0 8px;font-size:12px;">' + i18n('dpi') + '</h4>';

        html += '<div class="aie-control-group" style="border-bottom:none;margin-bottom:0;padding-bottom:0;">' +
            '<div class="aie-input-group">' +
            '<input type="number" id="aie-dpi" min="1" max="1200" value="" placeholder="72">' +
            '<label style="margin-left:8px;font-weight:400;"><input type="checkbox" id="aie-resample"> ' + i18n('resample') + '</label>' +
            '</div></div>';

        html += '<div class="aie-crop-actions" style="margin-top:8px;">' +
            '<button type="button" class="button button-primary" id="aie-apply-dpi">' + i18n('apply_dpi') + '</button>' +
            '<button type="button" class="button" id="aie-clear-dpi">' + i18n('clear_dpi') + '</button>' +
            '</div>';

        $panel.html(html);
        bindEvents($panel);
    }

    function setParam(key, value) {
        window.aieTransformParams[key] = value;
    }

    function removeParam(key) {
        delete window.aieTransformParams[key];
    }

    function triggerPreview() {
        var $contrast = $('#aie-contrast');
        if ($contrast.length) {
            $contrast.trigger('input');
        }
    }

    function bindEvents($panel) {
        // Aspect ratio presets.
        $panel.find('.aie-crop-presets button').on('click', function() {
            $panel.find('.aie-crop-presets button').removeClass('active');
            $(this).addClass('active');
            var ratio = $(this).data('ratio');
            if (ratio === 'free') {
                aspectRatio = null;
            } else {
                var parts = ratio.split(':');
                aspectRatio = parseFloat(parts[0]) / parseFloat(parts[1]);
            }
        });

        // Apply crop.
        $panel.find('#aie-apply-crop').on('click', function() {
            var x = Math.round(parseFloat($('#aie-crop-x').val()) || 0);
            var y = Math.round(parseFloat($('#aie-crop-y').val()) || 0);
            var w = Math.round(parseFloat($('#aie-crop-w').val()) || 0);
            var h = Math.round(parseFloat($('#aie-crop-h').val()) || 0);

            if (w <= 0 || h <= 0) return;

            setParam('advaimg_crop_x', x);
            setParam('advaimg_crop_y', y);
            setParam('advaimg_crop_w', w);
            setParam('advaimg_crop_h', h);
            triggerPreview();

            // Hide overlay after applying.
            hideCropOverlay();
        });

        // Clear crop.
        $panel.find('#aie-clear-crop').on('click', function() {
            removeParam('advaimg_crop_x');
            removeParam('advaimg_crop_y');
            removeParam('advaimg_crop_w');
            removeParam('advaimg_crop_h');
            hideCropOverlay();
            $('#aie-crop-x, #aie-crop-y').val(0);
            $('#aie-crop-w, #aie-crop-h').val(0);
            triggerPreview();
        });

        // Apply resize.
        $panel.find('#aie-apply-resize').on('click', function() {
            var w = parseInt($('#aie-resize-w').val()) || 0;
            var h = parseInt($('#aie-resize-h').val()) || 0;
            if (w <= 0 || h <= 0) return;

            setParam('advaimg_resize_w', w);
            setParam('advaimg_resize_h', h);
            triggerPreview();
        });

        // Clear resize.
        $panel.find('#aie-clear-resize').on('click', function() {
            removeParam('advaimg_resize_w');
            removeParam('advaimg_resize_h');
            if (originalWidth > 0) {
                $('#aie-resize-w').val(originalWidth);
                $('#aie-resize-h').val(originalHeight);
            }
            triggerPreview();
        });

        // Aspect ratio lock toggle for resize.
        $panel.find('#aie-aspect-lock').on('click', function() {
            aspectLocked = !aspectLocked;
            $(this).toggleClass('locked unlocked');
            $(this).html(aspectLocked ? '&#x1f512;' : '&#x1f513;');
        });

        // Resize width change — update linked height only, don't apply yet.
        $panel.find('#aie-resize-w').on('input', function() {
            var w = parseInt(this.value) || 0;
            if (aspectLocked && originalWidth > 0 && originalHeight > 0) {
                var h = Math.round(w * (originalHeight / originalWidth));
                $panel.find('#aie-resize-h').val(h);
            }
        });

        // Resize height change — update linked width only, don't apply yet.
        $panel.find('#aie-resize-h').on('input', function() {
            var h = parseInt(this.value) || 0;
            if (aspectLocked && originalWidth > 0 && originalHeight > 0) {
                var w = Math.round(h * (originalWidth / originalHeight));
                $panel.find('#aie-resize-w').val(w);
            }
        });

        // Apply DPI.
        $panel.find('#aie-apply-dpi').on('click', function() {
            var dpi = parseInt($('#aie-dpi').val()) || 0;
            if (dpi <= 0) return;

            setParam('advaimg_dpi', dpi);
            setParam('advaimg_resample', $('#aie-resample').is(':checked') ? '1' : '0');
            triggerPreview();
        });

        // Clear DPI.
        $panel.find('#aie-clear-dpi').on('click', function() {
            removeParam('advaimg_dpi');
            removeParam('advaimg_resample');
            $('#aie-dpi').val('');
            $('#aie-resample').prop('checked', false);
            triggerPreview();
        });

        // Crop overlay interactions.
        initCropOverlay();
    }

    /**
     * Show crop overlay on the preview image.
     */
    function showCropOverlay() {
        var $overlay = $('#aie-crop-overlay');
        var $selection = $overlay.find('.aie-crop-selection');

        // Position selection at 10% inset.
        $selection.css({ top: '10%', left: '10%', width: '80%', height: '80%' });
        $overlay.show();
        cropOverlayVisible = true;

        var $wrapper = $('.aie-preview-wrapper');
        updateCropInputs($selection, $wrapper.width(), $wrapper.height());
    }

    /**
     * Hide the crop overlay.
     */
    function hideCropOverlay() {
        $('#aie-crop-overlay').hide();
        cropOverlayVisible = false;
    }

    /**
     * Initialize crop overlay drag & resize, and click-to-show behavior.
     */
    function initCropOverlay() {
        var $overlay = $('#aie-crop-overlay');
        var $selection = $overlay.find('.aie-crop-selection');

        // Show crop overlay when user clicks on the preview image (only when crop tool is active).
        $('.aie-preview-wrapper').on('click', '#aie-preview, #aie-original-preview', function(e) {
            if (!cropToolActive || cropOverlayVisible) return;
            e.preventDefault();
            showCropOverlay();
        });

        // Hide crop overlay when clicking outside the preview wrapper (only for clicks outside).
        $(document).on('mousedown.aieTransformCropDismiss', function(e) {
            if (!cropOverlayVisible) return;

            var $target = $(e.target);
            // Don't dismiss if clicking inside the overlay, the preview wrapper, or the controls panel.
            if ($target.closest('.aie-crop-overlay').length ||
                $target.closest('.aie-preview-wrapper').length ||
                $target.closest('[data-tool="crop"]').length) {
                return;
            }

            hideCropOverlay();
        });

        // Drag selection (move).
        $selection.on('mousedown', function(e) {
            if ($(e.target).hasClass('aie-crop-handle')) return;
            e.preventDefault();
            isDragging = true;
            dragHandle = 'move';
            dragStart = {
                x: e.clientX,
                y: e.clientY,
                left: parseInt($selection.css('left')) || 0,
                top: parseInt($selection.css('top')) || 0
            };
        });

        // Drag handles (resize).
        $overlay.find('.aie-crop-handle').on('mousedown', function(e) {
            e.preventDefault();
            e.stopPropagation();
            isDragging = true;
            dragHandle = $(this).data('handle');
            dragStart = {
                x: e.clientX,
                y: e.clientY,
                left: parseInt($selection.css('left')) || 0,
                top: parseInt($selection.css('top')) || 0,
                width: $selection.width(),
                height: $selection.height()
            };
        });

        $(document).on('mousemove.aieTransformCrop', function(e) {
            if (!isDragging) return;
            e.preventDefault();

            var $wrapper = $('.aie-preview-wrapper');
            var ww = $wrapper.width();
            var wh = $wrapper.height();
            var dx = e.clientX - dragStart.x;
            var dy = e.clientY - dragStart.y;

            if (dragHandle === 'move') {
                var newLeft = Math.max(0, Math.min(ww - $selection.width(), dragStart.left + dx));
                var newTop = Math.max(0, Math.min(wh - $selection.height(), dragStart.top + dy));
                $selection.css({ left: newLeft + 'px', top: newTop + 'px' });
            } else {
                resizeSelection(dx, dy, ww, wh, $selection);
            }

            updateCropInputs($selection, ww, wh);
        });

        $(document).on('mouseup.aieTransformCrop', function() {
            isDragging = false;
            dragHandle = null;
        });
    }

    function resizeSelection(dx, dy, ww, wh, $selection) {
        var left = dragStart.left;
        var top = dragStart.top;
        var w = dragStart.width;
        var h = dragStart.height;
        var newLeft = left, newTop = top, newW = w, newH = h;

        switch (dragHandle) {
            case 'se':
                newW = Math.max(20, w + dx);
                newH = aspectRatio ? newW / aspectRatio : Math.max(20, h + dy);
                break;
            case 'nw':
                newW = Math.max(20, w - dx);
                newH = aspectRatio ? newW / aspectRatio : Math.max(20, h - dy);
                newLeft = left + (w - newW);
                newTop = top + (h - newH);
                break;
            case 'ne':
                newW = Math.max(20, w + dx);
                newH = aspectRatio ? newW / aspectRatio : Math.max(20, h - dy);
                newTop = top + (h - newH);
                break;
            case 'sw':
                newW = Math.max(20, w - dx);
                newH = aspectRatio ? newW / aspectRatio : Math.max(20, h + dy);
                newLeft = left + (w - newW);
                break;
            case 'n':
                newH = Math.max(20, h - dy);
                newTop = top + (h - newH);
                if (aspectRatio) { newW = newH * aspectRatio; }
                break;
            case 's':
                newH = Math.max(20, h + dy);
                if (aspectRatio) { newW = newH * aspectRatio; }
                break;
            case 'w':
                newW = Math.max(20, w - dx);
                newLeft = left + (w - newW);
                if (aspectRatio) { newH = newW / aspectRatio; }
                break;
            case 'e':
                newW = Math.max(20, w + dx);
                if (aspectRatio) { newH = newW / aspectRatio; }
                break;
        }

        // Clamp to wrapper bounds.
        newLeft = Math.max(0, newLeft);
        newTop = Math.max(0, newTop);
        newW = Math.min(newW, ww - newLeft);
        newH = Math.min(newH, wh - newTop);

        $selection.css({
            left: newLeft + 'px',
            top: newTop + 'px',
            width: newW + 'px',
            height: newH + 'px'
        });
    }

    /**
     * Update the numeric crop inputs from the overlay selection position.
     */
    function updateCropInputs($selection, wrapperW, wrapperH) {
        var scale = getScaleFactor();
        var left = parseInt($selection.css('left')) || 0;
        var top = parseInt($selection.css('top')) || 0;
        var w = $selection.width();
        var h = $selection.height();

        $('#aie-crop-x').val(Math.round(left * scale));
        $('#aie-crop-y').val(Math.round(top * scale));
        $('#aie-crop-w').val(Math.round(w * scale));
        $('#aie-crop-h').val(Math.round(h * scale));
    }

    /**
     * Update original dimensions when an image is loaded.
     */
    function watchImageLoad() {
        var $preview = $('#aie-preview');
        $preview.on('load', function() {
            if (this.naturalWidth) {
                originalWidth = this.naturalWidth;
                originalHeight = this.naturalHeight;
                $('#aie-resize-w').val(originalWidth).attr('placeholder', originalWidth);
                $('#aie-resize-h').val(originalHeight).attr('placeholder', originalHeight);
            }
        });
    }

    /**
     * Append transform params to AJAX preview and save requests.
     */
    function initAjaxPrefilter() {
        $.ajaxPrefilter(function(options) {
            if (!options.data || typeof options.data !== 'string') {
                return;
            }
            if (options.data.indexOf('action=advaimg_preview') === -1 &&
                options.data.indexOf('action=advaimg_save') === -1) {
                return;
            }
            var params = window.aieTransformParams;
            for (var key in params) {
                if (params.hasOwnProperty(key) && params[key] !== '' && params[key] !== null && params[key] !== undefined) {
                    options.data += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
                }
            }
        });
    }

    // Initialize.
    $(function() {
        if (typeof ADVAIMG_AJAX === 'undefined') return;

        buildPanel();
        watchImageLoad();
        initAjaxPrefilter();

        // Register crop tool with the toolbar system.
        if (typeof window.aieToolbar !== 'undefined') {
            window.aieToolbar.addTool('crop', function() {
                // Tool activated — just mark it active, don't show overlay yet.
                cropToolActive = true;
                // Hide comparison slider so it doesn't interfere with crop.
                $('#aie-slider-handle').hide();
                $('#aie-compare-slider').hide();
            }, function() {
                // Tool deactivated — hide overlay, restore comparison slider.
                cropToolActive = false;
                hideCropOverlay();
                $('#aie-slider-handle').show();
                $('#aie-compare-slider').show();
            });
        }
    });

})(jQuery);
