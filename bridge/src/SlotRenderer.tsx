import * as React from 'react';
import * as ReactDOM from 'react-dom';
import {
    PluginRegistry,
    SlotRegistration,
    SlotRenderCondition,
    SlotRenderContext,
    SlotRenderWhen,
} from './PluginRegistry';
import { SLOT_IDS, SlotId } from './slots/SlotDefinitions';
import { SlotErrorBoundary } from './ErrorBoundary';

interface SlotRendererProps {
    slotId: SlotId;
    registry: PluginRegistry;
    /** Extra props passed to each rendered component */
    componentProps?: Record<string, any>;
}

interface SlotRendererState {
    registrations: SlotRegistration[];
}

function toArray<T>(value?: T | T[]): T[] {
    if (value === undefined || value === null) return [];
    return Array.isArray(value) ? value : [value];
}

function resolveSlotContext(): SlotRenderContext {
    const path = typeof window !== 'undefined' ? window.location.pathname : '';
    const isAdmin = path.startsWith('/admin');
    const isServer = /\/server\/[a-f0-9-]+/i.test(path);
    const isAccount = path.startsWith('/account');
    const isAuth = path.startsWith('/auth') || path.startsWith('/login') || path.startsWith('/register') || path.startsWith('/password');
    const isDashboard = !isAdmin && !isServer && !isAccount && !isAuth;

    let permissions: string[] | null = null;
    if (typeof document !== 'undefined') {
        const appEl = document.getElementById('app');
        const serverData = appEl?.dataset?.server;
        if (serverData) {
            try {
                const parsed = JSON.parse(serverData);
                if (Array.isArray(parsed?.permissions)) {
                    permissions = parsed.permissions;
                }
            } catch {
                // Ignore parse errors
            }
        }
    }

    let area: SlotRenderContext['area'] = 'other';
    if (isAdmin) area = 'admin';
    else if (isServer) area = 'server';
    else if (isAccount) area = 'account';
    else if (isAuth) area = 'auth';
    else if (isDashboard) area = 'dashboard';

    return {
        path,
        area,
        isServer,
        isDashboard,
        isAccount,
        isAdmin,
        isAuth,
        permissions,
    };
}

function matchesPathStartsWith(path: string, values: string[]): boolean {
    return values.some(value => path.startsWith(value));
}

function matchesPathIncludes(path: string, values: string[]): boolean {
    return values.some(value => path.includes(value));
}

function matchesPathRegex(path: string, matcher: string | RegExp): boolean {
    try {
        const regex = matcher instanceof RegExp ? matcher : new RegExp(matcher);
        return regex.test(path);
    } catch {
        return false;
    }
}

function matchesPermission(permissions: string[] | null, required: string | string[]): boolean {
    if (!permissions || permissions.length === 0) {
        return false;
    }

    const requiredList = toArray(required);
    if (permissions.includes('*')) {
        return true;
    }

    return requiredList.some(req => permissions.includes(req));
}

function shouldRenderSlot(reg: SlotRegistration, context: SlotRenderContext): boolean {
    const condition: SlotRenderCondition | undefined = reg.when;
    let when: SlotRenderWhen | null = null;

    if (condition === false) {
        return false;
    }

    if (typeof condition === 'function') {
        return condition(context);
    }

    if (condition && typeof condition === 'object') {
        when = condition as SlotRenderWhen;

        if (when.area && when.area !== context.area) {
            return false;
        }

        if (when.areas && when.areas.length > 0 && !when.areas.includes(context.area)) {
            return false;
        }

        if (typeof when.server === 'boolean' && when.server !== context.isServer) {
            return false;
        }

        if (typeof when.dashboard === 'boolean' && when.dashboard !== context.isDashboard) {
            return false;
        }

        if (typeof when.account === 'boolean' && when.account !== context.isAccount) {
            return false;
        }

        if (typeof when.admin === 'boolean' && when.admin !== context.isAdmin) {
            return false;
        }

        if (typeof when.auth === 'boolean' && when.auth !== context.isAuth) {
            return false;
        }

        const startsWith = toArray(when.pathStartsWith ?? when.path);
        if (startsWith.length > 0 && !matchesPathStartsWith(context.path, startsWith)) {
            return false;
        }

        const includes = toArray(when.pathIncludes);
        if (includes.length > 0 && !matchesPathIncludes(context.path, includes)) {
            return false;
        }

        if (when.pathMatches) {
            if (!matchesPathRegex(context.path, when.pathMatches)) {
                return false;
            }
        }
    }

    const requiredPermission = when?.permission ?? reg.permission;
    if (requiredPermission) {
        return matchesPermission(context.permissions, requiredPermission);
    }

    return true;
}

/**
 * Renders all components registered to a slot using React portals.
 * Mounts into a DOM element with id="notur-slot-{slotId}".
 */
export class SlotRenderer extends React.Component<SlotRendererProps, SlotRendererState> {
    private unsubscribe?: () => void;

    constructor(props: SlotRendererProps) {
        super(props);
        this.state = {
            registrations: props.registry.getSlot(props.slotId),
        };
    }

    componentDidMount(): void {
        this.unsubscribe = this.props.registry.on('slot:' + this.props.slotId, () => {
            this.setState({
                registrations: this.props.registry.getSlot(this.props.slotId),
            });
        });
    }

    componentWillUnmount(): void {
        this.unsubscribe?.();
    }

    render(): React.ReactNode {
        const { slotId, componentProps = {} } = this.props;
        const { registrations } = this.state;

        const container = document.getElementById(`notur-slot-${slotId}`);

        if (!container || registrations.length === 0) {
            return null;
        }

        const context = resolveSlotContext();
        const visible = registrations.filter(reg => shouldRenderSlot(reg, context));

        if (visible.length === 0) {
            return null;
        }

        const elements = visible.map((reg, index) => {
            const Component = reg.component;
            const content = React.createElement(Component, {
                extensionId: reg.extensionId,
                ...componentProps,
                ...(reg.props ?? {}),
            });
            const scoped = reg.scopeClass
                ? React.createElement(
                      'div',
                      { className: reg.scopeClass, 'data-notur-extension': reg.extensionId },
                      content,
                  )
                : content;
            const wrapped =
                slotId === SLOT_IDS.DASHBOARD_WIDGETS
                    ? React.createElement(
                          'div',
                          { className: 'notur-surface notur-surface--card notur-surface--widget' },
                          scoped,
                      )
                    : scoped;
            return React.createElement(
                SlotErrorBoundary,
                { key: `${reg.extensionId}-${index}`, extensionId: reg.extensionId },
                wrapped,
            );
        });

        return ReactDOM.createPortal(elements, container);
    }
}

/**
 * Functional wrapper for rendering a slot inline (without portal).
 */
export function InlineSlot({
    slotId,
    registry,
    componentProps = {},
}: SlotRendererProps): React.ReactElement | null {
    const [registrations, setRegistrations] = React.useState<SlotRegistration[]>(
        registry.getSlot(slotId),
    );

    React.useEffect(() => {
        return registry.on('slot:' + slotId, () => {
            setRegistrations(registry.getSlot(slotId));
        });
    }, [slotId, registry]);

    if (registrations.length === 0) {
        return null;
    }

    const context = resolveSlotContext();
    const visible = registrations.filter(reg => shouldRenderSlot(reg, context));

    if (visible.length === 0) {
        return null;
    }

    return React.createElement(
        React.Fragment,
        null,
        ...visible.map((reg, index) => {
            const Component = reg.component;
            const content = React.createElement(Component, {
                extensionId: reg.extensionId,
                ...componentProps,
                ...(reg.props ?? {}),
            });
            const scoped = reg.scopeClass
                ? React.createElement(
                      'div',
                      { className: reg.scopeClass, 'data-notur-extension': reg.extensionId },
                      content,
                  )
                : content;
            const wrapped =
                slotId === SLOT_IDS.DASHBOARD_WIDGETS
                    ? React.createElement(
                          'div',
                          { className: 'notur-surface notur-surface--card notur-surface--widget' },
                          scoped,
                      )
                    : scoped;
            return React.createElement(
                SlotErrorBoundary,
                { key: `${reg.extensionId}-${index}`, extensionId: reg.extensionId },
                wrapped,
            );
        }),
    );
}
