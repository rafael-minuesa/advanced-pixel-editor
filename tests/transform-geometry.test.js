'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const geometry = require('../assets/js/transform-geometry.js');

test('maps a crop through a resized preview', () => {
    assert.deepEqual(
        geometry.composeCropRect(
            { x: 500, y: 0, width: 500, height: 750 },
            { width: 1000, height: 750 },
            { x: 0, y: 0, width: 4000, height: 3000 }
        ),
        { x: 2000, y: 0, width: 2000, height: 3000 }
    );
});

test('composes a second crop inside the first crop', () => {
    assert.deepEqual(
        geometry.composeCropRect(
            { x: 0, y: 0, width: 500, height: 750 },
            { width: 1000, height: 750 },
            { x: 1000, y: 500, width: 2000, height: 1500 }
        ),
        { x: 1000, y: 500, width: 1000, height: 1500 }
    );
});

test('calculates the canvas for a right-angle rotation', () => {
    assert.deepEqual(
        geometry.rotatedDimensions(4000, 3000, 90),
        { width: 3000, height: 4000 }
    );
});

test('clamps a selection to the visible preview', () => {
    assert.deepEqual(
        geometry.composeCropRect(
            { x: 900, y: 700, width: 500, height: 500 },
            { width: 1000, height: 750 },
            { x: 0, y: 0, width: 4000, height: 3000 }
        ),
        { x: 3600, y: 2800, width: 400, height: 200 }
    );
});
