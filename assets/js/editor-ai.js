/**
 * Advanced Pixel Editor, AI Edit Module
 *
 * Sends the image and a text prompt to the AI provider connected under
 * Settings > Connectors. The server keeps the result as a working file and
 * returns a token; the token rides every preview and save request as
 * `advaimg_ai_source`, so the other tools and both save modes work on the
 * AI result. A second prompt refines the current result.
 *
 * The token is dropped (and the working file deleted) when the user
 * discards it, selects another image, replaces the original or restores it.
 */

/* global jQuery, ADVAIMG_AJAX */

(function($) {
    'use strict';

    var token = null;
    var tokenImageId = null;
    var prompts = [];
    var busy = false;
    var selectingOwnSource = false;

    function i18n(key) {
        return ADVAIMG_AJAX.i18n[key] || key;
    }

    function setStatus(text) {
        $('#aie-ai-status').empty();
        appendStatus(text);
    }

    function appendStatus(text, linkUrl, linkText) {
        var $status = $('#aie-ai-status');
        if (text) {
            $status.append($('<p>').text(text));
        }
        if (linkUrl) {
            $status.append(
                $('<p>').append($('<a>').attr('href', linkUrl).text(linkText))
            );
        }
    }

    function showActiveState() {
        var active = token !== null;
        $('#aie-ai-discard').toggle(active);
        $('#aie-ai-generate').text(active ? i18n('ai_refine') : i18n('ai_generate'));

        if (!active) {
            setStatus('');
            return;
        }

        var $status = $('#aie-ai-status').empty();
        var $list = $('<ol class="aie-ai-prompts">');
        prompts.forEach(function(text) {
            $list.append($('<li>').text(text));
        });
        $status.append($list);
    }

    function setBusy(state) {
        busy = state;
        $('#aie-ai-generate, #aie-ai-discard, #aie-ai-prompt').prop('disabled', state);
        $('#aie-save, #aie-reset').prop('disabled', state);
        $('.aie-preview-wrapper').toggleClass('aie-loading', state);
    }

    /**
     * Forget the active result and delete its working file on the server.
     */
    function dropToken() {
        if (token === null) {
            return;
        }

        $.post(ADVAIMG_AJAX.ajax_url, {
            action: 'advaimg_ai_discard',
            _ajax_nonce: ADVAIMG_AJAX.nonce,
            image_id: tokenImageId,
            advaimg_ai_source: token
        });

        token = null;
        tokenImageId = null;
        prompts = [];
        showActiveState();
    }

    function generate() {
        if (busy) {
            return;
        }

        var imageId = $('#aie-image-id').val();
        if (!imageId || parseInt(imageId, 10) <= 0) {
            alert(i18n('no_image'));
            return;
        }

        var prompt = $.trim($('#aie-ai-prompt').val());
        if (!prompt) {
            alert(i18n('ai_prompt_empty'));
            return;
        }

        if (!confirm(i18n('ai_confirm'))) {
            return;
        }

        var data = {
            action: 'advaimg_ai_edit',
            _ajax_nonce: ADVAIMG_AJAX.nonce,
            image_id: imageId,
            prompt: prompt
        };
        if (token !== null) {
            data[ADVAIMG_AJAX.ai.source_field] = token;
        }

        setBusy(true);
        setStatus(i18n('ai_generating'));

        $.post(ADVAIMG_AJAX.ajax_url, data)
        .done(function(resp) {
            if (!resp || !resp.success) {
                var err = resp ? resp.data : null;
                var message = err && err.message ? err.message : (typeof err === 'string' ? err : i18n('unknown_error'));
                showActiveState();
                appendStatus(
                    i18n('ai_failed') + ': ' + message,
                    err && err.connectors_url ? err.connectors_url : '',
                    i18n('ai_open_connectors')
                );
                return;
            }

            token = resp.data.token;
            tokenImageId = imageId;
            prompts = resp.data.prompts || [prompt];
            $('#aie-ai-prompt').val('');
            showActiveState();

            // The working image changed size: let the crop/resize tools forget
            // cached dimensions, then reset all tools and render the result.
            selectingOwnSource = true;
            $(document).trigger('advaimg:image-selected');
            selectingOwnSource = false;
            window.aieEditor.resetToDefaults();
        })
        .fail(function() {
            showActiveState();
            appendStatus(i18n('ai_failed') + ': ' + i18n('network_error'));
        })
        .always(function() {
            setBusy(false);
        });
    }

    function discard() {
        if (busy || token === null) {
            return;
        }
        if (!confirm(i18n('ai_discard_confirm'))) {
            return;
        }

        dropToken();
        window.aieEditor.resetToDefaults();
    }

    /**
     * Send the active result token with preview and save requests.
     */
    function initAjaxPrefilter() {
        $.ajaxPrefilter(function(options) {
            if (token === null || !options.data || typeof options.data !== 'string') {
                return;
            }
            if (options.data.indexOf('action=advaimg_preview') === -1 &&
                options.data.indexOf('action=advaimg_save') === -1) {
                return;
            }
            options.data += '&' + encodeURIComponent(ADVAIMG_AJAX.ai.source_field) + '=' + encodeURIComponent(token);
        });
    }

    $(function() {
        if (typeof ADVAIMG_AJAX === 'undefined' || !ADVAIMG_AJAX.ai) return;

        if (typeof window.aieToolbar !== 'undefined') {
            window.aieToolbar.addTool('ai');
        }

        if (!ADVAIMG_AJAX.ai.available || !$('#aie-ai-generate').length) {
            return;
        }

        initAjaxPrefilter();

        $('#aie-ai-generate').on('click', generate);
        $('#aie-ai-discard').on('click', discard);

        // Another image, a replaced original or a restore: the result no
        // longer applies.
        $(document).on('advaimg:image-selected', function() {
            if (!selectingOwnSource) {
                dropToken();
            }
        });
    });

})(jQuery);
