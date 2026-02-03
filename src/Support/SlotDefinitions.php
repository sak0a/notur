<?php

declare(strict_types=1);

namespace Notur\Support;

final class SlotDefinitions
{
    private const JSON_PATH = __DIR__ . '/../../bridge/src/slots/slot-definitions.json';

    /**
     * Canonical slot definitions used by the admin UI.
     *
     * Keep this in sync with bridge/src/slots/SlotDefinitions.ts.
     *
     * @return array<int, array{id: string, type: string, description: string}>
     */
    public static function all(): array
    {
        $fromJson = self::loadFromJson();
        if ($fromJson !== null) {
            return $fromJson;
        }

        return [
            ['id' => 'navbar', 'type' => 'portal', 'description' => 'Top navigation bar'],
            ['id' => 'navbar.left', 'type' => 'portal', 'description' => 'Navbar left area (near logo)'],
            ['id' => 'navbar.before', 'type' => 'portal', 'description' => 'Navbar items (before built-ins)'],
            ['id' => 'navbar.after', 'type' => 'portal', 'description' => 'Navbar items (after built-ins)'],
            ['id' => 'server.subnav', 'type' => 'nav', 'description' => 'Server sub-navigation'],
            ['id' => 'server.subnav.before', 'type' => 'nav', 'description' => 'Server sub-navigation (before built-ins)'],
            ['id' => 'server.subnav.after', 'type' => 'nav', 'description' => 'Server sub-navigation (after built-ins)'],
            ['id' => 'server.header', 'type' => 'portal', 'description' => 'Server header area'],
            ['id' => 'server.page', 'type' => 'route', 'description' => 'Server area page'],
            ['id' => 'server.footer', 'type' => 'portal', 'description' => 'Server footer area'],
            ['id' => 'server.terminal.buttons', 'type' => 'portal', 'description' => 'Terminal power buttons'],
            ['id' => 'server.console.header', 'type' => 'portal', 'description' => 'Console page header'],
            ['id' => 'server.console.info.before', 'type' => 'portal', 'description' => 'Console info (before details)'],
            ['id' => 'server.console.info.after', 'type' => 'portal', 'description' => 'Console info (after details)'],
            ['id' => 'server.console.sidebar', 'type' => 'portal', 'description' => 'Console sidebar area'],
            ['id' => 'server.console.command', 'type' => 'portal', 'description' => 'Console command row'],
            ['id' => 'server.console.footer', 'type' => 'portal', 'description' => 'Console page footer'],
            ['id' => 'server.files.actions', 'type' => 'portal', 'description' => 'File manager toolbar'],
            ['id' => 'server.files.header', 'type' => 'portal', 'description' => 'File manager header'],
            ['id' => 'server.files.footer', 'type' => 'portal', 'description' => 'File manager footer'],
            ['id' => 'server.files.dropdown', 'type' => 'portal', 'description' => 'File manager dropdown items'],
            ['id' => 'server.files.edit.before', 'type' => 'portal', 'description' => 'File editor (before content)'],
            ['id' => 'server.files.edit.after', 'type' => 'portal', 'description' => 'File editor (after content)'],
            ['id' => 'server.databases.before', 'type' => 'portal', 'description' => 'Databases page (before content)'],
            ['id' => 'server.databases.after', 'type' => 'portal', 'description' => 'Databases page (after content)'],
            ['id' => 'server.schedules.before', 'type' => 'portal', 'description' => 'Schedules list (before content)'],
            ['id' => 'server.schedules.after', 'type' => 'portal', 'description' => 'Schedules list (after content)'],
            ['id' => 'server.schedules.edit.before', 'type' => 'portal', 'description' => 'Schedule editor (before content)'],
            ['id' => 'server.schedules.edit.after', 'type' => 'portal', 'description' => 'Schedule editor (after content)'],
            ['id' => 'server.users.before', 'type' => 'portal', 'description' => 'Users page (before content)'],
            ['id' => 'server.users.after', 'type' => 'portal', 'description' => 'Users page (after content)'],
            ['id' => 'server.backups.before', 'type' => 'portal', 'description' => 'Backups page (before content)'],
            ['id' => 'server.backups.after', 'type' => 'portal', 'description' => 'Backups page (after content)'],
            ['id' => 'server.backups.dropdown', 'type' => 'portal', 'description' => 'Backup row dropdown items'],
            ['id' => 'server.network.before', 'type' => 'portal', 'description' => 'Network page (before content)'],
            ['id' => 'server.network.after', 'type' => 'portal', 'description' => 'Network page (after content)'],
            ['id' => 'server.startup.before', 'type' => 'portal', 'description' => 'Startup page (before content)'],
            ['id' => 'server.startup.after', 'type' => 'portal', 'description' => 'Startup page (after content)'],
            ['id' => 'server.settings.before', 'type' => 'portal', 'description' => 'Settings page (before content)'],
            ['id' => 'server.settings.after', 'type' => 'portal', 'description' => 'Settings page (after content)'],
            ['id' => 'dashboard.header', 'type' => 'portal', 'description' => 'Dashboard header area'],
            ['id' => 'dashboard.widgets', 'type' => 'portal', 'description' => 'Dashboard widgets'],
            ['id' => 'dashboard.serverlist.before', 'type' => 'portal', 'description' => 'Dashboard server list (before)'],
            ['id' => 'dashboard.serverlist.after', 'type' => 'portal', 'description' => 'Dashboard server list (after)'],
            ['id' => 'dashboard.serverrow.name.before', 'type' => 'portal', 'description' => 'Dashboard server row name (before)'],
            ['id' => 'dashboard.serverrow.name.after', 'type' => 'portal', 'description' => 'Dashboard server row name (after)'],
            ['id' => 'dashboard.serverrow.description.before', 'type' => 'portal', 'description' => 'Dashboard server row description (before)'],
            ['id' => 'dashboard.serverrow.description.after', 'type' => 'portal', 'description' => 'Dashboard server row description (after)'],
            ['id' => 'dashboard.serverrow.limits', 'type' => 'portal', 'description' => 'Dashboard server row resource limits'],
            ['id' => 'dashboard.footer', 'type' => 'portal', 'description' => 'Dashboard footer area'],
            ['id' => 'dashboard.page', 'type' => 'route', 'description' => 'Dashboard page'],
            ['id' => 'account.header', 'type' => 'portal', 'description' => 'Account header area'],
            ['id' => 'account.page', 'type' => 'route', 'description' => 'Account page'],
            ['id' => 'account.footer', 'type' => 'portal', 'description' => 'Account footer area'],
            ['id' => 'account.subnav', 'type' => 'nav', 'description' => 'Account sub-navigation'],
            ['id' => 'account.subnav.before', 'type' => 'nav', 'description' => 'Account sub-navigation (before built-ins)'],
            ['id' => 'account.subnav.after', 'type' => 'nav', 'description' => 'Account sub-navigation (after built-ins)'],
            ['id' => 'account.overview.before', 'type' => 'portal', 'description' => 'Account overview (before content)'],
            ['id' => 'account.overview.after', 'type' => 'portal', 'description' => 'Account overview (after content)'],
            ['id' => 'account.api.before', 'type' => 'portal', 'description' => 'Account API (before content)'],
            ['id' => 'account.api.after', 'type' => 'portal', 'description' => 'Account API (after content)'],
            ['id' => 'account.ssh.before', 'type' => 'portal', 'description' => 'Account SSH (before content)'],
            ['id' => 'account.ssh.after', 'type' => 'portal', 'description' => 'Account SSH (after content)'],
            ['id' => 'auth.container.before', 'type' => 'portal', 'description' => 'Authentication container (before content)'],
            ['id' => 'auth.container.after', 'type' => 'portal', 'description' => 'Authentication container (after content)'],
        ];

    }

    /**
     * @return array<string, array{id: string, type: string, description: string}>
     */
    public static function map(): array
    {
        $map = [];
        foreach (self::all() as $def) {
            $map[$def['id']] = $def;
        }
        return $map;
    }

    /**
     * @return array<int, array{id: string, type: string, description: string}>|null
     */
    private static function loadFromJson(): ?array
    {
        if (!file_exists(self::JSON_PATH)) {
            return null;
        }

        $content = file_get_contents(self::JSON_PATH);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }
}
