import { expect, test, describe } from 'vitest';
const { predictSignalLoss } = require('../assets/js/network_utils.js');

describe('predictSignalLoss', () => {
    test('calculates loss correctly for standard parameters', () => {
        // (1000 / 1000 * 0.35) + (2 * 0.1) = 0.35 + 0.2 = 0.55
        expect(predictSignalLoss(1000, 2)).toBeCloseTo(0.55);
    });

    test('uses default splice count of 2', () => {
        // (2000 / 1000 * 0.35) + (2 * 0.1) = 0.7 + 0.2 = 0.9
        expect(predictSignalLoss(2000)).toBeCloseTo(0.9);
    });

    test('handles zero length', () => {
        // (0 / 1000 * 0.35) + (2 * 0.1) = 0.2
        expect(predictSignalLoss(0, 2)).toBeCloseTo(0.2);
    });

    test('handles zero splices', () => {
        // (1000 / 1000 * 0.35) + (0 * 0.1) = 0.35
        expect(predictSignalLoss(1000, 0)).toBeCloseTo(0.35);
    });

    test('handles zero length and zero splices', () => {
        expect(predictSignalLoss(0, 0)).toBe(0);
    });

    test('handles large values', () => {
        // (10000 / 1000 * 0.35) + (10 * 0.1) = 3.5 + 1.0 = 4.5
        expect(predictSignalLoss(10000, 10)).toBeCloseTo(4.5);
    });

    test('handles floating point lengths', () => {
        // (500.5 / 1000 * 0.35) + (2 * 0.1) = 0.175175 + 0.2 = 0.375175
        expect(predictSignalLoss(500.5, 2)).toBeCloseTo(0.375175);
    });
});
