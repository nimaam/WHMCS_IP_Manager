# IP Manager for WHMCS

IP Manager adds and manages IP subnets and pools, automatically assigns IP addresses to services when clients order VPS/cloud (Proxmox, VMware, OpenStack, cPanel, etc.), and integrates with WHMCS and third-party applications. Customers can view and manage their assigned IPs from the client area.

## Requirements

- **WHMCS** 8.10 – 9.x
- **PHP** 8.2 – 8.3
- **Themes**: Six, Twenty-One, Lagom (client area)

---

## Installation

1. Copy the `modules/addons/ipmanager` folder into your WHMCS installation’s `modules/addons/` directory:
   ```
   /path/to/whmcs/modules/addons/ipmanager/
   ```
2. In WHMCS Admin: **Setup → Addon Modules**.
3. Find **IP Manager** and click **Activate**.
4. Set access control (which admin roles can use the module).
5. Click **Configure** to set usage alert threshold, IP cleaner, and custom field options.

---

## Quick start (steps to use IP Manager)

1. **Add subnets** – **Addons → IP Manager → IP Subnets**. Add your CIDR ranges (e.g. `95.90.94.0/24`). Save with "Store free IPs" if you want pre-generated IPs.
2. **Create pools** (optional) – **IP Pools**. Create a pool per subnet (e.g. "Proxmox VPS pool") to group IPs.
3. **Link pool to products/servers** – **Configurations**. Create a configuration, then add **Relations**: choose **Product** (e.g. VPS, VDS) and/or **Server** (e.g. DE-FRE1 cluster, node), and assign the **Pool** (or Subnet).
4. **Sync existing usage** – **Synchronize**. Check "Import only live services" and click **Run Sync** to import current dedicated IPs from WHMCS into IP Manager (only Active/Pending services).
5. **Release orphaned IPs** – **Orphaned**. Release IPs assigned to Suspended/Cancelled/Terminated services so they become free again and are removed from the server (Proxmox, cPanel, etc.).

After this, when a client orders a product linked to a pool, an IP is auto-assigned from that pool and passed to the server module (Proxmox VE, VMware, etc.).

---

## Features and steps

### 1. IP Subnets

- **Addons → IP Manager → IP Subnets**
- Add subnets with **CIDR** (IPv4 or IPv6), optional parent, gateway, excluded IPs.
- **Reservation rules**: reserve network/gateway/broadcast addresses.
- **Store free IPs**: optionally pre-populate IP addresses in the database (max 65536 for IPv4).
- Saving a subnet generates the IP range and applies reservation rules.

### 2. IP Pools

- **Addons → IP Manager → IP Pools**
- Create pools linked to a subnet (e.g. "Proxmox VPS pool" for `95.90.94.0/24`).
- Pools group IPs for assignment to products or servers.

### 3. Configurations and relations

- **Addons → IP Manager → Configurations**
- Create a configuration (e.g. "Proxmox VPS/VDS") with options: omit dedicated IP field, use custom field instead of Assigned IP.
- **Relations**: link a **Pool** (or **Subnet**) to:
  - **Product** – e.g. VPS, VDS (any order for that product gets an IP from that pool).
  - **Server** – e.g. DE-FRE1 cluster, frankfurt1 node (each WHMCS server = one cluster or node).
  - **Addon** or **Config Option** – for add-on or configurable-option-based assignment.
- One relation = one target (product or server) + one pool/subnet. Add multiple relations to use the same pool for several products/servers.

### 4. Auto-assign IP when a service is provisioned

- When a client orders a product (e.g. VPS) and the server module (Proxmox VE, VMware WGS, OpenStack, cPanel, etc.) runs **CreateAccount**, IP Manager:
  1. **PreModuleCreate** hook runs first: assigns an IP from the pool linked to that product/server, sets `tblhosting.dedicatedip`, and returns the IP to WHMCS so the module receives it in its params.
  2. **AfterModuleCreate** hook runs: pushes the IP to the server via the configured integration (Proxmox, cPanel, etc.) if the module did not apply it at creation.
- **Requirement**: The product or server must be linked to a pool/subnet in **Configurations**, and the pool must have free IPs.

### 5. Synchronize from WHMCS database (import existing IPs)

- **Addons → IP Manager → Synchronize**
- Reads `tblhosting` for services that have a **dedicated IP** set.
- **Import only live services (Active and Pending)** (recommended, default checked):
  - Only services with status **Active** or **Pending** are imported. Suspended, Cancelled, Terminated are **skipped** so no orphans or dead data are imported.
- Uncheck to run a full sync (all statuses); use only if you need to import every dedicated IP regardless of status.
- For each row: if the IP exists in a subnet, it is created/updated and assigned to the service; if not, it is skipped (error listed). Already correct assignments are skipped.

### 6. Orphaned assignments (release IPs from suspended/cancelled services)

- **Addons → IP Manager → Orphaned**
- Lists IPs that are **assigned in IP Manager** to services whose WHMCS status is **not** Active or Pending (e.g. Suspended, Cancelled, Terminated).
- **Release** (per row) or **Release all**:
  1. Calls the integration to **remove the IP from the server** (Proxmox, cPanel, etc.) so the provider marks the IP as free.
  2. Unassigns in IP Manager (deletes assignment, sets IP status to free).
  3. Clears `tblhosting.dedicatedip` for that service.
  4. Pushes unassign to NetBox if enabled.
- Use this to fix cases where an IP still appears "in use" on the provider (e.g. Proxmox) but the service is suspended, and to reclaim IPs for reuse.

### 7. Assignments list and service status

- **Addons → IP Manager → Assignments**
- View services with assigned IPs, WHMCS dedicated IP, and **Service status** (Active, Pending, Suspended, Cancelled, etc.).
- Assign an IP from a pool/subnet to a service or **Unassign** (unassign also runs the integration to remove the IP from the server).
- Filter by Client ID or Service ID.

### 8. Integrations (push/remove IP on server)

- **Addons → IP Manager → Integrations**
- Enable the integration that matches your server module type (cPanel, DirectAdmin, Plesk, Proxmox, SolusVM, etc.).
- When you **assign** an IP to a service, IP Manager can **add** the IP on the server (e.g. add to cPanel account or Proxmox VM).
- When you **unassign** (or release from Orphaned), IP Manager calls **removeIpFromAccount** so the server frees the IP. This keeps the provider in sync and avoids "IP shows as free in one place but in use elsewhere" (e.g. suspended service with blank name).
- Integration implementations under `lib/integrations/` may be stubs; implement `addIpToAccount` and `removeIpFromAccount` for your provider.

### 9. IP Cleaner (cron)

- **Addons → Addon Modules → IP Manager → Configure**: **IP Cleaner Enabled**, **IP Cleaner Behavior** (Notify only / Mark free).
- **IP Cleaner** finds assignments where the service's **dedicated IP in WHMCS** no longer matches the assigned IP (e.g. changed outside IP Manager). **Notify only**: email report. **Mark free**: unassign and set IP to free, then email.
- **Addons → IP Manager → Settings**: run **Run IP Cleaner now** manually.
- Cron: run `modules/addons/ipmanager/cron/usage_alerts.php` (e.g. daily). It runs usage alerts and, if IP Cleaner is enabled, the cleaner with the configured behavior.

### 10. Usage alerts

- Configure **Usage Alert Threshold (%)** in module config (e.g. 80).
- Cron runs usage checks; when subnet/pool usage exceeds the threshold, an email is sent to the first admin. You can also run the check from **Settings**.

### 11. IPAM (NetBox)

- **Addons → IP Manager → IPAM (NetBox)**
- Configure NetBox URL and API token. **Sync from NetBox**: pull prefixes as subnets and IP addresses; status mapped to assigned/reserved/free.
- **Push to NetBox when assigning IP**: on assign, create/update IP in NetBox (active); on unassign, set to deprecated.
- Optional filters: Site ID, Tenant ID.

### 12. Export / Import

- **Export**: subnets and pools to CSV, XML, or JSON.
- **Import**: create subnets and pools from CSV, XML, or JSON (e.g. exported from this module).

### 13. Client area

- **IP Manager** (`index.php?m=ipmanager`): list assigned IPs per product/service.
- **Unassign**: confirm and unassign an IP (also removes from server via integration and clears WHMCS dedicated IP).
- **Order Additional IPs**: placeholder for ordering more IPs.

---

## Module structure

```
modules/addons/ipmanager/
├── ipmanager.php          # Main module (config, activate, output, clientarea)
├── hooks.php              # PreModuleCreate, AfterModuleCreate, ClientArea sidebar
├── lib/
│   ├── helpers.php        # Assign/unassign, sync, integration add/remove, CIDR
│   ├── Schema.php         # Database tables
│   ├── IpCleaner.php      # Orphan detection (dedicatedip mismatch)
│   ├── UsageAlerts.php
│   ├── ipam/              # NetBox client, sync, push
│   └── integrations/      # cPanel, DirectAdmin, Plesk, Proxmox, SolusVM
├── admin/
│   ├── _menu.php
│   ├── dashboard.php, subnets.php, pools.php, configurations.php
│   ├── assignments.php    # List + assign/unassign, service status column
│   ├── sync.php           # Sync from WHMCS (live-only option)
│   ├── orphans.php        # Orphaned assignments, release one/all
│   ├── export.php, import.php, logs.php, settings.php
│   ├── integrations.php, ipam.php
│   └── ...
├── cron/
│   └── usage_alerts.php   # Usage alerts + IP Cleaner
├── lang/english.php
└── templates/
```

---

## Database tables (prefix `mod_ipmanager_`)

| Table                   | Purpose |
|-------------------------|--------|
| subnets                 | IP subnets (CIDR, parent, gateway, excluded, reservation) |
| pools                   | Pools within subnets |
| ip_addresses             | IPs (free/assigned/reserved) |
| reservation_rules        | Network/gateway/broadcast reservations |
| configurations          | Scenario names and options |
| configuration_relations  | Links configs to products/addons/configoptions/servers + pool/subnet |
| assignments             | IP ↔ client/service |
| integration_config       | Enabled server types (cPanel, Proxmox, etc.) |
| usage_alerts            | Subnet usage % and last alert |
| logs                    | Action log |
| ipam_mapping            | NetBox sync mapping |
| ...                     | custom_fields, subnet_locks, acl, etc. |

---

## Configuration (Addon Module Settings)

- **Usage Alert Threshold (%)** – Email when subnet usage exceeds this %.
- **IP Cleaner Enabled** – Run cleaner in cron (notify or mark free).
- **IP Cleaner Behavior** – Notify only / Mark free.
- **Use Custom Field Instead Of Assigned IP** – Use custom field when set per config.

---

## Summary of workflows

| Goal | Where | Action |
|------|--------|--------|
| Use a subnet for VPS products | Configurations | Add relation Product → VPS (and VDS, etc.) → Pool |
| Use a subnet for specific servers/nodes | Configurations | Add relation Server → DE-FRE1 (and nodes) → Pool |
| Import existing WHMCS IPs without orphans | Synchronize | Check "Import only live services", Run Sync |
| Free IPs from suspended/cancelled services | Orphaned | Release one or Release all |
| Ensure server (Proxmox, etc.) frees the IP on unassign | Integrations | Enable Proxmox (etc.); unassign/release calls removeIpFromAccount |
| Auto-assign IP on new VPS order | (automatic) | Link product/server to pool in Configurations; PreModuleCreate does the rest |

---

## License

Proprietary.

## Support

Configure and manage from **Addons → IP Manager** in the WHMCS admin area.
