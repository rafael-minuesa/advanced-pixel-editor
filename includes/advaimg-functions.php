<?php
/**
 * Utility functions for Advanced Pixel Editor
 *
 * @package AdvancedImageEditor
 * @author Rafael Minuesa
 * @license GPL-2.0+
 * @link https://github.com/rafael-minuesa/advanced-image-editor
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get MIME type from image format
 *
 * @param string $format Image format (from Imagick)
 * @return string MIME type
 */
function advanced_image_editor_get_mime_type_from_format($format) {
    $format = strtolower($format);

    $mime_types = [
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'tiff' => 'image/tiff',
        'bmp' => 'image/bmp'
    ];

    return $mime_types[$format] ?? 'image/jpeg'; // Default to JPEG
}

/**
 * Get file extension from MIME type
 *
 * @param string $mime_type MIME type
 * @return string File extension
 */
function advanced_image_editor_get_extension_from_mime_type($mime_type) {
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/tiff' => 'tiff',
        'image/bmp' => 'bmp'
    ];

    return $extensions[$mime_type] ?? 'jpg'; // Default to JPG
}