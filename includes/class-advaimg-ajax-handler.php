<?php
/**
 * AJAX Handler class for Advanced Pixel Editor
 *
 * Handles all AJAX requests for image processing
 *
 * @package AdvancedImageEditor
 * @author Rafael Minuesa
 * @license GPL-2.0+
 * @link https://github.com/rafael-minuesa/advanced-pixel-editor
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADVAIMG_Ajax_Handler {

    /**
     * Canvas size after rotate and flip for the last processed frame.
     *
     * @var array{0:int,1:int}|null
     */
    private $last_canvas = null;

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
     * Normalize the request values used by the processing pipeline.
     *
     * Unknown keys are retained for add-ons using advaimg_after_process.
     *
     * @param array $request Request data.
     * @return array
     */
    private function get_processing_data(array $request) {
        $data = wp_unslash($request);

        $data['contrast']  = max(-1, min(1, isset($data['contrast']) ? (float) $data['contrast'] : 0));
        $data['amount']    = max(0, min(5, isset($data['amount']) ? (float) $data['amount'] : 0));
        $data['radius']    = max(0, min(5, isset($data['radius']) ? (float) $data['radius'] : 1));
        $data['threshold'] = max(0, min(1, isset($data['threshold']) ? (float) $data['threshold'] : 0));

        return $data;
    }

    /**
     * Validate source and planned output sizes without decoding pixel data.
     *
     * @param string $path      Source image path.
     * @param array  $post_data Normalized processing data.
     * @return array|WP_Error Source information on success.
     */
    private function validate_processing_request($path, array $post_data) {
        if (!class_exists('Imagick')) {
            return new WP_Error('imagick_missing', __('The Imagick PHP extension is required.', 'advanced-pixel-editor'));
        }

        if (!$path || !is_file($path) || !is_readable($path)) {
            return new WP_Error('file_missing', __('Image file not found on server.', 'advanced-pixel-editor'));
        }

        $file_size = filesize($path);
        if (false === $file_size || $file_size > Advanced_Pixel_Editor::MAX_FILE_SIZE) {
            return new WP_Error('file_too_large', __('Image file is too large to process.', 'advanced-pixel-editor'));
        }

        $probe = null;

        try {
            $probe = new Imagick();
            $probe->pingImage($path);

            $frame_count = max(1, $probe->getNumberImages());
            if ($frame_count > Advanced_Pixel_Editor::MAX_IMAGE_FRAMES) {
                return new WP_Error(
                    'too_many_frames',
                    sprintf(
                        /* translators: %d: Maximum number of frames. */
                        __('Images cannot contain more than %d frames.', 'advanced-pixel-editor'),
                        Advanced_Pixel_Editor::MAX_IMAGE_FRAMES
                    )
                );
            }

            $transform          = new ADVAIMG_Transform();
            $source_pixels      = 0;
            $output_pixels      = 0;
            $first_width        = 0;
            $first_height       = 0;
            $probe->setFirstIterator();
            $original_format = strtoupper($probe->getImageFormat());
            $animated_format = in_array($original_format, ['GIF', 'WEBP'], true);

            foreach ($probe as $index => $frame) {
                $width  = $frame->getImageWidth();
                $height = $frame->getImageHeight();

                // Coalescing expands optimized animation frames to the logical canvas.
                if ($animated_format) {
                    $page   = $frame->getImagePage();
                    $width  = max($width, !empty($page['width']) ? (int) $page['width'] : 0);
                    $height = max($height, !empty($page['height']) ? (int) $page['height'] : 0);
                }

                if (0 === $index) {
                    $first_width     = $width;
                    $first_height    = $height;
                }

                if (
                    $width <= 0 ||
                    $height <= 0 ||
                    $width > Advanced_Pixel_Editor::MAX_IMAGE_WIDTH ||
                    $height > Advanced_Pixel_Editor::MAX_IMAGE_HEIGHT
                ) {
                    return new WP_Error(
                        'source_dimensions_exceeded',
                        sprintf(
                            /* translators: 1: Current image width, 2: Current image height, 3: Maximum width, 4: Maximum height. */
                            __('Image dimensions (%1$dx%2$d) exceed the processing limit (%3$dx%4$d).', 'advanced-pixel-editor'),
                            $width,
                            $height,
                            Advanced_Pixel_Editor::MAX_IMAGE_WIDTH,
                            Advanced_Pixel_Editor::MAX_IMAGE_HEIGHT
                        )
                    );
                }

                $source_pixels += $width * $height;

                $resolution = $frame->getImageResolution();
                [$output_width, $output_height] = $transform->calculate_output_dimensions(
                    $width,
                    $height,
                    !empty($resolution['x']) ? (float) $resolution['x'] : 72,
                    $post_data
                );
                $output_pixels += $output_width * $output_height;

                if (
                    $source_pixels > Advanced_Pixel_Editor::MAX_TOTAL_IMAGE_PIXELS ||
                    $output_pixels > Advanced_Pixel_Editor::MAX_TOTAL_IMAGE_PIXELS
                ) {
                    return new WP_Error(
                        'total_pixels_exceeded',
                        __('The image contains too many pixels to process safely.', 'advanced-pixel-editor')
                    );
                }
            }

            // Imagick pixel caches live outside the PHP heap; this heuristic
            // guards the PHP-side work (blob, clone, base64) and is sized from
            // the source, as before. Output canvases are bounded separately by
            // calculate_output_dimensions().
            $estimated_memory = $source_pixels * 4 * 3;
            $memory_limit     = $this->get_memory_limit_bytes();
            $available_memory = PHP_INT_MAX === $memory_limit
                ? PHP_INT_MAX
                : max(0, $memory_limit - memory_get_usage(true));

            if ($estimated_memory > $available_memory) {
                return new WP_Error(
                    'memory_limit_exceeded',
                    __('Image processing would exceed the available memory limit.', 'advanced-pixel-editor')
                );
            }

            $mime_type = advanced_image_editor_get_mime_type_from_format($original_format);
            if ('' === $mime_type) {
                return new WP_Error('unsupported_format', __('This image format is not supported.', 'advanced-pixel-editor'));
            }

            return [
                'file_size'       => $file_size,
                'width'           => $first_width,
                'height'          => $first_height,
                'frame_count'     => $frame_count,
                'original_format' => $original_format,
                'mime_type'       => $mime_type,
            ];
        } catch (Throwable $error) {
            return new WP_Error('invalid_image', $error->getMessage());
        } finally {
            if ($probe instanceof Imagick) {
                $probe->clear();
            }
        }
    }

    /**
     * Apply filters and transforms to one frame.
     *
     * @param Imagick $frame         Frame to process.
     * @param int     $attachment_id Attachment ID.
     * @param array   $post_data     Normalized processing data.
     * @return Imagick
     * @throws UnexpectedValueException If an add-on returns an invalid value.
     */
    private function process_frame(Imagick $frame, $attachment_id, array $post_data) {
        $contrast  = $post_data['contrast'];
        $amount    = $post_data['amount'];
        $radius    = $post_data['radius'];
        $threshold = $post_data['threshold'];

        if (abs($contrast) > 0.001) {
            $quantum  = $frame->getQuantumRange();
            $midpoint = $quantum['quantumRangeLong'] * 0.5;
            $strength = abs($contrast) * 10;
            $frame->sigmoidalContrastImage(($contrast > 0), $strength, $midpoint);
        }

        if ($amount > 0 && $radius > 0) {
            $frame->unsharpMaskImage($radius, 1, $amount, $threshold);
        }

        $transform = new ADVAIMG_Transform();
        $frame     = $transform->process($frame, $post_data);
        $this->last_canvas = $transform->get_last_canvas();
        $frame     = apply_filters('advaimg_after_process', $frame, $attachment_id, $post_data);

        if (!$frame instanceof Imagick) {
            throw new UnexpectedValueException(__('An image-processing extension returned an invalid result.', 'advanced-pixel-editor'));
        }

        return $frame;
    }

    /**
     * Process every image frame while preserving animated sequences.
     *
     * @param string $path          Source image path.
     * @param int    $attachment_id Attachment ID.
     * @param array  $post_data     Normalized processing data.
     * @param array  $source_info   Validated source information.
     * @return Imagick
     */
    private function process_image($path, $attachment_id, array $post_data, array $source_info) {
        $image       = new Imagick($path);
        $frame_count = $source_info['frame_count'];
        $format      = $source_info['original_format'];
        $animated    = $frame_count > 1 && in_array($format, ['GIF', 'WEBP'], true);

        if ($animated) {
            $coalesced = $image->coalesceImages();
            $image->clear();
            $image = $coalesced;
        }

        if ($frame_count > 1) {
            foreach ($image as $frame) {
                $processed = $this->process_frame($frame, $attachment_id, $post_data);
                if ($processed !== $frame) {
                    $frame->setImage($processed);
                }
                $frame->setImageFormat($format);
            }
            $image->setFirstIterator();

            if ($animated) {
                $optimized = $image->deconstructImages();
                $image->clear();
                $image = $optimized;
            }
        } else {
            $image = $this->process_frame($image, $attachment_id, $post_data);
            $image->setImageFormat($format);
        }

        return $image;
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

        // Check per-attachment permission
        if (!current_user_can('edit_post', $attachment_id)) {
            wp_send_json_error(__('You do not have permission to edit this image.', 'advanced-pixel-editor'));
        }

        // Check if attachment exists
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(__('Invalid image attachment.', 'advanced-pixel-editor'));
        }

        $path          = get_attached_file($attachment_id);
        $post_data     = $this->get_processing_data($_POST);
        $source_info   = $this->validate_processing_request($path, $post_data);

        if (is_wp_error($source_info)) {
            wp_send_json_error($source_info->get_error_message());
        }

        $img = null;
        try {
            $img = $this->process_image($path, $attachment_id, $post_data, $source_info);

            // Preview the first frame as JPEG; saving still preserves all frames.
            $preview_img = clone $img;
            $preview_img->setFirstIterator();
            $preview_img->setImageFormat('jpeg');
            $preview_img->setImageCompressionQuality(Advanced_Pixel_Editor::PREVIEW_QUALITY);
            $preview_blob = $preview_img->getImageBlob();
            if ('' === $preview_blob) {
                throw new RuntimeException(__('ImageMagick returned an empty preview.', 'advanced-pixel-editor'));
            }
            $preview_base64 = base64_encode($preview_blob);
            $preview_img->clear();

            $canvas = $this->last_canvas;

            wp_send_json_success([
                'preview' => 'data:image/jpeg;base64,' . $preview_base64,
                'original_format' => $source_info['original_format'],
                'mime_type' => $source_info['mime_type'],
                'canvas_width' => $canvas ? $canvas[0] : 0,
                'canvas_height' => $canvas ? $canvas[1] : 0
            ]);

        } catch (Throwable $e) {
            $this->log_error(
                'Preview processing failed',
                [
                    'image_id' => $attachment_id,
                    'error' => $e->getMessage(),
                    'file_size' => $source_info['file_size'],
                    'dimensions' => [$source_info['width'], $source_info['height']]
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
     * AJAX handler for saving edited image.
     *
     * Re-processes the original file with the submitted filter parameters
     * and saves in the original format (preserves PNG transparency, WebP, etc.).
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

        $attachment_id = absint($_POST['image_id']);

        // Check per-attachment permission
        if (!current_user_can('edit_post', $attachment_id)) {
            wp_send_json_error(__('You do not have permission to edit this image.', 'advanced-pixel-editor'));
        }

        // Verify attachment exists
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(__('Invalid image attachment.', 'advanced-pixel-editor'));
        }

        $path        = get_attached_file($attachment_id);
        $post_data   = $this->get_processing_data($_POST);
        $source_info = $this->validate_processing_request($path, $post_data);

        if (is_wp_error($source_info)) {
            wp_send_json_error($source_info->get_error_message());
        }

        $img = null;
        try {
            $img = $this->process_image($path, $attachment_id, $post_data, $source_info);

            $decoded = $source_info['frame_count'] > 1
                ? $img->getImagesBlob()
                : $img->getImageBlob();

            if ('' === $decoded) {
                throw new RuntimeException(__('ImageMagick returned an empty image.', 'advanced-pixel-editor'));
            }

            $mime_type = $source_info['mime_type'];
        } catch (Throwable $e) {
            $this->log_save_error('Save processing failed: ' . $e->getMessage(), $attachment_id);
            wp_send_json_error(
                sprintf(
                    /* translators: %s: Error message from image processing */
                    __('Image processing failed: %s', 'advanced-pixel-editor'),
                    $e->getMessage()
                )
            );
        } finally {
            if ($img instanceof Imagick) {
                $img->clear();
            }
        }

        $save_mode       = isset($_POST['save_mode']) ? sanitize_key(wp_unslash($_POST['save_mode'])) : 'new';
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
        if ('' === $extension) {
            wp_send_json_error(__('This image format cannot be saved as a new attachment.', 'advanced-pixel-editor'));
        }

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
     * Create a validated temporary image beside the destination file.
     *
     * @param string $target_path  Destination path used for directory and permissions.
     * @param string $contents     Encoded image data.
     * @param string $expected_mime Expected image MIME type.
     * @return string|WP_Error Temporary path on success.
     */
    private function create_temporary_image($target_path, $contents, $expected_mime) {
        $directory = pathinfo($target_path, PATHINFO_DIRNAME);
        $temp_path = wp_tempnam(pathinfo($target_path, PATHINFO_BASENAME), $directory);

        if (!$temp_path) {
            return new WP_Error('temp_file_failed', __('Failed to create a temporary image file.', 'advanced-pixel-editor'));
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing validated binary image data.
        $written = file_put_contents($temp_path, $contents, LOCK_EX);
        if (strlen($contents) !== $written) {
            wp_delete_file($temp_path);
            return new WP_Error('temp_write_failed', __('Failed to write the complete edited image.', 'advanced-pixel-editor'));
        }

        $probe = null;
        try {
            $probe = new Imagick();
            $probe->pingImage($temp_path);
            $actual_mime = advanced_image_editor_get_mime_type_from_format($probe->getImageFormat());
            if ($actual_mime !== $expected_mime) {
                wp_delete_file($temp_path);
                return new WP_Error('temp_mime_mismatch', __('The generated image format is invalid.', 'advanced-pixel-editor'));
            }
        } catch (Throwable $error) {
            wp_delete_file($temp_path);
            return new WP_Error('temp_image_invalid', __('The generated image could not be validated.', 'advanced-pixel-editor'));
        } finally {
            if ($probe instanceof Imagick) {
                $probe->clear();
            }
        }

        $permissions = fileperms($target_path);
        if (false !== $permissions) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Preserve the attachment's existing permissions.
            chmod($temp_path, $permissions & 0777);
        }

        return $temp_path;
    }

    /**
     * Create a validated temporary copy beside the destination file.
     *
     * @param string $source_path   Image to copy.
     * @param string $target_path   Destination path used for directory and permissions.
     * @param string $expected_mime Expected image MIME type.
     * @return string|WP_Error Temporary path on success.
     */
    private function create_temporary_image_copy($source_path, $target_path, $expected_mime) {
        $contents = file_get_contents($source_path);
        if (false === $contents) {
            return new WP_Error('source_read_failed', __('Failed to read the replacement image.', 'advanced-pixel-editor'));
        }

        return $this->create_temporary_image($target_path, $contents, $expected_mime);
    }

    /**
     * Move a file over an existing destination while retaining rollback safety.
     *
     * @param string $source_path Source path.
     * @param string $target_path Existing destination path.
     * @return bool
     */
    private function move_over_existing_file($source_path, $target_path) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Same-filesystem rename provides an atomic replacement on supported platforms.
        if (@rename($source_path, $target_path)) {
            return true;
        }

        $directory          = pathinfo($target_path, PATHINFO_DIRNAME);
        $displaced_filename = wp_unique_filename($directory, 'advaimg-displaced-' . pathinfo($target_path, PATHINFO_BASENAME));
        $displaced_path     = trailingslashit($directory) . $displaced_filename;

        // Some platforms cannot rename directly over an existing file.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
        if (!@rename($target_path, $displaced_path)) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
        if (@rename($source_path, $target_path)) {
            wp_delete_file($displaced_path);
            return true;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
        @rename($displaced_path, $target_path);
        return false;
    }

    /**
     * Delete old sub-sizes that are not part of the regenerated metadata.
     *
     * @param string $original_path Original attachment path.
     * @param array  $old_meta      Previous attachment metadata.
     * @param array  $new_meta      Regenerated attachment metadata.
     * @return void
     */
    private function delete_obsolete_subsizes($original_path, $old_meta, $new_meta) {
        if (empty($old_meta['sizes']) || !is_array($old_meta['sizes'])) {
            return;
        }

        $new_files = [];
        if (!empty($new_meta['sizes']) && is_array($new_meta['sizes'])) {
            foreach ($new_meta['sizes'] as $size_data) {
                if (!empty($size_data['file'])) {
                    $new_files[] = basename($size_data['file']);
                }
            }
        }

        $directory = pathinfo($original_path, PATHINFO_DIRNAME);
        foreach ($old_meta['sizes'] as $size_data) {
            if (empty($size_data['file'])) {
                continue;
            }

            $filename = basename($size_data['file']);
            if (!in_array($filename, $new_files, true)) {
                $thumbnail_path = trailingslashit($directory) . $filename;
                if (is_file($thumbnail_path)) {
                    wp_delete_file($thumbnail_path);
                }
            }
        }
    }

    /**
     * Restore the original attachment file after a failed replacement.
     *
     * Regenerating the metadata also repairs any sub-sizes that may have been
     * overwritten before the replacement failure was detected.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $rollback_path Validated temporary copy of the original.
     * @param array  $old_meta      Previous attachment metadata.
     * @return bool Whether the original full-size file was restored.
     */
    private function restore_attachment_file($attachment_id, $rollback_path, array $old_meta) {
        $original_path = get_attached_file($attachment_id);

        if (!$this->move_over_existing_file($rollback_path, $original_path)) {
            return false;
        }

        $restored_meta = wp_generate_attachment_metadata($attachment_id, $original_path);
        wp_update_attachment_metadata(
            $attachment_id,
            is_array($restored_meta) && !empty($restored_meta['width']) ? $restored_meta : $old_meta
        );

        return true;
    }

    /**
     * Replace an attachment file and regenerate metadata with rollback support.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $replacement_path Validated temporary replacement.
     * @return array|WP_Error Regenerated metadata on success.
     */
    private function replace_attachment_file($attachment_id, $replacement_path) {
        $original_path = get_attached_file($attachment_id);
        $old_meta      = wp_get_attachment_metadata($attachment_id);
        $old_meta      = is_array($old_meta) ? $old_meta : [];
        $mime_type     = get_post_mime_type($attachment_id);
        $rollback_path = $this->create_temporary_image_copy($original_path, $original_path, $mime_type);

        if (is_wp_error($rollback_path)) {
            wp_delete_file($replacement_path);
            return $rollback_path;
        }

        if (!$this->move_over_existing_file($replacement_path, $original_path)) {
            wp_delete_file($replacement_path);
            wp_delete_file($rollback_path);
            return new WP_Error('replace_failed', __('Failed to replace the image file safely.', 'advanced-pixel-editor'));
        }

        $new_meta = wp_generate_attachment_metadata($attachment_id, $original_path);
        if (!is_array($new_meta) || empty($new_meta['width']) || empty($new_meta['height'])) {
            if (!$this->restore_attachment_file($attachment_id, $rollback_path, $old_meta)) {
                return new WP_Error('rollback_failed', __('Image metadata generation failed and the original file could not be restored automatically.', 'advanced-pixel-editor'));
            }

            return new WP_Error('metadata_failed', __('Failed to regenerate image metadata; the original file was restored.', 'advanced-pixel-editor'));
        }

        $metadata_updated = wp_update_attachment_metadata($attachment_id, $new_meta);
        if (false === $metadata_updated && wp_get_attachment_metadata($attachment_id) !== $new_meta) {
            if (!$this->restore_attachment_file($attachment_id, $rollback_path, $old_meta)) {
                return new WP_Error('rollback_failed', __('Image metadata update failed and the original file could not be restored automatically.', 'advanced-pixel-editor'));
            }

            return new WP_Error('metadata_update_failed', __('Failed to update image metadata; the original file was restored.', 'advanced-pixel-editor'));
        }

        $this->delete_obsolete_subsizes($original_path, $old_meta, $new_meta);
        wp_delete_file($rollback_path);

        return $new_meta;
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

        if (!$original_path || !is_file($original_path)) {
            wp_send_json_error(__('Original image file not found.', 'advanced-pixel-editor'));
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Create backup if one doesn't already exist
        $backup_sizes = get_post_meta($attachment_id, '_wp_attachment_backup_sizes', true);
        if (!is_array($backup_sizes)) {
            $backup_sizes = [];
        }

        $created_backup_path = '';

        if (!isset($backup_sizes['full-orig'])) {
            $dir             = pathinfo($original_path, PATHINFO_DIRNAME);
            $name            = pathinfo($original_path, PATHINFO_FILENAME);
            $ext             = pathinfo($original_path, PATHINFO_EXTENSION);
            $proposed_name   = $name . '-old.' . $ext;
            $backup_filename = wp_unique_filename($dir, $proposed_name);
            $backup_path     = trailingslashit($dir) . $backup_filename;

            if (!copy($original_path, $backup_path)) {
                wp_send_json_error(__('Failed to create backup of original image.', 'advanced-pixel-editor'));
            }

            $created_backup_path = $backup_path;
            $metadata            = wp_get_attachment_metadata($attachment_id);
            $backup_sizes['full-orig'] = [
                'file'     => $backup_filename,
                'width'    => !empty($metadata['width']) ? (int) $metadata['width'] : 0,
                'height'   => !empty($metadata['height']) ? (int) $metadata['height'] : 0,
                'filesize' => filesize($original_path),
            ];

            $updated = update_post_meta($attachment_id, '_wp_attachment_backup_sizes', $backup_sizes);
            if (false === $updated && get_post_meta($attachment_id, '_wp_attachment_backup_sizes', true) !== $backup_sizes) {
                wp_delete_file($backup_path);
                wp_send_json_error(__('Failed to record the original-image backup.', 'advanced-pixel-editor'));
            }
        } else {
            $backup_filename = basename($backup_sizes['full-orig']['file']);
            $backup_path     = trailingslashit(pathinfo($original_path, PATHINFO_DIRNAME)) . $backup_filename;
            if (!is_file($backup_path)) {
                wp_send_json_error(__('The recorded original-image backup is missing.', 'advanced-pixel-editor'));
            }
        }

        $replacement_path = $this->create_temporary_image($original_path, $decoded, $mime_type);
        if (is_wp_error($replacement_path)) {
            if ($created_backup_path) {
                wp_delete_file($created_backup_path);
                unset($backup_sizes['full-orig']);
                if (empty($backup_sizes)) {
                    delete_post_meta($attachment_id, '_wp_attachment_backup_sizes');
                } else {
                    update_post_meta($attachment_id, '_wp_attachment_backup_sizes', $backup_sizes);
                }
            }
            wp_send_json_error($replacement_path->get_error_message());
        }

        $result = $this->replace_attachment_file($attachment_id, $replacement_path);
        if (is_wp_error($result)) {
            if ($created_backup_path) {
                wp_delete_file($created_backup_path);
                unset($backup_sizes['full-orig']);
                if (empty($backup_sizes)) {
                    delete_post_meta($attachment_id, '_wp_attachment_backup_sizes');
                } else {
                    update_post_meta($attachment_id, '_wp_attachment_backup_sizes', $backup_sizes);
                }
            }
            wp_send_json_error($result->get_error_message());
        }

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

        // Check per-attachment permission
        if (!current_user_can('edit_post', $attachment_id)) {
            wp_send_json_error(__('You do not have permission to edit this image.', 'advanced-pixel-editor'));
        }

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

        // Check per-attachment permission
        if (!current_user_can('edit_post', $attachment_id)) {
            wp_send_json_error(__('You do not have permission to edit this image.', 'advanced-pixel-editor'));
        }

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

        $dir             = pathinfo($original_path, PATHINFO_DIRNAME);
        $backup_filename = basename($backup_sizes['full-orig']['file']);
        $backup_path     = trailingslashit($dir) . $backup_filename;

        // Verify backup file exists on disk
        if (!file_exists($backup_path)) {
            wp_send_json_error(__('Backup file not found on disk.', 'advanced-pixel-editor'));
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $replacement_path = $this->create_temporary_image_copy(
            $backup_path,
            $original_path,
            get_post_mime_type($attachment_id)
        );
        if (is_wp_error($replacement_path)) {
            wp_send_json_error($replacement_path->get_error_message());
        }

        $result = $this->replace_attachment_file($attachment_id, $replacement_path);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Remove every backup recorded in the metadata only after restore succeeds.
        foreach ($backup_sizes as $backup_data) {
            if (!empty($backup_data['file'])) {
                $recorded_backup_path = trailingslashit($dir) . basename($backup_data['file']);
                if (is_file($recorded_backup_path)) {
                    wp_delete_file($recorded_backup_path);
                }
            }
        }
        delete_post_meta($attachment_id, '_wp_attachment_backup_sizes');

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
        $memory_limit = trim((string) ini_get('memory_limit'));

        if ('-1' === $memory_limit) {
            return PHP_INT_MAX;
        }

        if (ctype_digit($memory_limit)) {
            return (int) $memory_limit;
        }

        if (preg_match('/^(\d+)([GgMmKk])$/', $memory_limit, $matches)) {
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

        if (defined('WP_DEBUG') && WP_DEBUG) {
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

}
