/**
 * Advanced Pixel Editor — Flip Module
 *
 * Horizontal and vertical mirroring via toggle buttons. The flags are
 * sent as `advaimg_flip_h` / `advaimg_flip_v` through the shared
 * transform params object, riding the same AJAX prefilter as
 * crop/resize/rotate for both preview and save. Flipping is applied
 * server-side by ADVAIMG_Transform::apply_flip() after rotate and
 * before crop.
 *
 * The comparison slider stays available: flipping preserves image
 * dimensions, so the before/after overlay remains aligned.
 */

/* global jQuery, ADVAIMG_AJAX */

(function($) {
    'use strict';

    function triggerPreview() {
        var $contrast = $('#aie-contrast');
        if ($contrast.length) {
            $contrast.trigger('input');
        }
    }

    /**
     * Sync one flip toggle's param and visual state.
     *
     * @param {jQuery}  $btn  Toggle button.
     * @param {string}  key   Param key (advaimg_flip_h / advaimg_flip_v).
     * @param {boolean} state New state.
     */
    function setFlip($btn, key, state) {
        $btn.toggleClass('active', state).attr('aria-pressed', state ? 'true' : 'false');
        if (state) {
            window.aieTransformParams[key] = '1';
        } else {
            delete window.aieTransformParams[key];
        }
    }

    function bindEvents() {
        $('#aie-flip-h').on('click', function() {
            setFlip($(this), 'advaimg_flip_h', !$(this).hasClass('active'));
            triggerPreview();
        });

        $('#aie-flip-v').on('click', function() {
            setFlip($(this), 'advaimg_flip_v', !$(this).hasClass('active'));
            triggerPreview();
        });

        $('#aie-clear-flip').on('click', function() {
            setFlip($('#aie-flip-h'), 'advaimg_flip_h', false);
            setFlip($('#aie-flip-v'), 'advaimg_flip_v', false);
            triggerPreview();
        });
    }

    // Initialize.
    $(function() {
        if (typeof ADVAIMG_AJAX === 'undefined') return;
        if (!$('#aie-flip-h').length) return;

        bindEvents();

        // Register with the toolbar (no activate/deactivate side effects:
        // flip keeps dimensions, so the comparison slider stays usable).
        if (typeof window.aieToolbar !== 'undefined') {
            window.aieToolbar.addTool('flip');
        }
    });

})(jQuery);
