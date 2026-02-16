<?php

/**
 * IP Manager for WHMCS
 *
 * Add and manage IP subnets, assign IPs to servers/products/add-ons/configurable options,
 * sync with WHMCS products, and integrate with cPanel/DirectAdmin/Plesk etc.
 *
 * @copyright 2025
 * @license   Proprietary
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require_once __DIR__ . "/lib/helpers.php";
require_once __DIR__ . "/lib/Schema.php";

/**
 * Module configuration.
 *
 * @return array<string, mixed>
 */
function ipmanager_config(): array {
    return [
        "name"        => "IP Manager",
        "description" => "Manage IP subnets and pools, assign IPs to products/servers, "
            . "sync with WHMCS, and integrate with cPanel, DirectAdmin, Plesk, etc.",
        "version"     => "1.0.0",
        "author"      => "IP Manager",
        "language"    => "english",
        "fields"      => [
            "usage_alert_percent" => [
                "FriendlyName" => "Usage Alert Threshold (%)",
                "Type"         => "text",
                "Size"         => "5",
                "Description"  => "Send email when subnet usage exceeds this percentage (e.g. 80).",
                "Default"      => "80",
            ],
            "cleaner_enabled" => [
                "FriendlyName" => "IP Cleaner Enabled",
                "Type"         => "yesno",
                "Description"  => "Enable IP cleaner to ensure assigned IPs are in use.",
                "Default"      => "",
            ],
            "custom_field_instead_of_assigned" => [
                "FriendlyName" => "Use Custom Field Instead Of Assigned IP",
                "Type"         => "yesno",
                "Description"  => "Use custom field for assigned IP (when configured per config).",
                "Default"      => "",
            ],
        ],
    ];
}

/**
 * Module activation: create tables and default data.
 *
 * @return array{status: string, description: string}
 */
function ipmanager_activate(): array {
    try {
        IpManagerSchema::install();
        require_once __DIR__ . "/hooks.php";
        add_hook("ClientAreaPrimarySidebar", 1, "ipmanager_clientarea_sidebar");
        return [
            "status"      => "success",
            "description" => "IP Manager has been activated. Configure addon and create IP subnets from Addons → IP Manager.",
        ];
    } catch (Exception $e) {
        return [
            "status"      => "error",
            "description" => "Activation failed: " . $e->getMessage(),
        ];
    }
}

/**
 * Module deactivation: drop module tables.
 *
 * @return array{status: string, description: string}
 */
function ipmanager_deactivate(): array {
    try {
        remove_hook("ClientAreaPrimarySidebar", 1, "ipmanager_clientarea_sidebar");
        IpManagerSchema::uninstall();
        return [
            "status"      => "success",
            "description" => "IP Manager has been deactivated. Database tables have been removed.",
        ];
    } catch (Exception $e) {
        return [
            "status"      => "error",
            "description" => "Deactivation failed: " . $e->getMessage(),
        ];
    }
}

/**
 * Admin area output (main entry).
 *
 * @param array<string, mixed> $vars
 */
function ipmanager_output(array $vars): void {
    $modulelink = $vars["modulelink"];
    $version    = $vars["version"];
    $LANG       = $vars["_lang"] ?? [];

    $action = $_GET["action"] ?? "dashboard";
    $allowed = [
        "dashboard",
        "subnets",
        "pools",
        "configurations",
        "assignments",
        "sync",
        "export",
        "import",
        "logs",
        "settings",
        "translations",
        "acl",
        "integrations",
    ];
    if (!in_array($action, $allowed, true)) {
        $action = "dashboard";
    }

    $actionFile = __DIR__ . "/admin/" . $action . ".php";
    if (is_file($actionFile)) {
        include $actionFile;
    } else {
        include __DIR__ . "/admin/dashboard.php";
    }
}

/**
 * Client area: view assigned IPs, unassign, order additional.
 *
 * @param array<string, mixed> $vars
 * @return array<string, mixed>
 */
function ipmanager_clientarea(array $vars): array {
    $modulelink = $vars["modulelink"];
    $LANG       = $vars["_lang"] ?? [];

    $action = $_GET["action"] ?? "index";
    $allowed = ["index", "unassign", "order"];
    if (!in_array($action, $allowed, true)) {
        $action = "index";
    }

    $clientId = (int) ($_SESSION["uid"] ?? 0);
    $templateVars = [
        "modulelink" => $modulelink,
        "action"     => $action,
    ];

    switch ($action) {
        case "unassign":
            $templateVars["serviceid"] = (int) ($_GET["serviceid"] ?? $_POST["serviceid"] ?? 0);
            $templateVars["ipid"]      = (int) ($_GET["ipid"] ?? $_POST["ipid"] ?? 0);
            if (isset($_POST["confirm"]) && $templateVars["serviceid"] > 0 && $templateVars["ipid"] > 0) {
                $assign = Capsule::table(ipmanager_table("assignments"))->where("ip_address_id", $templateVars["ipid"])->first();
                if ($assign && (int) $assign->client_id === $clientId && (int) $assign->service_id === $templateVars["serviceid"]) {
                    ipmanager_unassign_ip($templateVars["ipid"], true);
                    $templateVars["confirm"]   = false;
                    $templateVars["unassign_ok"] = true;
                } else {
                    $templateVars["confirm"] = true;
                    $templateVars["unassign_error"] = true;
                }
            } else {
                $templateVars["confirm"] = !isset($_POST["confirm"]);
            }
            return [
                "pagetitle"    => ($LANG["client_unassign_ip"] ?? "Unassign IP"),
                "breadcrumb"   => [
                    "index.php?m=ipmanager" => $LANG["client_ip_manager"] ?? "IP Manager",
                    "" => $LANG["client_unassign_ip"] ?? "Unassign IP",
                ],
                "templatefile" => "client_unassign",
                "requirelogin" => true,
                "forcessl"     => false,
                "vars"         => $templateVars,
            ];
        case "order":
            return [
                "pagetitle"    => ($LANG["client_order_ips"] ?? "Order Additional IPs"),
                "breadcrumb"   => [
                    "index.php?m=ipmanager" => $LANG["client_ip_manager"] ?? "IP Manager",
                    "" => $LANG["client_order_ips"] ?? "Order Additional IPs",
                ],
                "templatefile" => "client_order",
                "requirelogin" => true,
                "forcessl"     => false,
                "vars"         => $templateVars,
            ];
        default:
            $templateVars["assigned_ips"] = ipmanager_get_client_assigned_ips($clientId);
            return [
                "pagetitle"    => ($LANG["client_ip_manager"] ?? "IP Manager"),
                "breadcrumb"   => ["index.php?m=ipmanager" => $LANG["client_ip_manager"] ?? "IP Manager"],
                "templatefile" => "clienthome",
                "requirelogin" => true,
                "forcessl"     => false,
                "vars"         => $templateVars,
            ];
    }
}

/**
 * Get assigned IPs for a client (for client area).
 *
 * @param int $clientId
 * @return list<array{product_name: string, ip: string, subnet_name: string, service_id: int, ip_id: int}>
 */
function ipmanager_get_client_assigned_ips(int $clientId): array {
    if ($clientId <= 0) {
        return [];
    }
    try {
        $rows = Capsule::table(ipmanager_table("assignments") . " as a")
            ->join(ipmanager_table("ip_addresses") . " as ip", "ip.id", "=", "a.ip_address_id")
            ->leftJoin(ipmanager_table("subnets") . " as s", "s.id", "=", "ip.subnet_id")
            ->leftJoin("tblhosting as h", "h.id", "=", "a.service_id")
            ->leftJoin("tblproducts as p", "p.id", "=", "h.packageid")
            ->where("a.client_id", $clientId)
            ->select([
                "ip.ip",
                "ip.id as ip_id",
                "a.service_id",
                "s.name as subnet_name",
                "p.name as product_name",
            ])
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                "product_name" => $r->product_name ?? "—",
                "ip"           => $r->ip,
                "subnet_name"  => $r->subnet_name ?? "—",
                "service_id"   => (int) $r->service_id,
                "ip_id"        => (int) $r->ip_id,
            ];
        }
        return $out;
    } catch (Exception $e) {
        return [];
    }
}
