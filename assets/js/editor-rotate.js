/**
 * Advanced Pixel Editor — Rotate Module
 *
 * Free rotation via slider/number input plus 45 and 90 degree preset
 * buttons. The angle is sent as `advaimg_rotate` through the shared
 * transform params object, so it rides the same AJAX prefilter as
 * crop/resize for both preview and save. Rotation is applied server-side
 * by ADVAIMG_Transform::apply_rotate() before crop.
 *
 * Preview updates live (debounced through the free editor's preview
 * pipeline). Presets add to the current angle and wrap to (-180, 180].
 */

/* global jQuery, ADVAIMG_AJAX */

(function($) {
    'use strict';

    /**
     * Wrap an angle to the (-180, 180] range.
     */
    function normalizeAngle(deg) {
        deg = ((deg + 180) % 360 + 360) % 360 - 180;
        // Prefer +180 over -180 for display.
        return deg === -180 ? 180 : deg;
    }

    function triggerPreview() {
        var $contrast = $('#aie-contrast');
        if ($contrast.length) {
            $contrast.trigger('input');
        }
    }

    /**
     * Set the rotation angle, sync both inputs, update the shared
     * transform params, and request a preview.
     */
    function setAngle(deg) {
        deg = normalizeAngle(parseFloat(deg) || 0);
        // Round to one decimal to keep the param clean.
        deg = Math.round(deg * 10) / 10;

        $('#aie-rotate').val(deg).attr('aria-valuenow', deg);
        $('#aie-rotate-input').val(deg);

        if (deg === 0) {
            delete window.aieTransformParams.advaimg_rotate;
        } else {
            window.aieTransformParams.advaimg_rotate = deg;
        }

        triggerPreview();
    }

    function currentAngle() {
        return parseFloat($('#aie-rotate-input').val()) || 0;
    }

    function bindEvents() {
        $('#aie-rotate').on('input', function() {
            setAngle(this.value);
        });

        $('#aie-rotate-input').on('input', function() {
            setAngle(this.value);
        });

        $('.aie-rotate-presets button').on('click', function() {
            var delta = parseFloat($(this).data('deg')) || 0;
            setAngle(currentAngle() + delta);
        });

        $('#aie-clear-rotate').on('click', function() {
            setAngle(0);
        });
    }

    // Initialize.
    $(function() {
        if (typeof ADVAIMG_AJAX === 'undefined') return;
        if (!$('#aie-rotate').length) return;

        bindEvents();

        // Register with the toolbar. The comparison slider is hidden while
        // the rotate tool is active because a rotated preview no longer
        // aligns with the unrotated original behind it (same approach as
        // the crop tool).
        if (typeof window.aieToolbar !== 'undefined') {
            window.aieToolbar.addTool('rotate', function() {
                $('#aie-slider-handle').hide();
                $('#aie-compare-slider').hide();
            }, function() {
                $('#aie-slider-handle').show();
                $('#aie-compare-slider').show();
            });
        }
    });

})(jQuery);
