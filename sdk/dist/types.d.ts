/**
 * Known frontend slot IDs provided by the Notur bridge.
 *
 * Slot IDs are stable mount points patched into the Pterodactyl UI. Use these
 * strings in `SlotConfig.slot` to render React components in a specific panel
 * location.
 *
 * @example
 * ```ts
 * createExtension({
 *   id: 'acme/red-button',
 *   slots: [{ slot: 'server.header', component: RedButton }],
 * });
 * ```
 */
export type SlotId = 'navbar' | 'navbar.left' | 'navbar.before' | 'navbar.after' | 'server.subnav' | 'server.subnav.before' | 'server.subnav.after' | 'server.header' | 'server.page' | 'server.footer' | 'server.terminal.buttons' | 'server.console.header' | 'server.console.info.before' | 'server.console.info.after' | 'server.console.sidebar' | 'server.console.command' | 'server.console.footer' | 'server.files.actions' | 'server.files.header' | 'server.files.footer' | 'server.files.dropdown' | 'server.files.edit.before' | 'server.files.edit.after' | 'server.databases.before' | 'server.databases.after' | 'server.schedules.before' | 'server.schedules.after' | 'server.schedules.edit.before' | 'server.schedules.edit.after' | 'server.users.before' | 'server.users.after' | 'server.backups.before' | 'server.backups.after' | 'server.backups.dropdown' | 'server.network.before' | 'server.network.after' | 'server.startup.before' | 'server.startup.after' | 'server.settings.before' | 'server.settings.after' | 'dashboard.header' | 'dashboard.widgets' | 'dashboard.serverlist.before' | 'dashboard.serverlist.after' | 'dashboard.serverrow.name.before' | 'dashboard.serverrow.name.after' | 'dashboard.serverrow.description.before' | 'dashboard.serverrow.description.after' | 'dashboard.serverrow.limits' | 'dashboard.footer' | 'dashboard.page' | 'account.header' | 'account.page' | 'account.footer' | 'account.subnav' | 'account.subnav.before' | 'account.subnav.after' | 'account.overview.before' | 'account.overview.after' | 'account.api.before' | 'account.api.after' | 'account.ssh.before' | 'account.ssh.after' | 'auth.container.before' | 'auth.container.after';
/**
 * Panel route area for extension pages.
 *
 * Routes are scoped by area so pages are mounted in the correct Pterodactyl
 * router surface and navigation context.
 */
export type RouteArea = 'server' | 'dashboard' | 'account';
/**
 * Extension identity configuration.
 *
 * Only `id` is required for most extensions. `name` and `version` are normally
 * resolved from `extension.yaml`, which is injected into the bridge runtime by
 * the Notur PHP package.
 */
export interface ExtensionConfig {
    /** Unique extension identifier in vendor/name format, for example `acme/analytics`. */
    id: string;
    /** Human-readable display name. Omit this when it is present in `extension.yaml`. */
    name?: string;
    /** Semantic version string. Omit this when it is present in `extension.yaml`. */
    version?: string;
}
/**
 * CSS isolation options for components rendered by this extension.
 *
 * Root-class isolation wraps rendered slot/route components in a stable class so
 * extension CSS can be scoped without Shadow DOM.
 *
 * @example
 * ```ts
 * createExtension({
 *   id: 'acme/theme-tools',
 *   cssIsolation: { mode: 'root-class', className: 'notur-ext--theme-tools' },
 * });
 * ```
 */
export interface CssIsolationConfig {
    /** Isolation strategy. Currently only root class wrapping is supported. */
    mode: 'root-class';
    /** Optional class name. If omitted, the bridge derives one from the extension id. */
    className?: string;
}
/**
 * Runtime context passed to `when` predicate functions for conditional slot rendering.
 */
export interface SlotRenderContext {
    /** Current `window.location.pathname`, for example `/server/abc123`. */
    path: string;
    /** High-level panel area inferred from the current path. */
    area: 'server' | 'dashboard' | 'account' | 'admin' | 'auth' | 'other';
    /** True when the current URL is a server-scoped page. */
    isServer: boolean;
    /** True when the current URL is the dashboard area. */
    isDashboard: boolean;
    /** True when the current URL is an account page. */
    isAccount: boolean;
    /** True when the current URL is an admin page. */
    isAdmin: boolean;
    /** True when the current URL is an auth/login page. */
    isAuth: boolean;
    /** Current server permission list when available, otherwise `null`. */
    permissions: string[] | null;
}
/**
 * Declarative conditions that decide whether a slot registration should render.
 *
 * All provided conditions must match. Use this to keep a component limited to
 * specific panel areas, paths, or permissions.
 *
 * @example
 * ```ts
 * { slot: 'server.header', component: Button, when: { server: true } }
 * { slot: 'server.files.actions', component: Tool, when: { pathIncludes: '/files' } }
 * ```
 */
export interface SlotRenderWhen {
    /** Render only in one panel area. */
    area?: SlotRenderContext['area'];
    /** Render only in one of several panel areas. */
    areas?: Array<SlotRenderContext['area']>;
    /** Alias for `pathStartsWith`; render when the current path starts with this value. */
    path?: string | string[];
    /** Render when the current path starts with one of these prefixes. */
    pathStartsWith?: string | string[];
    /** Render when the current path contains one of these fragments. */
    pathIncludes?: string | string[];
    /** Render when the current path matches this regular expression. */
    pathMatches?: string | RegExp;
    /** Require one of these server permissions. `*` grants access. */
    permission?: string | string[];
    /** Render only when `context.isServer` equals this value. */
    server?: boolean;
    /** Render only when `context.isDashboard` equals this value. */
    dashboard?: boolean;
    /** Render only when `context.isAccount` equals this value. */
    account?: boolean;
    /** Render only when `context.isAdmin` equals this value. */
    admin?: boolean;
    /** Render only when `context.isAuth` equals this value. */
    auth?: boolean;
}
/**
 * Conditional render rule for a slot registration.
 *
 * - `false` disables rendering.
 * - an object uses declarative path/area/permission checks.
 * - a function receives `SlotRenderContext` for custom logic.
 */
export type SlotRenderCondition = boolean | SlotRenderWhen | ((context: SlotRenderContext) => boolean);
/**
 * Register a React component into a Notur frontend slot.
 */
export interface SlotConfig {
    /** Slot mount point. Known values autocomplete through `SlotId`; custom strings are accepted for forward compatibility. */
    slot: SlotId | (string & {});
    /** React component rendered into the slot. Receives at least `{ extensionId }` plus any `props`. */
    component: React.ComponentType<any>;
    /** Sort order within the same priority. Lower values render first. Defaults to `0`. */
    order?: number;
    /** Priority across registrations. Higher values render before lower values. Defaults to `0`. */
    priority?: number;
    /** Optional label used by navigation-style slots such as `server.subnav`. */
    label?: string;
    /** Optional icon name used by navigation-style slots when the bridge supports icons. */
    icon?: string;
    /** Server permission required to render this slot. Checked against current server permissions when available. */
    permission?: string;
    /** Static props merged into the component props every time it renders. */
    props?: Record<string, any>;
    /** Optional condition controlling whether the component renders for the current page/context. */
    when?: SlotRenderCondition;
}
/**
 * Register a full React page inside a Notur route area.
 *
 * Route paths are extension-local. A server route with `path: '/analytics'` is
 * mounted under the extension namespace rather than replacing Pterodactyl's
 * built-in routes.
 */
export interface RouteConfig {
    /** Panel area where the route belongs. */
    area: RouteArea;
    /** Extension-local path. Must start with `/`, for example `/analytics`. */
    path: string;
    /** Display name used by navigation/debug tooling. */
    name: string;
    /** React page component rendered for this route. */
    component: React.ComponentType<any>;
    /** Optional icon name for navigation entries. */
    icon?: string;
    /** Optional permission required before showing or using the route. */
    permission?: string;
}
/**
 * Full extension definition passed to `createExtension()`.
 *
 * Most new extensions should use the simpler top-level `id` form instead.
 *
 * @example
 * ```ts
 * createExtension({
 *   config: { id: 'acme/analytics' },
 *   slots: [{ slot: 'dashboard.widgets', component: Widget }],
 * });
 * ```
 */
export interface ExtensionDefinition {
    /** Extension identity. `id` must match `extension.yaml`. */
    config: ExtensionConfig;
    /** Components to render into existing Pterodactyl UI slots. */
    slots?: SlotConfig[];
    /** Full extension pages to register in supported panel areas. */
    routes?: RouteConfig[];
    /** Enable or configure root-class CSS isolation for this extension. */
    cssIsolation?: CssIsolationConfig | boolean;
    /** Called after registration succeeds. Keep this lightweight. */
    onInit?: () => void;
    /** Called when the bridge unregisters this extension. Use it for cleanup. */
    onDestroy?: () => void;
}
/**
 * Simplified extension definition with `id` at the top level.
 *
 * Name and version are auto-resolved from `extension.yaml`. This is the
 * recommended format for most extensions.
 *
 * @example
 * ```ts
 * createExtension({
 *   id: 'acme/analytics',
 *   slots: [{ slot: 'dashboard.widgets', component: Widget }],
 * });
 * ```
 */
export type SimpleExtensionDefinition = Omit<ExtensionDefinition, 'config'> & {
    /** Unique extension identifier in vendor/name format, for example `acme/analytics`. */
    id: string;
};
/** Internal registration shape stored by the bridge registry after a slot is registered. */
export interface SlotRegistration {
    /** Extension that owns this registration. */
    extensionId: string;
    /** Slot mount point. */
    slot: SlotId | (string & {});
    /** Component rendered by the bridge. */
    component: React.ComponentType<any>;
    /** Sort order within priority. */
    order?: number;
    /** Higher priority renders earlier. */
    priority?: number;
    /** Navigation label for nav slots. */
    label?: string;
    /** Navigation icon for nav slots. */
    icon?: string;
    /** Required permission, if any. */
    permission?: string;
    /** CSS isolation wrapper class resolved by the bridge. */
    scopeClass?: string;
    /** Static props passed into the component. */
    props?: Record<string, any>;
    /** Conditional render rule. */
    when?: SlotRenderCondition;
}
/** Internal registration shape stored by the bridge registry after a route is registered. */
export interface RouteRegistration {
    /** Extension that owns this route. */
    extensionId: string;
    /** Panel area where the route belongs. */
    area: RouteArea;
    /** Extension-local route path. */
    path: string;
    /** Display name. */
    name: string;
    /** Route page component. */
    component: React.ComponentType<any>;
    /** Optional navigation icon. */
    icon?: string;
    /** Optional permission requirement. */
    permission?: string;
    /** CSS isolation wrapper class resolved by the bridge. */
    scopeClass?: string;
}
/**
 * Low-level Notur bridge API exposed on `window.__NOTUR__`.
 *
 * Most extensions should use `createExtension()` and hooks instead of calling
 * this directly. It is exported as an escape hatch for advanced integrations and
 * debugging tools.
 */
export interface NoturApi {
    /** Notur bridge/runtime version. */
    version: string;
    /** Runtime registry for slots, routes, themes, lifecycle callbacks, and events. */
    registry: {
        registerSlot: (registration: Omit<SlotRegistration, 'extensionId'> & {
            extensionId?: string;
        }) => void;
        registerRoute: (area: RouteRegistration['area'], route: Omit<RouteRegistration, 'extensionId' | 'area'> & {
            extensionId?: string;
        }) => void;
        registerExtension: (ext: {
            id: string;
            name: string;
            version: string;
            slots: SlotRegistration[];
            routes: RouteRegistration[];
            theme?: {
                variables: Record<string, string>;
                priority?: number;
            };
            cssIsolation?: CssIsolationConfig;
        }) => void;
        registerDestroyCallback: (extensionId: string, callback: () => void) => void;
        unregisterExtension: (extensionId: string) => void;
        getSlot: (slotId: string) => SlotRegistration[];
        getRoutes: (area: RouteRegistration['area']) => RouteRegistration[];
        on: (event: string, callback: () => void) => () => void;
        emitEvent: (event: string, data?: unknown) => void;
        onEvent: (event: string, callback: (data?: unknown) => void) => () => void;
    };
    /** Bridge-provided hooks for advanced consumers. Prefer direct SDK hook exports. */
    hooks: {
        useSlot: (slotId: string) => SlotRegistration[];
        useExtensionApi: (options: {
            extensionId: string;
            baseUrl?: string;
        }) => {
            data: unknown;
            loading: boolean;
            error: string | null;
            get: (path: string) => Promise<unknown>;
            post: (path: string, body?: unknown) => Promise<unknown>;
            put: (path: string, body?: unknown) => Promise<unknown>;
            patch: (path: string, body?: unknown) => Promise<unknown>;
            delete: (path: string) => Promise<unknown>;
            request: (path: string, options?: RequestInit) => Promise<unknown>;
        };
        useExtensionState: <T extends Record<string, any>>(extensionId: string, initialState: T) => [T, (partial: Partial<T>) => void, () => void];
        useNoturTheme: () => Record<string, string>;
    };
    /** Bridge slot constants. */
    SLOT_IDS: Record<string, string>;
    /** Unregister an extension by id. Mostly useful for development tooling. */
    unregisterExtension: (id: string) => void;
    /** Enabled extension metadata injected by the PHP runtime. */
    extensions?: Array<{
        id: string;
        name?: string;
        version?: string;
        bundle?: string;
        styles?: string;
        cssIsolation?: CssIsolationConfig;
    }>;
}
/**
 * Base props received by components registered in Notur slots.
 *
 * Extend this interface for your own static props:
 *
 * ```tsx
 * import type { SlotComponentProps } from '@notur/sdk';
 *
 * type Props = SlotComponentProps & { label: string };
 * const MyWidget: React.FC<Props> = ({ extensionId, label }) => <button>{label}</button>;
 * ```
 */
export interface SlotComponentProps {
    /** Owning extension id, for example `acme/red-button`. */
    extensionId: string;
    /** Additional props supplied by `SlotConfig.props` or the bridge. */
    [key: string]: any;
}
/**
 * Get the Notur API from the browser global.
 *
 * This is an advanced escape hatch. Use `createExtension()` and exported hooks
 * for normal extension development.
 *
 * @throws If the Notur bridge runtime has not loaded yet.
 */
export declare function getNoturApi(): NoturApi;
//# sourceMappingURL=types.d.ts.map