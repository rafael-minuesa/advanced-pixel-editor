<?php
/**
 * Main Advanced Pixel Editor class
 *
 * Handles plugin initialization, assets, and core functionality
 *
 * @package AdvancedImageEditor
 * @author Rafael Minuesa
 * @license GPL-2.0+
 * @link https://github.com/rafael-minuesa/advanced-image-editor
 */

if (!defined('ABSPATH')) {
    exit;
}

class Advanced_Pixel_Editor {

    /**
     * Plugin version
     */
    const VERSION = ADVAIMG_VERSION;

    /**
     * Maximum image file size in bytes (10MB)
     */
    const MAX_FILE_SIZE = 10485760;

    /**
     * JPEG quality for preview images
     */
    const PREVIEW_QUALITY = 90;

    /**
     * Maximum image width for processing (pixels)
     */
    const MAX_IMAGE_WIDTH = 4096;

    /**
     * Maximum image height for processing (pixels)
     */
    const MAX_IMAGE_HEIGHT = 4096;

    /**
     * Rate limiting: maximum requests per minute
     */
    const RATE_LIMIT_REQUESTS = 30;

    /**
     * Rate limiting window in seconds
     */
    const RATE_LIMIT_WINDOW = 60;

    /**
     * Top-level menu slug (used by Pro to add submenus)
     */
    const MENU_SLUG = 'advanced-pixel-editor';

    /**
     * Valid tab slugs
     */
    const VALID_TABS = ['editor', 'settings', 'about'];

    /**
     * AJAX handler instance
     *
     * @var ADVAIMG_Ajax_Handler
     */
    private $ajax_handler;

    /**
     * Constructor - Initialize hooks and filters
     */
    public function __construct() {
        $this->ajax_handler = new ADVAIMG_Ajax_Handler();

        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // Add plugin action links
        add_filter('plugin_action_links_' . ADVAIMG_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);

        // Add "Advanced Edit" link to Media Library list view row actions
        add_filter('media_row_actions', [$this, 'add_media_row_action'], 10, 2);
    }


    /**
     * Add action links to plugin row
     *
     * @param array $links Existing plugin action links
     * @return array Modified links array
     */
    public function add_plugin_action_links($links) {
        $editor_link = '<a href="' . esc_url(admin_url('upload.php?page=' . self::MENU_SLUG)) . '">' . esc_html__('Editor', 'advanced-pixel-editor') . '</a>';
        $settings_link = '<a href="' . esc_url(admin_url('upload.php?page=' . self::MENU_SLUG . '&tab=settings')) . '">' . esc_html__('Settings', 'advanced-pixel-editor') . '</a>';
        array_unshift($links, $settings_link);
        array_unshift($links, $editor_link);
        return $links;
    }

    /**
     * Add "Advanced Edit" link to Media Library list view row actions
     *
     * @param array    $actions Existing row actions
     * @param \WP_Post $post    The attachment post object
     * @return array Modified actions array
     */
    public function add_media_row_action($actions, $post) {
        if (wp_attachment_is_image($post->ID)) {
            $url = admin_url('upload.php?page=' . self::MENU_SLUG . '&attachment_id=' . $post->ID);
            $link = '<a href="' . esc_url($url) . '">' . esc_html__('Advanced Edit', 'advanced-pixel-editor') . '</a>';

            // Insert after "edit" to appear right next to Edit
            $reordered = [];
            foreach ($actions as $key => $value) {
                $reordered[$key] = $value;
                if ($key === 'edit') {
                    $reordered['advaimg_edit'] = $link;
                }
            }
            return $reordered;
        }
        return $actions;
    }

    /**
     * Add editor and settings pages under the Media menu
     */
    public function add_menu() {
        add_media_page(
            esc_html__('Advanced Pixel Editor', 'advanced-pixel-editor'),
            esc_html__('Advanced Pixel Editor', 'advanced-pixel-editor'),
            'upload_files',
            self::MENU_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Get the current tab from the request
     *
     * @return string Current tab slug
     */
    private function get_current_tab() {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'editor';
        return in_array($tab, self::VALID_TABS, true) ? $tab : 'editor';
    }

    /**
     * Enqueue admin assets (CSS and JS)
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_assets($hook) {
        // Attachment edit page - add "Advanced Editor" button next to "Edit Image"
        if ($hook === 'post.php') {
            $post = get_post();
            if ($post && wp_attachment_is_image($post->ID)) {
                $url = admin_url('upload.php?page=' . self::MENU_SLUG . '&attachment_id=' . $post->ID);
                $label = esc_js(__('Advanced Editor', 'advanced-pixel-editor'));
                wp_add_inline_script('jquery', sprintf(
                    'jQuery(function($){var b=$("[id^=imgedit-open-btn-]");if(b.length){b.after(" <a href=\"%s\" class=\"button advaimg-advanced-edit\" style=\"margin-left:8px;\">%s</a>");}});',
                    esc_url($url),
                    $label
                ));
            }
        }

        // Media Library page - add "Advanced Editor" button to grid view modal
        if ($hook === 'upload.php') {
            $ml_js_path = ADVAIMG_PLUGIN_DIR . 'assets/js/media-library.js';
            $ml_js_version = file_exists($ml_js_path) ? filemtime($ml_js_path) : self::VERSION;

            wp_enqueue_script(
                'advaimg-media-library-js',
                ADVAIMG_PLUGIN_URL . 'assets/js/media-library.js',
                ['jquery', 'media-views'],
                $ml_js_version,
                true
            );

            wp_localize_script('advaimg-media-library-js', 'ADVAIMG_MEDIA', [
                'editor_url' => admin_url('upload.php?page=' . self::MENU_SLUG),
                'i18n'       => [
                    'advanced_editor' => __('Advanced Editor', 'advanced-pixel-editor'),
                ],
            ]);
        }

        // Plugin page — enqueue admin CSS for all tabs
        if ($hook !== 'media_page_' . self::MENU_SLUG) {
            return;
        }

        $css_path = ADVAIMG_PLUGIN_DIR . 'assets/css/admin.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : self::VERSION;

        wp_enqueue_style(
            'advaimg-admin-css',
            ADVAIMG_PLUGIN_URL . 'assets/css/admin.css',
            [],
            $css_version
        );

        // Only enqueue editor assets on the Editor tab
        $current_tab = $this->get_current_tab();
        if ($current_tab !== 'editor') {
            return;
        }

        wp_enqueue_media(); // Enables WP media modal

        // Get file modification time for cache busting
        $js_path = ADVAIMG_PLUGIN_DIR . 'assets/js/editor.js';
        $js_version = file_exists($js_path) ? filemtime($js_path) : self::VERSION;

        // Enqueue JS
        wp_enqueue_script(
            'advaimg-editor-js',
            ADVAIMG_PLUGIN_URL . 'assets/js/editor.js',
            ['jquery'],
            $js_version,
            true
        );

        // Localize script with translations and AJAX data
        $localize_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('advaimg_nonce'),
            'i18n'     => [
                'select_image'       => __('Select an Image to Edit', 'advanced-pixel-editor'),
                'use_this_image'     => __('Use This Image', 'advanced-pixel-editor'),
                'saving'             => __('Saving...', 'advanced-pixel-editor'),
                'processing'         => __('Processing...', 'advanced-pixel-editor'),
                'no_image'           => __('Please select an image first', 'advanced-pixel-editor'),
                'save_success'       => __('Image saved successfully!', 'advanced-pixel-editor'),
                'preview_failed'     => __('Failed to generate preview', 'advanced-pixel-editor'),
                'network_error'      => __('Network error - please try again', 'advanced-pixel-editor'),
                'save_failed'        => __('Failed to save image', 'advanced-pixel-editor'),
                'confirm_save'       => __('Save this edited image to your media library?', 'advanced-pixel-editor'),
                'view_edited'        => __('Would you like to view the edited image?', 'advanced-pixel-editor'),
                'unknown_error'      => __('Unknown error occurred', 'advanced-pixel-editor'),
                'rate_limit_error'   => __('Too many requests. Please wait a moment before trying again.', 'advanced-pixel-editor'),
                'reset_confirm'      => __('Reset all filters to default values?', 'advanced-pixel-editor'),
                'save_status_enabled' => __('Ready to save edited image', 'advanced-pixel-editor'),
                'reset_status_enabled' => __('Ready to reset filters', 'advanced-pixel-editor'),
                'save_button'        => __('Save Edited Image', 'advanced-pixel-editor'),
                'replace_button'     => __('Replace Original', 'advanced-pixel-editor'),
                'confirm_replace'    => __('Replace the original image? A backup will be saved automatically.', 'advanced-pixel-editor'),
                'restore_confirm'    => __('Restore the original image? The current edited version will be replaced.', 'advanced-pixel-editor'),
                'restore_success'    => __('Original image restored successfully.', 'advanced-pixel-editor'),
                'restore_failed'     => __('Failed to restore original image.', 'advanced-pixel-editor'),
                'replacing'          => __('Replacing...', 'advanced-pixel-editor'),
                'restoring'          => __('Restoring...', 'advanced-pixel-editor'),
            ]
        ];

        $localize_data = apply_filters('advaimg_editor_localize_data', $localize_data);
        wp_localize_script('advaimg-editor-js', 'ADVAIMG_AJAX', $localize_data);
    }

    /**
     * Render the main plugin page with tabs
     */
    public function render_page() {
        if (!current_user_can('upload_files')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'advanced-pixel-editor'));
        }

        $current_tab = $this->get_current_tab();

        // Settings tab requires manage_options
        if ($current_tab === 'settings' && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'advanced-pixel-editor'));
        }

        $base_url = admin_url('upload.php?page=' . self::MENU_SLUG);

        $tabs = [
            'editor'   => __('Editor', 'advanced-pixel-editor'),
            'settings' => __('Settings', 'advanced-pixel-editor'),
            'about'    => __('About', 'advanced-pixel-editor'),
        ];

        // Only show Settings tab to users who can manage options
        if (!current_user_can('manage_options')) {
            unset($tabs['settings']);
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Advanced Pixel Editor', 'advanced-pixel-editor'); ?></h1>

            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $slug => $label): ?>
                    <a href="<?php echo esc_url(add_query_arg('tab', $slug, $base_url)); ?>"
                       class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="aie-tab-content">
                <?php
                switch ($current_tab) {
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'about':
                        $this->render_about_tab();
                        break;
                    default:
                        $this->render_editor_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Editor tab content
     */
    private function render_editor_tab() {
        // Check if Imagick is available
        if (!extension_loaded('imagick') && !class_exists('Imagick')) {
            ?>
            <div class="notice notice-error" style="margin-top: 15px;">
                <p>
                    <strong><?php esc_html_e('Missing Required Extension: Imagick', 'advanced-pixel-editor'); ?></strong>
                </p>
                <p>
                    <?php esc_html_e('The Advanced Pixel Editor requires the Imagick PHP extension to function properly.', 'advanced-pixel-editor'); ?>
                </p>

                <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #856404; margin: 15px 0;">
                    <h4 style="margin-top: 0; color: #856404;">
                        <?php esc_html_e('How to Enable Imagick', 'advanced-pixel-editor'); ?>
                    </h4>
                    <ol style="margin-bottom: 10px;">
                        <li><?php esc_html_e('Contact your web hosting provider', 'advanced-pixel-editor'); ?></li>
                        <li><?php esc_html_e('Request that they enable the Imagick PHP extension', 'advanced-pixel-editor'); ?></li>
                        <li><?php esc_html_e('Most hosting providers can enable this quickly (usually within hours)', 'advanced-pixel-editor'); ?></li>
                    </ol>
                </div>

                <p>
                    <?php
                    printf(
                        /* translators: %s: URL to the About tab */
                        wp_kses(
                            __('Visit the <a href="%s">About tab</a> for more information about ImageMagick.', 'advanced-pixel-editor'),
                            ['a' => ['href' => []]]
                        ),
                        esc_url(admin_url('upload.php?page=' . self::MENU_SLUG . '&tab=about'))
                    );
                    ?>
                </p>
            </div>
            <?php
            return;
        }

        include ADVAIMG_PLUGIN_DIR . 'editor-page.php';
    }

    /**
     * Render the Settings tab content
     */
    private function render_settings_tab() {
        $pro_active = class_exists('Advanced_Pixel_Editor_Pro');

        /**
         * Pro plugin hooks here to render license form + active feature grid.
         * When hooked, it takes over the Pro Features section entirely.
         */
        do_action('advaimg_settings_page');

        if (!$pro_active):
        ?>
            <!-- Pro Features (dimmed — Pro not installed) -->
            <div class="aie-pro-admin-container" style="max-width: 900px;">
                <div style="margin: 24px 0; padding: 20px; background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                    <h2 style="margin-top: 0; padding-bottom: 8px; border-bottom: 1px solid #eee;">
                        <?php esc_html_e('Pro Features', 'advanced-pixel-editor'); ?>
                    </h2>
                    <p style="color: #50575e;">
                        <?php esc_html_e('Upgrade to Advanced Pixel Editor Pro to unlock professional image editing features.', 'advanced-pixel-editor'); ?>
                    </p>

                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; opacity: 0.6;">
                        <?php
                        $pro_features = [
                            __('Batch Processing', 'advanced-pixel-editor')   => __('Process multiple images simultaneously with progress tracking.', 'advanced-pixel-editor'),
                            __('Advanced Filters', 'advanced-pixel-editor')   => __('Brightness, saturation, hue, sepia, vignette, blur, noise reduction, and more.', 'advanced-pixel-editor'),
                            __('Crop & Resize', 'advanced-pixel-editor')      => __('Interactive crop with aspect presets, resize with aspect lock, DPI control.', 'advanced-pixel-editor'),
                            __('Watermarking', 'advanced-pixel-editor')       => __('Text and image watermarks with positioning, opacity, rotation, and tiling.', 'advanced-pixel-editor'),
                            __('Priority Support', 'advanced-pixel-editor')   => __('Get priority technical support and feature requests.', 'advanced-pixel-editor'),
                        ];
                        foreach ($pro_features as $label => $description):
                        ?>
                            <div style="padding: 16px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px;">
                                <h3 style="margin: 0 0 8px; font-size: 1em;"><?php echo esc_html($label); ?></h3>
                                <p style="margin: 0 0 8px; color: #50575e; font-size: 13px;"><?php echo esc_html($description); ?></p>
                                <span style="display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 12px; background: #dcdcde; color: #50575e;">
                                    <?php esc_html_e('Pro', 'advanced-pixel-editor'); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <p style="margin-top: 20px; text-align: center;">
                        <a href="https://prowoos.com/shop/web-development/plugins/advanced-pixel-editor-pro/" target="_blank" rel="noopener noreferrer" class="button button-primary button-hero">
                            <?php esc_html_e('Get Advanced Pixel Editor Pro', 'advanced-pixel-editor'); ?>
                        </a>
                    </p>
                </div>
            </div>
        <?php endif;
    }

    /**
     * Render the About tab content
     */
    private function render_about_tab() {
        $imagick_available = extension_loaded('imagick') || class_exists('Imagick');
        $imagick_version = '';
        if ($imagick_available && class_exists('Imagick')) {
            $version_info = \Imagick::getVersion();
            if (isset($version_info['versionString'])) {
                $imagick_version = $version_info['versionString'];
            }
        }

        ?>
        <!-- Plugin Info -->
        <div class="aie-about-section">
            <h2><?php esc_html_e('Advanced Pixel Editor', 'advanced-pixel-editor'); ?></h2>
            <p>
                <?php esc_html_e('A professional image editor for WordPress powered by ImageMagick. Edit, enhance, and transform your media library images with precision controls — right inside your dashboard.', 'advanced-pixel-editor'); ?>
            </p>
            <table class="aie-about-info-table">
                <tr>
                    <th><?php esc_html_e('Version', 'advanced-pixel-editor'); ?></th>
                    <td><?php echo esc_html(ADVAIMG_VERSION); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Author', 'advanced-pixel-editor'); ?></th>
                    <td>Rafael Minuesa</td>
                </tr>
            </table>
            <p class="aie-about-links">
                <a href="https://wordpress.org/plugins/advanced-pixel-editor/" target="_blank" rel="noopener noreferrer" class="button">
                    <?php esc_html_e('WordPress.org', 'advanced-pixel-editor'); ?>
                </a>
                <a href="https://github.com/rafael-minuesa/advanced-image-editor" target="_blank" rel="noopener noreferrer" class="button">
                    <?php esc_html_e('GitHub', 'advanced-pixel-editor'); ?>
                </a>
            </p>
        </div>

        <!-- About ImageMagick -->
        <div class="aie-about-section">
            <h2><?php esc_html_e('About ImageMagick', 'advanced-pixel-editor'); ?></h2>

            <div class="aie-imagick-status <?php echo $imagick_available ? 'available' : 'unavailable'; ?>">
                <span class="aie-imagick-badge">
                    <?php echo $imagick_available
                        ? esc_html__('Imagick Installed', 'advanced-pixel-editor')
                        : esc_html__('Imagick Not Installed', 'advanced-pixel-editor'); ?>
                </span>
                <?php if ($imagick_version): ?>
                    <span class="aie-imagick-version"><?php echo esc_html($imagick_version); ?></span>
                <?php endif; ?>
            </div>

            <p>
                <?php esc_html_e('Imagick is a powerful PHP extension that provides advanced image processing capabilities. It\'s based on the ImageMagick library, which has been a cornerstone of professional image manipulation since the early 1990s.', 'advanced-pixel-editor'); ?>
            </p>
            <ul>
                <li><?php esc_html_e('Enables high-quality image editing and manipulation', 'advanced-pixel-editor'); ?></li>
                <li><?php esc_html_e('Supports all major image formats (JPEG, PNG, GIF, WebP, TIFF, etc.)', 'advanced-pixel-editor'); ?></li>
                <li><?php esc_html_e('Provides professional-grade filters and effects', 'advanced-pixel-editor'); ?></li>
                <li><?php esc_html_e('Used by thousands of websites and professional applications worldwide', 'advanced-pixel-editor'); ?></li>
            </ul>

            <?php if (!$imagick_available): ?>
                <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #856404; margin: 15px 0;">
                    <h4 style="margin-top: 0; color: #856404;">
                        <?php esc_html_e('How to Enable Imagick', 'advanced-pixel-editor'); ?>
                    </h4>
                    <ol style="margin-bottom: 10px;">
                        <li><?php esc_html_e('Contact your web hosting provider', 'advanced-pixel-editor'); ?></li>
                        <li><?php esc_html_e('Request that they enable the Imagick PHP extension', 'advanced-pixel-editor'); ?></li>
                        <li><?php esc_html_e('Most hosting providers can enable this quickly (usually within hours)', 'advanced-pixel-editor'); ?></li>
                        <li><?php esc_html_e('If using shared hosting, you may need to upgrade to a VPS or dedicated server', 'advanced-pixel-editor'); ?></li>
                    </ol>
                    <p style="margin-bottom: 0;">
                        <em><?php esc_html_e('Note: Imagick is extremely common and should be available on most modern hosting platforms.', 'advanced-pixel-editor'); ?></em>
                    </p>
                </div>
            <?php endif; ?>

            <div class="aie-about-opensource">
                <h3><?php esc_html_e('Support Open Source Software', 'advanced-pixel-editor'); ?></h3>
                <p>
                    <?php esc_html_e('ImageMagick is free, open-source software that powers millions of websites and applications worldwide. Consider supporting their important work:', 'advanced-pixel-editor'); ?>
                </p>
                <p>
                    <a href="https://imagemagick.org/support/#support" target="_blank" rel="noopener noreferrer" class="button button-primary">
                        <?php esc_html_e('Sponsor ImageMagick Development', 'advanced-pixel-editor'); ?>
                    </a>
                </p>
            </div>
        </div>

        <!-- Support & Resources -->
        <div class="aie-about-section">
            <h2><?php esc_html_e('Support & Resources', 'advanced-pixel-editor'); ?></h2>
            <ul class="aie-about-resources">
                <li>
                    <a href="https://prowoos.com/plugins-reviews/advanced-pixel-editor/" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Documentation', 'advanced-pixel-editor'); ?>
                    </a>
                    — <?php esc_html_e('plugin review.', 'advanced-pixel-editor'); ?>
                </li>
                <li>
                    <a href="https://wordpress.org/support/plugin/advanced-pixel-editor/" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('WordPress.org Support Forum', 'advanced-pixel-editor'); ?>
                    </a>
                    — <?php esc_html_e('Community support for the free plugin.', 'advanced-pixel-editor'); ?>
                </li>
                <li>
                    <a href="https://prowoos.com/shop/web-development/plugins/advanced-pixel-editor-pro/" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Advanced Pixel Editor Pro', 'advanced-pixel-editor'); ?>
                    </a>
                    — <?php esc_html_e('Unlock batch processing, advanced filters, watermarking, and more.', 'advanced-pixel-editor'); ?>
                </li>
            </ul>
        </div>
        <?php
    }
}
