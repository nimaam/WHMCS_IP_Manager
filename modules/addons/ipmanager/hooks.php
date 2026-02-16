<?php

/**
 * IP Manager hooks - integrate with WHMCS product/service lifecycle.
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Add client area sidebar link to IP Manager.
 * Registered for ClientAreaPrimarySidebar hook.
 *
 * @param array<string, mixed> $vars
 * @return array<string, mixed>|null Menu item for sidebar
 */
function ipmanager_clientarea_sidebar(array $vars) {
    $lang = $vars["_lang"] ?? [];
    $label = $lang["client_ip_manager"] ?? "IP Manager";
    return [
        "label" => $label,
        "uri"   => "index.php?m=ipmanager",
        "icon"  => "fa-list-alt",
    ];
}

/**
 * Admin area: add link to IP Manager from products/services (optional).
 *
 * @param array<string, mixed> $vars
 * @return string
 */
function ipmanager_admin_client_services_tab(array $vars): string {
    $clientId = (int) ($vars["userid"] ?? 0);
    if ($clientId <= 0) {
        return "";
    }
    $url = "addonmodules.php?module=ipmanager&action=assignments&clientid=" . $clientId;
    return "<a href=\"" . htmlspecialchars($url) . "\" class=\"btn btn-default btn-sm\">IP Manager</a>";
}

/**
 * PreModuleCreate: auto-assign an IP from the configured pool/subnet before the server module runs.
 * Returns dedicatedip so Proxmox/VMware/cPanel etc. receive the IP in their params.
 *
 * @param array<string, mixed> $vars Module params (e.g. serviceid, userid) or wrapper with "params" key
 * @return array<string, mixed> Overrides for module params (e.g. ["dedicatedip" => "1.2.3.4"])
 */
function ipmanager_pre_module_create(array $vars): array {
    $params = $vars["params"] ?? $vars;
    $serviceId = (int) ($params["serviceid"] ?? 0);
    if ($serviceId <= 0) {
        return [];
    }
    if (!function_exists("ipmanager_auto_assign_ip_for_service")) {
        return [];
    }
    ipmanager_auto_assign_ip_for_service($serviceId);
    $service = Capsule::table("tblhosting")->where("id", $serviceId)->first();
    $dedicatedip = $service && !empty(trim((string) ($service->dedicatedip ?? "")))
        ? trim((string) $service->dedicatedip)
        : "";
    if ($dedicatedip === "") {
        return [];
    }
    return ["dedicatedip" => $dedicatedip];
}

/**
 * AfterModuleCreate: log and push the assigned IP to the server via integration (Proxmox, cPanel, etc.).
 *
 * @param array<string, mixed> $vars Module params (serviceid, userid, etc.)
 */
function ipmanager_after_module_create(array $vars): void {
    $params = $vars["params"] ?? $vars;
    $serviceId = (int) ($params["serviceid"] ?? 0);
    if ($serviceId <= 0) {
        return;
    }
    ipmanager_log("service_created", "Service #" . $serviceId . " created", null, $params["userid"] ?? null);
    $service = Capsule::table("tblhosting")->where("id", $serviceId)->first();
    $ip = $service && !empty(trim((string) ($service->dedicatedip ?? "")))
        ? trim((string) $service->dedicatedip)
        : "";
    if ($ip !== "" && function_exists("ipmanager_run_integration_add_ip")) {
        ipmanager_run_integration_add_ip($serviceId, $ip);
    }
}

/**
 * Log helper.
 */
function ipmanager_log(string $action, string $details, ?int $adminId = null, ?int $clientId = null): void {
    try {
        Capsule::table(ipmanager_table("logs"))->insert([
            "admin_id"   => $adminId,
            "client_id"  => $clientId,
            "action"     => $action,
            "details"    => $details,
            "ip_address" => $_SERVER["REMOTE_ADDR"] ?? null,
            "created_at" => date("Y-m-d H:i:s"),
        ]);
    } catch (Exception $e) {
        // silent
    }
}
