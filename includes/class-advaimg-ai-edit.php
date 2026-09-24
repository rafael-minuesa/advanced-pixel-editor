<?php
/**
 * AI Edit for Advanced Pixel Editor
 *
 * Sends the image being edited plus a text prompt to the AI provider the
 * site owner connected under Settings > Connectors (WordPress 7.0 AI
 * Client). The result is kept as a private working file in uploads and
 * used as the editor's source until it is saved, discarded or expires.
 *
 * @package AdvancedImageEditor
 * @author Rafael Minuesa
 * @license GPL-2.0+
 * @link https://github.com/rafael-minuesa/advanced-pixel-editor
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADVAIMG_AI_Edit {

    /**
     * Maximum prompt length in characters.
     */
    const PROMPT_MAX_LENGTH = 1000;

    /**
     * Longest edge of the image sent to the provider, in pixels.
     */
    const INPUT_MAX_EDGE = 2048;

    /**
     * Maximum size of a result downloaded from a provider URL, in bytes.
     */
    const RESULT_MAX_BYTES = 26214400;

    /**
     * How long an unsaved AI result is kept, in seconds.
     */
    const RESULT_TTL = DAY_IN_SECONDS;

    /**
     * AI requests allowed per user per minute.
     */
    const RATE_LIMIT_REQUESTS = 6;

    /**
     * Folder inside uploads that holds unsaved AI results.
     */
    const RESULT_DIR = 'advaimg-ai';

    /**
     * POST field carrying the active AI result token.
     */
    const SOURCE_FIELD = 'advaimg_ai_source';

    /**
     * Post meta recording the AI edit on a saved image.
     */
    const META_KEY = '_advaimg_ai_edit';

    /**
     * Cron hook that deletes expired AI results.
     */
    const CLEANUP_HOOK = 'advaimg_ai_cleanup';

    /**
     * Constructor - Register hooks
     */
    public function __construct() {
        add_action('wp_ajax_advaimg_ai_edit', [$this, 'ajax_generate']);
        add_action('wp_ajax_advaimg_ai_discard', [$this, 'ajax_discard']);

        add_filter('advaimg_source_path', [$this, 'filter_source_path'], 10, 2);
        add_action('advaimg_image_saved', [$this, 'record_saved_edit'], 10, 2);
        add_action('advaimg_image_restored', [$this, 'forget_restored_edit']);

        add_action(self::CLEANUP_HOOK, [__CLASS__, 'delete_expired_results']);
        add_action('init', [$this, 'schedule_cleanup']);
        add_action('admin_init', [$this, 'add_privacy_policy_content']);
    }

    /**
     * Whether this WordPress install has the AI Client and allows AI.
     *
     * @return bool
     */
    public static function is_available() {
        return function_exists('wp_ai_client_prompt')
            && function_exists('wp_supports_ai')
            && wp_supports_ai();
    }

    /**
     * URL of the screen where AI providers are connected.
     *
     * @return string
     */
    public static function get_connectors_url() {
        return admin_url('options-connectors.php');
    }

    /**
     * Schedule the daily cleanup of expired AI results.
     */
    public function schedule_cleanup() {
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
    }

    /**
     * Remove the cleanup event. Called on plugin deactivation.
     */
    public static function unschedule_cleanup() {
        wp_clear_scheduled_hook(self::CLEANUP_HOOK);
    }

    /**
     * Suggest privacy policy text for the site owner.
     */
    public function add_privacy_policy_content() {
        if (!function_exists('wp_add_privacy_policy_content') || !self::is_available()) {
            return;
        }

        wp_add_privacy_policy_content(
            __('Advanced Pixel Editor', 'advanced-pixel-editor'),
            '<p>' . esc_html__('When a user runs AI Edit, the image being edited and the text prompt are sent to the AI provider connected under Settings > Connectors, and the provider returns the edited image. The prompt, the provider and the model name are stored with the saved image.', 'advanced-pixel-editor') . '</p>'
        );
    }

    /**
     * AJAX handler that sends the image and prompt to the AI provider.
     */
    public function ajax_generate() {
        $attachment_id = $this->verify_request();

        if (!self::is_available()) {
            wp_send_json_error(__('AI Edit requires WordPress 7.0 or newer with AI features enabled.', 'advanced-pixel-editor'));
        }

        if ($this->check_rate_limit()) {
            wp_send_json_error(__('Too many AI requests. Please wait a minute before trying again.', 'advanced-pixel-editor'));
        }

        $prompt = isset($_POST['prompt']) ? trim(sanitize_textarea_field(wp_unslash($_POST['prompt']))) : '';
        if ('' === $prompt) {
            wp_send_json_error(__('Describe the change you want first.', 'advanced-pixel-editor'));
        }
        if ($this->string_length($prompt) > self::PROMPT_MAX_LENGTH) {
            wp_send_json_error(
                sprintf(
                    /* translators: %d: Maximum number of characters. */
                    __('The prompt cannot be longer than %d characters.', 'advanced-pixel-editor'),
                    self::PROMPT_MAX_LENGTH
                )
            );
        }

        // A previous AI result for this image, when the user refines it.
        $base = $this->get_result_from_request($attachment_id, $_POST);
        if (is_wp_error($base)) {
            wp_send_json_error($base->get_error_message());
        }

        $original_path = get_attached_file($attachment_id);
        $original      = $this->inspect_image($original_path);
        if (is_wp_error($original)) {
            wp_send_json_error($original->get_error_message());
        }
        if ($original['frames'] > 1) {
            wp_send_json_error(__('AI Edit does not support animated or multi-page images.', 'advanced-pixel-editor'));
        }

        $input_path = $base ? $base['path'] : $original_path;
        $input      = $this->prepare_input($input_path);
        if (is_wp_error($input)) {
            wp_send_json_error($input->get_error_message());
        }

        // Image models can take well over a minute to answer.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Disabled on some hosts.
        }
        add_filter('wp_ai_client_default_request_timeout', [$this, 'filter_request_timeout']);

        $generated = $this->request_edit($prompt, $input);

        remove_filter('wp_ai_client_default_request_timeout', [$this, 'filter_request_timeout']);

        if (is_wp_error($generated)) {
            $data = $generated->get_error_data();
            wp_send_json_error([
                'message'        => $generated->get_error_message(),
                'connectors_url' => is_array($data) && !empty($data['connectors']) ? self::get_connectors_url() : '',
            ]);
        }

        $stored = $this->store_result($generated['blob'], $original['format']);
        if (is_wp_error($stored)) {
            wp_send_json_error($stored->get_error_message());
        }

        $prompts   = $base ? $base['record']['prompts'] : [];
        $prompts[] = $prompt;

        set_transient(
            $this->transient_key($stored['token']),
            [
                'user_id'       => get_current_user_id(),
                'attachment_id' => $attachment_id,
                'file'          => $stored['file'],
                'prompts'       => $prompts,
                'provider'      => $generated['provider'],
                'model'         => $generated['model'],
                'created'       => time(),
            ],
            self::RESULT_TTL
        );

        // The refined result replaces the one it was made from.
        if ($base) {
            $this->delete_result($base['token'], $base['record']);
        }

        wp_send_json_success([
            'token'    => $stored['token'],
            'preview'  => $stored['preview'],
            'width'    => $stored['width'],
            'height'   => $stored['height'],
            'prompts'  => $prompts,
            'provider' => $generated['provider'],
            'model'    => $generated['model'],
        ]);
    }

    /**
     * AJAX handler that deletes an unsaved AI result.
     */
    public function ajax_discard() {
        $attachment_id = $this->verify_request();

        $result = $this->get_result_from_request($attachment_id, $_POST);
        if (is_wp_error($result)) {
            // Already expired or gone, which is what the user wanted.
            wp_send_json_success();
        }

        if ($result) {
            $this->delete_result($result['token'], $result['record']);
        }

        wp_send_json_success();
    }

    /**
     * Point the editor's preview and save pipeline at the active AI result.
     *
     * @param string|WP_Error $path          Source image path.
     * @param int             $attachment_id Attachment ID.
     * @return string|WP_Error
     */
    public function filter_source_path($path, $attachment_id) {
        if (is_wp_error($path)) {
            return $path;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The calling AJAX handler verified the nonce.
        $result = $this->get_result_from_request($attachment_id, $_POST);
        if (is_wp_error($result)) {
            return $result;
        }

        return $result ? $result['path'] : $path;
    }

    /**
     * Record the prompt, provider and model on a saved AI-edited image.
     *
     * @param int $saved_id      Attachment that received the image.
     * @param int $attachment_id Attachment that was being edited.
     */
    public function record_saved_edit($saved_id, $attachment_id) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The calling AJAX handler verified the nonce.
        $result = $this->get_result_from_request($attachment_id, $_POST);
        if (!$result || is_wp_error($result)) {
            return;
        }

        update_post_meta($saved_id, self::META_KEY, [
            'prompts'  => $result['record']['prompts'],
            'provider' => $result['record']['provider'],
            'model'    => $result['record']['model'],
            'date'     => gmdate('c'),
        ]);
    }

    /**
     * Drop the AI record once the original file is restored.
     *
     * @param int $attachment_id Attachment ID.
     */
    public function forget_restored_edit($attachment_id) {
        delete_post_meta($attachment_id, self::META_KEY);
    }

    /**
     * Give the provider request more time than the AI Client default.
     *
     * @return int Timeout in seconds.
     */
    public function filter_request_timeout() {
        return 240;
    }

    /**
     * Delete AI results older than the retention period.
     */
    public static function delete_expired_results() {
        $dir = self::get_result_dir();
        if (!$dir || !is_dir($dir)) {
            return;
        }

        $files = glob(trailingslashit($dir) . '*');
        if (!$files) {
            return;
        }

        $cutoff = time() - self::RESULT_TTL;
        foreach ($files as $file) {
            if ('index.php' === basename($file) || !is_file($file)) {
                continue;
            }
            $modified = filemtime($file);
            if (false !== $modified && $modified < $cutoff) {
                wp_delete_file($file);
            }
        }
    }

    /**
     * Run the shared capability, nonce and attachment checks.
     *
     * Ends the request with a JSON error when a check fails.
     *
     * @return int Attachment ID.
     */
    private function verify_request() {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'advanced-pixel-editor'));
        }

        $nonce = isset($_POST['_ajax_nonce']) ? sanitize_key(wp_unslash($_POST['_ajax_nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'advaimg_nonce')) {
            wp_send_json_error(__('Security check failed.', 'advanced-pixel-editor'));
        }

        $attachment_id = isset($_POST['image_id']) ? absint($_POST['image_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error(__('No image selected.', 'advanced-pixel-editor'));
        }

        if (!current_user_can('edit_post', $attachment_id)) {
            wp_send_json_error(__('You do not have permission to edit this image.', 'advanced-pixel-editor'));
        }

        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(__('Invalid image attachment.', 'advanced-pixel-editor'));
        }

        return $attachment_id;
    }

    /**
     * Look up the AI result named in the request.
     *
     * @param int   $attachment_id Attachment the result must belong to.
     * @param array $request       Request data.
     * @return array|null|WP_Error Null when the request names no result.
     */
    private function get_result_from_request($attachment_id, array $request) {
        if (empty($request[self::SOURCE_FIELD])) {
            return null;
        }

        $token = sanitize_key(wp_unslash($request[self::SOURCE_FIELD]));
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return new WP_Error('ai_source_invalid', __('The AI result reference is invalid.', 'advanced-pixel-editor'));
        }

        $record = get_transient($this->transient_key($token));
        $dir    = self::get_result_dir();
        $path   = is_array($record) && $dir ? trailingslashit($dir) . basename($record['file']) : '';

        if (
            !is_array($record) ||
            (int) $record['user_id'] !== get_current_user_id() ||
            (int) $record['attachment_id'] !== (int) $attachment_id ||
            !is_file($path)
        ) {
            return new WP_Error('ai_source_expired', __('The AI result has expired. Discard it and run AI Edit again.', 'advanced-pixel-editor'));
        }

        return [
            'token'  => $token,
            'record' => $record,
            'path'   => $path,
        ];
    }

    /**
     * Read format and frame count without decoding pixels.
     *
     * @param string $path Image path.
     * @return array|WP_Error
     */
    private function inspect_image($path) {
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

        $probe = new Imagick();
        try {
            $probe->pingImage($path);
            $format = strtoupper($probe->getImageFormat());

            if ('' === advanced_image_editor_get_mime_type_from_format($format)) {
                return new WP_Error('unsupported_format', __('This image format is not supported.', 'advanced-pixel-editor'));
            }

            return [
                'format' => $format,
                'frames' => max(1, $probe->getNumberImages()),
            ];
        } catch (Throwable $error) {
            return new WP_Error('invalid_image', $error->getMessage());
        } finally {
            $probe->clear();
        }
    }

    /**
     * Encode the source image the way image models accept it.
     *
     * The first frame is scaled to fit INPUT_MAX_EDGE and sent as PNG when it
     * has transparency, otherwise as JPEG.
     *
     * @param string $path Source image path.
     * @return string|WP_Error Data URI.
     */
    private function prepare_input($path) {
        $info = $this->inspect_image($path);
        if (is_wp_error($info)) {
            return $info;
        }

        $image = new Imagick();
        try {
            $image->readImage($path . '[0]');

            $width  = $image->getImageWidth();
            $height = $image->getImageHeight();
            if (
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

            if (max($width, $height) > self::INPUT_MAX_EDGE) {
                $image->thumbnailImage(self::INPUT_MAX_EDGE, self::INPUT_MAX_EDGE, true);
            }

            if ($image->getImageAlphaChannel()) {
                $image->setImageFormat('png');
                $mime = 'image/png';
            } else {
                $image->setImageFormat('jpeg');
                $image->setImageCompressionQuality(92);
                $mime = 'image/jpeg';
            }

            $blob = $image->getImageBlob();
            if ('' === $blob) {
                return new WP_Error('input_encode_failed', __('The image could not be prepared for AI Edit.', 'advanced-pixel-editor'));
            }

            return 'data:' . $mime . ';base64,' . base64_encode($blob);
        } catch (Throwable $error) {
            return new WP_Error('input_encode_failed', $error->getMessage());
        } finally {
            $image->clear();
        }
    }

    /**
     * Send the prompt and image through the WordPress AI Client.
     *
     * @param string $prompt   Edit instruction.
     * @param string $data_uri Source image as a data URI.
     * @return array|WP_Error Result bytes, provider name and model ID.
     */
    private function request_edit($prompt, $data_uri) {
        try {
            $file    = new \WordPress\AiClient\Files\DTO\File($data_uri);
            $builder = wp_ai_client_prompt()
                ->with_text($prompt)
                ->with_file($file)
                ->as_output_file_type(\WordPress\AiClient\Files\Enums\FileTypeEnum::inline());

            if (!$builder->is_supported_for_image_generation()) {
                return new WP_Error(
                    'ai_not_configured',
                    __('No connected AI provider can edit images. Connect OpenAI or Google under Settings > Connectors.', 'advanced-pixel-editor'),
                    ['connectors' => true]
                );
            }

            $result = $builder->generate_image_result();
            if (is_wp_error($result)) {
                return new WP_Error(
                    'ai_request_failed',
                    sprintf(
                        /* translators: %s: Error message from the AI provider. */
                        __('The AI provider returned an error: %s', 'advanced-pixel-editor'),
                        $result->get_error_message()
                    )
                );
            }

            $image = $result->toImageFile();
            $blob  = $this->read_result_file($image);
            if (is_wp_error($blob)) {
                return $blob;
            }

            return [
                'blob'     => $blob,
                'provider' => $result->getProviderMetadata()->getName(),
                'model'    => $result->getModelMetadata()->getId(),
            ];
        } catch (Throwable $error) {
            return new WP_Error(
                'ai_request_failed',
                sprintf(
                    /* translators: %s: Error message from the AI provider. */
                    __('The AI provider returned an error: %s', 'advanced-pixel-editor'),
                    $error->getMessage()
                )
            );
        }
    }

    /**
     * Get the bytes of the image a provider returned.
     *
     * @param \WordPress\AiClient\Files\DTO\File $file Result file.
     * @return string|WP_Error
     */
    private function read_result_file($file) {
        if ($file->isInline()) {
            $blob = base64_decode((string) $file->getBase64Data(), true);
        } else {
            $response = wp_safe_remote_get(
                (string) $file->getUrl(),
                [
                    'timeout'             => 60,
                    'limit_response_size' => self::RESULT_MAX_BYTES,
                ]
            );
            $blob = is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)
                ? false
                : wp_remote_retrieve_body($response);
        }

        if (false === $blob || '' === $blob) {
            return new WP_Error('ai_result_missing', __('The AI provider did not return an image.', 'advanced-pixel-editor'));
        }

        if (strlen($blob) > self::RESULT_MAX_BYTES) {
            return new WP_Error('ai_result_too_large', __('The AI result is too large to process.', 'advanced-pixel-editor'));
        }

        return $blob;
    }

    /**
     * Validate the AI result, convert it to the original's format and keep it.
     *
     * Keeping the original format lets Replace Original write the result
     * into the attachment's existing file name and MIME type.
     *
     * @param string $blob   Result image bytes.
     * @param string $format Imagick format of the original attachment.
     * @return array|WP_Error Token, file name, preview data URI and size.
     */
    private function store_result($blob, $format) {
        $dir = self::get_result_dir();
        if (!$dir || !wp_mkdir_p($dir)) {
            return new WP_Error('ai_dir_failed', __('Failed to create the AI results folder.', 'advanced-pixel-editor'));
        }

        $index = trailingslashit($dir) . 'index.php';
        if (!is_file($index)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Directory listing guard.
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }

        $mime      = advanced_image_editor_get_mime_type_from_format($format);
        $extension = advanced_image_editor_get_extension_from_mime_type($mime);
        if ('' === $extension) {
            return new WP_Error('unsupported_format', __('This image format is not supported.', 'advanced-pixel-editor'));
        }

        $image   = new Imagick();
        $preview = null;
        try {
            $image->readImageBlob($blob);
            $image->setFirstIterator();

            $width  = $image->getImageWidth();
            $height = $image->getImageHeight();
            if (
                $width <= 0 ||
                $height <= 0 ||
                $width > Advanced_Pixel_Editor::MAX_IMAGE_WIDTH ||
                $height > Advanced_Pixel_Editor::MAX_IMAGE_HEIGHT
            ) {
                return new WP_Error('ai_result_invalid', __('The AI result has unsupported dimensions.', 'advanced-pixel-editor'));
            }

            if ('image/jpeg' === $mime && $image->getImageAlphaChannel()) {
                $image->setImageBackgroundColor('white');
                $flattened = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                $image->clear();
                $image = $flattened;
            }

            $image->setImageFormat($format);
            if ('image/jpeg' === $mime || 'image/webp' === $mime) {
                $image->setImageCompressionQuality(92);
            }

            $token = bin2hex(random_bytes(16));
            $file  = $token . '.' . $extension;
            $path  = trailingslashit($dir) . $file;

            if (!$image->writeImage($path)) {
                return new WP_Error('ai_write_failed', __('Failed to store the AI result.', 'advanced-pixel-editor'));
            }

            $preview = clone $image;
            $preview->setImageFormat('jpeg');
            $preview->setImageCompressionQuality(Advanced_Pixel_Editor::PREVIEW_QUALITY);

            return [
                'token'   => $token,
                'file'    => $file,
                'preview' => 'data:image/jpeg;base64,' . base64_encode($preview->getImageBlob()),
                'width'   => $width,
                'height'  => $height,
            ];
        } catch (Throwable $error) {
            return new WP_Error('ai_result_invalid', __('The AI provider returned an image that could not be read.', 'advanced-pixel-editor'));
        } finally {
            $image->clear();
            if ($preview instanceof Imagick) {
                $preview->clear();
            }
        }
    }

    /**
     * Delete a stored AI result and its record.
     *
     * @param string $token  Result token.
     * @param array  $record Result record.
     */
    private function delete_result($token, array $record) {
        $dir = self::get_result_dir();
        if ($dir && !empty($record['file'])) {
            $path = trailingslashit($dir) . basename($record['file']);
            if (is_file($path)) {
                wp_delete_file($path);
            }
        }

        delete_transient($this->transient_key($token));
    }

    /**
     * Absolute path of the AI results folder.
     *
     * @return string Empty when the uploads folder is unavailable.
     */
    private static function get_result_dir() {
        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error']) || empty($uploads['basedir'])) {
            return '';
        }

        return trailingslashit($uploads['basedir']) . self::RESULT_DIR;
    }

    /**
     * Transient key for a result token.
     *
     * @param string $token Result token.
     * @return string
     */
    private function transient_key($token) {
        return 'advaimg_ai_' . $token;
    }

    /**
     * Per-user limit on AI requests, separate from the preview limit.
     *
     * @return bool True when the limit is exceeded.
     */
    private function check_rate_limit() {
        $key      = 'advaimg_rate_limit_ai_' . get_current_user_id();
        $requests = (int) get_transient($key);

        if ($requests >= self::RATE_LIMIT_REQUESTS) {
            return true;
        }

        set_transient($key, $requests + 1, MINUTE_IN_SECONDS);
        return false;
    }

    /**
     * Multibyte-safe string length.
     *
     * @param string $text Text.
     * @return int
     */
    private function string_length($text) {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }
}
