<?php

declare(strict_types=1);

namespace Notur\Support;

final class SlotDefinitions
{
    /**
     * Canonical slot definitions used by the admin UI.
     *
     * Keep this in sync with bridge/src/slots/SlotDefinitions.ts.
     *
     * @return array<int, array{id: string, type: string, description: string}>
     */
    public static function all(): array
    {
        return [
            ['id' => 'navbar', 'type' => 'portal', 'description' => 'Top navigation bar'],
            ['id' => 'navbar.left', 'type' => 'portal', 'description' => 'Navbar left area (near logo)'],
            ['id' => 'server.subnav', 'type' => 'nav', 'description' => 'Server sub-navigation'],
            ['id' => 'server.header', 'type' => 'portal', 'description' => 'Server header area'],
            ['id' => 'server.page', 'type' => 'route', 'description' => 'Server area page'],
            ['id' => 'server.footer', 'type' => 'portal', 'description' => 'Server footer area'],
            ['id' => 'server.terminal.buttons', 'type' => 'portal', 'description' => 'Terminal power buttons'],
            ['id' => 'server.console.header', 'type' => 'portal', 'description' => 'Console page header'],
            ['id' => 'server.console.sidebar', 'type' => 'portal', 'description' => 'Console sidebar area'],
            ['id' => 'server.console.footer', 'type' => 'portal', 'description' => 'Console page footer'],
            ['id' => 'server.files.actions', 'type' => 'portal', 'description' => 'File manager toolbar'],
            ['id' => 'server.files.header', 'type' => 'portal', 'description' => 'File manager header'],
            ['id' => 'server.files.footer', 'type' => 'portal', 'description' => 'File manager footer'],
            ['id' => 'dashboard.header', 'type' => 'portal', 'description' => 'Dashboard header area'],
            ['id' => 'dashboard.widgets', 'type' => 'portal', 'description' => 'Dashboard widgets'],
            ['id' => 'dashboard.serverlist.before', 'type' => 'portal', 'description' => 'Dashboard server list (before)'],
            ['id' => 'dashboard.serverlist.after', 'type' => 'portal', 'description' => 'Dashboard server list (after)'],
            ['id' => 'dashboard.footer', 'type' => 'portal', 'description' => 'Dashboard footer area'],
            ['id' => 'dashboard.page', 'type' => 'route', 'description' => 'Dashboard page'],
            ['id' => 'account.header', 'type' => 'portal', 'description' => 'Account header area'],
            ['id' => 'account.page', 'type' => 'route', 'description' => 'Account page'],
            ['id' => 'account.footer', 'type' => 'portal', 'description' => 'Account footer area'],
            ['id' => 'account.subnav', 'type' => 'nav', 'description' => 'Account sub-navigation'],
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
}
