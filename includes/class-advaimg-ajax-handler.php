<?php
/**
 * AJAX Handler class for Advanced Pixel Editor
 *
 * Handles all AJAX requests for image processing
 *
 * @package AdvancedImageEditor
 * @author Rafael Minuesa
 * @license GPL-2.0+
 * @link https://github.com/rafael-minuesa/advanced-image-editor
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADVAIMG_Ajax_Handler {

    /**
     * Constructor - Register AJAX hooks
     */
    public function __construct() {
        add_action('wp_ajax_advaimg_preview', [$this, 'ajax_preview']);
        add_action('wp_ajax_advaimg_save', [$this, 'ajax_save']);
        add_action('wp_ajax_advaimg_get_original', [$this, 'ajax_get_original']);
        add_action('wp_ajax_advaimg_restore', [$this, 'ajax_restore']);
    }

    /**
     * AJAX handler for previewing image filters
     */
    public function ajax_preview() {
        // Check user capability
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'advanced-pixel-editor'));
        }

        // Check rate limiting
        if ($this->check_rate_limit('preview')) {
            wp_send_json_error(__('Too many requests. Please wait a moment before trying again.', 'advanced-pixel-editor'));
        }

        // Validate nonce
        $nonce = isset($_POST['_ajax_nonce']) ? sanitize_key(wp_unslash($_POST['_ajax_nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'advaimg_nonce')) {
            wp_send_json_error(__('Security check failed.', 'advanced-pixel-editor'));
        }

        // Validate required parameters
        if (!isset($_POST['image_id']) || empty($_POST['image_id'])) {
            wp_send_json_error(__('No image selected.', 'advanced-pixel-editor'));
        }

        $attachment_id = absint($_POST['image_id']);
        $contrast      = isset($_POST['contrast']) ? floatval($_POST['contrast']) : 0;
        $amount        = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $radius        = isset($_POST['radius']) ? floatval($_POST['radius']) : 1;
        $threshold     = isset($_POST['threshold']) ? floatval($_POST['threshold']) : 0;

        // Validate parameter ranges
        $contrast = max(-1, min(1, $contrast)); // Clamp between -1 and 1
        $amount = max(0, min(5, $amount)); // Clamp between 0 and 5
        $radius = max(0, min(5, $radius)); // Clamp between 0 and 5
        $threshold = max(0, min(1, $threshold)); // Clamp between 0 and 1

        // Check if attachment exists
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(__('Invalid image attachment.', 'advanced-pixel-editor'));
        }

        $path = get_attached_file($attachment_id);

        if (!file_exists($path)) {
            wp_send_json_error(__("Image file not found on server.", 'advanced-pixel-editor'));
        }

        // Check file size
        $file_size = filesize($path);
        if ($file_size === false || $file_size > Advanced_Pixel_Editor::MAX_FILE_SIZE) {
            wp_send_json_error(__('Image file is too large to process.', 'advanced-pixel-editor'));
        }

        // Check image dimensions
        $image_info = @getimagesize($path);
        if ($image_info === false) {
            wp_send_json_error(__('Unable to read image dimensions.', 'advanced-pixel-editor'));
        }

        $width = $image_info[0];
        $height = $image_info[1];

        if ($width > Advanced_Pixel_Editor::MAX_IMAGE_WIDTH || $height > Advanced_Pixel_Editor::MAX_IMAGE_HEIGHT) {
            wp_send_json_error(
                sprintf(
            /* translators: 1: Current image width, 2: Current image height, 3: Maximum allowed width, 4: Maximum allowed height */
            __('Image dimensions (%1$dx%2$d) exceed maximum allowed size (%3$dx%4$d).', 'advanced-pixel-editor'),
                    $width, $height, Advanced_Pixel_Editor::MAX_IMAGE_WIDTH, Advanced_Pixel_Editor::MAX_IMAGE_HEIGHT
                )
            );
        }

        // Estimate memory usage (rough calculation: width * height * 4 bytes per pixel * 3 for processing)
        $estimated_memory = $width * $height * 4 * 3;
        $memory_limit = $this->get_memory_limit_bytes();

        if ($estimated_memory > $memory_limit) {
            wp_send_json_error(__('Image is too large to process with current memory limits.', 'advanced-pixel-editor'));
        }

        $img = null;
        try {
            $img = new Imagick($path);

            // Store original format for output
            $original_format = $img->getImageFormat();

            // Apply Contrast
            if ($contrast !== 0) {
                // Convert to correct range for contrastImage (boolean parameter)
                // Positive contrast = enhance, negative = reduce
                $img->contrastImage($contrast > 0);
            }

            // Apply Unsharp Mask
            if ($amount > 0 && $radius > 0) {
                $img->unsharpMaskImage($radius, 1, $amount, $threshold);
            }

            // Create preview in JPEG format for display (but keep original for saving)
            $preview_img = clone $img;
            $preview_img->setImageFormat('jpeg');
            $preview_img->setImageCompressionQuality(Advanced_Pixel_Editor::PREVIEW_QUALITY);
            $preview_blob = $preview_img->getImageBlob();
            $preview_base64 = base64_encode($preview_blob);
            $preview_img->clear();

            wp_send_json_success([
                'preview' => 'data:image/jpeg;base64,' . $preview_base64,
                'original_format' => $original_format,
                'mime_type' => advanced_image_editor_get_mime_type_from_format($original_format)
            ]);

        } catch (Exception $e) {
            $this->log_error(
                'Preview processing failed',
                [
                    'image_id' => $attachment_id,
                    'error' => $e->getMessage(),
                    'file_size' => $file_size ?? 0,
                    'dimensions' => [$width ?? 0, $height ?? 0]
                ]
            );

            wp_send_json_error(
                sprintf(
            /* translators: %s: Error message from image processing */
            __('Image processing failed: %s', 'advanced-pixel-editor'),
                    $e->getMessage()
                )
            );
        } finally {
            // Clean up Imagick resource
            if ($img instanceof Imagick) {
                $img->clear();
            }
        }
    }

    /**
     * AJAX handler for saving edited image
     */
    public function ajax_save() {
        // Check user capability
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'advanced-pixel-editor'));
        }

        // Check rate limiting (stricter for save operations)
        if ($this->check_rate_limit('save')) {
            wp_send_json_error(__('Too many save requests. Please wait a moment before trying again.', 'advanced-pixel-editor'));
        }

        // Validate nonce
        $nonce = isset($_POST['_ajax_nonce']) ? sanitize_key(wp_unslash($_POST['_ajax_nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'advaimg_nonce')) {
            wp_send_json_error(__('Security check failed.', 'advanced-pixel-editor'));
        }

        // Validate required parameters
        if (!isset($_POST['image_id']) || empty($_POST['image_id'])) {
            wp_send_json_error(__('No image selected.', 'advanced-pixel-editor'));
        }

        if (!isset($_POST['image_data']) || empty($_POST['image_data'])) {
            wp_send_json_error(__('No image data provided.', 'advanced-pixel-editor'));
        }

        $attachment_id = absint($_POST['image_id']);

        // Sanitize and validate base64 image data
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_base64_image_data method
        $raw_image_data = isset($_POST['image_data']) ? wp_unslash($_POST['image_data']) : '';
        $sanitized = $this->sanitize_base64_image_data($raw_image_data);

        if (is_wp_error($sanitized)) {
            $this->log_save_error(
                'Image data validation failed: ' . $sanitized->get_error_message(),
                $attachment_id,
                ['error_code' => $sanitized->get_error_code()]
            );
            wp_send_json_error($sanitized->get_error_message());
        }

        $mime_type = $sanitized['mime_type'];
        $decoded = $sanitized['decoded'];

        // Verify attachment exists
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(__('Invalid image attachment.', 'advanced-pixel-editor'));
        }

        $save_mode = isset($_POST['save_mode']) ? sanitize_key($_POST['save_mode']) : 'new';
        $custom_filename = isset($_POST['filename']) ? sanitize_file_name(wp_unslash($_POST['filename'])) : '';

        if ($save_mode === 'replace') {
            $this->save_replace($attachment_id, $decoded, $mime_type);
        } else {
            $this->save_as_new($attachment_id, $decoded, $mime_type, $custom_filename);
        }
    }

    /**
     * Save edited image as a new attachment
     *
     * @param int    $attachment_id Original attachment ID
     * @param string $decoded       Decoded image data
     * @param string $mime_type     MIME type of the image
     * @param string $custom_filename Custom filename (without extension)
     */
    private function save_as_new($attachment_id, $decoded, $mime_type, $custom_filename) {
        // Get original image for filename reference
        $original_path = get_attached_file($attachment_id);
        $original_info = pathinfo($original_path);
        $original_name = $original_info['filename'];

        $extension = advanced_image_editor_get_extension_from_mime_type($mime_type);

        // Use custom filename if provided, otherwise auto-generate
        if (!empty($custom_filename)) {
            $base_filename = $custom_filename;
        } else {
            $base_filename = $original_name . '-edited-' . time();
        }

        // Create new file with unique name
        $upload_dir = wp_upload_dir();
        if ($upload_dir['error'] !== false) {
            wp_send_json_error(__('Failed to access upload directory.', 'advanced-pixel-editor'));
        }

        $filename = sanitize_file_name($base_filename . '.' . $extension);
        $filename = wp_unique_filename($upload_dir['path'], $filename);

        // Use wp_upload_bits for better WordPress integration
        $upload = wp_upload_bits($filename, null, $decoded);

        if ($upload['error']) {
            /* translators: %s: Upload error message */
            wp_send_json_error(sprintf(__('Failed to save image file: %s', 'advanced-pixel-editor'), $upload['error']));
        }

        $file_path = $upload['file'];

        // Prepare attachment data
        $attachment = [
            'post_mime_type' => $mime_type,
            'post_title'     => sanitize_text_field($base_filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_excerpt'   => __('Edited with Advanced Pixel Editor', 'advanced-pixel-editor'),
            'post_parent'    => 0,
        ];

        // Insert attachment
        $new_id = wp_insert_attachment($attachment, $file_path);

        if (is_wp_error($new_id)) {
            wp_delete_file($file_path);

            $this->log_save_error(
                'Failed to insert attachment',
                $attachment_id,
                ['wp_error' => $new_id->get_error_data()]
            );

            wp_send_json_error($new_id->get_error_message());
        }

        // Generate metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($new_id, $file_path);
        wp_update_attachment_metadata($new_id, $metadata);

        // Get edit link for the new attachment
        $edit_link = get_edit_post_link($new_id, 'raw');

        wp_send_json_success([
            'new_attachment_id' => $new_id,
            'message' => __('Image saved successfully!', 'advanced-pixel-editor'),
            'edit_link' => $edit_link ?: admin_url('post.php?post=' . $new_id . '&action=edit')
        ]);
    }

    /**
     * Replace the original image with the edited version
     *
     * @param int    $attachment_id Original attachment ID
     * @param string $decoded       Decoded image data
     * @param string $mime_type     MIME type of the image
     */
    private function save_replace($attachment_id, $decoded, $mime_type) {
        $original_path = get_attached_file($attachment_id);

        if (!file_exists($original_path)) {
            wp_send_json_error(__('Original image file not found.', 'advanced-pixel-editor'));
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Get current metadata for backup
        $meta = wp_get_attachment_metadata($attachment_id);

        // Create backup if one doesn't already exist
        $backup_sizes = get_post_meta($attachment_id, '_wp_attachment_backup_sizes', true);
        if (!is_array($backup_sizes)) {
            $backup_sizes = [];
        }

        if (!isset($backup_sizes['full-orig'])) {
            $dir = pathinfo($original_path, PATHINFO_DIRNAME);
            $name = pathinfo($original_path, PATHINFO_FILENAME);
            $ext = pathinfo($original_path, PATHINFO_EXTENSION);
            $backup_filename = $name . '-old.' . $ext;
            $backup_path = trailingslashit($dir) . $backup_filename;

            if (!copy($original_path, $backup_path)) {
                wp_send_json_error(__('Failed to create backup of original image.', 'advanced-pixel-editor'));
            }

            $original_size = @getimagesize($original_path);
            $backup_sizes['full-orig'] = [
                'file'     => $backup_filename,
                'width'    => $original_size ? $original_size[0] : 0,
                'height'   => $original_size ? $original_size[1] : 0,
                'filesize' => filesize($original_path),
            ];
            update_post_meta($attachment_id, '_wp_attachment_backup_sizes', $backup_sizes);
        }

        // Delete old thumbnails
        if (!empty($meta['sizes'])) {
            $dir = pathinfo($original_path, PATHINFO_DIRNAME);
            foreach ($meta['sizes'] as $size_data) {
                $thumb_path = trailingslashit($dir) . $size_data['file'];
                if (file_exists($thumb_path)) {
                    wp_delete_file($thumb_path);
                }
            }
        }

        // Write edited image to original path
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing binary image data
        if (file_put_contents($original_path, $decoded) === false) {
            wp_send_json_error(__('Failed to write edited image file.', 'advanced-pixel-editor'));
        }

        // Regenerate metadata (thumbnails, dimensions, etc.)
        $new_meta = wp_generate_attachment_metadata($attachment_id, $original_path);
        wp_update_attachment_metadata($attachment_id, $new_meta);

        $edit_link = get_edit_post_link($attachment_id, 'raw');

        wp_send_json_success([
            'attachment_id' => $attachment_id,
            'message' => __('Original image replaced successfully!', 'advanced-pixel-editor'),
            'edit_link' => $edit_link ?: admin_url('post.php?post=' . $attachment_id . '&action=edit')
        ]);
    }

    /**
     * AJAX handler for getting original image URL
     */
    public function ajax_get_original() {
        // Check user capability
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'advanced-pixel-editor'));
        }

        // Validate nonce
        $nonce = isset($_POST['_ajax_nonce']) ? sanitize_key(wp_unslash($_POST['_ajax_nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'advaimg_nonce')) {
            wp_send_json_error(__('Security check failed.', 'advanced-pixel-editor'));
        }

        // Validate required parameters
        if (!isset($_POST['image_id']) || empty($_POST['image_id'])) {
            wp_send_json_error(__('No image selected.', 'advanced-pixel-editor'));
        }

        $attachment_id = absint($_POST['image_id']);

        // Check if attachment exists
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(__('Invalid image attachment.', 'advanced-pixel-editor'));
        }

        // Get the full-size image URL
        $image_url = wp_get_attachment_image_url($attachment_id, 'full');

        if (!$image_url) {
            wp_send_json_error(__('Unable to get image URL.', 'advanced-pixel-editor'));
        }

        // Check if this image has a backup
        $backup_sizes = get_post_meta($attachment_id, '_wp_attachment_backup_sizes', true);
        $has_backup = is_array($backup_sizes) && isset($backup_sizes['full-orig']);

        wp_send_json_success([
            'original_url' => $image_url,
            'has_backup'   => $has_backup,
        ]);
    }

    /**
     * AJAX handler for restoring the original image from backup
     */
    public function ajax_restore() {
        // Check user capability
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'advanced-pixel-editor'));
        }

        // Check rate limiting
        if ($this->check_rate_limit('save')) {
            wp_send_json_error(__('Too many requests. Please wait a moment before trying again.', 'advanced-pixel-editor'));
        }

        // Validate nonce
        $nonce = isset($_POST['_ajax_nonce']) ? sanitize_key(wp_unslash($_POST['_ajax_nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'advaimg_nonce')) {
            wp_send_json_error(__('Security check failed.', 'advanced-pixel-editor'));
        }

        // Validate required parameters
        if (!isset($_POST['image_id']) || empty($_POST['image_id'])) {
            wp_send_json_error(__('No image selected.', 'advanced-pixel-editor'));
        }

        $attachment_id = absint($_POST['image_id']);

        // Check if attachment exists
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(__('Invalid image attachment.', 'advanced-pixel-editor'));
        }

        // Get backup meta
        $backup_sizes = get_post_meta($attachment_id, '_wp_attachment_backup_sizes', true);
        if (!is_array($backup_sizes) || !isset($backup_sizes['full-orig'])) {
            wp_send_json_error(__('No backup found for this image.', 'advanced-pixel-editor'));
        }

        $original_path = get_attached_file($attachment_id);
        if (!$original_path) {
            wp_send_json_error(__('Original image file not found.', 'advanced-pixel-editor'));
        }

        $dir = pathinfo($original_path, PATHINFO_DIRNAME);
        $backup_filename = $backup_sizes['full-orig']['file'];
        $backup_path = trailingslashit($dir) . $backup_filename;

        // Verify backup file exists on disk
        if (!file_exists($backup_path)) {
            wp_send_json_error(__('Backup file not found on disk.', 'advanced-pixel-editor'));
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Delete current thumbnails
        $meta = wp_get_attachment_metadata($attachment_id);
        if (!empty($meta['sizes'])) {
            foreach ($meta['sizes'] as $size_data) {
                $thumb_path = trailingslashit($dir) . $size_data['file'];
                if (file_exists($thumb_path)) {
                    wp_delete_file($thumb_path);
                }
            }
        }

        // Copy backup file over current file
        if (!copy($backup_path, $original_path)) {
            wp_send_json_error(__('Failed to restore backup file.', 'advanced-pixel-editor'));
        }

        // Delete backup file
        wp_delete_file($backup_path);

        // Delete backup meta
        delete_post_meta($attachment_id, '_wp_attachment_backup_sizes');

        // Regenerate metadata
        $new_meta = wp_generate_attachment_metadata($attachment_id, $original_path);
        wp_update_attachment_metadata($attachment_id, $new_meta);

        // Get the restored image URL for preview refresh
        $image_url = wp_get_attachment_image_url($attachment_id, 'full');

        wp_send_json_success([
            'message'      => __('Original image restored successfully.', 'advanced-pixel-editor'),
            'original_url' => $image_url,
        ]);
    }

    /**
     * Check rate limiting for AJAX requests
     *
     * @param string $action Action name for rate limiting
     * @return bool True if rate limit exceeded
     */
    private function check_rate_limit($action = 'general') {
        $user_id = get_current_user_id();
        $ip = $this->get_client_ip();

        // Use IP + user ID as identifier for rate limiting
        $identifier = md5($ip . '_' . $user_id . '_' . $action);
        $transient_key = 'advaimg_rate_limit_' . $identifier;

        $requests = get_transient($transient_key);

        if ($requests === false) {
            // First request in window
            set_transient($transient_key, 1, Advanced_Pixel_Editor::RATE_LIMIT_WINDOW);
            return false;
        }

        if ($requests >= Advanced_Pixel_Editor::RATE_LIMIT_REQUESTS) {
            // Rate limit exceeded
            $this->log_error(
                'Rate limit exceeded',
                [
                    'action' => $action,
                    'requests' => $requests,
                    'ip' => $ip,
                    'user_id' => $user_id
                ],
                'warning'
            );
            return true;
        }

        // Increment counter
        set_transient($transient_key, $requests + 1, Advanced_Pixel_Editor::RATE_LIMIT_WINDOW);
        return false;
    }

    /**
     * Get memory limit in bytes
     *
     * @return int Memory limit in bytes
     */
    private function get_memory_limit_bytes() {
        $memory_limit = ini_get('memory_limit');

        if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];

            switch (strtoupper($unit)) {
                case 'G':
                    $value *= 1024 * 1024 * 1024;
                    break;
                case 'M':
                    $value *= 1024 * 1024;
                    break;
                case 'K':
                    $value *= 1024;
                    break;
            }

            return $value;
        }

        return 134217728; // Default 128MB if parsing fails
    }

    /**
     * Log errors with context for debugging
     *
     * @param string $message Error message
     * @param array $context Additional context data
     * @param string $level Log level (error, warning, info)
     */
    private function log_error($message, $context = [], $level = 'error') {
        $log_message = sprintf(
            '[Advanced Pixel Editor] %s - User: %s, IP: %s',
            $message,
            get_current_user_id(),
            $this->get_client_ip()
        );

        if (!empty($context)) {
            $log_message .= ' - Context: ' . wp_json_encode($context);
        }

        if (function_exists('wp_log_error')) {
            // WordPress 6.5+ has wp_log_error function
            wp_log_error($log_message);
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Only used in debug mode
            error_log($log_message);
        }
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Log save operation errors
     *
     * @param string $message Error message
     * @param int $attachment_id Original attachment ID
     * @param array $context Additional context
     */
    private function log_save_error($message, $attachment_id, $context = []) {
        $this->log_error(
            $message,
            array_merge([
                'original_image_id' => $attachment_id,
                'action' => 'save_edited_image'
            ], $context)
        );
    }

    /**
     * Sanitize and validate base64 image data
     *
     * Base64 image data cannot be sanitized with standard WordPress functions
     * as they would corrupt the data. Instead, we validate the format strictly
     * and ensure only valid base64 characters are present.
     *
     * @param string $data Raw base64 image data from POST
     * @return array|WP_Error Array with 'mime_type' and 'base64' on success, WP_Error on failure
     */
    private function sanitize_base64_image_data($data) {
        // Allowed image MIME types
        $allowed_types = [
            'jpeg' => 'image/jpeg',
            'jpg'  => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
        ];

        // Validate data URI format and extract components
        if (!preg_match('/^data:image\/([a-z]+);base64,([A-Za-z0-9+\/=]+)$/', $data, $matches)) {
            return new WP_Error('invalid_format', __('Invalid image data format.', 'advanced-pixel-editor'));
        }

        $image_type = strtolower($matches[1]);
        $base64_data = $matches[2];

        // Validate image type against whitelist
        if (!isset($allowed_types[$image_type])) {
            return new WP_Error('invalid_type', __('Unsupported image type.', 'advanced-pixel-editor'));
        }

        // Validate base64 string length (sanity check - max 50MB encoded)
        if (strlen($base64_data) > 50 * 1024 * 1024 * 1.37) {
            return new WP_Error('too_large', __('Image data is too large.', 'advanced-pixel-editor'));
        }

        // Decode and validate
        $decoded = base64_decode($base64_data, true);
        if ($decoded === false) {
            return new WP_Error('decode_failed', __('Failed to decode image data.', 'advanced-pixel-editor'));
        }

        // Verify decoded data is actually an image
        $image_info = @getimagesizefromstring($decoded);
        if ($image_info === false) {
            return new WP_Error('not_image', __('Decoded data is not a valid image.', 'advanced-pixel-editor'));
        }

        // Verify MIME type matches
        $detected_mime = $image_info['mime'];
        $expected_mime = $allowed_types[$image_type];

        // Allow jpeg/jpg flexibility
        $jpeg_mimes = ['image/jpeg', 'image/jpg'];
        $mime_matches = ($detected_mime === $expected_mime) ||
                        (in_array($detected_mime, $jpeg_mimes, true) && in_array($expected_mime, $jpeg_mimes, true));

        if (!$mime_matches) {
            return new WP_Error('mime_mismatch', __('Image type does not match data.', 'advanced-pixel-editor'));
        }

        return [
            'mime_type' => $detected_mime,
            'base64'    => $base64_data,
            'decoded'   => $decoded,
        ];
    }
}