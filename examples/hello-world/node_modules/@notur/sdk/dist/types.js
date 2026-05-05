/**
 * Get the Notur API from the window global.
 */
export function getNoturApi() {
    const api = window.__NOTUR__;
    if (!api) {
        throw new Error('[Notur SDK] Bridge runtime not found. Ensure bridge.js is loaded first.');
    }
    return api;
}
//# sourceMappingURL=types.js.map