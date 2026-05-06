# Slot Reference

Slot IDs are the frontend mount points exposed by the Notur bridge. Use them in `createExtension({ slots: [...] })`.

```tsx
createExtension({
  id: 'acme/red-button',
  slots: [{ slot: 'server.header', component: RedButton }],
});
```

| Slot ID | Type | Description |
|---|---|---|
| `navbar` | portal | Top navigation bar |
| `navbar.left` | portal | Navbar left area near logo |
| `navbar.before` | portal | Navbar items before built-ins |
| `navbar.after` | portal | Navbar items after built-ins |
| `server.subnav` | nav | Server sub-navigation |
| `server.subnav.before` | nav | Server sub-navigation before built-ins |
| `server.subnav.after` | nav | Server sub-navigation after built-ins |
| `server.header` | portal | Server header area |
| `server.page` | route | Server area page |
| `server.footer` | portal | Server footer area |
| `server.terminal.buttons` | portal | Terminal power buttons |
| `server.console.header` | portal | Console page header |
| `server.console.info.before` | portal | Console info before details |
| `server.console.info.after` | portal | Console info after details |
| `server.console.sidebar` | portal | Console sidebar area |
| `server.console.command` | portal | Console command row |
| `server.console.footer` | portal | Console page footer |
| `server.files.actions` | portal | File manager toolbar |
| `server.files.header` | portal | File manager header |
| `server.files.footer` | portal | File manager footer |
| `server.files.dropdown` | portal | File manager dropdown items |
| `server.files.edit.before` | portal | File editor before content |
| `server.files.edit.after` | portal | File editor after content |
| `server.databases.before` | portal | Databases page before content |
| `server.databases.after` | portal | Databases page after content |
| `server.schedules.before` | portal | Schedules list before content |
| `server.schedules.after` | portal | Schedules list after content |
| `server.schedules.edit.before` | portal | Schedule editor before content |
| `server.schedules.edit.after` | portal | Schedule editor after content |
| `server.users.before` | portal | Users page before content |
| `server.users.after` | portal | Users page after content |
| `server.backups.before` | portal | Backups page before content |
| `server.backups.after` | portal | Backups page after content |
| `server.backups.dropdown` | portal | Backup row dropdown items |
| `server.network.before` | portal | Network page before content |
| `server.network.after` | portal | Network page after content |
| `server.startup.before` | portal | Startup page before content |
| `server.startup.after` | portal | Startup page after content |
| `server.settings.before` | portal | Settings page before content |
| `server.settings.after` | portal | Settings page after content |
| `dashboard.header` | portal | Dashboard header area |
| `dashboard.widgets` | portal | Dashboard widgets |
| `dashboard.serverlist.before` | portal | Dashboard server list before |
| `dashboard.serverlist.after` | portal | Dashboard server list after |
| `dashboard.serverrow.name.before` | portal | Dashboard server row name before |
| `dashboard.serverrow.name.after` | portal | Dashboard server row name after |
| `dashboard.serverrow.description.before` | portal | Dashboard server row description before |
| `dashboard.serverrow.description.after` | portal | Dashboard server row description after |
| `dashboard.serverrow.limits` | portal | Dashboard server row resource limits |
| `dashboard.footer` | portal | Dashboard footer area |
| `dashboard.page` | route | Dashboard page |
| `account.header` | portal | Account header area |
| `account.page` | route | Account page |
| `account.footer` | portal | Account footer area |
| `account.subnav` | nav | Account sub-navigation |
| `account.subnav.before` | nav | Account sub-navigation before built-ins |
| `account.subnav.after` | nav | Account sub-navigation after built-ins |
| `account.overview.before` | portal | Account overview before content |
| `account.overview.after` | portal | Account overview after content |
| `account.api.before` | portal | Account API before content |
| `account.api.after` | portal | Account API after content |
| `account.ssh.before` | portal | Account SSH before content |
| `account.ssh.after` | portal | Account SSH after content |
| `auth.container.before` | portal | Authentication container before content |
| `auth.container.after` | portal | Authentication container after content |
