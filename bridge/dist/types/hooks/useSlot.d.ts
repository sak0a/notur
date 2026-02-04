import { SlotRegistration } from '../PluginRegistry';
import { SlotId } from '../slots/SlotDefinitions';
/**
 * Hook to get all components registered for a given slot.
 *
 * Subscribes to PluginRegistry change events so the consuming component
 * re-renders whenever a new component is registered (or removed) for the
 * given slot. Handles late registry initialization gracefully by polling
 * until the bridge is ready.
 */
export declare function useSlot(slotId: SlotId): SlotRegistration[];
