<?php
/**
 * Transform (Rotate, Flip, Crop, Resize, DPI) for Advanced Pixel Editor
 *
 * Provides Imagick-powered rotate, flip, crop, resize, and DPI/resample operations.
 *
 * @package AdvancedImageEditor
 * @author  Rafael Minuesa
 * @license GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADVAIMG_Transform {

    /**
     * Process transform operations on an Imagick instance.
     *
     * Order: rotate first (crop coordinates map to the rotated image the
     * user sees in the preview), then crop, then resize, then DPI.
     *
     * @param Imagick $img       The Imagick instance.
     * @param array   $post_data Raw POST data.
     * @return Imagick
     */
    public function process(Imagick $img, array $post_data) {
        $resolution = $img->getImageResolution();
        $this->calculate_output_dimensions(
            $img->getImageWidth(),
            $img->getImageHeight(),
            !empty($resolution['x']) ? (float) $resolution['x'] : 72,
            $post_data
        );

        $img = $this->apply_rotate($img, $post_data);
        $img = $this->apply_flip($img, $post_data);
        $img = $this->apply_crop($img, $post_data);
        $img = $this->apply_resize($img, $post_data);
        $img = $this->apply_dpi($img, $post_data);
        return $img;
    }

    /**
     * Calculate and validate the largest intermediate/output dimensions.
     *
     * This runs before ImageMagick allocates a rotated or resampled canvas,
     * preventing a small source image from expanding beyond the processing
     * limits through crafted resize or DPI parameters.
     *
     * @param int   $width        Source width.
     * @param int   $height       Source height.
     * @param float $resolution_x Current horizontal resolution in DPI.
     * @param array $post_data    POST data.
     * @return array{0:int,1:int} Final width and height.
     * @throws InvalidArgumentException When an intermediate size is unsafe.
     */
    public function calculate_output_dimensions($width, $height, $resolution_x, array $post_data) {
        $width  = (int) $width;
        $height = (int) $height;
        $this->assert_safe_dimensions($width, $height, false);

        $degrees = isset($post_data['advaimg_rotate']) ? (float) $post_data['advaimg_rotate'] : 0;
        if (!is_finite($degrees)) {
            throw new InvalidArgumentException(__('Invalid rotation value.', 'advanced-pixel-editor'));
        }
        $degrees = fmod($degrees, 360);

        if (abs($degrees) >= 0.01) {
            $radians        = deg2rad($degrees);
            $rotated_width  = (int) ceil(abs($width * cos($radians)) + abs($height * sin($radians)) - 1e-9);
            $rotated_height = (int) ceil(abs($width * sin($radians)) + abs($height * cos($radians)) - 1e-9);
            $this->assert_safe_dimensions($rotated_width, $rotated_height, true);
            $width  = $rotated_width;
            $height = $rotated_height;
        }

        $x = isset($post_data['advaimg_crop_x']) ? (int) $post_data['advaimg_crop_x'] : -1;
        $y = isset($post_data['advaimg_crop_y']) ? (int) $post_data['advaimg_crop_y'] : -1;
        $w = isset($post_data['advaimg_crop_w']) ? (int) $post_data['advaimg_crop_w'] : 0;
        $h = isset($post_data['advaimg_crop_h']) ? (int) $post_data['advaimg_crop_h'] : 0;

        if ($x >= 0 && $y >= 0 && $w > 0 && $h > 0) {
            $x      = min($x, $width - 1);
            $y      = min($y, $height - 1);
            $width  = min($w, $width - $x);
            $height = min($h, $height - $y);
            $this->assert_safe_dimensions($width, $height, true);
        }

        $resize_width  = isset($post_data['advaimg_resize_w']) ? (int) $post_data['advaimg_resize_w'] : 0;
        $resize_height = isset($post_data['advaimg_resize_h']) ? (int) $post_data['advaimg_resize_h'] : 0;

        if ($resize_width > 0 && $resize_height > 0) {
            $this->assert_safe_dimensions($resize_width, $resize_height, true);
            $width  = $resize_width;
            $height = $resize_height;
        }

        $dpi      = isset($post_data['advaimg_dpi']) ? (int) $post_data['advaimg_dpi'] : 0;
        $resample = !empty($post_data['advaimg_resample']) && '0' !== $post_data['advaimg_resample'];

        if ($dpi > Advanced_Pixel_Editor::MAX_DPI) {
            throw new InvalidArgumentException(
                sprintf(
                    /* translators: %d: Maximum allowed DPI. */
                    __('Resolution cannot exceed %d DPI.', 'advanced-pixel-editor'),
                    Advanced_Pixel_Editor::MAX_DPI
                )
            );
        }

        if ($dpi > 0 && $resample) {
            $resolution_x = $resolution_x > 0 ? $resolution_x : 72;
            $scale        = $dpi / $resolution_x;
            $scaled_width = $width * $scale;
            $scaled_height = $height * $scale;

            if (!is_finite($scaled_width) || !is_finite($scaled_height)) {
                throw new InvalidArgumentException(__('Requested output dimensions are invalid.', 'advanced-pixel-editor'));
            }

            $width  = (int) round($scaled_width);
            $height = (int) round($scaled_height);
            $this->assert_safe_dimensions($width, $height, true);
        }

        return [$width, $height];
    }

    /**
     * Ensure a canvas remains inside the configured processing limits.
     *
     * Source images are held to the source limits. Canvases produced by a
     * transform (rotation, resize, DPI resampling) are held to the larger
     * output limits, so a rotation of an accepted source is never refused
     * while crafted resize or DPI values still cannot allocate unbounded
     * canvases.
     *
     * @param int  $width     Canvas width.
     * @param int  $height    Canvas height.
     * @param bool $is_output Whether the canvas is produced by a transform.
     * @return void
     * @throws InvalidArgumentException When dimensions are outside the limits.
     */
    private function assert_safe_dimensions($width, $height, $is_output) {
        $pixels     = $width * $height;
        $max_width  = $is_output ? Advanced_Pixel_Editor::MAX_OUTPUT_IMAGE_DIMENSION : Advanced_Pixel_Editor::MAX_IMAGE_WIDTH;
        $max_height = $is_output ? Advanced_Pixel_Editor::MAX_OUTPUT_IMAGE_DIMENSION : Advanced_Pixel_Editor::MAX_IMAGE_HEIGHT;
        $max_pixels = $is_output ? Advanced_Pixel_Editor::MAX_OUTPUT_IMAGE_PIXELS : Advanced_Pixel_Editor::MAX_TOTAL_IMAGE_PIXELS;

        if (
            $width <= 0 ||
            $height <= 0 ||
            $width > $max_width ||
            $height > $max_height ||
            $pixels > $max_pixels
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    /* translators: 1: Requested width, 2: Requested height, 3: Maximum width, 4: Maximum height. */
                    __('Requested output dimensions (%1$dx%2$d) exceed the processing limit (%3$dx%4$d).', 'advanced-pixel-editor'),
                    $width,
                    $height,
                    $max_width,
                    $max_height
                )
            );
        }
    }

    /**
     * Apply horizontal and/or vertical flip if requested.
     *
     * Runs after rotate so mirroring always matches the on-screen
     * orientation, and before crop so crop coordinates keep mapping to
     * the displayed preview.
     *
     * @param Imagick $img       The Imagick instance.
     * @param array   $post_data POST data.
     * @return Imagick
     */
    private function apply_flip(Imagick $img, array $post_data) {
        $flip_h = !empty($post_data['advaimg_flip_h']) && $post_data['advaimg_flip_h'] !== '0';
        $flip_v = !empty($post_data['advaimg_flip_v']) && $post_data['advaimg_flip_v'] !== '0';

        if ($flip_h) {
            $img->flopImage(); // Mirror left-right.
        }
        if ($flip_v) {
            $img->flipImage(); // Mirror top-bottom.
        }

        return $img;
    }

    /**
     * Apply rotation if a non-zero angle is present.
     *
     * Non-right-angle rotations enlarge the canvas; the new corner area is
     * filled transparent for alpha-capable formats (PNG, WebP, GIF) and
     * white for opaque formats (JPEG).
     *
     * @param Imagick $img       The Imagick instance.
     * @param array   $post_data POST data.
     * @return Imagick
     */
    private function apply_rotate(Imagick $img, array $post_data) {
        $degrees = isset($post_data['advaimg_rotate']) ? floatval($post_data['advaimg_rotate']) : 0;
        $degrees = fmod($degrees, 360);

        if (abs($degrees) < 0.01) {
            return $img;
        }

        $format = strtoupper($img->getImageFormat());
        $supports_alpha = in_array($format, ['PNG', 'WEBP', 'GIF'], true);
        $background = new ImagickPixel($supports_alpha ? 'transparent' : 'white');

        $img->rotateImage($background, $degrees);
        $img->setImagePage(0, 0, 0, 0); // Reset canvas offset.

        return $img;
    }

    /**
     * Apply crop if coordinates are present and valid.
     *
     * @param Imagick $img       The Imagick instance.
     * @param array   $post_data POST data.
     * @return Imagick
     */
    private function apply_crop(Imagick $img, array $post_data) {
        $x = isset($post_data['advaimg_crop_x']) ? intval($post_data['advaimg_crop_x']) : -1;
        $y = isset($post_data['advaimg_crop_y']) ? intval($post_data['advaimg_crop_y']) : -1;
        $w = isset($post_data['advaimg_crop_w']) ? intval($post_data['advaimg_crop_w']) : 0;
        $h = isset($post_data['advaimg_crop_h']) ? intval($post_data['advaimg_crop_h']) : 0;

        if ($x < 0 || $y < 0 || $w <= 0 || $h <= 0) {
            return $img;
        }

        $img_w = $img->getImageWidth();
        $img_h = $img->getImageHeight();

        // Clamp crop region to image bounds.
        $x = min($x, $img_w - 1);
        $y = min($y, $img_h - 1);
        $w = min($w, $img_w - $x);
        $h = min($h, $img_h - $y);

        if ($w > 0 && $h > 0) {
            $img->cropImage($w, $h, $x, $y);
            $img->setImagePage(0, 0, 0, 0); // Reset canvas.
        }

        return $img;
    }

    /**
     * Apply resize if dimensions differ from current.
     *
     * @param Imagick $img       The Imagick instance.
     * @param array   $post_data POST data.
     * @return Imagick
     */
    private function apply_resize(Imagick $img, array $post_data) {
        $new_w = isset($post_data['advaimg_resize_w']) ? intval($post_data['advaimg_resize_w']) : 0;
        $new_h = isset($post_data['advaimg_resize_h']) ? intval($post_data['advaimg_resize_h']) : 0;

        if ($new_w <= 0 || $new_h <= 0) {
            return $img;
        }

        $cur_w = $img->getImageWidth();
        $cur_h = $img->getImageHeight();

        // Only resize if dimensions actually differ.
        if ($new_w !== $cur_w || $new_h !== $cur_h) {
            $img->resizeImage($new_w, $new_h, Imagick::FILTER_LANCZOS, 1);
        }

        return $img;
    }

    /**
     * Apply DPI metadata and optionally resample.
     *
     * @param Imagick $img       The Imagick instance.
     * @param array   $post_data POST data.
     * @return Imagick
     */
    private function apply_dpi(Imagick $img, array $post_data) {
        $dpi      = isset($post_data['advaimg_dpi']) ? intval($post_data['advaimg_dpi']) : 0;
        $resample = !empty($post_data['advaimg_resample']) && $post_data['advaimg_resample'] !== '0';

        if ($dpi <= 0) {
            return $img;
        }

        if ($resample) {
            // Get current resolution to compute scale factor.
            $res = $img->getImageResolution();
            $cur_dpi_x = !empty($res['x']) ? $res['x'] : 72;

            if ($cur_dpi_x > 0 && $dpi !== (int) $cur_dpi_x) {
                $scale = $dpi / $cur_dpi_x;
                $new_w = (int) round($img->getImageWidth() * $scale);
                $new_h = (int) round($img->getImageHeight() * $scale);

                if ($new_w > 0 && $new_h > 0) {
                    $img->resizeImage($new_w, $new_h, Imagick::FILTER_LANCZOS, 1);
                }
            }
        }

        // Set DPI metadata.
        $img->setImageResolution($dpi, $dpi);
        $img->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);

        return $img;
    }
}
