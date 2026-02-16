# IP Manager for WHMCS

IP Manager adds and manages IP subnets and pools, automatically assigns IP addresses to servers, products, add-ons, or configurable options, and integrates with third-party applications (cPanel, DirectAdmin, Plesk, Proxmox, etc.). Customers can view and manage their assigned IPs from the WHMCS client area.

## Requirements

- **WHMCS** 8.10 – 9.x
- **PHP** 8.2 – 8.3
- **Themes**: Six, Twenty-One, Lagom (client area)

## Installation

1. Copy the `modules/addons/ipmanager` folder into your WHMCS installation’s `modules/addons/` directory so that the path is:
   ```
   /path/to/whmcs/modules/addons/ipmanager/
   ```
2. In WHMCS Admin: **Setup → Addon Modules**.
3. Find **IP Manager** and click **Activate**.
4. Set access control (which admin roles can use the module).
5. Click **Configure** to set usage alert threshold, IP cleaner, and custom field options.

## Module Structure

```
modules/addons/ipmanager/
├── ipmanager.php          # Main module (config, activate, output, clientarea)
├── hooks.php              # WHMCS hooks (sidebar, logging)
├── lib/
│   ├── helpers.php        # CIDR/IP helpers
│   └── Schema.php         # Database tables create/drop
├── admin/
│   ├── _menu.php         # Admin sidebar
│   ├── dashboard.php
│   ├── subnets.php       # IP subnets CRUD
│   ├── pools.php
│   ├── configurations.php
│   ├── assignments.php
│   ├── sync.php
│   ├── export.php
│   ├── import.php
│   ├── logs.php
│   ├── settings.php
│   ├── translations.php
│   └── acl.php
├── lang/
│   └── english.php
└── templates/
    ├── clienthome.tpl
    ├── client_unassign.tpl
    └── client_order.tpl
```

## Admin Area

- **Dashboard** – Subnet/pool and IP usage stats, quick actions.
- **IP Subnets** – Add/edit subnets with CIDR (IPv4/IPv6), parent/child tree.
- **IP Pools** – Pools per subnet (stub for full CRUD).
- **Configurations** – Assignment scenarios (products, addons, config options, servers).
- **Assignments** – Assign/unassign IPs to/from services (stub).
- **Synchronize** – Sync WHMCS product IPs with IP Manager (stub).
- **Export / Import** – Subnets/pools in CSV, XML, JSON (stub).
- **Logs** – Module action log.
- **Settings / Translations / ACL** – Stub pages.

## Client Area

- **IP Manager** (`index.php?m=ipmanager`) – List assigned IPs per product/service.
- **Unassign** – Confirm and unassign an IP.
- **Order Additional IPs** – Placeholder for ordering more IPs.

## Database Tables (prefix `mod_ipmanager_`)

| Table                   | Purpose |
|-------------------------|--------|
| subnets                 | IP subnets (CIDR, parent, gateway, excluded IPs) |
| pools                   | Pools within subnets |
| ip_addresses            | Assigned/reserved IPs (free IPs not stored) |
| reservation_rules      | Network/gateway/broadcast reservations |
| configurations         | Assignment scenario names and options |
| configuration_relations| Links configs to products/addons/configoptions/servers |
| assignments             | IP ↔ client/service/addon/configoption |
| custom_fields          | Custom fields for subnet/pool/IP |
| custom_field_values    | Values for custom fields |
| subnet_locks            | Lock subnet/pool to client or service |
| logs                    | Action log |
| acl                     | Staff access control |
| integration_config      | cPanel, DirectAdmin, Plesk, etc. (JSON) |
| usage_alerts            | Subnet usage % and last alert sent |

## Configuration (Addon Module Settings)

- **Usage Alert Threshold (%)** – Email when subnet usage exceeds this %.
- **IP Cleaner Enabled** – Ensure assigned IPs are in use (stub).
- **Use Custom Field Instead Of Assigned IP** – Use custom field when set per config.

## Planned / Stub Features

- Full tree view for subnets (with expand/collapse).
- Subnet save handler (create IP range, apply reservation rules).
- Pool CRUD and link to configurations.
- Assign/unassign from admin product list and from client area (with confirm).
- Sync: read WHMCS product dedicated IP and insert into IP Manager.
- Export/Import CSV, XML, JSON.
- Usage alert cron and email.
- IP cleaner cron.
- Integration modules: cPanel, cPanel Extended, DirectAdmin, Plesk, Proxmox, SolusVM (server module API).
- ACL UI and enforcement.
- Translations UI.

## License

Proprietary.

## Support

Configure and manage from **Addons → IP Manager** in the WHMCS admin area.
