import { useState, useEffect, useRef } from 'react';
import { PluginRegistry, SlotRegistration } from '../PluginRegistry';
import { SlotId } from '../slots/SlotDefinitions';

/**
 * Safely retrieve the Notur registry from the global scope.
 * Returns null if the bridge has not initialized yet.
 */
function getRegistry(): PluginRegistry | null {
    return (window as any).__NOTUR__?.registry ?? null;
}

/**
 * Hook to get all components registered for a given slot.
 *
 * Subscribes to PluginRegistry change events so the consuming component
 * re-renders whenever a new component is registered (or removed) for the
 * given slot. Handles late registry initialization gracefully by polling
 * until the bridge is ready.
 */
export function useSlot(slotId: SlotId): SlotRegistration[] {
    const [registrations, setRegistrations] = useState<SlotRegistration[]>(() => {
        const registry = getRegistry();
        return registry ? registry.getSlot(slotId) : [];
    });

    const slotIdRef = useRef(slotId);
    slotIdRef.current = slotId;

    useEffect(() => {
        const registry = getRegistry();

        if (registry) {
            // Sync immediately in case registrations changed between render and effect
            setRegistrations(registry.getSlot(slotId));

            const unsubSlot = registry.on('slot:' + slotId, () => {
                setRegistrations(registry.getSlot(slotId));
            });

            // Also listen for bulk extension registrations
            const unsubExt = registry.on('extension:registered', () => {
                setRegistrations(registry.getSlot(slotIdRef.current));
            });

            return () => {
                unsubSlot();
                unsubExt();
            };
        }

        // Registry not available yet — poll until bridge initializes
        const interval = setInterval(() => {
            const reg = getRegistry();
            if (reg) {
                setRegistrations(reg.getSlot(slotIdRef.current));
                clearInterval(interval);
            }
        }, 50);

        return () => clearInterval(interval);
    }, [slotId]);

    return registrations;
}
