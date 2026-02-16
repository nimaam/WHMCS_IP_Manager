# IP Manager for WHMCS – Complete User Guide

This document is the **complete user guide** for the IP Manager addon. It explains every part of the plugin step by step so you can install, configure, and use it from start to finish.

**What IP Manager does:** It adds and manages IP subnets and pools, **automatically assigns IP addresses** to services when clients order VPS/cloud (Proxmox, VMware, OpenStack, cPanel, etc.), and integrates with WHMCS and third-party applications. Customers can view and manage their assigned IPs from the client area.

**How to use this guide:** Read the [Table of contents](#table-of-contents) and follow [Getting started](#getting-started-first-time-setup) for a first-time setup. Use the numbered sections for detailed instructions on each area (Subnets, Pools, Configurations, Assignments, Sync, Orphaned, Export, Import, Logs, Settings, Integrations, IPAM, Client area, Cron). The [Workflows summary](#21-workflows-summary) and [Example: end-to-end setup](#example-end-to-end-setup) give quick reference and a concrete walkthrough.

---

## Table of contents

1. [Requirements](#1-requirements)
2. [Installation](#2-installation)
3. [Module configuration (global settings)](#3-module-configuration-global-settings)
4. [Getting started (first-time setup)](#getting-started-first-time-setup)
5. [Admin area overview](#4-admin-area-overview)
6. [Dashboard](#5-dashboard)
7. [IP Subnets](#6-ip-subnets)
8. [IP Pools](#7-ip-pools)
9. [Configurations](#8-configurations)
10. [Assignments](#9-assignments)
11. [Synchronize](#10-synchronize)
12. [Orphaned assignments](#11-orphaned-assignments)
13. [Export](#12-export)
14. [Import](#13-import)
15. [Logs](#14-logs)
16. [Settings](#15-settings)
17. [Translations and ACL](#translations-and-acl)
18. [Integrations](#16-integrations)
19. [IPAM (NetBox)](#17-ipam-netbox)
20. [Client area](#18-client-area)
21. [Cron job](#19-cron-job)
22. [Database tables](#20-database-tables)
23. [Workflows summary](#21-workflows-summary)
24. [Example: end-to-end setup](#example-end-to-end-setup)

---

## 1. Requirements

- **WHMCS** 8.10 – 9.x  
- **PHP** 8.2 – 8.3  
- **Client area themes:** Six, Twenty-One, Lagom (or compatible)

---

## 2. Installation

1. Copy the entire folder **`modules/addons/ipmanager`** into your WHMCS installation so the path is:
   ```
   /path/to/whmcs/modules/addons/ipmanager/
   ```
2. In WHMCS Admin go to **Setup → Addon Modules**.
3. Find **IP Manager** in the list and click **Activate**.
4. Set **Access Control:** choose which admin roles can use the module (e.g. Full Administrator).
5. Click **Configure** and set the global options (see [Section 3](#3-module-configuration-global-settings)).

After activation, the module creates its database tables (prefix `mod_ipmanager_`) and registers its hooks. You can open **Addons → IP Manager** in the admin menu.

---

## 3. Module configuration (global settings)

**Where:** **Setup → Addon Modules** → find **IP Manager** → **Configure**.

These settings apply to the whole module. Change them once; they affect usage alerts, IP Cleaner, and optional custom field behaviour.

| Setting | Description |
|--------|--------------|
| **Usage Alert Threshold (%)** | When subnet/pool usage exceeds this percentage, an email is sent to the first admin (e.g. 80). Used by the usage alerts feature and cron. |
| **IP Cleaner Enabled** | Yes/No. When **Yes**, the IP Cleaner runs with the cron job (see [Settings](#15-settings) and [Cron job](#19-cron-job)). |
| **IP Cleaner Behavior** | **Notify only:** only send an email report when assigned IPs are no longer in use. **Mark free:** unassign those IPs and mark them free, then send the report. |
| **Use Custom Field Instead Of Assigned IP** | Yes/No. When enabled and configured per configuration, the module can use a product custom field for the assigned IP instead of WHMCS’s standard dedicated IP field. |

Click **Save Changes** after editing.

---

## Getting started (first-time setup)

Follow these steps once to get IP Manager working for new and existing services.

1. **Add at least one subnet**  
   Go to **Addons → IP Manager → IP Subnets** → **Add IP Subnet**. Enter **Name** (e.g. “Production IPv4”), **CIDR** (e.g. `79.133.56.0/24`), optional **Gateway** (e.g. `79.133.56.1`). Optionally check **Reserve** for network/broadcast/gateway and **Store free IPs** if you want pre-generated IPs. Save.

2. **Create a pool (recommended)**  
   Go to **IP Pools** → **Add Pool**. Enter **Name** (e.g. “79.133.56.0/24” or “Proxmox VPS pool”) and select the **Subnet** you created. Save.

3. **Create a configuration and link products/servers**  
   Go to **Configurations** → **Add Configuration**. Enter **Name** (e.g. “Proxmox VPS”). In **Assign pool to products and servers** select your **Pool** (or Subnet), check the **Products** that should get IPs from this pool (e.g. “Basic Windows VPS”), and optionally check **Servers (clusters / nodes)**. Click **Create configuration**.

4. **Import existing WHMCS IPs (optional)**  
   If you already have services with dedicated IPs, go to **Synchronize**. Leave **Import only live services (Active and Pending)** checked and click **Run Sync**. This brings existing usage into IP Manager without importing suspended/cancelled services.

5. **Enable integration for your server type (optional but recommended)**  
   Go to **Integrations** and enable the type that matches your product’s server module (e.g. Proxmox, cPanel). This makes the module add/remove the IP on the server when you assign or unassign.

6. **Optional: set up cron**  
   To run usage alerts and IP Cleaner automatically, add a cron job as in [Section 19](#19-cron-job).

After this, **new orders** for the products you linked will receive an IP from the chosen pool automatically when the server module provisions the service.

---

## 4. Admin area overview

**Where:** **Addons → IP Manager** (main admin menu).

The module opens with a **horizontal tab menu** at the top. Each tab is one part of the plugin:

| Tab | Purpose |
|-----|--------|
| **Dashboard** | Summary (subnets, pools, assigned/free IPs) and quick links. |
| **IP Subnets** | Define IP ranges (CIDR). |
| **IP Pools** | Create pools per subnet for assignment. |
| **Configurations** | Link pools (or subnets) to products and servers. |
| **Assignments** | View services and assign/unassign IPs manually. |
| **Synchronize** | Import existing WHMCS dedicated IPs into IP Manager. |
| **Orphaned** | Release IPs from suspended/cancelled/terminated services. |
| **Export** | Export subnets and pools (CSV, XML, JSON). |
| **Import** | Import subnets and pools from file. |
| **Logs** | View module action log. |
| **Settings** | Run usage alerts and IP Cleaner manually; see cron info. |
| **Translations** | Language customisation. |
| **ACL** | Access control for the addon. |
| **Integrations** | Enable server types (cPanel, Proxmox, Plesk, etc.). |
| **IPAM (NetBox)** | NetBox sync and push. |

The following sections explain each part in detail.

---

## 5. Dashboard

**Where:** **Addons → IP Manager** (default first tab).

**What it shows:**

- **IP Subnets** – Total number of subnets.
- **IP Pools** – Total number of pools.
- **Assigned IPs** – Number of IP addresses currently assigned to services.
- **Free IPs** – Number of IP addresses with status “free” (only counted if you use “Store free IPs” on subnets).

**Quick actions (links):**

- **Add IP Subnet** – Opens the add subnet form.
- **Manage Configurations** – Opens Configurations.
- **Sync with WHMCS** – Opens Synchronize.

Use the dashboard to see at a glance how many subnets, pools, and IPs you have and to jump to common tasks.

---

## 6. IP Subnets

**Where:** **Addons → IP Manager → IP Subnets**.

Subnets are the base IP ranges (e.g. `79.133.56.0/24`) from which IPs are taken. You must add at least one subnet before creating pools or assigning IPs.

### 6.1 Subnet list

- Lists all subnets with name, CIDR, gateway, and other details.
- **Add IP Subnet** (or similar button) opens the create form.
- Click **Edit** on a row to change an existing subnet.

### 6.2 Add / Edit subnet form – step by step

| Field | What to enter |
|-------|----------------|
| **Name** | A label for this subnet (e.g. “subnet 79.133.56.0/24” or “Production IPv4”). |
| **CIDR** | The network in CIDR notation, e.g. `79.133.56.0/24` (IPv4) or `2001:db8::/32` (IPv6). Must be valid; the module derives the range from it. |
| **Parent** | Optional parent subnet for hierarchy; choose “None” for a top-level subnet. |
| **Gateway** | Optional gateway IP (e.g. `79.133.56.1`). |
| **Excluded IPs** | Optional list of IPs to exclude from the range (e.g. comma-separated). |
| **Reserve** | Checkboxes to **reserve** the network address, broadcast address, and/or gateway so they are never assigned. |
| **Store free IPs** | If checked, the module pre-populates the database with IPs in the range as “free” (up to 65536 for IPv4). This lets you assign from a fixed set. If unchecked, free IPs may be generated on demand. |

**Save:** Click **Save**. On **create**, the module generates the IP range, applies reservation rules, and optionally creates the free IP records. On **edit**, it updates name, gateway, excluded IPs, etc., as supported.

### 6.3 After creating a subnet

- Create **IP Pools** linked to this subnet (next section).
- Use this subnet or a pool on it in **Configurations** and in **Assignments**.

---

## 7. IP Pools

**Where:** **Addons → IP Manager → IP Pools**.

Pools group IPs within a subnet. Configurations link **products** and **servers** to a **pool** (or directly to a subnet), so assignment comes from that pool.

### 7.1 Pool list

- Shows all pools with name and linked subnet.
- You can filter by subnet using the dropdown.
- **Add Pool** opens the create form.

### 7.2 Add / Edit pool – step by step

| Field | What to enter |
|-------|----------------|
| **Name** | A label (e.g. “79.133.56.0/24” or “Proxmox VPS pool”). |
| **Subnet** | The subnet this pool belongs to (required). |

Click **Save** to create or update. IPs in the subnet (or generated from it) can then be associated with this pool for assignment.

---

## 8. Configurations

**Where:** **Addons → IP Manager → Configurations**.

Configurations define **which products and servers** get IPs from **which pool (or subnet)**. You can do this both when **creating** a new configuration and when **editing** an existing one.

### 8.1 Configuration list

- Lists all configurations by **Name**.
- **Add Configuration** opens the create form.
- **Edit** opens the edit form for that configuration.

### 8.2 Create configuration (Add) – step by step

When you click **Add Configuration**, you see one page with two parts.

**Part 1 – Basic settings**

| Field | What to enter |
|-------|----------------|
| **Name** | Name of the configuration (e.g. “Proxmox VPS/VDS” or “Basic Windows VPS”). |
| **Omit dedicated IP field** | Check if you want to omit the dedicated IP field where the module controls the IP. |
| **Use custom field instead of Assigned IP** | Check to use a product custom field for the assigned IP (then set custom field name below). |
| **Custom field name** | Name of the product custom field when “Use custom field instead of Assigned IP” is enabled. |

**Part 2 – Assign pool to products and servers** (same page, below)

| Field | What to do |
|-------|------------|
| **Pool** | Choose the pool that will supply IPs (e.g. “79.133.56.0/24”). |
| **Subnet** | Or choose a subnet; at least one of Pool or Subnet must be selected when you have products/servers selected. |
| **Products** | Check **one or more** products that should receive an IP from the selected pool (e.g. “Basic Windows VPS”, “VDS”). |
| **Servers (clusters / nodes)** | Check **one or more** servers from **Setup → Products/Services → Servers** (each entry = one cluster or node). Leave all unchecked if any server is fine. |

**Submit:** Click **Create configuration**. The module (1) creates the configuration with the name and options, and (2) if you selected at least one Pool or Subnet and at least one Product or Server, creates relations for each selected product and each selected server. You are then redirected to the **edit** page of the new configuration.

### 8.3 Edit configuration – step by step

When you **Edit** an existing configuration you see four parts.

**Part 1 – Basic settings (first form)**

- Same fields as create: Name, Omit dedicated IP field, Use custom field, Custom field name.
- **Save** and **Cancel** save only these and keep you on the same page.

**Part 2 – Assign pool to products and servers (second form)**

- **Pool** and **Subnet** are pre-filled from current relations.
- **Products** and **Servers (clusters / nodes)** show checkboxes; already-linked items are pre-checked.
- Change selections as needed, then click **Save products and servers selection**. This **replaces** all existing product and server relations for this configuration with the new selection.

**Part 3 – Relations table**

- Table of all relations: **Type** (product, server, addon, configoption), **Target** (name), **Pool / Subnet**.
- Use **Delete** on a row to remove that single relation.

**Part 4 – Add single relation (optional)**

- To add **one** relation (e.g. for an Addon or Config Option): choose **Type**, then **Target** from the dropdown, then **Pool** or **Subnet**, and click **Add**.

### 8.4 How configurations are used

- When a client **orders a product** that is linked (via a configuration relation) to a pool or subnet, the **PreModuleCreate** hook runs and assigns an IP from that pool/subnet and sets WHMCS **dedicatedip**.
- The module looks up the pool/subnet by **product** or **server**; if both are linked, it uses the first matching relation.
- **Servers** in WHMCS = “clusters” or “nodes”: each entry under **Setup → Servers** is one server; select one or more to restrict which servers use the pool.

---

## 9. Assignments

**Where:** **Addons → IP Manager → Assignments**.

This page shows WHMCS services and their IP assignment status and lets you **assign** or **unassign** IPs manually.

### 9.1 List columns

- **Service ID**, **Client**, **Product**, **Service status** (Active, Pending, Suspended, Cancelled, etc.), **Assigned IPs** (from IP Manager), **WHMCS dedicatedip**, **Actions**.
- **Service status** comes from WHMCS; green for Active/Pending, grey for others.
- The list is limited (e.g. last 500 services); use filters to narrow.

### 9.2 Filters

- **Client ID** – Restrict to one client.
- **Service ID** – Restrict to one service.
- Click **Filter** to apply.

### 9.3 How to assign an IP to a service

1. Find the service in the list (use filters if needed).
2. In that row, choose a **Pool** or **Subnet** from the dropdowns (use one).
3. Click **Assign**.
4. The module picks a free IP from the selected pool/subnet, creates the assignment, updates **tblhosting.dedicatedip**, and (if integrations are enabled) can push the IP to the server.

### 9.4 How to unassign an IP

1. Find the service and the IP in the **Assigned IPs** column.
2. Click **Unassign** next to that IP.
3. Confirm. The module removes the assignment, sets the IP to “free”, clears WHMCS dedicated IP, and (if enabled) calls the integration to remove the IP from the server and optionally updates NetBox.

---

## 10. Synchronize

**Where:** **Addons → IP Manager → Synchronize**.

This imports **existing** dedicated IPs from WHMCS into IP Manager so already-provisioned services are reflected in the module.

### 10.1 What sync does

- Reads **tblhosting** for rows that have **dedicatedip** set.
- For each service (optionally filtered by status):
  - If the IP already exists in IP Manager, it is assigned to that service if not already.
  - If the IP does not exist, the module finds a subnet that contains the IP (by range); if found, it creates the IP record and the assignment. If no subnet contains the IP, the row is skipped and an error is listed in the result.

### 10.2 Options

- **Import only live services (Active and Pending)** – **Checked (recommended):** only services with status **Active** or **Pending** are processed. Suspended, Cancelled, Terminated, etc. are skipped so you do not import orphans.
- **Unchecked:** all services with a dedicated IP are processed (full sync). Use only when you need every dedicated IP regardless of status.

### 10.3 How to run sync

1. Open **Addons → IP Manager → Synchronize**.
2. Leave **Import only live services** checked (or uncheck for full sync).
3. Click **Run Sync**.
4. Read the result: how many synced, how many IPs created, how many skipped, and any errors (e.g. “no matching subnet”).

Run sync after you have added subnets (and optionally pools) so that existing WHMCS usage is reflected in IP Manager.

---

## 11. Orphaned assignments

**Where:** **Addons → IP Manager → Orphaned**.

Here “orphaned” means: IPs that are **assigned in IP Manager** to a service whose WHMCS status is **not** Active or Pending (e.g. Suspended, Cancelled, Terminated). You can release these so the IPs become free again.

### 11.1 What the list shows

- Table: **IP**, **Service ID**, **Client**, **Product**, **Service status**, **Actions**.
- Only assignments where the service status is not “Active” or “Pending” are listed.

### 11.2 How to release one IP

1. Find the row in the Orphaned list.
2. Click **Release**.
3. Confirm. The module will: (1) call the integration to remove the IP from the server, (2) unassign in IP Manager and set IP to free, (3) clear **tblhosting.dedicatedip**, (4) update NetBox if push is enabled.

### 11.3 How to release all

1. Click **Release all**.
2. Confirm. The same steps are applied to every listed assignment.

Use this when an IP still appears in use on the provider (e.g. Proxmox) for a suspended service, or to reclaim IPs for reuse.

---

## 12. Export

**Where:** **Addons → IP Manager → Export**.

Export **subnets** and **pools** to a file for backup or for use in another system or WHMCS instance.

### 12.1 How to export

1. Open **Addons → IP Manager → Export**.
2. Choose **Format:** CSV, XML, or JSON.
3. Use the link or button to download (e.g. “Download CSV”, “Download XML”, “Download JSON”). The file is generated and downloaded (e.g. `ipmanager_export_YYYY-MM-DD.csv`).

### 12.2 What is exported

- **Subnets:** type, id, parent_id, subnet_id, name, cidr, gateway, start_ip, end_ip, version (and any other columns the export includes).
- **Pools:** type, id, subnet_id, name, and related data.  
Use the same format for **Import** (see next section) when re-importing into IP Manager.

---

## 13. Import

**Where:** **Addons → IP Manager → Import**.

Import subnets and pools from a file (e.g. one exported from this module).

### 13.1 How to import

1. Open **Addons → IP Manager → Import**.
2. Choose **Format:** CSV, XML, or JSON (must match the file).
3. Choose **File** (upload or path as per the form).
4. Click **Import**.

### 13.2 Important

- **Warning:** Import **creates new records**; parent and subnet IDs may be remapped. For best results use a file **exported from this module** (JSON or XML).
- After import, check **IP Subnets** and **IP Pools** to confirm data; then you can use **Configurations** to link pools to products/servers.

---

## 14. Logs

**Where:** **Addons → IP Manager → Logs**.

View the **module action log** (e.g. assignments, sync, integration calls).

### 14.1 What you see

- Table with **ID**, **Action**, **Details**, **Date** (and any other columns the module logs).
- The list is typically the **last 100** log entries, newest first.
- Use it to audit what the module has done (who assigned what, sync results, etc.).

---

## 15. Settings

**Where:** **Addons → IP Manager → Settings**.

This page **does not** change the global module configuration (that is under **Setup → Addon Modules → IP Manager → Configure**). It lets you **run** usage alerts and IP Cleaner manually and see cron information.

### 15.1 Usage alerts

- **Run usage check now** – Runs the usage alert check immediately. If any subnet/pool exceeds the **Usage Alert Threshold (%)** from module config, an email is sent to the first admin.
- **Cron** – The page shows the command to schedule (e.g. `php modules/addons/ipmanager/cron/usage_alerts.php`). See [Section 19](#19-cron-job).

### 15.2 IP Cleaner

- **Behavior** is read from module config (Notify only / Mark free).
- **Run IP Cleaner now** – Runs the IP Cleaner once: finds assignments where the service’s **dedicated IP in WHMCS** no longer matches the assigned IP, then either sends a report (Notify only) or unassigns and marks IPs free (Mark free) and sends a report.
- The button may be **disabled** if **IP Cleaner** is not enabled in **Setup → Addon Modules → IP Manager → Configure**.

---

## Translations and ACL

- **Translations** (**Addons → IP Manager → Translations**): Customise language strings for the module (multi-language support).
- **ACL** (**Addons → IP Manager → ACL**): Control staff access to specific resources (subnets, pools, configurations) if your installation uses this.

Details depend on your WHMCS and the module implementation; use these tabs to adjust labels and permissions as needed.

---

## 16. Integrations

**Where:** **Addons → IP Manager → Integrations**.

Integrations let the module **add** and **remove** IPs on the actual server (cPanel, Proxmox, Plesk, etc.) when you assign or unassign in IP Manager.

### 16.1 What to do

1. Open **Addons → IP Manager → Integrations**.
2. Enable the integration that **matches your server module type** (e.g. **Proxmox**, **cPanel**, **Plesk**, **DirectAdmin**, **SolusVM**).
3. When you **assign** an IP to a service, the module can call **addIpToAccount** so the IP is applied on the server.
4. When you **unassign** (or release from Orphaned), the module calls **removeIpFromAccount** so the server frees the IP. This keeps the provider in sync.

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

Save the configuration.

### 17.2 Sync from NetBox

- Click **Sync from NetBox** to pull prefixes (as subnets) and their IP addresses into IP Manager. NetBox status (active, reserved, etc.) is mapped to assigned/reserved/free. Existing mappings are updated; new ones created.

---

## 18. Client area

**Where:** Clients open **IP Manager** from the client area (e.g. sidebar link “IP Manager” or similar).

### 18.1 Main page

- Lists the client’s **assigned IPs** per product/service: product name, IP, subnet (if shown), and an **Unassign** link.

### 18.2 Unassign

- The client clicks **Unassign** for an IP and confirms.
- The module unassigns the IP, clears WHMCS dedicated IP, and (if enabled) runs the integration to remove the IP from the server.

### 18.3 Order Additional IPs

- Placeholder for a future “order more IPs” flow; can be extended as needed.

---

## 19. Cron job

**File:** `modules/addons/ipmanager/cron/usage_alerts.php`

**What it does:**

1. **Usage alerts** – Checks subnet/pool usage against the **Usage Alert Threshold (%)** from module config. If exceeded, sends an email to the first admin.
2. **IP Cleaner** – If **IP Cleaner Enabled** is **Yes** in module config, runs the IP Cleaner with the configured behavior (Notify only or Mark free).

**How to run:**

- From the WHMCS root directory:
  ```bash
  php modules/addons/ipmanager/cron/usage_alerts.php
  ```
- To schedule (e.g. daily at 9:00):
  ```cron
  0 9 * * * cd /path/to/whmcs && php modules/addons/ipmanager/cron/usage_alerts.php
  ```
Replace `/path/to/whmcs` with your WHMCS path.

---

## 20. Database tables

All module tables use the prefix **mod_ipmanager_** (or the value configured for the module).

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
| Use a subnet for VPS products | Configurations | Create or edit a configuration; in “Assign pool to products and servers” select **Pool**, check the **Products** (e.g. VPS, VDS), then Save or Create configuration. |
| Use a subnet for specific servers/nodes | Configurations | In the same section, select **Pool**, check the **Servers (clusters / nodes)** you want, then Save. |
| Import existing WHMCS IPs without orphans | Synchronize | Check **Import only live services**, click **Run Sync**. |
| Free IPs from suspended/cancelled services | Orphaned | Review the list, then **Release** one or **Release all**. |
| Ensure server (Proxmox, etc.) frees the IP on unassign | Integrations | Enable the matching integration (e.g. Proxmox); unassign and release will then call removeIpFromAccount. |
| Auto-assign IP on new VPS order | (automatic) | Link product and/or servers to a pool in **Configurations** and ensure the pool has free IPs. PreModuleCreate will assign and set dedicated IP when the server module runs. |

---

## Example: end-to-end setup

This example walks through one complete setup from scratch.

1. **Subnet**  
   **IP Subnets** → Add: Name “Production 79.133.56”, CIDR `79.133.56.0/24`, Gateway `79.133.56.1`, reserve network/broadcast/gateway, **Store free IPs** checked → Save.

2. **Pool**  
   **IP Pools** → Add: Name “79.133.56.0/24”, Subnet “Production 79.133.56” → Save.

3. **Configuration**  
   **Configurations** → Add Configuration: Name “Basic Windows VPS”. In Assign pool to products and servers: Pool “79.133.56.0/24”, check product “Basic Windows VPS”, leave servers unchecked → **Create configuration**.

4. **Existing services**  
   **Synchronize** → **Import only live services** checked → **Run Sync**. Check result (synced/created/skipped/errors).

5. **Integrations**  
   **Integrations** → Enable “Proxmox” (or your server module type).

6. **New order**  
   When a client orders “Basic Windows VPS”, the server module (e.g. Proxmox) runs; before it, IP Manager assigns an IP from the pool “79.133.56.0/24” and sets WHMCS dedicated IP; the server module receives that IP and can apply it on the VM.

7. **Optional: reclaim IPs**  
   **Orphaned** → Review list → **Release** or **Release all** for suspended/cancelled services.

---

## License

Proprietary.

---

## Support

- Day-to-day use: **Addons → IP Manager** in the WHMCS admin area.
- Global addon settings: **Setup → Addon Modules → IP Manager → Configure**.

This completes the **IP Manager for WHMCS – Complete User Guide**. For any section, refer to the matching numbered section and follow the steps there.
