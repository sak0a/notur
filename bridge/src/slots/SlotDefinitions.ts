/**
 * Predefined slot IDs injected into the Pterodactyl panel during Notur install.
 */
import slotDefinitions from './slot-definitions.json';

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

export const SLOT_DEFINITIONS: SlotDefinition[] = slotDefinitions as SlotDefinition[];
