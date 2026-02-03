/**
 * Predefined slot IDs injected into the Pterodactyl panel during Notur install.
 */
import slotDefinitions from './slot-definitions.json';

export const SLOT_IDS = {
    /** Top navigation bar */
    NAVBAR: 'navbar',

    /** Top navigation bar (left area, near logo) */
    NAVBAR_LEFT: 'navbar.left',

    /** Navbar items before built-in actions */
    NAVBAR_BEFORE: 'navbar.before',

    /** Navbar items after built-in actions */
    NAVBAR_AFTER: 'navbar.after',

    /** Server sub-navigation items */
    SERVER_SUBNAV: 'server.subnav',

    /** Server sub-navigation (before built-ins) */
    SERVER_SUBNAV_BEFORE: 'server.subnav.before',

    /** Server sub-navigation (after built-ins) */
    SERVER_SUBNAV_AFTER: 'server.subnav.after',

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

    /** Console info (before server details) */
    SERVER_CONSOLE_INFO_BEFORE: 'server.console.info.before',

    /** Console info (after server details) */
    SERVER_CONSOLE_INFO_AFTER: 'server.console.info.after',

    /** Console sidebar area */
    SERVER_CONSOLE_SIDEBAR: 'server.console.sidebar',

    /** Console command row */
    SERVER_CONSOLE_COMMAND: 'server.console.command',

    /** Console page footer */
    SERVER_CONSOLE_FOOTER: 'server.console.footer',

    /** File manager toolbar */
    SERVER_FILES_ACTIONS: 'server.files.actions',

    /** File manager header */
    SERVER_FILES_HEADER: 'server.files.header',

    /** File manager footer */
    SERVER_FILES_FOOTER: 'server.files.footer',

    /** File manager dropdown items */
    SERVER_FILES_DROPDOWN: 'server.files.dropdown',

    /** File editor (before content) */
    SERVER_FILES_EDIT_BEFORE: 'server.files.edit.before',

    /** File editor (after content) */
    SERVER_FILES_EDIT_AFTER: 'server.files.edit.after',

    /** Databases page (before content) */
    SERVER_DATABASES_BEFORE: 'server.databases.before',

    /** Databases page (after content) */
    SERVER_DATABASES_AFTER: 'server.databases.after',

    /** Schedules list (before content) */
    SERVER_SCHEDULES_BEFORE: 'server.schedules.before',

    /** Schedules list (after content) */
    SERVER_SCHEDULES_AFTER: 'server.schedules.after',

    /** Schedule editor (before content) */
    SERVER_SCHEDULES_EDIT_BEFORE: 'server.schedules.edit.before',

    /** Schedule editor (after content) */
    SERVER_SCHEDULES_EDIT_AFTER: 'server.schedules.edit.after',

    /** Users page (before content) */
    SERVER_USERS_BEFORE: 'server.users.before',

    /** Users page (after content) */
    SERVER_USERS_AFTER: 'server.users.after',

    /** Backups page (before content) */
    SERVER_BACKUPS_BEFORE: 'server.backups.before',

    /** Backups page (after content) */
    SERVER_BACKUPS_AFTER: 'server.backups.after',

    /** Backup row dropdown items */
    SERVER_BACKUPS_DROPDOWN: 'server.backups.dropdown',

    /** Network page (before content) */
    SERVER_NETWORK_BEFORE: 'server.network.before',

    /** Network page (after content) */
    SERVER_NETWORK_AFTER: 'server.network.after',

    /** Startup page (before content) */
    SERVER_STARTUP_BEFORE: 'server.startup.before',

    /** Startup page (after content) */
    SERVER_STARTUP_AFTER: 'server.startup.after',

    /** Settings page (before content) */
    SERVER_SETTINGS_BEFORE: 'server.settings.before',

    /** Settings page (after content) */
    SERVER_SETTINGS_AFTER: 'server.settings.after',

    /** Dashboard header area */
    DASHBOARD_HEADER: 'dashboard.header',

    /** Dashboard below server list */
    DASHBOARD_WIDGETS: 'dashboard.widgets',

    /** Dashboard server list (before) */
    DASHBOARD_SERVERLIST_BEFORE: 'dashboard.serverlist.before',

    /** Dashboard server list (after) */
    DASHBOARD_SERVERLIST_AFTER: 'dashboard.serverlist.after',

    /** Dashboard server row name (before) */
    DASHBOARD_SERVERROW_NAME_BEFORE: 'dashboard.serverrow.name.before',

    /** Dashboard server row name (after) */
    DASHBOARD_SERVERROW_NAME_AFTER: 'dashboard.serverrow.name.after',

    /** Dashboard server row description (before) */
    DASHBOARD_SERVERROW_DESCRIPTION_BEFORE: 'dashboard.serverrow.description.before',

    /** Dashboard server row description (after) */
    DASHBOARD_SERVERROW_DESCRIPTION_AFTER: 'dashboard.serverrow.description.after',

    /** Dashboard server row resource limits */
    DASHBOARD_SERVERROW_LIMITS: 'dashboard.serverrow.limits',

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

    /** Account sub-navigation (before built-ins) */
    ACCOUNT_SUBNAV_BEFORE: 'account.subnav.before',

    /** Account sub-navigation (after built-ins) */
    ACCOUNT_SUBNAV_AFTER: 'account.subnav.after',

    /** Account overview (before content) */
    ACCOUNT_OVERVIEW_BEFORE: 'account.overview.before',

    /** Account overview (after content) */
    ACCOUNT_OVERVIEW_AFTER: 'account.overview.after',

    /** Account API (before content) */
    ACCOUNT_API_BEFORE: 'account.api.before',

    /** Account API (after content) */
    ACCOUNT_API_AFTER: 'account.api.after',

    /** Account SSH (before content) */
    ACCOUNT_SSH_BEFORE: 'account.ssh.before',

    /** Account SSH (after content) */
    ACCOUNT_SSH_AFTER: 'account.ssh.after',

    /** Authentication container (before content) */
    AUTH_CONTAINER_BEFORE: 'auth.container.before',

    /** Authentication container (after content) */
    AUTH_CONTAINER_AFTER: 'auth.container.after',
} as const;

export type SlotId = typeof SLOT_IDS[keyof typeof SLOT_IDS];

export interface SlotDefinition {
    id: SlotId;
    type: 'portal' | 'nav' | 'route';
    description: string;
}

export const SLOT_DEFINITIONS: SlotDefinition[] = slotDefinitions as SlotDefinition[];
