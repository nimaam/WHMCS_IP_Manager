# IP Manager for WHMCS

**Complete usage guide**

IP Manager adds and manages IP subnets and pools, automatically assigns IP addresses to services when clients order VPS/cloud (Proxmox, VMware, OpenStack, cPanel, etc.), and integrates with WHMCS and third-party applications. Customers can view and manage their assigned IPs from the client area.

---

## Table of contents

1. [Requirements](#1-requirements)
2. [Installation](#2-installation)
3. [Module configuration (global settings)](#3-module-configuration-global-settings)
4. [Admin area overview](#4-admin-area-overview)
5. [Dashboard](#5-dashboard)
6. [IP Subnets](#6-ip-subnets)
7. [IP Pools](#7-ip-pools)
8. [Configurations](#8-configurations)
9. [Assignments](#9-assignments)
10. [Synchronize](#10-synchronize)
11. [Orphaned assignments](#11-orphaned-assignments)
12. [Export](#12-export)
13. [Import](#13-import)
14. [Logs](#14-logs)
15. [Settings](#15-settings)
16. [Integrations](#16-integrations)
17. [IPAM (NetBox)](#17-ipam-netbox)
18. [Client area](#18-client-area)
19. [Cron job](#19-cron-job)
20. [Database tables](#20-database-tables)
21. [Workflows summary](#21-workflows-summary)

---

## 1. Requirements

- **WHMCS** 8.10 – 9.x  
- **PHP** 8.2 – 8.3  
- **Client area themes**: Six, Twenty-One, Lagom (or compatible)

---

## 2. Installation

1. Copy the entire folder `modules/addons/ipmanager` into your WHMCS installation so the path is:
   ```
   /path/to/whmcs/modules/addons/ipmanager/
   ```
2. In WHMCS Admin go to **Setup → Addon Modules**.
3. Find **IP Manager** in the list and click **Activate**.
4. Set **Access Control**: choose which admin roles can use the module (e.g. Full Administrator).
5. Click **Configure** to set the global options (see next section).

After activation, the module creates its database tables (prefix `mod_ipmanager_`) and registers its hooks. You can now open **Addons → IP Manager** in the admin menu.

---

## 3. Module configuration (global settings)

**Where:** **Setup → Addon Modules** → find **IP Manager** → **Configure**.

These settings apply to the whole module:

| Setting | Description |
|--------|--------------|
| **Usage Alert Threshold (%)** | When subnet/pool usage exceeds this percentage, an email is sent to the first admin (e.g. 80). Used by the usage alerts feature and cron. |
| **IP Cleaner Enabled** | Yes/No. When **Yes**, the IP Cleaner runs with the cron job (see [Settings](#15-settings) and [Cron job](#19-cron-job)). |
| **IP Cleaner Behavior** | **Notify only**: only send an email report when assigned IPs are no longer in use. **Mark free**: unassign those IPs and mark them free, then send the report. |
| **Use Custom Field Instead Of Assigned IP** | Yes/No. When enabled and configured per configuration, the module can use a product custom field for the assigned IP instead of WHMCS’s standard dedicated IP field. |

Save after changing any value.

---

## 4. Admin area overview

**Where:** **Addons → IP Manager** (in the main admin menu).

The module opens with a **horizontal tab menu** at the top:

- **Dashboard** – Summary and quick links  
- **IP Subnets** – Define IP ranges (CIDR)  
- **IP Pools** – Group IPs per subnet for assignment  
- **Configurations** – Link pools to products/servers  
- **Assignments** – View and assign/unassign IPs per service  
- **Synchronize** – Import existing WHMCS dedicated IPs  
- **Orphaned** – Release IPs from suspended/cancelled services  
- **Export** – Export subnets/pools (CSV, XML, JSON)  
- **Import** – Import subnets/pools  
- **Logs** – Module action log  
- **Settings** – Run usage alerts and IP Cleaner manually, cron info  
- **Translations** – Language customisation  
- **ACL** – Access control (if used)  
- **Integrations** – Enable server types (cPanel, Proxmox, etc.)  
- **IPAM (NetBox)** – NetBox sync and push

The following sections explain each part in order.

---

## 5. Dashboard

**Where:** **Addons → IP Manager** (default first tab).

**What it shows:**

- **IP Subnets** – Total number of subnets.
- **IP Pools** – Total number of pools.
- **Assigned IPs** – Number of IP addresses currently assigned to services.
- **Free IPs** – Number of IP addresses with status “free” (only counted if you use “Store free IPs” on subnets).

**Quick actions** (links):

- Add IP Subnet  
- Manage Configurations  
- Sync with WHMCS  

Use the dashboard to see at a glance how many subnets, pools, and IPs you have and to jump to common tasks.

---

## 6. IP Subnets

**Where:** **Addons → IP Manager → IP Subnets**.

Subnets are the base IP ranges (e.g. `79.133.56.0/24`) from which IPs are taken. You must add at least one subnet before creating pools or assigning IPs.

### 6.1 Subnet list

- Lists all subnets (name, CIDR, gateway, etc.).
- Use **Add IP Subnet** (or equivalent) to create a new one.
- Click **Edit** on a row to change an existing subnet.

### 6.2 Add / Edit subnet form

| Field | Description |
|-------|-------------|
| **Name** | Label for this subnet (e.g. “subnet 79.133.56.0/24”). |
| **CIDR** | Network in CIDR notation, e.g. `79.133.56.0/24` (IPv4) or `2001:db8::/32` (IPv6). Must be valid; the module derives the range from it. |
| **Parent** | Optional parent subnet (for hierarchy; can be “None”). |
| **Gateway** | Optional gateway IP (e.g. `79.133.56.1`). |
| **Excluded IPs** | Optional list of IPs to exclude from the range (format depends on implementation, e.g. comma-separated or one per line). |
| **Reserve** | Checkboxes to **reserve** network address, broadcast address, and/or gateway so they are not assigned. |
| **Store free IPs** | If checked, the module pre-populates the database with all (or up to 65536 for IPv4) IPs in the range as “free”. This allows you to see and assign from a fixed set of IPs. If unchecked, free IPs may be generated on demand from the range. |

**Save:** Click **Save**. On **create**, the module generates the IP range, applies reservation rules, and optionally creates the free IP records. On **edit**, it updates name, gateway, excluded IPs, etc., as supported.

### 6.3 After creating a subnet

- You can create **IP Pools** linked to this subnet (next section).
- You can use this subnet (or a pool on it) in **Configurations** and in **Assignments**.

---

## 7. IP Pools

**Where:** **Addons → IP Manager → IP Pools**.

Pools group IPs within a subnet. Configurations link **products** and **servers** to a **pool** (or directly to a subnet), so assignment comes from that pool.

### 7.1 Pool list

- Shows pools with name and linked subnet.
- You can filter by subnet from a dropdown.
- **Add Pool** opens the create form.

### 7.2 Add / Edit pool

| Field | Description |
|-------|-------------|
| **Name** | Label (e.g. “79.133.56.0/24” or “Proxmox VPS pool”). |
| **Subnet** | The subnet this pool belongs to (required). |

Save to create or update the pool. IPs in the subnet (or generated from it) can then be associated with this pool for assignment.

---

## 8. Configurations

**Where:** **Addons → IP Manager → Configurations**.

Configurations define **which products and servers** get IPs from **which pool (or subnet)**. They are used both when **creating** a new configuration and when **editing** an existing one.

### 8.1 Configuration list

- Lists all configurations by **Name**.
- **Add Configuration** opens the create form.
- **Edit** opens the edit form for that configuration.

### 8.2 Create configuration (Add)

When you click **Add Configuration**, you see one form that includes:

**Step 1 – Basic settings**

| Field | Description |
|-------|-------------|
| **Name** | Name of the configuration (e.g. “Proxmox VPS/VDS” or “Basic Windows VPS”). |
| **Omit dedicated IP field** | If checked, the dedicated IP field can be omitted in contexts where the module controls the IP. |
| **Use custom field instead of Assigned IP** | Use a product custom field for the assigned IP when set (requires custom field name below). |
| **Custom field name** | Name of the product custom field to use when “Use custom field instead of Assigned IP” is enabled. |

**Step 2 – Assign pool to products and servers** (same page, below)

- **Pool** – Dropdown: choose the pool that will supply IPs (e.g. “79.133.56.0/24”).
- **Subnet** – Dropdown: alternatively or in addition, you can choose a subnet (at least one of Pool or Subnet must be selected when saving relations).
- **Products** – List of checkboxes, one per WHMCS product. Check every product that should receive an IP from the selected pool (e.g. “Basic Windows VPS”, “VDS”).
- **Servers (clusters / nodes)** – List of checkboxes, one per WHMCS server (from **Setup → Products/Services → Servers**). Check every server (cluster or node) that should use this pool. Leave all unchecked if any server is fine (e.g. when linking only by product).

**Submit:** Click **Create configuration**. The module:

1. Creates the configuration with the name and options.
2. If you selected at least one Pool or Subnet and at least one Product or Server, it creates relations for each selected product and each selected server linked to that pool/subnet.

You are then redirected to the **edit** page of the new configuration.

### 8.3 Edit configuration

When you **Edit** an existing configuration:

**Part 1 – Basic settings (first form)**

- Same fields as create: Name, Omit dedicated IP field, Use custom field, Custom field name.
- **Save** and **Cancel** save only these basic settings and stay on the same page.

**Part 2 – Assign pool to products and servers (second form)**

- **Pool** and **Subnet** – Pre-filled from current relations if they exist.
- **Products** – Checkboxes; already-linked products are pre-checked.
- **Servers (clusters / nodes)** – Checkboxes; already-linked servers are pre-checked.
- **Save products and servers selection** – Saves the current choice of pool/subnet and selected products/servers. All existing product and server relations for this configuration are replaced by the new selection.

**Part 3 – Relations table**

- Table of all relations: **Type** (product, server, addon, configoption), **Target** (name), **Pool / Subnet**.
- **Delete** per row to remove a single relation.

**Part 4 – Add single relation (optional)**

- For **Addon** or **Config Option** (or one-off product/server), you can add a single relation:
  - **Type**: Product, Addon, Config Option, or Server.
  - **Target**: choose from the dropdown (populated by type).
  - **Pool** or **Subnet**: choose one.
  - **Add** to create one relation.

### 8.4 How configurations are used

- When a client **orders a product** that is linked (via a configuration relation) to a pool or subnet, the **PreModuleCreate** hook runs and assigns an IP from that pool/subnet to the service and sets WHMCS **dedicated IP**.
- If the product is linked to **multiple** configurations (e.g. via different relations), the module uses the first matching relation (e.g. by product or by server).
- **Servers** in WHMCS are the same as “clusters” or “nodes” in the UI: each entry under **Setup → Servers** is one server; you select one or more to restrict which servers use the pool.

---

## 9. Assignments

**Where:** **Addons → IP Manager → Assignments**.

This page shows WHMCS services and their IP assignment status, and lets you assign or unassign IPs manually.

### 9.1 List

- **Service ID**, **Client**, **Product**, **Service status** (Active, Pending, Suspended, Cancelled, etc.), **Assigned IPs** (from IP Manager), **WHMCS dedicatedip**, **Actions**.
- **Service status** comes from WHMCS (`domainstatus`). Green label for Active/Pending, grey for others.
- List is typically limited (e.g. last 500 services); use filters to narrow.

### 9.2 Filters

- **Client ID** – Restrict to one client.
- **Service ID** – Restrict to one service.
- Click **Filter** to apply.

### 9.3 Assign an IP

- In the row of the service, use the **Pool** and **Subnet** dropdowns (choose one), then click **Assign**.
- The module picks a free IP from the selected pool or subnet, creates the assignment, updates **tblhosting.dedicatedip**, and (if integrations are enabled) can push the IP to the server (cPanel, Proxmox, etc.).

### 9.4 Unassign an IP

- Click **Unassign** next to the IP in that service’s row.
- The module removes the assignment, sets the IP to “free”, clears WHMCS dedicated IP, and (if enabled) calls the integration to remove the IP from the server and optionally updates NetBox.

---

## 10. Synchronize

**Where:** **Addons → IP Manager → Synchronize**.

This imports **existing** dedicated IPs from WHMCS into IP Manager so that already-provisioned services are reflected in the module.

### 10.1 What it does

- Reads **tblhosting** for rows that have **dedicatedip** set.
- For each such service (optionally filtered by status, see below):
  - If the IP already exists in IP Manager (in any subnet), it is assigned to that service if not already.
  - If the IP does not exist, the module finds a subnet that contains the IP (by range); if found, it creates the IP record and the assignment. If no subnet contains the IP, the row is skipped and an error is listed.

### 10.2 Options

- **Import only live services (Active and Pending)** – **Checked (recommended):** only services with status **Active** or **Pending** are processed. Suspended, Cancelled, Terminated, etc. are skipped so you do not import orphans.
- **Unchecked:** all services with a dedicated IP are processed (full sync). Use only if you need to import every dedicated IP regardless of status.

### 10.3 Run Sync

- Click **Run Sync**.
- Result message shows how many were synced, how many IPs were created, how many skipped, and any errors (e.g. “no matching subnet”).

Use this after you have added subnets and optionally pools, so that existing WHMCS usage is reflected in IP Manager.

---

## 11. Orphaned assignments

**Where:** **Addons → IP Manager → Orphaned**.

“Orphaned” here means: IPs that are **assigned in IP Manager** to a service whose WHMCS status is **not** Active or Pending (e.g. Suspended, Cancelled, Terminated). These IPs are still tied to inactive services and can be released so they become free again.

### 11.1 List

- Table: **IP**, **Service ID**, **Client**, **Product**, **Service status**, **Actions**.
- Only assignments where the service status is not “Active” or “Pending” are listed.

### 11.2 Release one IP

- Click **Release** on the row.
- Confirm. The module will:
  1. Call the integration to **remove the IP from the server** (Proxmox, cPanel, etc.) so the provider marks it free.
  2. Unassign in IP Manager (delete assignment, set IP status to free).
  3. Clear **tblhosting.dedicatedip** for that service.
  4. If NetBox push is enabled, update NetBox (e.g. set IP to deprecated).

### 11.3 Release all

- Click **Release all** and confirm.
- The same steps are applied to every listed assignment.

Use this to fix cases where an IP still appears in use on the provider (e.g. Proxmox) for a suspended service, and to reclaim IPs for reuse.

---

## 12. Export

**Where:** **Addons → IP Manager → Export**.

- Exports **subnets** and **pools** to a file.
- **Format:** CSV, XML, or JSON (choose one).
- Use for backup or for importing into another system or another WHMCS with IP Manager.

---

## 13. Import

**Where:** **Addons → IP Manager → Import**.

- Imports subnets and pools from a previously **exported** file (CSV, XML, or JSON from this module) or a compatible format.
- **Warning:** Import creates new records; parent IDs may be remapped. Best used with files exported from this module.
- Choose format and file, then run **Import**.

---

## 14. Logs

**Where:** **Addons → IP Manager → Logs**.

- Lists **module actions** (e.g. assignments, sync, integration calls) with date, details, and optionally admin/client.
- Use it to audit what the module has done.

---

## 15. Settings

**Where:** **Addons → IP Manager → Settings**.

This page does **not** change the global module configuration (that stays under **Setup → Addon Modules → IP Manager → Configure**). It lets you **run** some tasks manually and see cron information.

### 15.1 Usage alerts

- **Run usage check now** – Runs the usage alert check immediately. If any subnet/pool exceeds the **Usage Alert Threshold (%)** set in module config, an email is sent to the first admin.
- **Cron:** You can schedule the same check via cron (see [Cron job](#19-cron-job)).

### 15.2 IP Cleaner

- **Behavior** is read from module config (Notify only / Mark free).
- **Run IP Cleaner now** – Runs the IP Cleaner once. It finds assignments where the service’s **dedicated IP in WHMCS** no longer matches the assigned IP, then either sends a report (Notify only) or unassigns and marks IPs free (Mark free) and sends a report.
- The button may be disabled if **IP Cleaner** is not enabled in **Setup → Addon Modules → IP Manager → Configure**.

---

## 16. Integrations

**Where:** **Addons → IP Manager → Integrations**.

Integrations allow the module to **add** and **remove** IPs on the actual server (cPanel, Proxmox, Plesk, etc.) when you assign or unassign in IP Manager.

### 16.1 What to do

- Enable the integration that **matches your server module type** (e.g. **Proxmox**, **cPanel**, **Plesk**, **DirectAdmin**, **SolusVM**).
- When you **assign** an IP to a service, the module can call the integration’s **addIpToAccount** so the IP is applied on the server.
- When you **unassign** (or release from Orphaned), the module calls **removeIpFromAccount** so the server frees the IP. This keeps the provider in sync and avoids “IP shows free in one place but in use elsewhere” (e.g. suspended service with blank name).

### 16.2 Implementation note

- The classes under `lib/integrations/` may be stubs. For full behaviour, implement **addIpToAccount** and **removeIpFromAccount** for your provider’s API.

---

## 17. IPAM (NetBox)

**Where:** **Addons → IP Manager → IPAM (NetBox)**.

Optional integration with **NetBox** as an external IPAM.

### 17.1 Configuration

- **NetBox URL** – Base URL of your NetBox instance.
- **API Token** – From NetBox: **Profile → API Tokens**.
- **Enable NetBox IPAM** – Turn sync/push on or off.
- **Push to NetBox when assigning IP** – When enabled, assigning an IP in WHMCS creates/updates the IP in NetBox (e.g. status active, description “WHMCS Service #ID”). Unassigning sets the IP to deprecated in NetBox.
- Optional: **Site ID**, **Tenant ID** to limit which prefixes are synced.

### 17.2 Sync from NetBox

- **Sync from NetBox** – Pulls prefixes (as subnets) and their IP addresses into IP Manager. NetBox status (active, reserved, etc.) is mapped to assigned/reserved/free. Existing mappings are updated; new ones created.

---

## 18. Client area

**Where:** Clients open **IP Manager** from the client area (e.g. sidebar link or menu).

### 18.1 Main page

- Lists the client’s **assigned IPs** per product/service: product name, IP, subnet (if shown), and **Unassign** link.

### 18.2 Unassign

- Client clicks **Unassign** for an IP and confirms.
- The module unassigns the IP, clears WHMCS dedicated IP, and (if enabled) runs the integration to remove the IP from the server.

### 18.3 Order Additional IPs

- Placeholder for future “order more IPs” flow; can be extended as needed.

---

## 19. Cron job

**File:** `modules/addons/ipmanager/cron/usage_alerts.php`

**What it does:**

1. **Usage alerts** – Checks subnet/pool usage against the **Usage Alert Threshold (%)** from module config. If exceeded, sends an email to the first admin.
2. **IP Cleaner** – If **IP Cleaner Enabled** is **Yes** in module config, runs the IP Cleaner with the configured behavior (Notify only or Mark free).

**How to run:**

- From WHMCS root, run for example:
  ```bash
  php modules/addons/ipmanager/cron/usage_alerts.php
  ```
- Schedule it (e.g. daily) in your system crontab or WHMCS cron, e.g.:
  ```cron
  0 9 * * * cd /path/to/whmcs && php modules/addons/ipmanager/cron/usage_alerts.php
  ```

---

## 20. Database tables

All module tables use the prefix **mod_ipmanager_** (or the value from the module).

| Table | Purpose |
|-------|--------|
| **subnets** | IP subnets (CIDR, parent, gateway, excluded IPs, start/end range, version). |
| **pools** | Pools linked to a subnet. |
| **ip_addresses** | IP records (subnet_id, pool_id, ip, status: free/assigned/reserved). |
| **reservation_rules** | Rules to reserve network/gateway/broadcast (or other) IPs. |
| **configurations** | Configuration name and options (omit dedicated IP, custom field, etc.). |
| **configuration_relations** | Links configuration to product/addon/configoption/server and to pool_id/subnet_id. |
| **assignments** | Links an IP (ip_address_id) to client and service (and type). |
| **integration_config** | Which server types (cPanel, Proxmox, etc.) have integration enabled. |
| **usage_alerts** | Per-subnet/pool usage and last alert sent. |
| **logs** | Action log (admin, client, action, details, IP, date). |
| **ipam_mapping** | Mapping for NetBox sync (external ID ↔ local subnet/IP). |
| **custom_fields**, **custom_field_values** | Custom fields for subnet/pool/IP. |
| **subnet_locks** | Optional locks. |
| **acl** | Optional access control. |

---

## 21. Workflows summary

| Goal | Where | What to do |
|------|--------|------------|
| Use a subnet for VPS products | Configurations | Create/edit a configuration; in “Assign pool to products and servers” select **Pool**, check the **Products** (e.g. VPS, VDS), then Save (or Create configuration). |
| Use a subnet for specific servers/nodes | Configurations | In the same section, select **Pool**, check the **Servers (clusters / nodes)** you want, then Save. |
| Import existing WHMCS IPs without orphans | Synchronize | Check **Import only live services**, click **Run Sync**. |
| Free IPs from suspended/cancelled services | Orphaned | Review the list, then **Release** one or **Release all**. |
| Ensure server (Proxmox, etc.) frees the IP on unassign | Integrations | Enable the matching integration (e.g. Proxmox); unassign and release will then call removeIpFromAccount. |
| Auto-assign IP on new VPS order | (automatic) | Link product and/or servers to a pool in **Configurations**; ensure the pool has free IPs. PreModuleCreate will assign and set dedicated IP when the server module runs. |

---

## License

Proprietary.

---

## Support

All configuration and daily use is done from **Addons → IP Manager** in the WHMCS admin area. For global addon settings use **Setup → Addon Modules → IP Manager → Configure**.
