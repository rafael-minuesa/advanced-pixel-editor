<?php
/**
 * Advanced Pixel Editor Page
 *
 * @package AdvancedImageEditor
 * @author Rafael Minuesa
 * @license GPL-2.0+
 * @link https://github.com/rafael-minuesa/advanced-image-editor
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Check for pre-loaded attachment from Media Library
$advaimg_preload_id = '';
$advaimg_preload_title = '';

if (isset($_GET['attachment_id'])) {
    $attachment_id = absint($_GET['attachment_id']);
    if ($attachment_id > 0 && wp_attachment_is_image($attachment_id)) {
        $advaimg_preload_id = $attachment_id;
        $advaimg_preload_title = basename(get_attached_file($attachment_id));
    }
}
?>
<div class="aie-container">
        
        <!-- Image Selection Section -->
        <div class="aie-section aie-compact-section">
            <div class="aie-image-selector">
                <button id="aie-select-image" class="aie-button aie-button-small button-primary" aria-describedby="aie-select-help">
                    <?php esc_html_e('Select Image', 'advanced-pixel-editor'); ?>
                </button>
                <div id="aie-select-help" class="screen-reader-text">
                    <?php esc_html_e('Opens WordPress media library to select an image for editing', 'advanced-pixel-editor'); ?>
                </div>

                <div id="aie-selected-image" style="display: none;" class="aie-selected-image-name" role="status" aria-live="polite">
                    <span id="aie-image-title"><?php echo esc_html($advaimg_preload_title); ?></span>
                    <input type="hidden" id="aie-image-id" value="<?php echo esc_attr($advaimg_preload_id); ?>" aria-label="<?php esc_attr_e('Selected image ID', 'advanced-pixel-editor'); ?>">
                </div>
            </div>
        </div>
        
        <!-- Editor Section -->
        <div id="aie-editor" class="aie-section aie-editor-section" style="display: none;" role="region" aria-labelledby="editor-heading">
            <h2 id="editor-heading"><?php esc_html_e('2. Edit Image', 'advanced-pixel-editor'); ?></h2>

            <div class="aie-editor-layout">
                <!-- Tool Sidebar -->
                <div class="aie-toolbar" role="toolbar" aria-label="<?php esc_attr_e('Editor tools', 'advanced-pixel-editor'); ?>">
                    <button class="aie-toolbar-btn is-active" data-tool="contrast" title="<?php esc_attr_e('Contrast', 'advanced-pixel-editor'); ?>">
                        <span class="dashicons dashicons-image-filter"></span>
                    </button>
                    <button class="aie-toolbar-btn" data-tool="sharpness" title="<?php esc_attr_e('Sharpness', 'advanced-pixel-editor'); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                    <?php
                    if (has_action('advaimg_editor_toolbar_icons')) {
                        do_action('advaimg_editor_toolbar_icons');
                    } else {
                        // Show dimmed Pro upsell icons when Pro is not installed.
                        ?>
                        <button class="aie-toolbar-btn aie-toolbar-btn--locked" disabled title="<?php esc_attr_e('Advanced Filters (Pro)', 'advanced-pixel-editor'); ?>">
                            <span class="dashicons dashicons-art"></span>
                            <span class="aie-pro-badge">PRO</span>
                        </button>
                        <button class="aie-toolbar-btn aie-toolbar-btn--locked" disabled title="<?php esc_attr_e('Crop & Resize (Pro)', 'advanced-pixel-editor'); ?>">
                            <span class="dashicons dashicons-image-crop"></span>
                            <span class="aie-pro-badge">PRO</span>
                        </button>
                        <button class="aie-toolbar-btn aie-toolbar-btn--locked" disabled title="<?php esc_attr_e('Watermark (Pro)', 'advanced-pixel-editor'); ?>">
                            <span class="dashicons dashicons-media-text"></span>
                            <span class="aie-pro-badge">PRO</span>
                        </button>
                        <?php
                    }
                    ?>
                </div>

                <!-- Preview Section -->
                <div class="aie-preview-panel">
                    <div class="aie-preview-controls">
                        <label class="aie-preview-toggle">
                            <input type="checkbox" id="aie-preview-toggle" checked>
                            <?php esc_html_e('Preview', 'advanced-pixel-editor'); ?>
                        </label>
                        <div class="aie-slider-container">
                            <input type="range" id="aie-compare-slider" min="0" max="100" value="100" aria-label="<?php esc_attr_e('Compare original and edited image', 'advanced-pixel-editor'); ?>">
                            <div class="aie-slider-labels">
                                <span><?php esc_html_e('Edited', 'advanced-pixel-editor'); ?></span>
                                <span><?php esc_html_e('Original', 'advanced-pixel-editor'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="aie-preview-wrapper">
                        <img id="aie-original-preview" src="" alt="<?php esc_attr_e('Original image', 'advanced-pixel-editor'); ?>" style="max-width: 100%; height: auto; display: none;" role="img">
                        <img id="aie-preview" src="" alt="<?php esc_attr_e('Edited image preview', 'advanced-pixel-editor'); ?>" style="max-width: 100%; height: auto; display: none;" role="img">
                        <div id="aie-slider-handle" class="aie-slider-handle"></div>
                        <?php do_action('advaimg_editor_preview_overlay'); ?>
                    </div>
                    <p id="aie-no-preview" style="color: #666; font-style: italic;" role="status" aria-live="polite">
                        <?php esc_html_e('Preview will appear here after selecting an image', 'advanced-pixel-editor'); ?>
                    </p>
                </div>

                <!-- Controls Section -->
                <div class="aie-controls-panel" role="group" aria-labelledby="controls-heading">
                    <h3 id="controls-heading"><?php esc_html_e('Adjust Filters', 'advanced-pixel-editor'); ?></h3>

                    <!-- Contrast Tool Controls -->
                    <div class="aie-tool-controls is-active" data-tool="contrast">
                        <div class="aie-control-group">
                            <label for="aie-contrast">
                                <?php esc_html_e('Contrast', 'advanced-pixel-editor'); ?>
                            </label>
                            <div class="aie-input-group">
                                <input type="number" id="aie-contrast-input" min="-1" max="1" step="0.01" value="0" aria-label="<?php esc_attr_e('Contrast value', 'advanced-pixel-editor'); ?>">
                                <input type="range" id="aie-contrast" min="-1" max="1" step="0.01" value="0"
                                       aria-describedby="contrast-help" aria-valuemin="-1" aria-valuemax="1" aria-valuenow="0">
                            </div>
                            <div id="contrast-help" class="aie-help-text">
                                <small><?php esc_html_e('Adjust image contrast (-1 to 1)', 'advanced-pixel-editor'); ?></small>
                            </div>
                        </div>

                        <?php if (has_action('advaimg_editor_contrast_after')) : ?>
                            <?php do_action('advaimg_editor_contrast_after'); ?>
                        <?php else : ?>
                        <div class="aie-pro-upsell-controls">
                            <div class="aie-control-group">
                                <label><?php esc_html_e('Brightness', 'advanced-pixel-editor'); ?> <span class="aie-pro-badge">PRO</span></label>
                                <div class="aie-input-group">
                                    <input type="number" value="100" disabled>
                                    <input type="range" min="50" max="150" value="100" disabled>
                                </div>
                            </div>
                            <div class="aie-control-group">
                                <label><?php esc_html_e('Saturation', 'advanced-pixel-editor'); ?> <span class="aie-pro-badge">PRO</span></label>
                                <div class="aie-input-group">
                                    <input type="number" value="100" disabled>
                                    <input type="range" min="0" max="200" value="100" disabled>
                                </div>
                            </div>
                            <div class="aie-control-group">
                                <label><?php esc_html_e('Hue', 'advanced-pixel-editor'); ?> <span class="aie-pro-badge">PRO</span></label>
                                <div class="aie-input-group">
                                    <input type="number" value="100" disabled>
                                    <input type="range" min="0" max="200" value="100" disabled>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sharpness Tool Controls -->
                    <div class="aie-tool-controls" data-tool="sharpness">
                        <!-- Amount Control -->
                        <div class="aie-control-group">
                            <label for="aie-amount">
                                <?php esc_html_e('Sharpness Amount', 'advanced-pixel-editor'); ?>
                            </label>
                            <div class="aie-input-group">
                                <input type="number" id="aie-amount-input" min="0" max="5" step="0.1" value="0" aria-label="<?php esc_attr_e('Sharpness amount value', 'advanced-pixel-editor'); ?>">
                                <input type="range" id="aie-amount" min="0" max="5" step="0.1" value="0"
                                       aria-describedby="amount-help" aria-valuemin="0" aria-valuemax="5" aria-valuenow="0">
                            </div>
                            <div id="amount-help" class="aie-help-text">
                                <small><?php esc_html_e('Sharpening intensity (0 to 5)', 'advanced-pixel-editor'); ?></small>
                            </div>
                        </div>

                        <!-- Radius Control -->
                        <div class="aie-control-group">
                            <label for="aie-radius">
                                <?php esc_html_e('Sharpness Radius', 'advanced-pixel-editor'); ?>
                            </label>
                            <div class="aie-input-group">
                                <input type="number" id="aie-radius-input" min="0" max="5" step="0.1" value="1" aria-label="<?php esc_attr_e('Sharpness radius value', 'advanced-pixel-editor'); ?>">
                                <input type="range" id="aie-radius" min="0" max="5" step="0.1" value="1"
                                       aria-describedby="radius-help" aria-valuemin="0" aria-valuemax="5" aria-valuenow="1">
                            </div>
                            <div id="radius-help" class="aie-help-text">
                                <small><?php esc_html_e('Sharpening radius (0 to 5px)', 'advanced-pixel-editor'); ?></small>
                            </div>
                        </div>

                        <!-- Threshold Control -->
                        <div class="aie-control-group">
                            <label for="aie-threshold">
                                <?php esc_html_e('Sharpness Threshold', 'advanced-pixel-editor'); ?>
                            </label>
                            <div class="aie-input-group">
                                <input type="number" id="aie-threshold-input" min="0" max="1" step="0.01" value="0" aria-label="<?php esc_attr_e('Sharpness threshold value', 'advanced-pixel-editor'); ?>">
                                <input type="range" id="aie-threshold" min="0" max="1" step="0.01" value="0"
                                       aria-describedby="threshold-help" aria-valuemin="0" aria-valuemax="1" aria-valuenow="0">
                            </div>
                            <div id="threshold-help" class="aie-help-text">
                                <small><?php esc_html_e('Sharpening threshold (0 to 1)', 'advanced-pixel-editor'); ?></small>
                            </div>
                        </div>
                    </div>

                    <?php do_action('advaimg_editor_controls_after'); ?>
                </div>
            </div>

            <!-- Save Options (below the editor layout) -->
            <div class="aie-save-options" role="group" aria-labelledby="save-options-heading">
                <h3 id="save-options-heading"><?php esc_html_e('Save Options', 'advanced-pixel-editor'); ?></h3>

                <!-- Restore notice (hidden by default, shown via JS when image has backup) -->
                <div id="aie-restore-notice" class="aie-restore-notice" style="display: none;">
                    <span><?php esc_html_e('This image was previously edited with Advanced Pixel Editor.', 'advanced-pixel-editor'); ?></span>
                    <button id="aie-restore" class="aie-button aie-button-small aie-button-secondary">
                        <?php esc_html_e('Restore Original', 'advanced-pixel-editor'); ?>
                    </button>
                </div>

                <div class="aie-save-options-row">
                    <!-- Save mode -->
                    <div class="aie-control-group">
                        <label for="aie-save-mode"><?php esc_html_e('Save Mode', 'advanced-pixel-editor'); ?></label>
                        <select id="aie-save-mode">
                            <option value="new"><?php esc_html_e('Save as new image', 'advanced-pixel-editor'); ?></option>
                            <option value="replace"><?php esc_html_e('Replace original image', 'advanced-pixel-editor'); ?></option>
                        </select>
                    </div>

                    <!-- Filename (only visible in "new" mode) -->
                    <div id="aie-filename-group" class="aie-control-group">
                        <label for="aie-filename"><?php esc_html_e('Filename', 'advanced-pixel-editor'); ?></label>
                        <div class="aie-filename-input">
                            <input type="text" id="aie-filename" value="" placeholder="<?php esc_attr_e('edited-image', 'advanced-pixel-editor'); ?>">
                            <span id="aie-file-extension">.jpg</span>
                        </div>
                    </div>
                </div>

                <!-- Replace info (only visible in "replace" mode) -->
                <div id="aie-replace-info" class="aie-replace-info" style="display: none;">
                    <small><?php esc_html_e('A backup of the original will be saved automatically. You can restore it later.', 'advanced-pixel-editor'); ?></small>
                </div>

                <!-- Action Buttons -->
                <div class="aie-button-group" role="group" aria-label="<?php esc_attr_e('Image editing actions', 'advanced-pixel-editor'); ?>">
                    <button id="aie-save" class="aie-button aie-button-success" disabled aria-describedby="save-status">
                        <?php esc_html_e('Save Edited Image', 'advanced-pixel-editor'); ?>
                    </button>
                    <div id="save-status" class="screen-reader-text">
                        <?php esc_html_e('Save button is disabled until an image is selected and edited', 'advanced-pixel-editor'); ?>
                    </div>

                    <button id="aie-reset" class="aie-button aie-button-secondary" disabled aria-describedby="reset-status">
                        <?php esc_html_e('Reset to Defaults', 'advanced-pixel-editor'); ?>
                    </button>
                    <div id="reset-status" class="screen-reader-text">
                        <?php esc_html_e('Reset button is disabled until an image is selected', 'advanced-pixel-editor'); ?>
                    </div>
                </div>
            </div>
        </div>
        
          <!-- Help Section -->
         <div class="aie-section">
             <h2><?php esc_html_e('How to use the Advanced Pixel Editor', 'advanced-pixel-editor'); ?></h2>
             <ol>
                 <li><?php esc_html_e('Click "Select Image" to choose or upload an image.', 'advanced-pixel-editor'); ?></li>
                 <li><?php esc_html_e('Adjust the sliders or enter a value to apply filters in real-time. Move the comparison slider on the image to preview changes.', 'advanced-pixel-editor'); ?></li>
                 <li><?php esc_html_e('Click "Save Edited Image" to save. You can save as a new image with a custom name, or replace the original (a backup is created automatically).', 'advanced-pixel-editor'); ?></li>
             </ol>

             <h3><?php esc_html_e('Filter Explanations', 'advanced-pixel-editor'); ?></h3>
             <ul>
                 <li><strong><?php esc_html_e('Contrast:', 'advanced-pixel-editor'); ?></strong> <?php esc_html_e('Adjusts the difference between light and dark areas', 'advanced-pixel-editor'); ?></li>
                 <li><strong><?php esc_html_e('Sharpness Amount:', 'advanced-pixel-editor'); ?></strong> <?php esc_html_e('Controls the intensity of sharpening', 'advanced-pixel-editor'); ?></li>
                 <li><strong><?php esc_html_e('Sharpness Radius:', 'advanced-pixel-editor'); ?></strong> <?php esc_html_e('Determines how far the sharpening effect spreads', 'advanced-pixel-editor'); ?></li>
                 <li><strong><?php esc_html_e('Sharpness Threshold:', 'advanced-pixel-editor'); ?></strong> <?php esc_html_e('Sets the minimum contrast level for sharpening to apply', 'advanced-pixel-editor'); ?></li>
             </ul>
             <?php do_action('advaimg_editor_help_after'); ?>
         </div>
</div>