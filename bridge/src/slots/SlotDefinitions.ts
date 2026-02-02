/**
 * Predefined slot IDs injected into the Pterodactyl panel during Notur install.
 */
export const SLOT_IDS = {
    /** Top navigation bar */
    NAVBAR: 'navbar',

    /** Server sub-navigation items */
    SERVER_SUBNAV: 'server.subnav',

    /** Server area — full route/page */
    SERVER_PAGE: 'server.page',

    /** Terminal power buttons */
    SERVER_TERMINAL_BUTTONS: 'server.terminal.buttons',

    /** File manager toolbar */
    SERVER_FILES_ACTIONS: 'server.files.actions',

    /** Dashboard below server list */
    DASHBOARD_WIDGETS: 'dashboard.widgets',

    /** Dashboard area — full route/page */
    DASHBOARD_PAGE: 'dashboard.page',

    /** Account area — full route/page */
    ACCOUNT_PAGE: 'account.page',

    /** Account sub-navigation items */
    ACCOUNT_SUBNAV: 'account.subnav',
} as const;

export type SlotId = typeof SLOT_IDS[keyof typeof SLOT_IDS];

export interface SlotDefinition {
    id: SlotId;
    type: 'portal' | 'nav' | 'route';
    description: string;
}

export const SLOT_DEFINITIONS: SlotDefinition[] = [
    { id: SLOT_IDS.NAVBAR, type: 'portal', description: 'Top navigation bar' },
    { id: SLOT_IDS.SERVER_SUBNAV, type: 'nav', description: 'Server sub-navigation' },
    { id: SLOT_IDS.SERVER_PAGE, type: 'route', description: 'Server area page' },
    { id: SLOT_IDS.SERVER_TERMINAL_BUTTONS, type: 'portal', description: 'Terminal power buttons' },
    { id: SLOT_IDS.SERVER_FILES_ACTIONS, type: 'portal', description: 'File manager toolbar' },
    { id: SLOT_IDS.DASHBOARD_WIDGETS, type: 'portal', description: 'Dashboard widgets' },
    { id: SLOT_IDS.DASHBOARD_PAGE, type: 'route', description: 'Dashboard page' },
    { id: SLOT_IDS.ACCOUNT_PAGE, type: 'route', description: 'Account page' },
    { id: SLOT_IDS.ACCOUNT_SUBNAV, type: 'nav', description: 'Account sub-navigation' },
];
