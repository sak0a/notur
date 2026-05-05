import { ExtensionDefinition, SimpleExtensionDefinition } from './types';
/**
 * Factory for registering a Notur extension.
 *
 * Supports two calling conventions:
 *
 * **Simplified** (recommended) — `id` at the top level, name/version auto-resolved from manifest:
 * ```ts
 * createExtension({
 *     id: 'acme/analytics',
 *     slots: [
 *         { slot: 'dashboard.widgets', component: AnalyticsWidget, order: 10 },
 *     ],
 * });
 * ```
 *
 * **Full** — explicit config object (backward compatible):
 * ```ts
 * createExtension({
 *     config: { id: 'acme/analytics', name: 'Analytics', version: '1.0.0' },
 *     slots: [
 *         { slot: 'dashboard.widgets', component: AnalyticsWidget, order: 10 },
 *     ],
 * });
 * ```
 */
export declare function createExtension(definition: ExtensionDefinition): void;
export declare function createExtension(definition: SimpleExtensionDefinition): void;
//# sourceMappingURL=createExtension.d.ts.map