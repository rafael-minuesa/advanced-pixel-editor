/**
 * Pure geometry helpers shared by the transform UI and its regression tests.
 */

(function(root, factory) {
    'use strict';

    var geometry = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = geometry;
    } else {
        root.aieTransformGeometry = geometry;
    }
})(typeof window !== 'undefined' ? window : globalThis, function() {
    'use strict';

    function clamp(value, minimum, maximum) {
        return Math.max(minimum, Math.min(maximum, value));
    }

    /**
     * Return the bounding canvas created by rotating an image.
     */
    function rotatedDimensions(width, height, degrees) {
        var radians = (parseFloat(degrees) || 0) * Math.PI / 180;

        var rotatedWidth = Math.abs(width * Math.cos(radians)) + Math.abs(height * Math.sin(radians));
        var rotatedHeight = Math.abs(width * Math.sin(radians)) + Math.abs(height * Math.cos(radians));

        return {
            width: Math.ceil(rotatedWidth - 1e-9),
            height: Math.ceil(rotatedHeight - 1e-9)
        };
    }

    /**
     * Convert a crop selected on the current preview into coordinates on the
     * pre-resize source canvas. If a crop already exists, compose the new crop
     * inside it so repeated crops remain accurate.
     */
    function composeCropRect(selection, preview, base) {
        if (!preview.width || !preview.height || !base.width || !base.height) {
            return null;
        }

        var left = clamp(selection.x / preview.width, 0, 1);
        var top = clamp(selection.y / preview.height, 0, 1);
        var right = clamp((selection.x + selection.width) / preview.width, left, 1);
        var bottom = clamp((selection.y + selection.height) / preview.height, top, 1);

        var baseRight = Math.round(base.x + base.width);
        var baseBottom = Math.round(base.y + base.height);
        var x = clamp(Math.round(base.x + left * base.width), Math.round(base.x), baseRight - 1);
        var y = clamp(Math.round(base.y + top * base.height), Math.round(base.y), baseBottom - 1);
        var cropRight = clamp(Math.round(base.x + right * base.width), x + 1, baseRight);
        var cropBottom = clamp(Math.round(base.y + bottom * base.height), y + 1, baseBottom);

        return {
            x: x,
            y: y,
            width: cropRight - x,
            height: cropBottom - y
        };
    }

    return {
        rotatedDimensions: rotatedDimensions,
        composeCropRect: composeCropRect
    };
});
