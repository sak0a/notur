import * as React from 'react';
import { PluginRegistry, SlotRegistration } from './PluginRegistry';
import { SlotId } from './slots/SlotDefinitions';
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
export declare class SlotRenderer extends React.Component<SlotRendererProps, SlotRendererState> {
    private unsubscribe?;
    constructor(props: SlotRendererProps);
    componentDidMount(): void;
    componentWillUnmount(): void;
    render(): React.ReactNode;
}
/**
 * Functional wrapper for rendering a slot inline (without portal).
 */
export declare function InlineSlot({ slotId, registry, componentProps, }: SlotRendererProps): React.ReactElement | null;
export {};
