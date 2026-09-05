<?php
/**
 * Standalone regression checks for processing limits and format mappings.
 */

define('ABSPATH', __DIR__);

function __($text) {
    return $text;
}

class Advanced_Pixel_Editor {
    const MAX_IMAGE_WIDTH = 4096;
    const MAX_IMAGE_HEIGHT = 4096;
    const MAX_TOTAL_IMAGE_PIXELS = 16777216;
    const MAX_DPI = 1200;
}

require_once dirname(__DIR__) . '/includes/advaimg-functions.php';
require_once dirname(__DIR__) . '/includes/class-advaimg-transform.php';

function advaimg_assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function advaimg_assert_rejected(callable $callback, $message) {
    try {
        $callback();
    } catch (InvalidArgumentException $error) {
        return;
    }

    throw new RuntimeException($message);
}

$transform = new ADVAIMG_Transform();

advaimg_assert_same(
    [800, 600],
    $transform->calculate_output_dimensions(800, 600, 72, []),
    'Neutral transforms must preserve dimensions'
);

advaimg_assert_same(
    [400, 300],
    $transform->calculate_output_dimensions(
        800,
        600,
        72,
        [
            'advaimg_crop_x' => 100,
            'advaimg_crop_y' => 100,
            'advaimg_crop_w' => 400,
            'advaimg_crop_h' => 300,
        ]
    ),
    'Crop dimensions must be calculated before allocation'
);

advaimg_assert_same(
    [3000, 4000],
    $transform->calculate_output_dimensions(4000, 3000, 72, ['advaimg_rotate' => 90]),
    'Right-angle rotation must not gain a floating-point pixel'
);

advaimg_assert_rejected(
    function () use ($transform) {
        $transform->calculate_output_dimensions(
            800,
            600,
            72,
            ['advaimg_resize_w' => 100000, 'advaimg_resize_h' => 100000]
        );
    },
    'Oversized resize was accepted'
);

advaimg_assert_rejected(
    function () use ($transform) {
        $transform->calculate_output_dimensions(
            4096,
            4096,
            72,
            ['advaimg_dpi' => 1200, 'advaimg_resample' => '1']
        );
    },
    'Unsafe DPI resampling was accepted'
);

advaimg_assert_rejected(
    function () use ($transform) {
        $transform->calculate_output_dimensions(
            4000,
            4000,
            72,
            ['advaimg_rotate' => 45]
        );
    },
    'Oversized rotation canvas was accepted'
);

advaimg_assert_same('image/avif', advanced_image_editor_get_mime_type_from_format('AVIF'), 'AVIF MIME mapping failed');
advaimg_assert_same('avif', advanced_image_editor_get_extension_from_mime_type('image/avif'), 'AVIF extension mapping failed');
advaimg_assert_same('', advanced_image_editor_get_mime_type_from_format('UNKNOWN'), 'Unknown formats must not become JPEG');

echo "Server safety checks passed.\n";
