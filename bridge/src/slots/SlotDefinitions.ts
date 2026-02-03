/**
 * Predefined slot IDs injected into the Pterodactyl panel during Notur install.
 */
export const SLOT_IDS = {
    /** Top navigation bar */
    NAVBAR: 'navbar',

    /** Top navigation bar (left area, near logo) */
    NAVBAR_LEFT: 'navbar.left',

    /** Server sub-navigation items */
    SERVER_SUBNAV: 'server.subnav',

    /** Server header area */
    SERVER_HEADER: 'server.header',

    /** Server area — full route/page */
    SERVER_PAGE: 'server.page',

    /** Server footer area */
    SERVER_FOOTER: 'server.footer',

    /** Terminal power buttons */
    SERVER_TERMINAL_BUTTONS: 'server.terminal.buttons',

    /** Console page header */
    SERVER_CONSOLE_HEADER: 'server.console.header',

    /** Console sidebar area */
    SERVER_CONSOLE_SIDEBAR: 'server.console.sidebar',

    /** Console page footer */
    SERVER_CONSOLE_FOOTER: 'server.console.footer',

    /** File manager toolbar */
    SERVER_FILES_ACTIONS: 'server.files.actions',

    /** File manager header */
    SERVER_FILES_HEADER: 'server.files.header',

    /** File manager footer */
    SERVER_FILES_FOOTER: 'server.files.footer',

    /** Dashboard header area */
    DASHBOARD_HEADER: 'dashboard.header',

    /** Dashboard below server list */
    DASHBOARD_WIDGETS: 'dashboard.widgets',

    /** Dashboard server list (before) */
    DASHBOARD_SERVERLIST_BEFORE: 'dashboard.serverlist.before',

    /** Dashboard server list (after) */
    DASHBOARD_SERVERLIST_AFTER: 'dashboard.serverlist.after',

    /** Dashboard footer area */
    DASHBOARD_FOOTER: 'dashboard.footer',

    /** Dashboard area — full route/page */
    DASHBOARD_PAGE: 'dashboard.page',

    /** Account header area */
    ACCOUNT_HEADER: 'account.header',

    /** Account area — full route/page */
    ACCOUNT_PAGE: 'account.page',

    /** Account footer area */
    ACCOUNT_FOOTER: 'account.footer',

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
    { id: SLOT_IDS.NAVBAR_LEFT, type: 'portal', description: 'Navbar left area (near logo)' },
    { id: SLOT_IDS.SERVER_SUBNAV, type: 'nav', description: 'Server sub-navigation' },
    { id: SLOT_IDS.SERVER_HEADER, type: 'portal', description: 'Server header area' },
    { id: SLOT_IDS.SERVER_PAGE, type: 'route', description: 'Server area page' },
    { id: SLOT_IDS.SERVER_FOOTER, type: 'portal', description: 'Server footer area' },
    { id: SLOT_IDS.SERVER_TERMINAL_BUTTONS, type: 'portal', description: 'Terminal power buttons' },
    { id: SLOT_IDS.SERVER_CONSOLE_HEADER, type: 'portal', description: 'Console page header' },
    { id: SLOT_IDS.SERVER_CONSOLE_SIDEBAR, type: 'portal', description: 'Console sidebar area' },
    { id: SLOT_IDS.SERVER_CONSOLE_FOOTER, type: 'portal', description: 'Console page footer' },
    { id: SLOT_IDS.SERVER_FILES_ACTIONS, type: 'portal', description: 'File manager toolbar' },
    { id: SLOT_IDS.SERVER_FILES_HEADER, type: 'portal', description: 'File manager header' },
    { id: SLOT_IDS.SERVER_FILES_FOOTER, type: 'portal', description: 'File manager footer' },
    { id: SLOT_IDS.DASHBOARD_HEADER, type: 'portal', description: 'Dashboard header area' },
    { id: SLOT_IDS.DASHBOARD_WIDGETS, type: 'portal', description: 'Dashboard widgets' },
    { id: SLOT_IDS.DASHBOARD_SERVERLIST_BEFORE, type: 'portal', description: 'Dashboard server list (before)' },
    { id: SLOT_IDS.DASHBOARD_SERVERLIST_AFTER, type: 'portal', description: 'Dashboard server list (after)' },
    { id: SLOT_IDS.DASHBOARD_FOOTER, type: 'portal', description: 'Dashboard footer area' },
    { id: SLOT_IDS.DASHBOARD_PAGE, type: 'route', description: 'Dashboard page' },
    { id: SLOT_IDS.ACCOUNT_HEADER, type: 'portal', description: 'Account header area' },
    { id: SLOT_IDS.ACCOUNT_PAGE, type: 'route', description: 'Account page' },
    { id: SLOT_IDS.ACCOUNT_FOOTER, type: 'portal', description: 'Account footer area' },
    { id: SLOT_IDS.ACCOUNT_SUBNAV, type: 'nav', description: 'Account sub-navigation' },
];
