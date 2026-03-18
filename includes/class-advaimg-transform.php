<?php
/**
 * Transform (Crop, Resize, DPI) for Advanced Pixel Editor
 *
 * Provides Imagick-powered crop, resize, and DPI/resample operations.
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
     * Order: crop first, then resize, then DPI.
     *
     * @param Imagick $img       The Imagick instance.
     * @param array   $post_data Raw POST data.
     * @return Imagick
     */
    public function process(Imagick $img, array $post_data) {
        $img = $this->apply_crop($img, $post_data);
        $img = $this->apply_resize($img, $post_data);
        $img = $this->apply_dpi($img, $post_data);
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
