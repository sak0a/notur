import { createExtension } from '@notur/sdk';
import { mountTheme } from './runtime';

let cleanup: (() => void) | undefined;
createExtension({
    config: { id: 'notur/obsidian', name: 'Obsidian', version: '0.1.0' },
    onInit: () => { cleanup = mountTheme(); },
    onDestroy: () => { cleanup?.(); cleanup = undefined; },
});
