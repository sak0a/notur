import * as React from 'react';
import * as ReactDOM from 'react-dom';
import { PluginRegistry, SlotRegistration } from './PluginRegistry';
import { SlotId } from './slots/SlotDefinitions';
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

        const elements = registrations.map((reg, index) => {
            const Component = reg.component;
            return React.createElement(
                SlotErrorBoundary,
                { key: `${reg.extensionId}-${index}`, extensionId: reg.extensionId },
                React.createElement(Component, {
                    extensionId: reg.extensionId,
                    ...componentProps,
                }),
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

    return React.createElement(
        React.Fragment,
        null,
        ...registrations.map((reg, index) => {
            const Component = reg.component;
            return React.createElement(
                SlotErrorBoundary,
                { key: `${reg.extensionId}-${index}`, extensionId: reg.extensionId },
                React.createElement(Component, {
                    extensionId: reg.extensionId,
                    ...componentProps,
                }),
            );
        }),
    );
}
