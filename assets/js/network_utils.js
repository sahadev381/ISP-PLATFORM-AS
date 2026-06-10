/**
 * Predicts fiber signal loss based on length and splice count.
 * @param {number} lengthM - Length in meters.
 * @param {number} spliceCount - Number of splices (default 2).
 * @returns {number} Predicted loss in dB.
 */
function predictSignalLoss(lengthM, spliceCount = 2) {
    const lossPerKm = 0.35; // Standard 1310nm
    const spliceLoss = 0.1;
    return (lengthM / 1000 * lossPerKm) + (spliceCount * spliceLoss);
}

// Export for Node environment (testing)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { predictSignalLoss };
} else if (typeof exports !== 'undefined') {
    exports.predictSignalLoss = predictSignalLoss;
}
