import { ExtensionDefinition } from './types';
/**
 * Factory for registering a Notur extension.
 *
 * Usage:
 * ```ts
 * import { createExtension } from '@notur/sdk';
 *
 * createExtension({
 *   config: { id: 'acme/analytics', name: 'Analytics', version: '1.0.0' },
 *   slots: [
 *     { slot: 'dashboard.widgets', component: AnalyticsWidget, order: 10 },
 *   ],
 *   routes: [
 *     { area: 'server', path: '/analytics', name: 'Analytics', component: AnalyticsPage },
 *   ],
 * });
 * ```
 */
export declare function createExtension(definition: ExtensionDefinition): void;
//# sourceMappingURL=createExtension.d.ts.map