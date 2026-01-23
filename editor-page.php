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
?>
<div class="wrap">
    <h1><?php esc_html_e('Advanced Pixel Editor', 'advanced-pixel-editor'); ?></h1>
    
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
                    <span id="aie-image-title"></span>
                    <input type="hidden" id="aie-image-id" value="" aria-label="<?php esc_attr_e('Selected image ID', 'advanced-pixel-editor'); ?>">
                </div>
            </div>
        </div>
        
        <!-- Editor Section -->
        <div id="aie-editor" class="aie-section aie-editor-section" style="display: none;" role="region" aria-labelledby="editor-heading">
            <h2 id="editor-heading"><?php esc_html_e('2. Edit Image', 'advanced-pixel-editor'); ?></h2>

            <div class="aie-editor-layout">
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
                                <span><?php esc_html_e('Original', 'advanced-pixel-editor'); ?></span>
                                <span><?php esc_html_e('Edited', 'advanced-pixel-editor'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="aie-preview-wrapper">
                        <img id="aie-original-preview" src="" alt="<?php esc_attr_e('Original image', 'advanced-pixel-editor'); ?>" style="max-width: 100%; height: auto; display: none;" role="img">
                        <img id="aie-preview" src="" alt="<?php esc_attr_e('Edited image preview', 'advanced-pixel-editor'); ?>" style="max-width: 100%; height: auto; display: none;" role="img">
                        <div id="aie-slider-handle" class="aie-slider-handle"></div>
                    </div>
                    <p id="aie-no-preview" style="color: #666; font-style: italic;" role="status" aria-live="polite">
                        <?php esc_html_e('Preview will appear here after selecting an image', 'advanced-pixel-editor'); ?>
                    </p>
                </div>

                <!-- Controls Section -->
                <div class="aie-controls-panel" role="group" aria-labelledby="controls-heading">
                    <h3 id="controls-heading"><?php esc_html_e('Adjust Filters', 'advanced-pixel-editor'); ?></h3>

                    <!-- Contrast Control -->
                    <div class="aie-control-group">
                        <label for="aie-contrast">
                            <?php esc_html_e('Contrast', 'advanced-pixel-editor'); ?>
                        </label>
                        <div class="aie-input-group">
                            <input type="number" id="aie-contrast-input" min="-1" max="1" step="0.01" value="0.5" aria-label="<?php esc_attr_e('Contrast value', 'advanced-pixel-editor'); ?>">
                            <input type="range" id="aie-contrast" min="-1" max="1" step="0.01" value="0.5"
                                   aria-describedby="contrast-help" aria-valuemin="-1" aria-valuemax="1" aria-valuenow="0.5">
                        </div>
                        <div id="contrast-help" class="aie-help-text">
                            <small><?php esc_html_e('Adjust image contrast (-1 to 1)', 'advanced-pixel-editor'); ?></small>
                        </div>
                    </div>

                    <!-- Amount Control -->
                    <div class="aie-control-group">
                        <label for="aie-amount">
                            <?php esc_html_e('Sharpness Amount', 'advanced-pixel-editor'); ?>
                        </label>
                        <div class="aie-input-group">
                            <input type="number" id="aie-amount-input" min="-5" max="5" step="0.1" value="0.5" aria-label="<?php esc_attr_e('Sharpness amount value', 'advanced-pixel-editor'); ?>">
                            <input type="range" id="aie-amount" min="-5" max="5" step="0.1" value="0.5"
                                   aria-describedby="amount-help" aria-valuemin="-5" aria-valuemax="5" aria-valuenow="0.5">
                        </div>
                        <div id="amount-help" class="aie-help-text">
                            <small><?php esc_html_e('Sharpening intensity (-5 to 5)', 'advanced-pixel-editor'); ?></small>
                        </div>
                    </div>

                    <!-- Radius Control -->
                    <div class="aie-control-group">
                        <label for="aie-radius">
                            <?php esc_html_e('Sharpness Radius', 'advanced-pixel-editor'); ?>
                        </label>
                        <div class="aie-input-group">
                            <input type="number" id="aie-radius-input" min="-10" max="10" step="0.1" value="1" aria-label="<?php esc_attr_e('Sharpness radius value', 'advanced-pixel-editor'); ?>">
                            <input type="range" id="aie-radius" min="-10" max="10" step="0.1" value="1"
                                   aria-describedby="radius-help" aria-valuemin="-10" aria-valuemax="10" aria-valuenow="1">
                        </div>
                        <div id="radius-help" class="aie-help-text">
                            <small><?php esc_html_e('Sharpening radius (-10 to 10px)', 'advanced-pixel-editor'); ?></small>
                        </div>
                    </div>

                    <!-- Threshold Control -->
                    <div class="aie-control-group">
                        <label for="aie-threshold">
                            <?php esc_html_e('Sharpness Threshold', 'advanced-pixel-editor'); ?>
                        </label>
                        <div class="aie-input-group">
                            <input type="number" id="aie-threshold-input" min="-1" max="1" step="0.01" value="0" aria-label="<?php esc_attr_e('Sharpness threshold value', 'advanced-pixel-editor'); ?>">
                            <input type="range" id="aie-threshold" min="-1" max="1" step="0.01" value="0"
                                   aria-describedby="threshold-help" aria-valuemin="-1" aria-valuemax="1" aria-valuenow="0">
                        </div>
                        <div id="threshold-help" class="aie-help-text">
                            <small><?php esc_html_e('Sharpening threshold (-1 to 1)', 'advanced-pixel-editor'); ?></small>
                        </div>
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
        </div>
        
          <!-- Help Section -->
         <div class="aie-section">
             <h2><?php esc_html_e('How to use the Advanced Pixel Editor', 'advanced-pixel-editor'); ?></h2>
             <ol>
                 <li><?php esc_html_e('Click "Select Image" to choose or upload an image.', 'advanced-pixel-editor'); ?></li>
                 <li><?php esc_html_e('Adjust the sliders or enter a value to apply filters in real-time. Move the comparison slider on the image to preview changes.', 'advanced-pixel-editor'); ?></li>
                 <li><?php esc_html_e('Click "Save Edited Image" to save a copy to your media library (original image will remain untouched).', 'advanced-pixel-editor'); ?></li>
             </ol>

             <h3><?php esc_html_e('Filter Explanations', 'advanced-pixel-editor'); ?></h3>
             <ul>
                 <li><strong><?php esc_html_e('Contrast:', 'advanced-pixel-editor'); ?></strong> <?php esc_html_e('Adjusts the difference between light and dark areas', 'advanced-pixel-editor'); ?></li>
                 <li><strong><?php esc_html_e('Sharpness Amount:', 'advanced-pixel-editor'); ?></strong> <?php esc_html_e('Controls the intensity of sharpening', 'advanced-pixel-editor'); ?></li>
                 <li><strong><?php esc_html_e('Sharpness Radius:', 'advanced-pixel-editor'); ?></strong> <?php esc_html_e('Determines how far the sharpening effect spreads', 'advanced-pixel-editor'); ?></li>
                 <li><strong><?php esc_html_e('Sharpness Threshold:', 'advanced-pixel-editor'); ?></strong> <?php esc_html_e('Sets the minimum contrast level for sharpening to apply', 'advanced-pixel-editor'); ?></li>
             </ul>
         </div>
    </div>
</div>