/**
 * Advanced Pixel Editor - Media Library Integration
 * Adds "Advanced Editor" button to Media Library attachment details
 */

(function($) {
    'use strict';

    if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
        return;
    }

    var OriginalTwoColumn = wp.media.view.Attachment.Details.TwoColumn;

    wp.media.view.Attachment.Details.TwoColumn = OriginalTwoColumn.extend({
        render: function() {
            OriginalTwoColumn.prototype.render.apply(this, arguments);

            if (this.model.get('type') !== 'image') {
                return this;
            }

            if (this.$el.find('.advaimg-advanced-edit').length) {
                return this;
            }

            var $editBtn = this.$el.find('.edit-attachment');
            if ($editBtn.length) {
                var id = this.model.get('id');
                var url = ADVAIMG_MEDIA.editor_url + '&attachment_id=' + id;
                $editBtn.after(
                    '<a href="' + url + '" class="button advaimg-advanced-edit" style="margin-left: 8px;">' +
                    ADVAIMG_MEDIA.i18n.advanced_editor +
                    '</a>'
                );
            }

            return this;
        }
    });
})(jQuery);
