/**
 * Get the Notur API from the browser global.
 *
 * This is an advanced escape hatch. Use `createExtension()` and exported hooks
 * for normal extension development.
 *
 * @throws If the Notur bridge runtime has not loaded yet.
 */
export function getNoturApi() {
    const api = window.__NOTUR__;
    if (!api) {
        throw new Error('[Notur SDK] Bridge runtime not found. Ensure bridge.js is loaded first.');
    }
    return api;
}
//# sourceMappingURL=types.js.map